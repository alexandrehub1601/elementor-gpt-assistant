<?php
/**
 * Plugin Name: Agenda Conexão Pop
 * Description: Agenda de shows e festivais do Conexão Pop. Cadastro de shows no painel, envio de shows por artistas e produtoras (com aprovação), alertas de fãs e importação da Ticketmaster.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Conexão Pop
 * Text Domain: agenda-conexao-pop
 */

if (!defined('ABSPATH')) {
	exit;
}

define('CPA_VERSION', '2.0.0');
define('CPA_FILE', __FILE__);
define('CPA_DIR', plugin_dir_path(__FILE__));
define('CPA_URL', plugin_dir_url(__FILE__));

final class Agenda_Conexao_Pop {

	const CPT        = 'cp_show';
	const CPT_ALERTA = 'cp_alerta';
	const TAX        = 'cp_genero';
	const OPT        = 'cpa_opcoes';
	const CACHE      = 'cpa_eventos_v1';
	const CRON       = 'cpa_importar_ticketmaster';

	/** Ticketeiras aceitas: chave => nome mostrado. */
	public static function ticketeiras() {
		return array(
			''             => 'A confirmar',
			'ticketmaster' => 'Ticketmaster',
			'eventim'      => 'Eventim',
			'ticket360'    => 'Ticket360',
			'ingresse'     => 'Ingresse',
			'sympla'       => 'Sympla',
			'outra'        => 'Site da produtora',
		);
	}

	public static function generos_padrao() {
		return array(
			'pop'        => 'Pop',
			'popbr'      => 'Pop nacional',
			'rnb'        => 'R&B',
			'kpop'       => 'K-pop / J-pop',
			'rock'       => 'Rock',
			'indie'      => 'Indie',
			'mpb'        => 'MPB',
			'pagode'     => 'Pagode',
			'rap'        => 'Rap',
			'metal'      => 'Metal',
			'eletronica' => 'Eletrônica',
			'sertanejo'  => 'Sertanejo',
			'funk'       => 'Funk',
			'reggae'     => 'Reggae',
		);
	}

