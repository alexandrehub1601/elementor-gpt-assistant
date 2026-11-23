<?php
/**
 * Plugin Name: Login Conexão Pop
 * Description: Troca o endereço do login (esconde o wp-login.php), limita tentativas de senha, bloqueia XML-RPC e a listagem de usuários, e deixa a tela de login com a cara do Conexão Pop.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Conexão Pop
 * Text Domain: conexao-pop-login
 */

if (!defined('ABSPATH')) {
	exit;
}

/*
 * Se um dia ninguém conseguir entrar: adicione no wp-config.php a linha
 *     define('CP_LOGIN_PADRAO', true);
 * O endereço volta a ser /wp-login.php (as outras proteções continuam). Depois, remova a linha.
 * Outra saída: renomear a pasta deste plugin pelo FTP ou pelo gerenciador de arquivos da hospedagem.
 */

final class CP_Login {

	const OPT        = 'cp_login_opcoes';
	const TENTATIVAS = 5;            // erros seguidos antes de bloquear
	const BLOQUEIO   = 15 * MINUTE_IN_SECONDS;

	private $no_login = false;

	public static function opcoes() {
		return wp_parse_args(get_option(self::OPT, array()), array(
			'slug'   => 'acesso-redacao',
			'xmlrpc' => 1,
		));
	}

	private function slug() {
		return self::opcoes()['slug'];
	}

	private function ativo() {
		return !(defined('CP_LOGIN_PADRAO') && CP_LOGIN_PADRAO) && $this->slug() !== '';
	}

	public function __construct() {
		// Proteções que valem sempre.
		add_filter('authenticate', array($this, 'checar_bloqueio'), 30, 3);
		add_action('wp_login_failed', array($this, 'contar_erro'));
		add_action('wp_login', array($this, 'limpar_erros'), 10, 2);
		add_filter('login_errors', array($this, 'erro_generico'));
		add_action('plugins_loaded', array($this, 'xmlrpc'), 1);
		add_filter('xmlrpc_enabled', array($this, 'xmlrpc_filtro'));
		add_filter('rest_endpoints', array($this, 'esconder_usuarios_rest'));
		add_action('template_redirect', array($this, 'bloquear_author'), 1);

		// Visual da tela de login.
		add_action('login_enqueue_scripts', array($this, 'visual'));
		add_filter('login_headerurl', function () {
			return home_url('/');
		});
		add_filter('login_headertext', function () {
			return 'Conexão Pop';
		});
		add_filter('login_display_language_dropdown', '__return_false');
		add_filter('login_title', function () {
			return 'Entrar | Conexão Pop';
		});

		// Endereço novo.
		add_action('plugins_loaded', array($this, 'interceptar'), 9999);
		add_action('wp_loaded', array($this, 'servir'));
		add_filter('site_url', array($this, 'trocar_url'), 10, 4);
		add_filter('network_site_url', array($this, 'trocar_url'), 10, 3);
		add_filter('wp_redirect', array($this, 'trocar_redirect'), 10, 2);
		add_filter('login_url', array($this, 'trocar_simples'), 10, 1);
		add_filter('logout_url', array($this, 'trocar_simples'), 10, 1);
		add_filter('lostpassword_url', array($this, 'trocar_simples'), 10, 1);
		add_filter('register_url', array($this, 'trocar_simples'), 10, 1);
		remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);

