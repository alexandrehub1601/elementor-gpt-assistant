<?php
/**
 * Plugin Name: Home Conexão Pop
 * Description: Blocos de home com a identidade 2026 do Conexão Pop: faixa de manchetes, destaques, "acabou de sair", em alta, editorias, próximos shows, slides e newsletter. Use os shortcodes no Elementor ou [cp_home] para a home inteira.
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Conexão Pop
 * Text Domain: conexao-pop-home
 */

if (!defined('ABSPATH')) {
	exit;
}

define('CPH_VERSION', '1.1.0');
define('CPH_URL', plugin_dir_url(__FILE__));
define('CPH_DIR', plugin_dir_path(__FILE__));

require_once CPH_DIR . 'inc/render.php';

final class CPH {

	const OPT = 'cph_opcoes';

	/** Editorias, na ordem de preferência para a etiqueta do card. */
	public static $editorias = array(
		'musica' => 'Música', 'shows' => 'Shows', 'festival' => 'Festivais', 'entrevistas' => 'Entrevistas',
		'marcas' => 'Marcas', 'coberturas' => 'Coberturas', 'eventos' => 'Eventos', 'filmes' => 'Filmes',
		'series' => 'Séries', 'games' => 'Games', 'lollapalooza' => 'Lollapalooza', 'rock-in-rio' => 'Rock in Rio',
		'the-town' => 'The Town', 'primavera-sound' => 'Primavera Sound', 'mercado' => 'Mercado',
	);

	/** Posts já mostrados nesta página, para nenhum bloco repetir matéria. */
	private static $usados = array();

	public static function opcoes() {
		return wp_parse_args(get_option(self::OPT, array()), array(
			'news_titulo' => 'Receba o melhor do pop no seu e-mail',
			'news_texto'  => 'Lançamentos, shows e festivais da semana, sem spam.',
			'news_embed'  => '',
		));
	}

	public function __construct() {
		add_action('wp_enqueue_scripts', array($this, 'assets'));
		foreach (array('cp_home', 'cp_faixa', 'cp_destaques', 'cp_agora', 'cp_em_alta', 'cp_editoria', 'cp_proximos_shows', 'cp_newsletter', 'cp_slides', 'cp_divisor', 'cp_capa', 'cp_mosaico', 'cp_manchetes', 'cp_sanfona') as $sc) {
			add_shortcode($sc, array($this, 'sc_' . substr($sc, 3)));
		}
		add_action('admin_menu', array($this, 'menu'));
		add_action('admin_init', array($this, 'registrar'));
		add_action('save_post_post', array(__CLASS__, 'limpar_cache'));
	}

	public static function limpar_cache() {
		delete_transient('cph_alta');
	}

	public function assets() {
		wp_register_style('cph-kensington', 'https://use.typekit.net/xqr3xib.css', array(), null);
		wp_register_style('cph-fontes', 'https://fonts.googleapis.com/css2?family=Geist+Pixel&family=TikTok+Sans:wght@400;500;600;700&display=swap', array('cph-kensington'), null);
		wp_register_style('cph', CPH_URL . 'assets/home.css', array('cph-fontes'), CPH_VERSION);
		wp_register_script('cph', CPH_URL . 'assets/home.js', array(), CPH_VERSION, true);
	}

	private function usar_assets() {
		wp_enqueue_style('cph');
		wp_enqueue_script('cph');
	}

	/* ------------------------------------------------------------------ */
	/* Dados                                                               */
	/* ------------------------------------------------------------------ */

