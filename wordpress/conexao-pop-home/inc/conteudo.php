<?php
/**
 * Melhorias no texto das matérias:
 * - nomes que já existem como tag viram link para a tag (só a primeira vez que aparecem);
 * - um "Leia também" discreto depois do segundo parágrafo.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class CPH_Conteudo {

	const CACHE   = 'cph_mapa_tags_v1';
	const MAX     = 10; // links de tag por matéria
	const PALAVRAS = 4; // nomes de tag com até 4 palavras

	/** Tags genéricas demais para virar link no meio do texto. */
	public static function ignoradas() {
		$base = array('música', 'musica', 'show', 'shows', 'festival', 'festivais', 'marca', 'marcas', 'entrevista', 'entrevistas', 'brasil', 'pop', 'destaque', 'notícias', 'noticias', 'cobertura', 'coberturas', 'lançamento', 'lançamentos', 'mercado', 'eventos', 'evento');
		$extra = array_filter(array_map('trim', explode(',', (string) CPH::opcoes()['tags_ignorar'])));
		return array_map(array(__CLASS__, 'norm'), array_merge($base, $extra));
	}

	public function __construct() {
		add_filter('the_content', array($this, 'filtrar'), 20);
		foreach (array('created_post_tag', 'edited_post_tag', 'delete_post_tag') as $g) {
			add_action($g, array(__CLASS__, 'limpar'));
		}
	}

	public static function limpar() {
		delete_transient(self::CACHE);
	}

	private static function norm($s) {
		return function_exists('mb_strtolower') ? mb_strtolower(trim($s), 'UTF-8') : strtolower(trim($s));
	}

	/** Mapa "nome da tag em minúsculas" => link. Fica em cache por 12 horas. */
	private static function mapa() {
		$m = get_transient(self::CACHE);
		if (is_array($m)) {
			return $m;
		}
		$m = array();
		$ign = self::ignoradas();
		$tags = get_terms(array('taxonomy' => 'post_tag', 'hide_empty' => true, 'fields' => 'all', 'number' => 20000));
		if (!is_wp_error($tags)) {
			foreach ($tags as $t) {
				$nome = self::norm(html_entity_decode($t->name, ENT_QUOTES, 'UTF-8'));
				$qtd = count(preg_split('/\s+/u', $nome));
				if (mb_strlen($nome, 'UTF-8') < 3 || $qtd > self::PALAVRAS || in_array($nome, $ign, true)) {
					continue;
				}
				$m[$nome] = get_term_link($t);
			}
		}
		set_transient(self::CACHE, $m, 12 * HOUR_IN_SECONDS);
		return $m;
	}

	public function filtrar($html) {
		if (is_admin() || !is_singular('post') || !in_the_loop() || !is_main_query() || get_post_type() !== 'post') {
			return $html;
		}
		$o = CPH::opcoes();
		if ($o['tags_link']) {
			$html = $this->linkar_tags($html);
		}
		if ($o['leia_tambem']) {
			$html = $this->leia_tambem($html);
		}
		return $html;
	}

	/** Percorre só o texto fora de links, títulos, legendas, scripts e embeds, e liga a primeira menção de cada tag. */
	private function linkar_tags($html) {
		$mapa = self::mapa();
		if (!$mapa) {
			return $html;
		}
		$partes = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
		$bloqueio = 0;
		$feitos = array();
		$total = 0;
		$proibidas = 'a|h1|h2|h3|h4|h5|h6|script|style|figcaption|blockquote|iframe|code|pre|button|cite|table';
		foreach ($partes as $i => $p) {
			if ($p === '') {
				continue;
			}
			if ($p[0] === '<') {
				if (preg_match('#^<(' . $proibidas . ')[\s>]#i', $p)) {
					$bloqueio++;
				} elseif (preg_match('#^</(' . $proibidas . ')>#i', $p)) {
					$bloqueio = max(0, $bloqueio - 1);
				}
				continue;
			}
			if ($bloqueio || $total >= self::MAX || trim($p) === '') {
				continue;
			}
			$partes[$i] = $this->linkar_trecho($p, $mapa, $feitos, $total);
		}
		return implode('', $partes);
	}

	private function linkar_trecho($texto, $mapa, &$feitos, &$total) {
		// Palavras com a posição de cada uma (letras, números, apóstrofo, ponto e hífen no meio).
		if (!preg_match_all("/[\\p{L}\\p{N}](?:[\\p{L}\\p{N}'’.&-]*[\\p{L}\\p{N}])?/u", $texto, $m, PREG_OFFSET_CAPTURE)) {
			return $texto;
		}
		$pal = $m[0];
		$n = count($pal);
		$trocas = array();
		for ($i = 0; $i < $n && $total < self::MAX; $i++) {
			for ($k = min(self::PALAVRAS, $n - $i); $k >= 1; $k--) {
				$ini = $pal[$i][1];
				$fim = $pal[$i + $k - 1][1] + strlen($pal[$i + $k - 1][0]);
				$trecho = substr($texto, $ini, $fim - $ini);
				if (preg_match('/[^\s\p{L}\p{N}\'’.&-]/u', $trecho)) {
					continue; // não atravessa vírgula, parêntese etc.
				}
				$chave = self::norm(preg_replace('/\s+/u', ' ', $trecho));
				if (isset($mapa[$chave]) && !isset($feitos[$chave])) {
					$feitos[$chave] = true;
					$total++;
					$trocas[] = array($ini, $fim - $ini, '<a class="cph-taglink" href="' . esc_url($mapa[$chave]) . '">' . $trecho . '</a>');
					$i += $k - 1;
					break;
				}
			}
		}
		foreach (array_reverse($trocas) as $t) {
			$texto = substr_replace($texto, $t[2], $t[0], $t[1]);
		}
		return $texto;
	}

	/** Um "Leia também" discreto logo depois do segundo parágrafo. */
	private function leia_tambem($html) {
		$id = get_the_ID();
		$cats = wp_get_post_categories($id);
		$rel = get_posts(array(
			'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'post__not_in' => array($id),
			'category__in' => $cats ? $cats : array(0), 'ignore_sticky_posts' => true,
		));
		if (!$rel) {
			return $html;
		}
		$p = $rel[0];
		$img = get_the_post_thumbnail_url($p, 'thumbnail');
		$box = '<aside class="cph-leia" aria-label="Leia também"><a href="' . esc_url(get_permalink($p)) . '">'
			. ($img ? '<img src="' . esc_url($img) . '" alt="" loading="lazy" width="72" height="72">' : '')
			. '<span><em>Leia também</em><strong>' . esc_html(html_entity_decode(get_the_title($p), ENT_QUOTES, 'UTF-8')) . '</strong></span>'
			. '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a></aside>';
		// Depois do segundo </p> que estiver no nível de cima do texto.
		$pos = 0;
		for ($i = 0; $i < 2; $i++) {
			$pos = stripos($html, '</p>', $pos);
			if ($pos === false) {
				return $html;
			}
			$pos += 4;
		}
		return substr($html, 0, $pos) . $box . substr($html, $pos);
	}
}