	public static function ufs() {
		return array('AC' => 'Acre', 'AL' => 'Alagoas', 'AM' => 'Amazonas', 'AP' => 'Amapá', 'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão', 'MG' => 'Minas Gerais', 'MS' => 'Mato Grosso do Sul', 'MT' => 'Mato Grosso', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PE' => 'Pernambuco', 'PI' => 'Piauí', 'PR' => 'Paraná', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'RS' => 'Rio Grande do Sul', 'SC' => 'Santa Catarina', 'SE' => 'Sergipe', 'SP' => 'São Paulo', 'TO' => 'Tocantins');
	}

	public static function opcoes() {
		return wp_parse_args(get_option(self::OPT, array()), array(
			'tm_chave'      => '',
			'tm_auto'       => 0,
			'banner_imagem' => '',
			'banner_link'   => '',
			'banner_alt'    => '',
			'pagina_cadastro' => 0,
			'email_aviso'   => get_option('admin_email'),
		));
	}

	public function __construct() {
		add_action('init', array($this, 'registrar_tipos'));
		add_action('add_meta_boxes', array($this, 'caixas'));
		add_action('save_post_' . self::CPT, array($this, 'salvar'), 10, 2);
		add_action('save_post_' . self::CPT, array(__CLASS__, 'limpar_cache'));
		add_action('deleted_post', array(__CLASS__, 'limpar_cache'));
		add_action('transition_post_status', array($this, 'mudou_status'), 10, 3);
		add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
		add_filter('manage_' . self::CPT . '_posts_columns', array($this, 'colunas'));
		add_action('manage_' . self::CPT . '_posts_custom_column', array($this, 'coluna'), 10, 2);
		add_filter('post_row_actions', array($this, 'acao_aprovar'), 10, 2);
		add_action('admin_post_cpa_aprovar', array($this, 'aprovar'));
		add_action('admin_menu', array($this, 'menu'));
		add_action('admin_init', array($this, 'registrar_opcoes'));
		add_action('admin_init', array(__CLASS__, 'garantir_pagina_cadastro'));
		add_action('admin_post_cpa_importar_inicial', array($this, 'importar_inicial'));
		add_action('admin_post_cpa_importar_tm', array($this, 'importar_tm_agora'));
		add_action(self::CRON, array($this, 'importar_ticketmaster'));
		add_action('admin_notices', array($this, 'avisos_admin'));

		add_shortcode('agenda_conexao', array($this, 'sc_agenda'));
		add_shortcode('agenda_conexao_cadastro', array($this, 'sc_cadastro'));
		add_action('admin_post_cpa_enviar', array($this, 'receber_envio'));
		add_action('admin_post_nopriv_cpa_enviar', array($this, 'receber_envio'));
		add_action('rest_api_init', array($this, 'rotas'));
		add_action('wp_enqueue_scripts', array($this, 'registrar_assets'));
	}

	/* ------------------------------------------------------------------ */
	/* Tipos de conteúdo                                                   */
	/* ------------------------------------------------------------------ */

	public function registrar_tipos() {
		register_post_type(self::CPT, array(
			'labels' => array(
				'name' => 'Shows', 'singular_name' => 'Show', 'menu_name' => 'Agenda de shows',
				'add_new' => 'Adicionar show', 'add_new_item' => 'Adicionar show', 'edit_item' => 'Editar show',
				'new_item' => 'Novo show', 'view_item' => 'Ver show', 'search_items' => 'Buscar shows',
				'not_found' => 'Nenhum show encontrado', 'all_items' => 'Todos os shows',
				'featured_image' => 'Capa do show', 'set_featured_image' => 'Escolher capa',
				'remove_featured_image' => 'Remover capa', 'use_featured_image' => 'Usar como capa',
			),
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_icon'     => 'dashicons-tickets-alt',
			'menu_position' => 6,
			'supports'      => array('title', 'thumbnail'),
			'capability_type' => 'post',
		));

		register_taxonomy(self::TAX, self::CPT, array(
			'labels' => array('name' => 'Gêneros', 'singular_name' => 'Gênero', 'menu_name' => 'Gêneros', 'add_new_item' => 'Adicionar gênero'),
			'public' => false, 'show_ui' => true, 'show_admin_column' => true, 'hierarchical' => true,
		));

		register_post_type(self::CPT_ALERTA, array(
			'labels' => array('name' => 'Alertas de fãs', 'singular_name' => 'Alerta', 'menu_name' => 'Alertas de fãs', 'all_items' => 'Alertas de fãs'),
			'public' => false, 'show_ui' => true, 'show_in_menu' => 'edit.php?post_type=' . self::CPT,
			'supports' => array('title'), 'capabilities' => array('create_posts' => 'do_not_allow'), 'map_meta_cap' => true,
		));
	}

	public static function ativar() {
		$self = new self();
		$self->registrar_tipos();
		foreach (self::generos_padrao() as $slug => $nome) {
			if (!term_exists($slug, self::TAX)) {
				wp_insert_term($nome, self::TAX, array('slug' => $slug));
			}
		}
		if (!wp_next_scheduled(self::CRON)) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON);
		}
	}

	/** Cria a página "Cadastrar meu show" (/cadastrar-meu-show/) se ela ainda não existir, e liga ela à agenda. */
	public static function garantir_pagina_cadastro() {
		if (!current_user_can('manage_options')) {
			return;
		}
		$o  = get_option(self::OPT, array());
		$id = absint($o['pagina_cadastro'] ?? 0);
		if ($id && get_post_status($id) && get_post_status($id) !== 'trash') {
			return;
		}
		$pag = get_page_by_path('cadastrar-meu-show');
		if ($pag && $pag->post_status !== 'trash') {
			$id = $pag->ID;
			if (strpos((string) $pag->post_content, '[agenda_conexao_cadastro]') === false) {
				wp_update_post(array('ID' => $id, 'post_content' => $pag->post_content . "\n\n[agenda_conexao_cadastro]"));
			}
		} else {
			$id = wp_insert_post(array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Cadastrar meu show',
				'post_name'    => 'cadastrar-meu-show',
				'post_content' => "<!-- wp:shortcode -->\n[agenda_conexao_cadastro]\n<!-- /wp:shortcode -->",
			));
		}
		if ($id && !is_wp_error($id)) {
			$o['pagina_cadastro'] = $id;
			update_option(self::OPT, $o);
			self::limpar_cache();
		}
	}

	public static function desativar() {
		wp_clear_scheduled_hook(self::CRON);
	}

	public static function limpar_cache() {
		delete_transient(self::CACHE);
	}

	/* ------------------------------------------------------------------ */
	/* Edição no painel                                                    */
	/* ------------------------------------------------------------------ */

	public function caixas() {
		add_meta_box('cpa_dados', 'Datas, local e ingressos', array($this, 'caixa_dados'), self::CPT, 'normal', 'high');
		add_meta_box('cpa_envio', 'Enviado por', array($this, 'caixa_envio'), self::CPT, 'side');
	}

	public static function datas($post_id) {
		$d = get_post_meta($post_id, '_cpa_datas', true);
		return is_array($d) ? $d : array();
	}

	public function caixa_dados($post) {
		wp_nonce_field('cpa_salvar', 'cpa_nonce');
		$sub   = get_post_meta($post->ID, '_cpa_sub', true);
		$fest  = get_post_meta($post->ID, '_cpa_festival', true);
		$tk    = get_post_meta($post->ID, '_cpa_ticketeira', true);
		$link  = get_post_meta($post->ID, '_cpa_link', true);
		$datas = self::datas($post->ID);
		if (!$datas) {
			$datas = array(array('data' => '', 'hora' => '', 'cidade' => '', 'uf' => '', 'local' => '', 'status' => '', 'portoes' => ''));
		}
		?>
		<table class="form-table cpa-form">
			<tr><th><label for="cpa_sub">Turnê ou subtítulo</label></th>
				<td><input type="text" class="regular-text" id="cpa_sub" name="cpa_sub" value="<?php echo esc_attr($sub); ?>" placeholder="Ex.: The Lucky Me Tour"></td></tr>
			<tr><th>Tipo</th>
				<td><label><input type="checkbox" name="cpa_festival" value="1" <?php checked($fest, '1'); ?>> É um festival (aparece na aba Festivais)</label></td></tr>
			<tr><th><label for="cpa_ticketeira">Ticketeira</label></th>
				<td><select id="cpa_ticketeira" name="cpa_ticketeira">
					<?php foreach (self::ticketeiras() as $k => $n) : ?>
						<option value="<?php echo esc_attr($k); ?>" <?php selected($tk, $k); ?>><?php echo esc_html($n); ?></option>
					<?php endforeach; ?>
				</select></td></tr>
			<tr><th><label for="cpa_link">Link de ingressos</label></th>
				<td><input type="url" class="large-text" id="cpa_link" name="cpa_link" value="<?php echo esc_attr($link); ?>" placeholder="https://">
					<p class="description">Opcional. Só preencha quando quiser que o card leve para a compra (por exemplo, o link de parceria com UTM). Sem link, o card mostra só o nome da ticketeira.</p></td></tr>
		</table>

		<h4 style="margin:18px 0 8px">Datas</h4>
		<p class="description">Uma linha por apresentação. Datas da mesma turnê no mesmo local aparecem juntas no mesmo card.</p>
		<div class="cpa-datas-wrap">
		<table class="widefat cpa-datas">
			<thead><tr><th>Data</th><th>Horário</th><th>Cidade</th><th>UF</th><th>Local</th><th>Situação</th><th>Portões</th><th></th></tr></thead>
			<tbody>
			<?php foreach ($datas as $i => $d) : $this->linha_data($i, $d); endforeach; ?>
			</tbody>
		</table>
		</div>
		<p><button type="button" class="button cpa-add">Adicionar data</button></p>
		<script type="text/html" id="tmpl-cpa-linha"><?php $this->linha_data('__i__', array()); ?></script>
		<?php
	}

	private function linha_data($i, $d) {
		$d = wp_parse_args($d, array('data' => '', 'hora' => '', 'cidade' => '', 'uf' => '', 'local' => '', 'status' => '', 'portoes' => ''));
		$n = 'cpa_datas[' . $i . ']';
		?>
		<tr>
			<td><input type="date" name="<?php echo esc_attr($n); ?>[data]" value="<?php echo esc_attr($d['data']); ?>"></td>
			<td><input type="text" size="6" name="<?php echo esc_attr($n); ?>[hora]" value="<?php echo esc_attr($d['hora']); ?>" placeholder="21h"></td>
			<td><input type="text" name="<?php echo esc_attr($n); ?>[cidade]" value="<?php echo esc_attr($d['cidade']); ?>"></td>
			<td><select name="<?php echo esc_attr($n); ?>[uf]"><option value=""></option><?php foreach (array_keys(self::ufs()) as $uf) : ?><option <?php selected($d['uf'], $uf); ?>><?php echo esc_html($uf); ?></option><?php endforeach; ?></select></td>
			<td><input type="text" name="<?php echo esc_attr($n); ?>[local]" value="<?php echo esc_attr($d['local']); ?>"></td>
			<td><select name="<?php echo esc_attr($n); ?>[status]">
				<option value="" <?php selected($d['status'], ''); ?>>Normal</option>
				<option value="esgotado" <?php selected($d['status'], 'esgotado'); ?>>Esgotado</option>
				<option value="extra" <?php selected($d['status'], 'extra'); ?>>Data extra</option>
				<option value="cancelado" <?php selected($d['status'], 'cancelado'); ?>>Cancelado</option>
			</select></td>
			<td><input type="text" size="6" name="<?php echo esc_attr($n); ?>[portoes]" value="<?php echo esc_attr($d['portoes']); ?>" placeholder="18h"></td>
			<td><button type="button" class="button-link cpa-del" aria-label="Remover data">Remover</button></td>
		</tr>
		<?php
	}

	public function caixa_envio($post) {
		$nome  = get_post_meta($post->ID, '_cpa_contato_nome', true);
		$email = get_post_meta($post->ID, '_cpa_contato_email', true);
		$tm    = get_post_meta($post->ID, '_cpa_tm_id', true);
		if ($nome || $email) {
			echo '<p><strong>' . esc_html($nome) . '</strong><br>' . esc_html($email) . '</p><p class="description">Enviado pelo formulário "Cadastrar meu show".</p>';
		} elseif ($tm) {
			echo '<p>Importado da Ticketmaster.</p><p class="description">ID do evento: ' . esc_html($tm) . '</p>';
		} else {
			echo '<p class="description">Cadastrado pela redação.</p>';
		}
	}

	public function salvar($post_id, $post) {
		if (!isset($_POST['cpa_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cpa_nonce'])), 'cpa_salvar')) {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}
		update_post_meta($post_id, '_cpa_sub', sanitize_text_field(wp_unslash($_POST['cpa_sub'] ?? '')));
		update_post_meta($post_id, '_cpa_festival', empty($_POST['cpa_festival']) ? '' : '1');
		$tk = sanitize_key(wp_unslash($_POST['cpa_ticketeira'] ?? ''));
		update_post_meta($post_id, '_cpa_ticketeira', array_key_exists($tk, self::ticketeiras()) ? $tk : '');
		update_post_meta($post_id, '_cpa_link', esc_url_raw(wp_unslash($_POST['cpa_link'] ?? '')));
		$brutas = isset($_POST['cpa_datas']) && is_array($_POST['cpa_datas']) ? wp_unslash($_POST['cpa_datas']) : array(); // phpcs:ignore -- limpo abaixo
		self::gravar_datas($post_id, $brutas);
	}

	/** Limpa, ordena e grava as datas, junto com a primeira e a última data (para filtrar e ordenar). */
	public static function gravar_datas($post_id, $brutas) {
		$ufs   = self::ufs();
		$datas = array();
		foreach ((array) $brutas as $d) {
			$data = sanitize_text_field($d['data'] ?? '');
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
				continue;
			}
			$uf = strtoupper(sanitize_text_field($d['uf'] ?? ''));
			$st = sanitize_key($d['status'] ?? '');
			$datas[] = array(
				'data'    => $data,
				'hora'    => sanitize_text_field($d['hora'] ?? ''),
				'cidade'  => sanitize_text_field($d['cidade'] ?? ''),
				'uf'      => isset($ufs[$uf]) ? $uf : '',
				'local'   => sanitize_text_field($d['local'] ?? ''),
				'status'  => in_array($st, array('esgotado', 'extra', 'cancelado'), true) ? $st : '',
				'portoes' => sanitize_text_field($d['portoes'] ?? ''),
			);
		}
		usort($datas, function ($a, $b) {
			return strcmp($a['data'], $b['data']);
		});
		update_post_meta($post_id, '_cpa_datas', $datas);
		update_post_meta($post_id, '_cpa_primeira', $datas ? $datas[0]['data'] : '');
		update_post_meta($post_id, '_cpa_ultima', $datas ? end($datas)['data'] : '');
		self::limpar_cache();
	}

	public function mudou_status($novo, $antigo, $post) {
		if ($post->post_type === self::CPT && $novo !== $antigo) {
			self::limpar_cache();
		}
	}

	public function admin_assets($hook) {
		$tela = get_current_screen();
		if (!$tela || $tela->post_type !== self::CPT) {
			return;
		}
		wp_enqueue_script('cpa-admin', CPA_URL . 'assets/admin.js', array(), CPA_VERSION, true);
		wp_add_inline_style('wp-admin', '.cpa-datas input[type=text]{width:100%}.cpa-datas td{vertical-align:middle}.cpa-datas-wrap{overflow-x:auto}.cpa-del{color:#b32d2e}');
	}

	public function colunas($c) {
		$novo = array();
		foreach ($c as $k => $v) {
			$novo[$k] = $v;
			if ($k === 'title') {
				$novo['cpa_data']  = 'Data';
				$novo['cpa_local'] = 'Local';
				$novo['cpa_tk']    = 'Ticketeira';
			}
		}
		unset($novo['date']);
		return $novo;
	}

	public function coluna($col, $id) {
		$datas = self::datas($id);
		if ($col === 'cpa_data') {
			if (!$datas) {
				echo '—';
				return;
			}
			$p = date_i18n('d/m/Y', strtotime($datas[0]['data']));
			echo esc_html($p . (count($datas) > 1 ? ' (+' . (count($datas) - 1) . ')' : ''));
		} elseif ($col === 'cpa_local') {
			echo $datas ? esc_html($datas[0]['local'] . ', ' . $datas[0]['cidade'] . ' (' . $datas[0]['uf'] . ')') : '—';
		} elseif ($col === 'cpa_tk') {
			$t = self::ticketeiras();
			$k = get_post_meta($id, '_cpa_ticketeira', true);
			echo esc_html($t[$k] ?? '—');
		}
	}

	public function acao_aprovar($acoes, $post) {
		if ($post->post_type === self::CPT && $post->post_status === 'pending' && current_user_can('publish_post', $post->ID)) {
			$url = wp_nonce_url(admin_url('admin-post.php?action=cpa_aprovar&post=' . $post->ID), 'cpa_aprovar_' . $post->ID);
			$acoes = array('cpa_aprovar' => '<a href="' . esc_url($url) . '" style="color:#008a20;font-weight:600">Aprovar e publicar</a>') + $acoes;
		}
		return $acoes;
	}

	public function aprovar() {
		$id = absint($_GET['post'] ?? 0);
		check_admin_referer('cpa_aprovar_' . $id);
		if (!current_user_can('publish_post', $id)) {
			wp_die('Você não tem permissão para publicar este show.');
		}
		wp_update_post(array('ID' => $id, 'post_status' => 'publish'));
		wp_safe_redirect(admin_url('edit.php?post_type=' . self::CPT . '&cpa_msg=aprovado'));
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Configurações                                                       */
	/* ------------------------------------------------------------------ */

	public function menu() {
		$pend = wp_count_posts(self::CPT)->pending ?? 0;
		if ($pend) {
			global $menu;
			foreach ((array) $menu as $i => $m) {
				if (($m[2] ?? '') === 'edit.php?post_type=' . self::CPT) {
					$menu[$i][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . intval($pend) . '</span></span>';
				}
			}
		}
		add_submenu_page('edit.php?post_type=' . self::CPT, 'Configurações da agenda', 'Configurações', 'manage_options', 'cpa-config', array($this, 'pagina_config'));
	}

	public function registrar_opcoes() {
		register_setting('cpa_config', self::OPT, array('sanitize_callback' => array($this, 'limpar_opcoes')));
	}

	public function limpar_opcoes($v) {
		self::limpar_cache();
		$auto = empty($v['tm_auto']) ? 0 : 1;
		if ($auto && !wp_next_scheduled(self::CRON)) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON);
		}
		return array(
			'tm_chave'        => sanitize_text_field($v['tm_chave'] ?? ''),
			'tm_auto'         => $auto,
			'banner_imagem'   => esc_url_raw($v['banner_imagem'] ?? ''),
			'banner_link'     => esc_url_raw($v['banner_link'] ?? ''),
			'banner_alt'      => sanitize_text_field($v['banner_alt'] ?? ''),
			'pagina_cadastro' => absint($v['pagina_cadastro'] ?? 0),
			'email_aviso'     => sanitize_email($v['email_aviso'] ?? ''),
		);
	}

	public function pagina_config() {
		$o = self::opcoes();
		?>
		<div class="wrap">
			<h1>Configurações da agenda</h1>
			<form method="post" action="options.php">
				<?php settings_fields('cpa_config'); ?>
				<h2>Páginas</h2>
				<table class="form-table">
					<tr><th>Página "Cadastrar meu show"</th><td>
						<?php wp_dropdown_pages(array('name' => self::OPT . '[pagina_cadastro]', 'selected' => $o['pagina_cadastro'], 'show_option_none' => 'Escolha a página', 'option_none_value' => 0)); ?>
						<p class="description">A página onde você colou <code>[agenda_conexao_cadastro]</code>. O botão "Cadastrar meu show" da agenda leva para ela.</p>
					</td></tr>
					<tr><th><label for="cpa_email">E-mail para avisos</label></th><td>
						<input type="email" class="regular-text" id="cpa_email" name="<?php echo esc_attr(self::OPT); ?>[email_aviso]" value="<?php echo esc_attr($o['email_aviso']); ?>">
						<p class="description">Recebe um aviso quando chega um show para aprovar.</p>
					</td></tr>
				</table>
				<h2>Banner de destaque</h2>
				<p>Aparece acima da lista de shows, por exemplo para uma parceria com a Ticketmaster.</p>
				<table class="form-table">
					<tr><th><label for="cpa_bi">Imagem (URL)</label></th><td><input type="url" class="large-text" id="cpa_bi" name="<?php echo esc_attr(self::OPT); ?>[banner_imagem]" value="<?php echo esc_attr($o['banner_imagem']); ?>" placeholder="https://conexaopop.com.br/wp-content/uploads/..."><p class="description">Tamanho sugerido: 1200 × 250. Deixe vazio para não mostrar banner.</p></td></tr>
					<tr><th><label for="cpa_bl">Link</label></th><td><input type="url" class="large-text" id="cpa_bl" name="<?php echo esc_attr(self::OPT); ?>[banner_link]" value="<?php echo esc_attr($o['banner_link']); ?>"></td></tr>
					<tr><th><label for="cpa_ba">Texto alternativo</label></th><td><input type="text" class="large-text" id="cpa_ba" name="<?php echo esc_attr(self::OPT); ?>[banner_alt]" value="<?php echo esc_attr($o['banner_alt']); ?>" placeholder="O que está escrito no banner"></td></tr>
				</table>
				<h2>Ticketmaster</h2>
				<table class="form-table">
					<tr><th><label for="cpa_tm">Chave da API (Consumer Key)</label></th><td><input type="text" class="regular-text" id="cpa_tm" name="<?php echo esc_attr(self::OPT); ?>[tm_chave]" value="<?php echo esc_attr($o['tm_chave']); ?>" autocomplete="off"><p class="description">Grátis em developer.ticketmaster.com (My Apps). Com a chave, a agenda importa shows de música no Brasil com a capa oficial.</p></td></tr>
					<tr><th>Importação diária</th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[tm_auto]" value="1" <?php checked($o['tm_auto'], 1); ?>> Importar shows novos uma vez por dia</label><p class="description">Os shows importados chegam como "Pendente" para a redação revisar.</p></td></tr>
				</table>
				<?php submit_button('Salvar configurações'); ?>
			</form>
			<hr>
			<h2>Importar shows</h2>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:12px">
				<?php wp_nonce_field('cpa_importar_tm'); ?><input type="hidden" name="action" value="cpa_importar_tm">
				<?php submit_button('Importar da Ticketmaster agora', 'secondary', 'submit', false, $o['tm_chave'] ? array() : array('disabled' => 'disabled')); ?>
			</form>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block">
				<?php wp_nonce_field('cpa_importar_inicial'); ?><input type="hidden" name="action" value="cpa_importar_inicial">
				<?php submit_button('Importar agenda inicial (125 shows e festivais)', 'secondary', 'submit', false); ?>
			</form>
			<p class="description">Traz os 125 shows e festivais que estão hoje em conexaopop.com.br/agenda, já publicados e com capa. Depois é só editar ou mandar para a lixeira o que quiser. Se clicar de novo, não duplica: só cria o que ainda não existe. A importação sobe as capas para a biblioteca de mídia e pode levar alguns minutos.</p>
			<h2>Como usar</h2>
			<ol>
				<li>Crie uma página "Agenda" e cole <code>[agenda_conexao]</code> num widget de shortcode do Elementor (ou no editor).</li>
				<li>Crie uma página "Cadastrar meu show" com <code>[agenda_conexao_cadastro]</code> e escolha essa página acima.</li>
				<li>Shows enviados por artistas e produtoras chegam em <strong>Agenda de shows</strong> como "Pendente". Use "Aprovar e publicar" na lista.</li>
			</ol>
		</div>
		<?php
	}

	public function avisos_admin() {
		$m = sanitize_key($_GET['cpa_msg'] ?? '');
		$n = absint($_GET['cpa_n'] ?? 0);
		$txt = array(
			'aprovado'  => 'Show aprovado e publicado na agenda.',
			'inicial'   => sprintf('%d shows e festivais importados e publicados. Edite ou mande para a lixeira o que quiser.', $n),
			'tm'        => sprintf('%d shows novos importados da Ticketmaster como pendentes.', $n),
			'tm_erro'   => 'A Ticketmaster não respondeu. Confira a chave da API e tente de novo.',
		);
		if (isset($txt[$m])) {
			$cls = $m === 'tm_erro' ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr($cls) . ' is-dismissible"><p>' . esc_html($txt[$m]) . '</p></div>';
		}
		// Agenda vazia: oferece a importação ali mesmo.
		$tela = get_current_screen();
		$contagem = wp_count_posts(self::CPT);
		$total = 0;
		foreach ((array) $contagem as $st => $q) {
			if ($st !== 'trash' && $st !== 'auto-draft') {
				$total += (int) $q;
			}
		}
		if (!$total && $tela && $tela->post_type === self::CPT && current_user_can('manage_options') && $m !== 'inicial') {
			echo '<div class="notice notice-info"><p><strong>A agenda ainda está vazia.</strong> Importe os shows e festivais que já estão na página /agenda do site, já publicados e com capa.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0 0 12px">';
			wp_nonce_field('cpa_importar_inicial');
			echo '<input type="hidden" name="action" value="cpa_importar_inicial"><button class="button button-primary">Importar agenda agora</button></form></div>';
		}
	}

	/* ------------------------------------------------------------------ */
	/* Importações                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Importa a agenda inicial em lotes de 10, com uma tela de progresso entre um lote e outro.
	 * Assim a hospedagem não corta a importação no meio por demorar demais.
	 */
	public function importar_inicial() {
		check_admin_referer('cpa_importar_inicial');
		if (!current_user_can('manage_options')) {
			wp_die('Sem permissão.');
		}
		if (function_exists('set_time_limit')) {
			@set_time_limit(120); // phpcs:ignore
		}
		$lote   = 10;
		$inicio = absint($_REQUEST['cpa_off'] ?? 0);
		$n      = absint($_REQUEST['cpa_n'] ?? 0);
		$json   = json_decode((string) file_get_contents(CPA_DIR . 'data/agenda-inicial.json'), true);
		$shows  = $json['shows'] ?? array();
		$total  = count($shows);
		if (!$total) {
			wp_die('Não encontrei o arquivo da agenda inicial dentro do plugin. Reinstale o plugin e tente de novo.');
		}
		$mapa_tk = array('tm' => 'ticketmaster', 'ing' => 'ingresse');
		foreach (array_slice($shows, $inicio, $lote) as $s) {
			$existe = get_posts(array('post_type' => self::CPT, 'post_status' => 'any', 'meta_key' => '_cpa_seed', 'meta_value' => $s['id'], 'fields' => 'ids', 'numberposts' => 1));
			if ($existe) {
				continue;
			}
			$id = wp_insert_post(array('post_type' => self::CPT, 'post_status' => 'publish', 'post_title' => $s['n']), true);
			if (is_wp_error($id) || !$id) {
				continue;
			}
			update_post_meta($id, '_cpa_seed', $s['id']);
			update_post_meta($id, '_cpa_sub', $s['sub'] ?? '');
			update_post_meta($id, '_cpa_festival', in_array('fest', $s['g'], true) ? '1' : '');
			update_post_meta($id, '_cpa_ticketeira', $mapa_tk[$s['s'] ?? ''] ?? '');
			update_post_meta($id, '_cpa_link', $s['link'] ?? '');
			$generos = array_values(array_diff($s['g'], array('fest')));
			foreach ($generos as $g) {
				if (!term_exists($g, self::TAX)) {
					$nomes = $json['generos'] ?? array();
					wp_insert_term($nomes[$g] ?? ucfirst($g), self::TAX, array('slug' => $g));
				}
			}
			if ($generos) {
				wp_set_object_terms($id, $generos, self::TAX);
			}
			$datas = array();
			foreach ($s['d'] as $d) {
				$datas[] = array('data' => $d[0], 'cidade' => $d[1], 'uf' => $d[2], 'local' => $d[3], 'hora' => $d[4] ?? '', 'status' => $d[5] ?? '', 'portoes' => $d[6] ?? '');
			}
			self::gravar_datas($id, $datas);
			// Capa que vem dentro do plugin: sobe para a biblioteca de mídia do site.
			if (!empty($s['capa'])) {
				$orig = CPA_DIR . 'assets/capas/' . basename($s['capa']);
				if (file_exists($orig)) {
					require_once ABSPATH . 'wp-admin/includes/media.php';
					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/image.php';
					$tmp = wp_tempnam($s['capa']);
					copy($orig, $tmp);
					$anexo = media_handle_sideload(array('name' => sanitize_file_name($s['id'] . '.webp'), 'tmp_name' => $tmp), $id, $s['n']);
					if (is_wp_error($anexo)) {
						wp_delete_file($tmp);
					} else {
						set_post_thumbnail($id, $anexo);
					}
				}
			}
			$n++;
		}
		self::limpar_cache();
		$proximo = $inicio + $lote;
		if ($proximo < $total) {
			$url = wp_nonce_url(admin_url('admin-post.php?action=cpa_importar_inicial&cpa_off=' . $proximo . '&cpa_n=' . $n), 'cpa_importar_inicial');
			$feito = min($proximo, $total);
			nocache_headers();
			echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . esc_url($url) . '"><title>Importando a agenda</title>'
				. '<style>body{font-family:system-ui,sans-serif;background:#f0f0f1;display:grid;place-items:center;min-height:100vh;margin:0}div{background:#fff;padding:32px 40px;border-radius:8px;border-top:6px solid #FF56AC;max-width:420px}progress{width:100%;height:14px;accent-color:#FF56AC}</style></head><body><div>'
				. '<h1 style="margin:0 0 8px;font-size:20px">Importando a agenda…</h1>'
				. '<p>' . intval($feito) . ' de ' . intval($total) . ' shows e festivais. Não feche esta página.</p>'
				. '<progress max="' . intval($total) . '" value="' . intval($feito) . '"></progress>'
				. '<p style="font-size:13px;color:#666">Se parar por mais de um minuto, <a href="' . esc_url($url) . '">clique aqui para continuar</a>.</p>'
				. '</div></body></html>';
			exit;
		}
		wp_safe_redirect(admin_url('edit.php?post_type=' . self::CPT . '&cpa_msg=inicial&cpa_n=' . $n));
		exit;
	}

	public function importar_tm_agora() {
		check_admin_referer('cpa_importar_tm');
		if (!current_user_can('manage_options')) {
			wp_die('Sem permissão.');
		}
		$n = $this->importar_ticketmaster(true);
		$msg = $n === false ? 'tm_erro' : 'tm';
		wp_safe_redirect(admin_url('edit.php?post_type=' . self::CPT . '&post_status=pending&cpa_msg=' . $msg . '&cpa_n=' . intval($n)));
		exit;
	}

	/**
	 * Busca shows de música no Brasil na Discovery API e cria os que ainda não existem, como pendentes.
	 * Retorna quantos foram criados, ou false se a API falhar.
	 */
	public function importar_ticketmaster($forcar = false) {
		$o = self::opcoes();
		if (!$o['tm_chave'] || (!$forcar && !$o['tm_auto'])) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$criados = 0;
		for ($pagina = 0; $pagina < 3; $pagina++) {
			$url = add_query_arg(array(
				'apikey' => $o['tm_chave'], 'countryCode' => 'BR', 'classificationName' => 'music',
				'size' => 100, 'page' => $pagina, 'sort' => 'date,asc', 'locale' => '*',
			), 'https://app.ticketmaster.com/discovery/v2/events.json');
			$r = wp_remote_get($url, array('timeout' => 20));
			if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) {
				return $pagina === 0 ? false : $criados;
			}
			$corpo = json_decode(wp_remote_retrieve_body($r), true);
			$eventos = $corpo['_embedded']['events'] ?? array();
			foreach ($eventos as $ev) {
				$tm_id = sanitize_text_field($ev['id'] ?? '');
				if (!$tm_id) {
					continue;
				}
				$existe = get_posts(array('post_type' => self::CPT, 'post_status' => 'any', 'meta_key' => '_cpa_tm_id', 'meta_value' => $tm_id, 'fields' => 'ids', 'numberposts' => 1));
				if ($existe) {
					continue;
				}
				$venue = $ev['_embedded']['venues'][0] ?? array();
				$hora  = '';
				if (!empty($ev['dates']['start']['localTime'])) {
					$p = explode(':', $ev['dates']['start']['localTime']);
					$hora = intval($p[0]) . 'h' . ($p[1] !== '00' ? $p[1] : '');
				}
				$status = ($ev['dates']['status']['code'] ?? '') === 'cancelled' ? 'cancelado' : '';
				$id = wp_insert_post(array('post_type' => self::CPT, 'post_status' => 'pending', 'post_title' => sanitize_text_field($ev['name'] ?? 'Show')));
				if (is_wp_error($id) || !$id) {
					continue;
				}
				update_post_meta($id, '_cpa_tm_id', $tm_id);
				update_post_meta($id, '_cpa_ticketeira', 'ticketmaster');
				self::gravar_datas($id, array(array(
					'data' => $ev['dates']['start']['localDate'] ?? '', 'hora' => $hora,
					'cidade' => $venue['city']['name'] ?? '', 'uf' => $venue['state']['stateCode'] ?? '',
					'local' => $venue['name'] ?? '', 'status' => $status,
				)));
				// Capa: a maior imagem 16:9 que a Ticketmaster fornece.
				$melhor = null;
				foreach (($ev['images'] ?? array()) as $img) {
					if (($img['ratio'] ?? '') === '16_9' && (!$melhor || $img['width'] > $melhor['width'])) {
						$melhor = $img;
					}
				}
				if ($melhor && !empty($melhor['url'])) {
					$anexo = media_sideload_image(esc_url_raw($melhor['url']), $id, sanitize_text_field($ev['name'] ?? ''), 'id');
					if (!is_wp_error($anexo)) {
						set_post_thumbnail($id, $anexo);
					}
				}
				$criados++;
			}
			if (($corpo['page']['totalPages'] ?? 1) <= $pagina + 1) {
				break;
			}
		}
		return $criados;
	}

	/* ------------------------------------------------------------------ */
	/* Página da agenda                                                    */
	/* ------------------------------------------------------------------ */

	public function registrar_assets() {
		wp_register_style('cpa-kensington', 'https://use.typekit.net/xqr3xib.css', array(), null);
		wp_register_style('cpa-fontes', 'https://fonts.googleapis.com/css2?family=Geist+Pixel&family=TikTok+Sans:wght@400;500;600;700&display=swap', array('cpa-kensington'), null);
		wp_register_style('cpa-agenda', CPA_URL . 'assets/agenda.css', array('cpa-fontes'), CPA_VERSION);
		wp_register_script('cpa-agenda', CPA_URL . 'assets/agenda.js', array(), CPA_VERSION, true);
	}

	/** Shows publicados com alguma data de hoje em diante, já no formato que o JavaScript usa. */
	public static function eventos() {
		$cache = get_transient(self::CACHE);
		if (is_array($cache)) {
			return $cache;
		}
		$hoje = current_time('Y-m-d');
		$q = get_posts(array(
			'post_type' => self::CPT, 'post_status' => 'publish', 'numberposts' => 500,
			'meta_query' => array(array('key' => '_cpa_ultima', 'value' => $hoje, 'compare' => '>=', 'type' => 'DATE')),
			'meta_key' => '_cpa_primeira', 'orderby' => 'meta_value', 'order' => 'ASC',
		));
		$tks = self::ticketeiras();
		$out = array();
		foreach ($q as $p) {
			$datas = array();
			foreach (self::datas($p->ID) as $d) {
				if ($d['status'] === 'cancelado') {
					continue;
				}
				$datas[] = array($d['data'], $d['cidade'], $d['uf'], $d['local'], $d['hora'], $d['status'], $d['portoes']);
			}
			if (!$datas) {
				continue;
			}
			$generos = wp_get_object_terms($p->ID, self::TAX, array('fields' => 'slugs'));
			$g = is_wp_error($generos) ? array() : $generos;
			if (get_post_meta($p->ID, '_cpa_festival', true)) {
				$g[] = 'fest';
			}
			$tk  = get_post_meta($p->ID, '_cpa_ticketeira', true);
			$img = get_the_post_thumbnail_url($p->ID, 'large');
			$out[] = array(
				'id'   => 'p' . $p->ID,
				'n'    => html_entity_decode(get_the_title($p), ENT_QUOTES, 'UTF-8'),
				'sub'  => get_post_meta($p->ID, '_cpa_sub', true),
				'g'    => array_values($g),
				'tk'   => $tk ? ($tks[$tk] ?? '') : '',
				'link' => get_post_meta($p->ID, '_cpa_link', true),
				'img'  => $img ? $img : '',
				'd'    => $datas,
			);
		}
		set_transient(self::CACHE, $out, HOUR_IN_SECONDS);
		return $out;
	}

	public function sc_agenda() {
		wp_enqueue_style('cpa-agenda');
		wp_enqueue_script('cpa-agenda');
		$o = self::opcoes();
		$generos = array();
		foreach (get_terms(array('taxonomy' => self::TAX, 'hide_empty' => false)) as $t) {
			$generos[$t->slug] = html_entity_decode($t->name, ENT_QUOTES, 'UTF-8');
		}
		wp_localize_script('cpa-agenda', 'CPAGENDA', array(
			'eventos' => self::eventos(),
			'generos' => $generos,
			'ufs'     => self::ufs(),
			'hoje'    => current_time('Y-m-d'),
			'alerta'  => esc_url_raw(rest_url('agenda-cp/v1/alerta')),
			'nonce'   => wp_create_nonce('wp_rest'),
			'plateia' => CPA_URL . 'assets/img/plateia.webp',
		));
		$cad = $o['pagina_cadastro'] ? get_permalink($o['pagina_cadastro']) : '';
		ob_start();
		include CPA_DIR . 'templates/agenda.php';
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* Cadastro de shows por artistas e produtoras                         */
	/* ------------------------------------------------------------------ */

	public function sc_cadastro() {
		wp_enqueue_style('cpa-agenda');
		$o = self::opcoes();
		$enviado = isset($_GET['cpa_enviado']);
		$erro    = sanitize_key($_GET['cpa_erro'] ?? '');
		ob_start();
		include CPA_DIR . 'templates/cadastro.php';
		return ob_get_clean();
	}

	public function receber_envio() {
		$volta = wp_get_referer() ? wp_get_referer() : home_url('/');
		$volta = remove_query_arg(array('cpa_enviado', 'cpa_erro'), $volta);
		if (!isset($_POST['cpa_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cpa_nonce'])), 'cpa_enviar')) {
			wp_safe_redirect(add_query_arg('cpa_erro', 'expirou', $volta) . '#cpa-form');
			exit;
		}
		// Armadilha para robôs: campo escondido que pessoas não preenchem.
		if (!empty($_POST['cpa_site'])) {
			wp_safe_redirect(add_query_arg('cpa_enviado', '1', $volta));
			exit;
		}
		// Limite simples: 5 envios por hora por IP.
		$ip    = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
		$chave = 'cpa_env_' . md5($ip);
		$qtd   = (int) get_transient($chave);
		if ($qtd >= 5) {
			wp_safe_redirect(add_query_arg('cpa_erro', 'limite', $volta) . '#cpa-form');
			exit;
		}
		$c = function ($k) {
			return sanitize_text_field(wp_unslash($_POST[$k] ?? ''));
		};
		$artista = $c('cpa_artista');
		$data    = $c('cpa_data');
		$cidade  = $c('cpa_cidade');
		$uf      = strtoupper($c('cpa_uf'));
		$local   = $c('cpa_local');
		$nome    = $c('cpa_nome');
		$email   = sanitize_email(wp_unslash($_POST['cpa_email'] ?? ''));
		if (!$artista || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !$cidade || !isset(self::ufs()[$uf]) || !$local || !$nome || !is_email($email)) {
			wp_safe_redirect(add_query_arg('cpa_erro', 'campos', $volta) . '#cpa-form');
			exit;
		}
		$link = esc_url_raw(wp_unslash($_POST['cpa_link'] ?? ''));
		$tk = '';
		foreach (array('ticketmaster', 'eventim', 'ticket360', 'ingresse', 'sympla') as $s) {
			if ($link && stripos($link, $s) !== false) {
				$tk = $s;
			}
		}
		if (!$tk && $link) {
			$tk = 'outra';
		}
		$id = wp_insert_post(array('post_type' => self::CPT, 'post_status' => 'pending', 'post_title' => $artista));
		if (is_wp_error($id) || !$id) {
			wp_safe_redirect(add_query_arg('cpa_erro', 'falha', $volta) . '#cpa-form');
			exit;
		}
		update_post_meta($id, '_cpa_sub', $c('cpa_turne'));
		update_post_meta($id, '_cpa_ticketeira', $tk);
		// O link enviado fica guardado para a redação, mas só aparece no card se a redação decidir.
		update_post_meta($id, '_cpa_link_enviado', $link);
		update_post_meta($id, '_cpa_contato_nome', $nome);
		update_post_meta($id, '_cpa_contato_email', $email);
		$genero = sanitize_key(wp_unslash($_POST['cpa_genero'] ?? ''));
		if ($genero === 'festival') {
			update_post_meta($id, '_cpa_festival', '1');
		} elseif ($genero && term_exists($genero, self::TAX)) {
			wp_set_object_terms($id, array($genero), self::TAX);
		}
		self::gravar_datas($id, array(array('data' => $data, 'hora' => $c('cpa_hora'), 'cidade' => $cidade, 'uf' => $uf, 'local' => $local)));

		if (!empty($_FILES['cpa_arte']['name'])) {
			$tipo = wp_check_filetype(sanitize_file_name(wp_unslash($_FILES['cpa_arte']['name'])));
			$ok   = in_array($tipo['ext'], array('jpg', 'jpeg', 'png', 'webp'), true) && (int) $_FILES['cpa_arte']['size'] <= 5 * MB_IN_BYTES;
			if ($ok) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$anexo = media_handle_upload('cpa_arte', $id);
				if (!is_wp_error($anexo)) {
					set_post_thumbnail($id, $anexo);
				}
			}
		}
		set_transient($chave, $qtd + 1, HOUR_IN_SECONDS);

		$o = self::opcoes();
		if ($o['email_aviso']) {
			wp_mail(
				$o['email_aviso'],
				'Show para aprovar: ' . $artista,
				"Chegou um show pelo formulário da agenda.\n\n{$artista}\n" . date_i18n('d/m/Y', strtotime($data)) . " em {$local}, {$cidade} ({$uf})\nEnviado por {$nome} <{$email}>\n\nRevise e aprove: " . admin_url('post.php?post=' . $id . '&action=edit')
			);
		}
		wp_safe_redirect(add_query_arg('cpa_enviado', '1', $volta) . '#cpa-form');
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Alertas de fãs ("Me avisa")                                         */
	/* ------------------------------------------------------------------ */

	public function rotas() {
		register_rest_route('agenda-cp/v1', '/alerta', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array($this, 'salvar_alerta'),
			'args'                => array(
				'email'  => array('required' => true, 'type' => 'string'),
				'show'   => array('required' => true, 'type' => 'string'),
				'opcoes' => array('required' => false, 'type' => 'array'),
			),
		));
	}

	public function salvar_alerta(WP_REST_Request $r) {
		$email = sanitize_email($r['email']);
		$show  = absint(ltrim((string) $r['show'], 'p'));
		if (!is_email($email) || get_post_type($show) !== self::CPT || get_post_status($show) !== 'publish') {
			return new WP_Error('cpa_invalido', 'Confira o e-mail e tente de novo.', array('status' => 400));
		}
		$ip    = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
		$chave = 'cpa_al_' . md5($ip);
		if ((int) get_transient($chave) >= 20) {
			return new WP_Error('cpa_limite', 'Muitos pedidos seguidos. Tente de novo mais tarde.', array('status' => 429));
		}
		set_transient($chave, (int) get_transient($chave) + 1, HOUR_IN_SECONDS);
		$permitidas = array('venda', 'lote', 'extra', 'volta', 'lineup');
		$opcoes = array_values(array_intersect((array) $r['opcoes'], $permitidas));
		$existe = get_posts(array('post_type' => self::CPT_ALERTA, 'post_status' => 'private', 'title' => $email, 'meta_key' => '_cpa_show', 'meta_value' => $show, 'fields' => 'ids', 'numberposts' => 1));
		$id = $existe ? $existe[0] : wp_insert_post(array('post_type' => self::CPT_ALERTA, 'post_status' => 'private', 'post_title' => $email));
		if (is_wp_error($id) || !$id) {
			return new WP_Error('cpa_falha', 'Não foi possível salvar agora.', array('status' => 500));
		}
		update_post_meta($id, '_cpa_show', $show);
		update_post_meta($id, '_cpa_show_nome', get_the_title($show));
		update_post_meta($id, '_cpa_opcoes', $opcoes);
		return array('ok' => true);
	}
}

register_activation_hook(__FILE__, array('Agenda_Conexao_Pop', 'ativar'));
register_deactivation_hook(__FILE__, array('Agenda_Conexao_Pop', 'desativar'));
new Agenda_Conexao_Pop();