	/** Converte um post no formato que os blocos usam. */
	public static function card($p) {
		$cats = get_the_category($p->ID);
		$slugs = wp_list_pluck($cats, 'slug');
		$label = '';
		foreach (self::$editorias as $slug => $nome) {
			if (in_array($slug, $slugs, true)) {
				$label = $nome;
				break;
			}
		}
		if (!$label && $cats) {
			foreach ($cats as $c) {
				if (!in_array($c->slug, array('destaque', 'brasil', 'especiais', 'conectese'), true)) {
					$label = $c->name;
					break;
				}
			}
		}
		$img = get_the_post_thumbnail_url($p->ID, 'large');
		return array(
			'id'    => $p->ID,
			't'     => html_entity_decode(get_the_title($p), ENT_QUOTES, 'UTF-8'),
			'u'     => get_permalink($p),
			'img'   => $img ? $img : '',
			'cat'   => $label,
			'quando'=> sprintf('há %s', human_time_diff(get_post_time('U', true, $p), time())),
			'ex'    => wp_trim_words(wp_strip_all_tags(get_the_excerpt($p)), 26, '…'),
		);
	}

	public static function buscar($args, $qtd) {
		$q = new WP_Query(wp_parse_args($args, array(
			'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => $qtd,
			'post__not_in' => self::$usados, 'ignore_sticky_posts' => true, 'no_found_rows' => true,
		)));
		$out = array();
		foreach ($q->posts as $p) {
			self::$usados[] = $p->ID;
			$out[] = self::card($p);
		}
		return $out;
	}

	/** Mais lidas dos últimos 14 dias. Usa o contador de visitas que o site já tiver. */
	public static function em_alta($qtd) {
		$base = array('date_query' => array(array('after' => '14 days ago')));
		if (function_exists('pvc_get_post_views')) { // Post Views Counter
			return self::buscar($base + array('orderby' => 'post_views', 'order' => 'DESC', 'suppress_filters' => false), $qtd);
		}
		$chave = get_transient('cph_chave_views');
		if ($chave === false) {
			global $wpdb;
			$chave = '';
			foreach (array('views', 'post_views_count', '_post_views', 'wpb_post_views_count', 'post_views') as $k) {
				if ($wpdb->get_var($wpdb->prepare("SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1", $k))) {
					$chave = $k;
					break;
				}
			}
			set_transient('cph_chave_views', $chave, DAY_IN_SECONDS);
		}
		if ($chave) {
			$r = self::buscar($base + array('meta_key' => $chave, 'orderby' => 'meta_value_num', 'order' => 'DESC'), $qtd);
			if (count($r) >= $qtd) {
				return $r;
			}
			return array_merge($r, self::buscar(array(), $qtd - count($r)));
		}
		// Sem contador: as mais comentadas recentes.
		return self::buscar($base + array('orderby' => 'comment_count date', 'order' => 'DESC'), $qtd);
	}

	/* ------------------------------------------------------------------ */
	/* Shortcodes                                                          */
	/* ------------------------------------------------------------------ */

	public function sc_home() {
		$this->usar_assets();
		$d = function ($n, $cor, $virar = false) {
			return CPH_Render::divisor($n, $cor, $virar);
		};
		return '<div class="cph cph-home">'
			. $this->sc_faixa(array('_dentro' => 1))
			. $this->sc_destaques(array('_dentro' => 1))
			. $d(3, 'rosa')
			. $this->sc_agora(array('_dentro' => 1))
			. $this->sc_em_alta(array('_dentro' => 1))
			. $d(1, 'preto')
			. $this->sc_editoria(array('categoria' => 'musica', 'titulo' => 'Música', '_dentro' => 1))
			. $this->sc_capa(array('categoria' => 'coberturas', 'rotulo' => 'Cobertura', '_dentro' => 1))
			. $this->sc_mosaico(array('categoria' => 'festival', 'titulo' => 'Festivais', '_dentro' => 1))
			. $d(4, 'azul')
			. $this->sc_proximos_shows(array('_dentro' => 1))
			. $this->sc_sanfona(array('categoria' => 'entrevistas', 'titulo' => 'Entrevistas', '_dentro' => 1))
			. $d(2, 'rosa')
			. $this->sc_manchetes(array('categoria' => 'marcas', 'titulo' => 'Marcas & Mercado', '_dentro' => 1))
			. $this->sc_slides(array('categoria' => 'shows', 'titulo' => 'Shows', 'cor' => 'azul', '_dentro' => 1))
			. $this->sc_newsletter(array('_dentro' => 1))
			. $this->sc_editoria(array('categoria' => 'eventos', 'titulo' => 'Eventos', '_dentro' => 1))
			. $d(3, 'preto')
			. '</div>';
	}