		// Painel.
		add_action('admin_menu', array($this, 'menu'));
		add_action('admin_init', array($this, 'registrar'));
		add_action('admin_notices', array($this, 'aviso_ativacao'));
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'link_config'));
	}

	/* ------------------------------------------------------------------ */
	/* Endereço do login                                                   */
	/* ------------------------------------------------------------------ */

	private function caminho_pedido() {
		$uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : ''; // phpcs:ignore
		$path = wp_parse_url($uri, PHP_URL_PATH);
		return untrailingslashit(rawurldecode((string) $path));
	}

	private function caminho_slug() {
		return untrailingslashit((string) wp_parse_url(home_url('/' . $this->slug()), PHP_URL_PATH));
	}

	public function url_login($query = '') {
		$url = home_url('/' . $this->slug() . '/');
		return $query ? $url . '?' . $query : $url;
	}

	public function interceptar() {
		if (!$this->ativo() || is_multisite() && is_network_admin()) {
			return;
		}
		global $pagenow;
		$pedido = $this->caminho_pedido();
		$eh_wplogin = (bool) preg_match('#/wp-login\.php$#i', $pedido) || (bool) preg_match('#/wp-register\.php$#i', $pedido);

		if ($eh_wplogin && !is_admin()) {
			// Acesso direto ao wp-login.php: some.
			$this->no_login = true;
			$pagenow = 'index.php';
		} elseif ($pedido === $this->caminho_slug()) {
			$pagenow = 'wp-login.php';
		}
	}

	public function servir() {
		if (!$this->ativo()) {
			return;
		}
		global $pagenow;
		$doing_ajax = wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON) || (defined('REST_REQUEST') && REST_REQUEST);

		// Painel sem estar logado: some (exceto admin-ajax e admin-post, usados por formulários do site).
		if (is_admin() && !is_user_logged_in() && !$doing_ajax && !in_array($pagenow, array('admin-post.php', 'admin-ajax.php'), true)) {
			$this->pagina_404();
		}

		if ($this->no_login) {
			$this->pagina_404();
		}

		if ($pagenow === 'wp-login.php') {
			// Endereço novo: entrega o login padrão do WordPress.
			if ($this->caminho_pedido() !== $this->caminho_slug()) {
				$this->pagina_404();
			}
			global $error, $interim_login, $action, $user_login;
			$user_login = '';
			$error = '';
			@require_once ABSPATH . 'wp-login.php'; // phpcs:ignore
			exit;
		}
	}

	private function pagina_404() {
		global $wp_query;
		if ($wp_query) {
			$wp_query->set_404();
		}
		status_header(404);
		nocache_headers();
		$t = get_404_template();
		if ($t && file_exists($t)) {
			include $t;
		} else {
			echo '<!doctype html><meta charset="utf-8"><title>Página não encontrada</title><p>Página não encontrada.</p>';
		}
		exit;
	}

	private function trocar($url) {
		if (!$this->ativo() || strpos($url, 'wp-login.php') === false) {
			return $url;
		}
		// Mantém só o que vem depois de "?".
		$partes = explode('?', $url, 2);
		$query  = isset($partes[1]) ? $partes[1] : '';
		return $this->url_login($query);
	}

	public function trocar_url($url, $path = '', $scheme = null, $blog_id = null) {
		return $this->trocar($url);
	}

	public function trocar_redirect($location, $status) {
		return $this->trocar($location);
	}

	public function trocar_simples($url) {
		return $this->trocar($url);
	}

	/* ------------------------------------------------------------------ */
	/* Limite de tentativas                                                */
	/* ------------------------------------------------------------------ */

	private function chave_ip() {
		$ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
		return 'cp_login_' . md5($ip);
	}

	public function checar_bloqueio($user, $username, $password) {
		$d = get_transient($this->chave_ip());
		if (is_array($d) && $d['n'] >= self::TENTATIVAS) {
			$min = max(1, (int) ceil(($d['ate'] - time()) / 60));
			return new WP_Error('cp_bloqueado', sprintf('Muitas tentativas erradas. Tente de novo em %d minuto(s).', $min));
		}
		return $user;
	}

	public function contar_erro() {
		$k = $this->chave_ip();
		$d = get_transient($k);
		$d = is_array($d) ? $d : array('n' => 0, 'ate' => 0);
		$d['n']++;
		$d['ate'] = time() + self::BLOQUEIO;
		set_transient($k, $d, self::BLOQUEIO);
	}

	public function limpar_erros() {
		delete_transient($this->chave_ip());
	}

	public function erro_generico($msg) {
		if (strpos((string) $msg, 'Muitas tentativas') !== false) {
			return $msg;
		}
		// Não conta se o erro foi no usuário ou na senha.
		return '<strong>Erro:</strong> usuário ou senha incorretos.';
	}

	/* ------------------------------------------------------------------ */
	/* XML-RPC e listagem de usuários                                      */
	/* ------------------------------------------------------------------ */

	public function xmlrpc() {
		if (self::opcoes()['xmlrpc'] && defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
			status_header(403);
			exit;
		}
	}

	public function xmlrpc_filtro($on) {
		return self::opcoes()['xmlrpc'] ? false : $on;
	}

	public function esconder_usuarios_rest($endpoints) {
		if (!is_user_logged_in()) {
			unset($endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)']);
		}
		return $endpoints;
	}

	public function bloquear_author() {
		if (!is_user_logged_in() && isset($_GET['author']) && !is_admin()) { // phpcs:ignore
			wp_safe_redirect(home_url('/'), 301);
			exit;
		}
	}

	/* ------------------------------------------------------------------ */
	/* Visual                                                              */
	/* ------------------------------------------------------------------ */

	public function visual() {
		wp_enqueue_style('cp-login-fontes', 'https://fonts.googleapis.com/css2?family=Geist+Pixel&family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,700&display=swap', array(), null);
		wp_enqueue_style('cp-login', plugin_dir_url(__FILE__) . 'assets/login.css', array('login'), '1.0.0');
		wp_add_inline_style('cp-login', '.login h1 a{background-image:url(' . esc_url(plugin_dir_url(__FILE__) . 'assets/logo.png') . ')}body.login::before{background-image:url(' . esc_url(plugin_dir_url(__FILE__) . 'assets/plateia.webp') . ')}');
	}

	/* ------------------------------------------------------------------ */
	/* Configurações                                                       */
	/* ------------------------------------------------------------------ */

	public function menu() {
		add_options_page('Login do Conexão Pop', 'Login do Conexão Pop', 'manage_options', 'cp-login', array($this, 'pagina'));
	}

	public function link_config($links) {
		array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=cp-login')) . '">Configurar</a>');
		return $links;
	}

	public function registrar() {
		register_setting('cp_login', self::OPT, array('sanitize_callback' => array($this, 'limpar')));
	}

	public function limpar($v) {
		$atual = self::opcoes();
		$slug  = sanitize_title($v['slug'] ?? '');
		$proibidos = array('wp-admin', 'wp-login', 'login', 'admin', 'dashboard', 'wp-content', 'wp-includes', 'wp-json', 'feed');
		if ($slug === '' || in_array($slug, $proibidos, true) || get_page_by_path($slug)) {
			add_settings_error(self::OPT, 'slug', 'Escolha outro endereço: esse está vazio, é muito óbvio ou já é uma página do site.');
			$slug = $atual['slug'];
		}
		return array('slug' => $slug, 'xmlrpc' => empty($v['xmlrpc']) ? 0 : 1);
	}

	public function pagina() {
		$o = self::opcoes();
		?>
		<div class="wrap">
			<h1>Login do Conexão Pop</h1>
			<?php if (defined('CP_LOGIN_PADRAO') && CP_LOGIN_PADRAO) : ?>
				<div class="notice notice-warning"><p>O endereço novo está desligado pelo <code>CP_LOGIN_PADRAO</code> no wp-config.php. Remova essa linha para ligar de novo.</p></div>
			<?php endif; ?>
			<p>Endereço de login agora: <strong><code><?php echo esc_html($this->url_login()); ?></code></strong> — salve nos favoritos e passe para a equipe.</p>
			<form method="post" action="options.php">
				<?php settings_fields('cp_login'); ?>
				<table class="form-table">
					<tr><th><label for="cp_slug">Endereço do login</label></th>
						<td><code><?php echo esc_html(home_url('/')); ?></code><input type="text" id="cp_slug" name="<?php echo esc_attr(self::OPT); ?>[slug]" value="<?php echo esc_attr($o['slug']); ?>" class="regular-text">
						<p class="description">Use algo que não seja fácil de adivinhar. O <code>/wp-login.php</code> e o <code>/wp-admin</code> (para quem não está logado) passam a mostrar "página não encontrada".</p></td></tr>
					<tr><th>XML-RPC</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[xmlrpc]" value="1" <?php checked($o['xmlrpc'], 1); ?>> Bloquear o xmlrpc.php</label>
						<p class="description">É a porta mais usada por robôs para testar senhas. Só desmarque se vocês usam o app do WordPress no celular ou o Jetpack.</p></td></tr>
				</table>
				<?php submit_button('Salvar'); ?>
			</form>
			<h2>O que mais este plugin faz</h2>
			<ul style="list-style:disc;padding-left:20px">
				<li>Bloqueia por <?php echo intval(self::BLOQUEIO / 60); ?> minutos o endereço de internet que errar a senha <?php echo intval(self::TENTATIVAS); ?> vezes seguidas.</li>
				<li>Mostra só "usuário ou senha incorretos", sem dizer qual dos dois estava errado.</li>
				<li>Esconde a lista de usuários do site (<code>?author=</code> e <code>/wp-json/wp/v2/users</code>) para quem não está logado.</li>
			</ul>
			<h2>Se alguém ficar sem acesso</h2>
			<p>Adicione <code>define('CP_LOGIN_PADRAO', true);</code> no wp-config.php: o login volta para <code>/wp-login.php</code>. Depois de entrar, remova a linha.</p>
		</div>
		<?php
	}

	public static function ativar() {
		add_option(self::OPT, array('slug' => 'acesso-redacao', 'xmlrpc' => 1));
		set_transient('cp_login_recem_ativado', 1, 10 * MINUTE_IN_SECONDS);
	}

	public function aviso_ativacao() {
		if (!get_transient('cp_login_recem_ativado') || !current_user_can('manage_options')) {
			return;
		}
		delete_transient('cp_login_recem_ativado');
		echo '<div class="notice notice-warning"><p><strong>O login do site mudou.</strong> Agora é <a href="' . esc_url($this->url_login()) . '"><code>' . esc_html($this->url_login()) . '</code></a>. Salve esse endereço antes de sair. <a href="' . esc_url(admin_url('options-general.php?page=cp-login')) . '">Mudar o endereço</a></p></div>';
	}
}

register_activation_hook(__FILE__, array('CP_Login', 'ativar'));
new CP_Login();
