<?php
/**
 * Plugin Name: Home Conexão Pop
 * Description: Blocos de home com a identidade 2026 do Conexão Pop: faixa de manchetes, destaques, "acabou de sair", em alta, editorias, próximos shows, slides e newsletter. Use os shortcodes no Elementor ou [cp_home] para a home inteira.
 * Version: 1.6.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Conexão Pop
 * Text Domain: conexao-pop-home
 */

if (!defined('ABSPATH')) {
	exit;
}

define('CPH_VERSION', '1.6.2');
define('CPH_URL', plugin_dir_url(__FILE__));
define('CPH_DIR', plugin_dir_path(__FILE__));

require_once CPH_DIR . 'inc/render.php';
require_once CPH_DIR . 'inc/paginas.php';
require_once CPH_DIR . 'inc/newsletter.php';
require_once CPH_DIR . 'inc/conteudo.php';

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
			'news_titulo' => 'A semana pop na sua caixa de entrada',
			'news_texto'  => 'Toda semana: o que bombou no pop, os lançamentos e os shows que vêm por aí. Direto na sua caixa de entrada.',
			'news_embed'  => '',
			'layout_materia'   => 0,
			'layout_categoria' => 0,
			'layout_busca'     => 0,
			'layout_404'       => 0,
			'tags_link'        => 1,
			'leia_tambem'      => 1,
			'tags_ignorar'     => '',
		));
	}

	public function __construct() {
		add_action('wp_enqueue_scripts', array($this, 'assets'));
		foreach (array('cp_home', 'cp_faixa', 'cp_destaques', 'cp_agora', 'cp_em_alta', 'cp_editoria', 'cp_proximos_shows', 'cp_newsletter', 'cp_slides', 'cp_divisor', 'cp_capa', 'cp_mosaico', 'cp_manchetes', 'cp_sanfona', 'cp_materia', 'cp_categoria', 'cp_busca', 'cp_404', 'cp_marcas', 'cp_ultimas') as $sc) {
			add_shortcode($sc, array($this, 'sc_' . substr($sc, 3)));
		}
		add_action('admin_menu', array($this, 'menu'));
		add_action('admin_init', array($this, 'registrar'));
		add_action('save_post_post', array(__CLASS__, 'limpar_cache'));
		add_filter('template_include', array($this, 'modelo'), 99);
		add_filter('wp_resource_hints', array($this, 'dicas'), 10, 2);
		add_action('pre_get_posts', array($this, 'por_pagina'));
		add_filter('body_class', array($this, 'classe_body'));
	}

	public static function limpar_cache() {
		delete_transient('cph_alta');
	}

	public function assets() {
		wp_register_style('cph-kensington', 'https://use.typekit.net/xqr3xib.css', array(), null);
		wp_register_style('cph-fontes', 'https://fonts.googleapis.com/css2?family=Geist+Pixel&family=TikTok+Sans:wght@400;500;600;700&display=swap', array('cph-kensington'), null);
		wp_register_style('cph', CPH_URL . 'assets/home.css', array('cph-fontes'), CPH_VERSION);
		wp_register_script('cph', CPH_URL . 'assets/home.js', array(), CPH_VERSION, true);
		// O visual vai no <head> em todas as páginas: assim nada aparece desconfigurado antes de carregar.
		if (!is_admin()) {
			wp_enqueue_style('cph');
		}
	}

	/** Avisa o navegador cedo que vai buscar as fontes. */
	public function dicas($urls, $tipo) {
		if ($tipo === 'preconnect') {
			$urls[] = 'https://use.typekit.net';
			$urls[] = array('href' => 'https://p.typekit.net', 'crossorigin');
			$urls[] = 'https://fonts.googleapis.com';
			$urls[] = array('href' => 'https://fonts.gstatic.com', 'crossorigin');
		}
		return $urls;
	}

	/** Categoria com 12 matérias por página (3 de abertura + 9 em grade, ou 12 em grade). */
	public function por_pagina($q) {
		if (!is_admin() && $q->is_main_query() && $q->is_category() && self::opcoes()['layout_categoria']) {
			$q->set('posts_per_page', 12);
		}
	}

	public function classe_body($c) {
		$o = self::opcoes();
		if (($o['layout_materia'] && is_singular('post')) || ($o['layout_categoria'] && is_category()) || ($o['layout_busca'] && is_search()) || ($o['layout_404'] && is_404())) {
			$c[] = 'cph-pagina';
		}
		return $c;
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
			. $this->sc_marcas(array('_dentro' => 1))
			. $this->sc_slides(array('categoria' => 'shows', 'titulo' => 'Shows', 'cor' => 'azul', '_dentro' => 1))
			. $this->sc_newsletter(array('_dentro' => 1))
			. $this->sc_editoria(array('categoria' => 'eventos', 'titulo' => 'Eventos', '_dentro' => 1))
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

	public function sc_marcas($a = array()) {
		$a = shortcode_atts(array('categoria' => 'marcas', 'titulo' => 'Marcas & Mercado', 'chamada' => 'Onde as marcas encontram o pop', 'qtd' => 8, '_dentro' => 0), $a);
		$slug = sanitize_title($a['categoria']);
		$cat = get_category_by_slug($slug);
		$posts = self::buscar(array('category_name' => $slug), intval($a['qtd']));
		return $this->envolver(CPH_Render::marcas($a['titulo'], $cat ? get_category_link($cat) : '', $posts, $a['chamada']), $a);
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
		return $this->envolver(CPH_Render::agora(self::buscar(array(), 10)), $a);
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
		$amostra = array();
		foreach (get_posts(array('numberposts' => 3, 'post_status' => 'publish')) as $p) {
			$amostra[] = array('t' => html_entity_decode(get_the_title($p), ENT_QUOTES, 'UTF-8'));
		}
		return $this->envolver(CPH_Render::newsletter($o['news_titulo'], $o['news_texto'], $o['news_embed'], $amostra, CPH_News::contar('ativo'), rest_url('cph/v1/news')), $a);
	}

	/* ------------------------------------------------------------------ */
	/* Matéria e categoria                                                 */
	/* ------------------------------------------------------------------ */

	public function modelo($tpl) {
		$o = self::opcoes();
		if ($o['layout_materia'] && is_singular('post')) {
			$this->usar_assets();
			return CPH_DIR . 'templates/single.php';
		}
		if ($o['layout_categoria'] && is_category()) {
			$this->usar_assets();
			return CPH_DIR . 'templates/categoria.php';
		}
		if ($o['layout_busca'] && is_search()) {
			$this->usar_assets();
			return CPH_DIR . 'templates/busca.php';
		}
		if ($o['layout_404'] && is_404()) {
			$this->usar_assets();
			return CPH_DIR . 'templates/404.php';
		}
		return $tpl;
	}

	public function sc_materia() {
		if (!is_singular('post')) {
			return '';
		}
		$this->usar_assets();
		return self::html_materia(get_post());
	}

	public function sc_categoria() {
		if (!is_category()) {
			return '';
		}
		$this->usar_assets();
		return self::html_categoria();
	}

	/** Página "Últimas notícias": todas as matérias, da mais nova para a mais antiga, com paginação. */
	public function sc_ultimas($a = array()) {
		$a = shortcode_atts(array('titulo' => 'Últimas notícias', 'texto' => 'Tudo o que saiu no Conexão Pop, da mais nova para a mais antiga.', 'qtd' => 12), $a);
		$this->usar_assets();
		$pag = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
		$q = new WP_Query(array('post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => max(3, (int) $a['qtd']), 'paged' => $pag, 'ignore_sticky_posts' => true));
		$posts = array();
		foreach ($q->posts as $p) {
			$posts[] = self::card($p);
		}
		$links = paginate_links(array(
			'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))), 'format' => '',
			'current' => $pag, 'total' => (int) $q->max_num_pages, 'type' => 'array', 'mid_size' => 1,
			'prev_text' => '← Anteriores', 'next_text' => 'Próximas →',
		));
		return '<div class="cph-ultimas">' . CPH_Paginas::categoria(array(
			'nome' => $a['titulo'], 'desc' => $a['texto'], 'total' => (int) wp_count_posts('post')->publish,
			'posts' => $posts, 'paginas' => $links ? $links : array(), 'pagina' => $pag,
		)) . '</div>';
	}

	public function sc_busca() {
		if (!is_search()) {
			return '';
		}
		$this->usar_assets();
		return self::html_busca();
	}

	public function sc_404() {
		$this->usar_assets();
		return self::html_404();
	}

	public static function html_busca() {
		global $wp_query;
		$q = get_search_query(false);
		$posts = array();
		foreach ((array) $wp_query->posts as $p) {
			if ($p->post_type === 'post') {
				$posts[] = self::card($p);
			}
		}
		$atual = (string) get_query_var('category_name');
		$eds = array(array('Tudo', add_query_arg('s', rawurlencode($q), home_url('/')), $atual === ''));
		foreach (array('musica', 'shows', 'festival', 'entrevistas', 'marcas', 'coberturas') as $slug) {
			if (get_category_by_slug($slug)) {
				$eds[] = array(self::$editorias[$slug], add_query_arg(array('s' => rawurlencode($q), 'category_name' => $slug), home_url('/')), $atual === $slug);
			}
		}
		$pag = paginate_links(array('type' => 'array', 'prev_text' => '← Anteriores', 'next_text' => 'Próximas →', 'mid_size' => 1));
		return CPH_Paginas::busca(array(
			'q'         => $q,
			'total'     => (int) $wp_query->found_posts,
			'posts'     => $posts,
			'paginas'   => $pag ? $pag : array(),
			'editorias' => $eds,
			'alta'      => $posts ? array() : self::em_alta(6),
			'acao'      => home_url('/'),
		));
	}

	public static function html_404() {
		return CPH_Paginas::erro404(array(
			'home'   => home_url('/'),
			'agenda' => home_url('/agenda/'),
			'acao'   => home_url('/'),
			'alta'   => self::em_alta(6),
		));
	}

	/** Subtítulo da matéria: o resumo (excerpt) escrito pela redação ou o subtítulo do tema SmartMag. */
	public static function subtitulo($post) {
		$s = trim(wp_strip_all_tags((string) $post->post_excerpt));
		if ($s === '') {
			foreach (array('_bunyad_sub_title', 'sub_title', '_subtitle', 'subtitle') as $k) {
				$v = trim(wp_strip_all_tags((string) get_post_meta($post->ID, $k, true)));
				if ($v !== '') {
					return html_entity_decode($v, ENT_QUOTES, 'UTF-8');
				}
			}
		}
		return html_entity_decode($s, ENT_QUOTES, 'UTF-8');
	}

	/** Categoria principal: a do Yoast, se existir; senão a primeira editoria conhecida. */
	private static function cat_principal($id) {
		$prim = (int) get_post_meta($id, '_yoast_wpseo_primary_category', true);
		if ($prim && ($c = get_category($prim)) && !is_wp_error($c)) {
			return $c;
		}
		$cats = get_the_category($id);
		foreach (array_keys(self::$editorias) as $slug) {
			foreach ($cats as $c) {
				if ($c->slug === $slug) {
					return $c;
				}
			}
		}
		foreach ($cats as $c) {
			if (!in_array($c->slug, array('destaque', 'brasil', 'especiais', 'conectese'), true)) {
				return $c;
			}
		}
		return $cats ? $cats[0] : null;
	}

	public static function proximos_lista($qtd) {
		if (!class_exists('Agenda_Conexao_Pop')) {
			return array();
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
		return array_slice($lista, 0, $qtd);
	}

	public static function html_materia($post) {
		$id = $post->ID;
		self::$usados[] = $id;
		$cat = self::cat_principal($id);
		$palavras = str_word_count(wp_strip_all_tags($post->post_content));
		$tags = array();
		foreach ((array) get_the_tags($id) as $tg) {
			if ($tg) {
				$tags[] = array($tg->name, get_tag_link($tg));
			}
		}
		$thumb_id = get_post_thumbnail_id($id);
		$m = array(
			't'        => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
			'sub'      => self::subtitulo($post),
			'cat'      => $cat ? $cat->name : '',
			'cat_url'  => $cat ? get_category_link($cat) : '',
			'data'     => get_the_date('j \d\e F \d\e Y, H\hi', $post),
			'data_iso' => get_the_date('c', $post),
			'autor'    => get_the_author_meta('display_name', $post->post_author),
			'leitura'  => sprintf('%d min de leitura', max(1, (int) ceil($palavras / 220))),
			'img'      => $thumb_id ? wp_get_attachment_image_url($thumb_id, 'full') : '',
			'legenda'  => $thumb_id ? wp_get_attachment_caption($thumb_id) : '',
			'conteudo' => apply_filters('the_content', $post->post_content),
			'tags'     => $tags,
			'url'      => get_permalink($post),
		);
		$m['autor_box'] = CPH_Paginas::autor(array(
			'nome' => get_the_author_meta('display_name', $post->post_author),
			'foto' => get_avatar_url($post->post_author, array('size' => 192)),
			'bio'  => get_the_author_meta('description', $post->post_author),
			'url'  => get_author_posts_url($post->post_author),
		));
		$lateral = CPH_Paginas::lateral(self::em_alta(5), self::proximos_lista(4), home_url('/agenda/'));
		$rel = $cat ? self::buscar(array('cat' => $cat->term_id), 4) : array();
		$depois = $rel ? CPH_Render::editoria('Leia também', $cat ? get_category_link($cat) : '', $rel) : '';
		if (comments_open($id) || get_comments_number($id)) {
			ob_start();
			comments_template();
			$depois .= '<section class="cph-bloco"><div class="cph-wrap cph-comentarios">' . ob_get_clean() . '</div></section>';
		}
		return CPH_Paginas::materia($m, $lateral, $depois);
	}

	public static function html_categoria() {
		global $wp_query;
		$term = get_queried_object();
		$posts = array();
		foreach ((array) $wp_query->posts as $p) {
			$posts[] = self::card($p);
		}
		$pag = paginate_links(array('type' => 'array', 'prev_text' => '← Anteriores', 'next_text' => 'Próximas →', 'mid_size' => 1));
		return CPH_Paginas::categoria(array(
			'nome'    => $term ? $term->name : '',
			'desc'    => $term ? wp_strip_all_tags(term_description($term)) : '',
			'total'   => $term ? (int) $term->count : 0,
			'posts'   => $posts,
			'paginas' => $pag ? $pag : array(),
			'pagina'  => max(1, (int) get_query_var('paged')),
		));
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
				'layout_materia'   => empty($v['layout_materia']) ? 0 : 1,
				'layout_categoria' => empty($v['layout_categoria']) ? 0 : 1,
				'layout_busca'     => empty($v['layout_busca']) ? 0 : 1,
				'layout_404'       => empty($v['layout_404']) ? 0 : 1,
				'tags_link'        => empty($v['tags_link']) ? 0 : 1,
				'leia_tambem'      => empty($v['leia_tambem']) ? 0 : 1,
				'tags_ignorar'     => sanitize_text_field($v['tags_ignorar'] ?? ''),
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
				<tr><td><code>[cp_marcas]</code></td><td>Marcas &amp; Mercado em destaque: seção azul de ponta a ponta, com uma grande, quatro com foto e uma faixa de manchetes</td></tr>
				<tr><td><code>[cp_manchetes categoria="marcas" titulo="Marcas"]</code></td><td>Seis manchetes só em texto, estilo revista</td></tr>
				<tr><td><code>[cp_sanfona categoria="entrevistas" titulo="Entrevistas"]</code></td><td>Colunas com título na vertical que abrem ao passar o mouse (<code>cor="rosa"</code>, <code>azul</code>, <code>limao</code> ou <code>preto</code>)</td></tr>
				<tr><td><code>[cp_ultimas]</code></td><td>Página "Últimas notícias": todas as matérias com paginação (12 por página)</td></tr>
				<tr><td><code>[cp_divisor padrao="3" cor="rosa"]</code></td><td>Divisória de pixels da identidade (padrão 1 a 4; cor rosa, preto, azul ou limao; <code>virar="sim"</code> inverte)</td></tr>
				</tbody>
			</table>
			<p>Nenhum bloco repete matéria de outro na mesma página. A ordem dos blocos na página define quem pega primeiro.</p>
			<form method="post" action="options.php">
				<?php settings_fields('cph'); ?>
				<h2>Matérias e categorias</h2>
				<table class="form-table">
					<tr><th>Layout novo</th><td>
						<label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[layout_materia]" value="1" <?php checked($o['layout_materia'], 1); ?>> Usar nas matérias</label><br>
						<label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[layout_categoria]" value="1" <?php checked($o['layout_categoria'], 1); ?>> Usar nas páginas de categoria</label><br>
						<label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[layout_busca]" value="1" <?php checked($o['layout_busca'], 1); ?>> Usar no resultado da busca</label><br>
						<label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[layout_404]" value="1" <?php checked($o['layout_404'], 1); ?>> Usar na página não encontrada (404)</label>
						<p class="description">Usa o cabeçalho e o rodapé do tema. Se vocês têm Elementor Pro, dá para deixar desmarcado e usar <code>[cp_materia]</code>, <code>[cp_categoria]</code>, <code>[cp_busca]</code> e <code>[cp_404]</code> nos modelos do Construtor de temas.</p>
					</td></tr>
				</table>
				<h2>Texto das matérias</h2>
				<table class="form-table">
					<tr><th>Links automáticos</th><td>
						<label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[tags_link]" value="1" <?php checked($o['tags_link'], 1); ?>> Transformar em link os nomes que já existem como tag (só a primeira vez que aparecem, até 10 por matéria)</label><br>
						<label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[leia_tambem]" value="1" <?php checked($o['leia_tambem'], 1); ?>> Mostrar um "Leia também" depois do segundo parágrafo</label>
					</td></tr>
					<tr><th><label for="cph_ti">Tags que não viram link</label></th><td><input class="large-text" id="cph_ti" name="<?php echo esc_attr(self::OPT); ?>[tags_ignorar]" value="<?php echo esc_attr($o['tags_ignorar']); ?>" placeholder="Ex.: são paulo, rio de janeiro, natal">
						<p class="description">Separe por vírgula. Palavras genéricas como música, shows, festivais e marcas já ficam de fora.</p></td></tr>
				</table>
				<h2>Newsletter</h2>
				<table class="form-table">
					<tr><th><label for="cph_nt">Título</label></th><td><input class="regular-text" id="cph_nt" name="<?php echo esc_attr(self::OPT); ?>[news_titulo]" value="<?php echo esc_attr($o['news_titulo']); ?>"></td></tr>
					<tr><th><label for="cph_nx">Texto</label></th><td><input class="large-text" id="cph_nx" name="<?php echo esc_attr(self::OPT); ?>[news_texto]" value="<?php echo esc_attr($o['news_texto']); ?>"></td></tr>
					<tr><th><label for="cph_ne">Formulário</label></th><td><textarea class="large-text code" rows="6" id="cph_ne" name="<?php echo esc_attr(self::OPT); ?>[news_embed]"><?php echo esc_textarea($o['news_embed']); ?></textarea>
						<p class="description">Deixe vazio para usar a newsletter do próprio WordPress (menu <strong>Newsletter</strong>). Só cole um código aqui se quiserem usar outra ferramenta (Mailchimp, RD Station, Brevo…).</p></td></tr>
				</table>
				<?php submit_button('Salvar'); ?>
			</form>
		</div>
		<?php
	}
}

new CPH();
new CPH_News();
new CPH_Conteudo();