	public function sc_divisor($a = array()) {
		$a = shortcode_atts(array('padrao' => 2, 'cor' => 'rosa', 'virar' => 'nao'), $a);
		$this->usar_assets();
		return '<div class="cph">' . CPH_Render::divisor(intval($a['padrao']), sanitize_key($a['cor']), $a['virar'] === 'sim') . '</div>';
	}

	public function sc_capa($a = array()) {
		$a = shortcode_atts(array('categoria' => 'coberturas', 'rotulo' => '', '_dentro' => 0), $a);
		$p = self::buscar(array('category_name' => sanitize_title($a['categoria'])), 1);
		if (!$p) {
			$p = self::buscar(array(), 1);
		}
		return $this->envolver(CPH_Render::capa($p ? $p[0] : null, sanitize_text_field($a['rotulo'])), $a);
	}

	private function bloco_lista($a, $qtd_padrao, $render, $extra = null) {
		$a = shortcode_atts(array('categoria' => 'musica', 'titulo' => '', 'qtd' => $qtd_padrao, 'cor' => '', '_dentro' => 0), $a);
		$slug = sanitize_title($a['categoria']);
		$cat = get_category_by_slug($slug);
		$titulo = $a['titulo'] ? $a['titulo'] : ($cat ? $cat->name : $slug);
		$link = $cat ? get_category_link($cat) : home_url('/');
		$posts = self::buscar(array('category_name' => $slug), intval($a['qtd']));
		$html = $extra === null ? CPH_Render::$render($titulo, $link, $posts) : CPH_Render::$render($titulo, $link, $posts, sanitize_key($a['cor'] ? $a['cor'] : $extra));
		return $this->envolver($html, $a);
	}

	public function sc_mosaico($a = array()) {
		return $this->bloco_lista((array) $a + array('categoria' => 'festival'), 5, 'mosaico');
	}

	public function sc_manchetes($a = array()) {
		return $this->bloco_lista((array) $a + array('categoria' => 'marcas'), 6, 'manchetes');
	}

	public function sc_sanfona($a = array()) {
		return $this->bloco_lista((array) $a + array('categoria' => 'entrevistas'), 6, 'sanfona', 'preto');
	}

	private function envolver($html, $a) {
		$this->usar_assets();
		return empty($a['_dentro']) ? '<div class="cph">' . $html . '</div>' : $html;
	}

	public function sc_faixa($a = array()) {
		$a = shortcode_atts(array('qtd' => 10, '_dentro' => 0), $a);
		$posts = self::buscar(array(), intval($a['qtd']));
		// A faixa não "gasta" matérias: os outros blocos podem repeti-las.
		self::$usados = array_diff(self::$usados, wp_list_pluck($posts, 'id'));
		return $this->envolver(CPH_Render::faixa($posts), $a);
	}

	public function sc_destaques($a = array()) {
		$a = shortcode_atts(array('categoria' => 'destaque', '_dentro' => 0), $a);
		$posts = self::buscar(array('category_name' => sanitize_title($a['categoria'])), 2);
		if (count($posts) < 2) {
			$posts = array_merge($posts, self::buscar(array(), 2 - count($posts)));
		}
		return $this->envolver(CPH_Render::destaques($posts), $a);
	}

	public function sc_agora($a = array()) {
		$a = shortcode_atts(array('_dentro' => 0), $a);
		return $this->envolver(CPH_Render::agora(self::buscar(array(), 6)), $a);
	}

	public function sc_em_alta($a = array()) {
		$a = shortcode_atts(array('_dentro' => 0), $a);
		return $this->envolver(CPH_Render::em_alta(self::em_alta(6)), $a);
	}

	public function sc_editoria($a = array()) {
		$a = shortcode_atts(array('categoria' => 'musica', 'titulo' => '', 'qtd' => 4, '_dentro' => 0), $a);
		$slug = sanitize_title($a['categoria']);
		$cat = get_category_by_slug($slug);
		$titulo = $a['titulo'] ? $a['titulo'] : ($cat ? $cat->name : $slug);
		$link = $cat ? get_category_link($cat) : home_url('/');
		return $this->envolver(CPH_Render::editoria($titulo, $link, self::buscar(array('category_name' => $slug), intval($a['qtd']))), $a);
	}

	public function sc_slides($a = array()) {
		$a = shortcode_atts(array('categoria' => 'entrevistas', 'titulo' => '', 'qtd' => 8, 'cor' => 'limao', '_dentro' => 0), $a);
		$slug = sanitize_title($a['categoria']);
		$cat = get_category_by_slug($slug);
		$titulo = $a['titulo'] ? $a['titulo'] : ($cat ? $cat->name : $slug);
		$link = $cat ? get_category_link($cat) : home_url('/');
		return $this->envolver(CPH_Render::slides($titulo, $link, self::buscar(array('category_name' => $slug), intval($a['qtd'])), sanitize_key($a['cor'])), $a);
	}

	public function sc_proximos_shows($a = array()) {
		$a = shortcode_atts(array('qtd' => 6, '_dentro' => 0), $a);
		if (!class_exists('Agenda_Conexao_Pop')) {
			return '';
		}
		$hoje = current_time('Y-m-d');
		$lista = array();
		foreach (Agenda_Conexao_Pop::eventos() as $e) {
			if (in_array('fest', $e['g'], true)) {
				continue;
			}
			foreach ($e['d'] as $d) {
				if ($d[0] >= $hoje) {
					$lista[] = array('n' => $e['n'], 'data' => $d[0], 'local' => $d[3], 'cidade' => $d[1], 'uf' => $d[2], 'img' => $e['img'], 'status' => $d[5]);
					break;
				}
			}
		}
		usort($lista, function ($x, $y) {
			return strcmp($x['data'], $y['data']);
		});
		return $this->envolver(CPH_Render::proximos(array_slice($lista, 0, intval($a['qtd'])), home_url('/agenda/')), $a);
	}

	public function sc_newsletter($a = array()) {
		$a = shortcode_atts(array('_dentro' => 0), $a);
		$o = self::opcoes();
		return $this->envolver(CPH_Render::newsletter($o['news_titulo'], $o['news_texto'], $o['news_embed']), $a);
	}

	/* ------------------------------------------------------------------ */
	/* Configurações                                                       */
	/* ------------------------------------------------------------------ */

	public function menu() {
		add_options_page('Home Conexão Pop', 'Home Conexão Pop', 'manage_options', 'cph', array($this, 'pagina'));
	}

	public function registrar() {
		register_setting('cph', self::OPT, array('sanitize_callback' => function ($v) {
			return array(
				'news_titulo' => sanitize_text_field($v['news_titulo'] ?? ''),
				'news_texto'  => sanitize_text_field($v['news_texto'] ?? ''),
				// Código do formulário da ferramenta de newsletter: só administradores salvam isto.
				'news_embed'  => current_user_can('unfiltered_html') ? (string) ($v['news_embed'] ?? '') : wp_kses_post($v['news_embed'] ?? ''),
			);
		}));
	}

	public function pagina() {
		$o = self::opcoes();
		?>
		<div class="wrap">
			<h1>Home Conexão Pop</h1>
			<h2>Como montar a home</h2>
			<p>No Elementor, use um widget <strong>Shortcode</strong> com <code>[cp_home]</code> para a home inteira, ou monte do seu jeito com os blocos abaixo (cada um num widget):</p>
			<table class="widefat striped" style="max-width:900px">
				<tbody>
				<tr><td><code>[cp_faixa]</code></td><td>Faixa rosa com as últimas manchetes passando</td></tr>
				<tr><td><code>[cp_destaques]</code></td><td>2 manchetes grandes (categoria "destaque"; outra: <code>categoria="shows"</code>)</td></tr>
				<tr><td><code>[cp_agora]</code></td><td>Destaque + "Acabou de sair" com as 5 mais recentes</td></tr>
				<tr><td><code>[cp_em_alta]</code></td><td>Ranking verde-limão 01 a 06 com as mais lidas dos últimos 14 dias</td></tr>
				<tr><td><code>[cp_editoria categoria="musica" titulo="Música"]</code></td><td>4 matérias de uma editoria (<code>qtd="8"</code> para mais)</td></tr>
				<tr><td><code>[cp_slides categoria="entrevistas" titulo="Entrevistas"]</code></td><td>Carrossel de matérias (<code>cor="rosa"</code>, <code>cor="azul"</code> ou <code>cor="limao"</code>)</td></tr>
				<tr><td><code>[cp_proximos_shows]</code></td><td>Bloco preto com os próximos shows da agenda</td></tr>
				<tr><td><code>[cp_newsletter]</code></td><td>Newsletter (configure abaixo)</td></tr>
				<tr><td><code>[cp_capa categoria="coberturas" rotulo="Cobertura"]</code></td><td>Uma matéria em tela cheia, com título enorme e a skyline rosa</td></tr>
				<tr><td><code>[cp_mosaico categoria="festival" titulo="Festivais"]</code></td><td>Uma grande e quatro menores</td></tr>
				<tr><td><code>[cp_manchetes categoria="marcas" titulo="Marcas"]</code></td><td>Seis manchetes só em texto, estilo revista</td></tr>
				<tr><td><code>[cp_sanfona categoria="entrevistas" titulo="Entrevistas"]</code></td><td>Colunas com título na vertical que abrem ao passar o mouse (<code>cor="rosa"</code>, <code>azul</code>, <code>limao</code> ou <code>preto</code>)</td></tr>
				<tr><td><code>[cp_divisor padrao="3" cor="rosa"]</code></td><td>Divisória de pixels da identidade (padrão 1 a 4; cor rosa, preto, azul ou limao; <code>virar="sim"</code> inverte)</td></tr>
				</tbody>
			</table>
			<p>Nenhum bloco repete matéria de outro na mesma página. A ordem dos blocos na página define quem pega primeiro.</p>
			<form method="post" action="options.php">
				<?php settings_fields('cph'); ?>
				<h2>Newsletter</h2>
				<table class="form-table">
					<tr><th><label for="cph_nt">Título</label></th><td><input class="regular-text" id="cph_nt" name="<?php echo esc_attr(self::OPT); ?>[news_titulo]" value="<?php echo esc_attr($o['news_titulo']); ?>"></td></tr>
					<tr><th><label for="cph_nx">Texto</label></th><td><input class="large-text" id="cph_nx" name="<?php echo esc_attr(self::OPT); ?>[news_texto]" value="<?php echo esc_attr($o['news_texto']); ?>"></td></tr>
					<tr><th><label for="cph_ne">Formulário</label></th><td><textarea class="large-text code" rows="6" id="cph_ne" name="<?php echo esc_attr(self::OPT); ?>[news_embed]"><?php echo esc_textarea($o['news_embed']); ?></textarea>
						<p class="description">Cole o código do formulário da ferramenta de vocês (Mailchimp, RD Station, Brevo, Substack…). Vazio: o bloco mostra as redes sociais do Conexão Pop.</p></td></tr>
				</table>
				<?php submit_button('Salvar'); ?>
			</form>
		</div>
		<?php
	}
}

new CPH();
