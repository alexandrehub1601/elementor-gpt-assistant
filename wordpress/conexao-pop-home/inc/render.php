<?php
/**
 * HTML dos blocos. Recebe listas já prontas ({id, t, u, img, cat, quando, ex}) e só monta a marcação.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class CPH_Render {

	const SKY = 'M508.26,705.15v86.14H65.06c.21-1.53-.55-3.62.55-4.79h21.17v-19.88h36.07v-16.93h27.24v25.4h16.56v-11.23c.73-.48,2.46-.53,3.48-.57,4.58-.18,9.28-.04,13.82.39v17.48l.55.55h44.36v-11.04h30.37l.55-.55v-16.01h29.45v19.14h38.47l.55.55v11.6h21.72v-16.2h19.51v-15.28l.55-.55h20.43v12.52h23.56v-8.65c0-.2.57-.82.37-1.29h32.58c1.03,0,.15,2.51.16,3.12.05,3.21.35,6.41.4,9.57.02,1.36.13,2.76-.29,4.08l.46.54h18.96v-31.66h28.71v-36.07c.47.2,1.09-.37,1.29-.37h5.89c.09,0,.33.24.66.24,1.53,0,3.4-.49,5.05-.24Z';

	public static function sky($cls = '') {
		return '<svg class="cph-sky ' . esc_attr($cls) . '" viewBox="65 705 443 86.2" preserveAspectRatio="none" aria-hidden="true"><path d="' . self::SKY . '"/></svg>';
	}

	private static function seta() {
		return '<svg class="cph-i" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
	}

	private static function tag($txt, $cls = '') {
		return $txt === '' ? '' : '<span class="cph-tag ' . esc_attr($cls) . '">' . esc_html($txt) . '</span>';
	}

	private static function img($p, $cls = '', $lazy = true) {
		if (!$p['img']) {
			return '<span class="cph-img cph-semfoto ' . esc_attr($cls) . '" aria-hidden="true">' . self::sky() . '</span>';
		}
		return '<span class="cph-img ' . esc_attr($cls) . '"><img src="' . esc_url($p['img']) . '" alt=""' . ($lazy ? ' loading="lazy"' : ' fetchpriority="high"') . ' decoding="async"></span>';
	}

	private static function cabecalho($titulo, $link = '', $txt_link = 'Ver tudo', $tag = '') {
		return '<header class="cph-head"><h2 class="cph-h2">' . esc_html($titulo) . '</h2>' . ($tag ? self::tag($tag, 'cph-tag-ink') : '')
			. '<span class="cph-rule" aria-hidden="true"></span>'
			. ($link ? '<a class="cph-more" href="' . esc_url($link) . '">' . esc_html($txt_link) . self::seta() . '</a>' : '') . '</header>';
	}

	public static function faixa($posts) {
		if (!$posts) {
			return '';
		}
		$itens = '';
		foreach ($posts as $p) {
			$itens .= '<a href="' . esc_url($p['u']) . '">' . esc_html($p['t']) . '</a><span class="cph-px" aria-hidden="true"></span>';
		}
		// O conteúdo vai duas vezes para a faixa rodar sem emenda; a segunda cópia fica escondida de leitores de tela.
		return '<section class="cph-faixa" aria-label="Últimas manchetes"><div class="cph-faixa-track"><div class="cph-faixa-grupo">' . $itens . '</div><div class="cph-faixa-grupo" aria-hidden="true">' . str_replace('<a ', '<a tabindex="-1" ', $itens) . '</div></div></section>';
	}

	public static function destaques($posts) {
		if (!$posts) {
			return '';
		}
		$h = '<section class="cph-bloco cph-destaques"><div class="cph-wrap"><div class="cph-dest-grid">';
		foreach ($posts as $i => $p) {
			$h .= '<article class="cph-dest cph-dest-' . ($i % 2 ? 'rosa' : 'limao') . '"><a href="' . esc_url($p['u']) . '">'
				. self::img($p, 'cph-img-dest', $i > 0)
				. '<span class="cph-dest-txt">' . self::tag($p['cat'], 'cph-tag-ink') . '<h2 class="cph-dest-t">' . esc_html($p['t']) . '</h2><span class="cph-dest-go">' . self::seta() . '</span></span>'
				. '</a></article>';
		}
		return $h . '</div></div></section>';
	}

	public static function agora($posts) {
		if (!$posts) {
			return '';
		}
		$top = array_shift($posts);
		$h = '<section class="cph-bloco cph-agora"><div class="cph-wrap cph-agora-grid">'
			. '<article class="cph-agora-top"><a href="' . esc_url($top['u']) . '">' . self::img($top, 'cph-img-wide') . '<span class="cph-agora-meta">' . self::tag($top['cat']) . '<span class="cph-quando">' . esc_html($top['quando']) . '</span></span>'
			. '<h2 class="cph-agora-t">' . esc_html($top['t']) . '</h2>' . ($top['ex'] ? '<p class="cph-ex">' . esc_html($top['ex']) . '</p>' : '') . '</a></article>'
			. '<aside class="cph-lista"><h3 class="cph-lista-h"><span class="cph-tag cph-tag-ink">Acabou de sair</span></h3><ol>';
		foreach ($posts as $p) {
			$h .= '<li><a href="' . esc_url($p['u']) . '">' . self::img($p, 'cph-img-mini') . '<span><span class="cph-lista-meta">' . self::tag($p['cat']) . '<span class="cph-quando">' . esc_html($p['quando']) . '</span></span><strong>' . esc_html($p['t']) . '</strong></span></a></li>';
		}
		return $h . '</ol></aside></div></section>';
	}

	public static function em_alta($posts) {
		if (!$posts) {
			return '';
		}
		$h = '<section class="cph-bloco"><div class="cph-wrap"><div class="cph-alta">' . self::cabecalho('Em alta', '', '', 'Mais lidas da semana') . '<ol class="cph-alta-lista">';
		foreach ($posts as $i => $p) {
			$h .= '<li><a href="' . esc_url($p['u']) . '"><span class="cph-num">' . sprintf('%02d', $i + 1) . '</span><span>' . self::tag($p['cat'], 'cph-tag-ink') . '<strong>' . esc_html($p['t']) . '</strong></span>' . self::seta() . '</a></li>';
		}
		return $h . '</ol></div></div></section>';
	}

	public static function editoria($titulo, $link, $posts) {
		if (!$posts) {
			return '';
		}
		$h = '<section class="cph-bloco"><div class="cph-wrap">' . self::cabecalho($titulo, $link) . '<div class="cph-cards">';
		foreach ($posts as $p) {
			$h .= '<article class="cph-card"><a href="' . esc_url($p['u']) . '">' . self::img($p, 'cph-img-card') . '<span class="cph-card-meta">' . self::tag($p['cat']) . '<span class="cph-quando">' . esc_html($p['quando']) . '</span></span><h3 class="cph-card-t">' . esc_html($p['t']) . '</h3></a></article>';
		}
		return $h . '</div></div></section>';
	}

	public static function slides($titulo, $link, $posts, $cor = 'limao') {
		if (!$posts) {
			return '';
		}
		$cor = in_array($cor, array('limao', 'rosa', 'azul'), true) ? $cor : 'limao';
		$h = '<section class="cph-bloco"><div class="cph-wrap"><div class="cph-slides cph-slides-' . $cor . '">' . self::cabecalho($titulo, $link)
			. '<div class="cph-slides-nav"><button type="button" class="cph-nav" data-dir="-1" aria-label="Anteriores"><svg class="cph-i" viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></button><button type="button" class="cph-nav" data-dir="1" aria-label="Próximas">' . self::seta() . '</button></div>'
			. '<ol class="cph-track" tabindex="0" aria-label="' . esc_attr($titulo) . '">';
		foreach ($posts as $i => $p) {
			$h .= '<li class="cph-slide"><a href="' . esc_url($p['u']) . '">' . self::img($p, 'cph-img-slide') . '<span class="cph-num" aria-hidden="true">' . sprintf('%02d', $i + 1) . '</span><span class="cph-slide-txt">' . self::tag($p['cat'], 'cph-tag-lime') . '<strong>' . esc_html($p['t']) . '</strong></span></a></li>';
		}
		return $h . '</ol></div></div></section>';
	}

	public static function proximos($lista, $link) {
		if (!$lista) {
			return '';
		}
		$mes = array('jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez');
		$h = '<section class="cph-bloco"><div class="cph-wrap"><div class="cph-shows">'
			. '<header class="cph-head"><h2 class="cph-h2">Próximos shows</h2><span class="cph-rule" aria-hidden="true"></span><a class="cph-more cph-more-lime" href="' . esc_url($link) . '">Ver a agenda' . self::seta() . '</a></header><ul class="cph-shows-lista">';
		foreach ($lista as $s) {
			$t = strtotime($s['data']);
			$h .= '<li><a href="' . esc_url($link) . '"><span class="cph-stub"><b>' . esc_html(gmdate('d', $t)) . '</b><small>' . esc_html($mes[(int) gmdate('n', $t) - 1]) . '</small></span><span class="cph-show-txt"><strong>' . esc_html($s['n']) . '</strong><span>' . esc_html($s['local'] . ($s['cidade'] ? ', ' . $s['cidade'] : '')) . '</span>' . ($s['status'] === 'esgotado' ? '<em>Esgotado</em>' : '') . '</span></a></li>';
		}
		return $h . '</ul>' . self::sky() . '</div></div></section>';
	}

	public static function newsletter($titulo, $texto, $embed) {
		$redes = array(
			'Instagram' => 'https://www.instagram.com/siteconexaopop',
			'TikTok'    => 'https://www.tiktok.com/@siteconexaopop',
			'YouTube'   => 'https://www.youtube.com/c/Conex%C3%A3oPOP',
			'X'         => 'https://twitter.com/conexaopopsite',
		);
		$h = '<section class="cph-bloco"><div class="cph-wrap"><div class="cph-news"><div class="cph-news-txt">' . self::tag('Newsletter', 'cph-tag-ink') . '<h2 class="cph-h2">' . esc_html($titulo) . '</h2><p>' . esc_html($texto) . '</p></div><div class="cph-news-form">';
		if (trim($embed) !== '') {
			$h .= $embed; // código do formulário salvo por administrador nas configurações
		} else {
			$h .= '<p class="cph-news-alt">Enquanto isso, siga o Conexão Pop:</p><div class="cph-redes">';
			foreach ($redes as $n => $u) {
				$h .= '<a href="' . esc_url($u) . '" target="_blank" rel="noopener">' . esc_html($n) . '</a>';
			}
			$h .= '</div>';
		}
		return $h . '</div>' . self::sky() . '</div></div></section>';
	}
	/** Divisória de pixels. $n = 1 a 4 (padrão), $cor = rosa, preto, azul ou limao. */
	public static function divisor($n = 2, $cor = 'rosa', $virar = false) {
		$n = max(1, min(4, intval($n)));
		$cor = in_array($cor, array('rosa', 'preto', 'azul', 'limao'), true) ? $cor : 'rosa';
		return '<div class="cph-div cph-div-' . $n . ' cph-div-' . $cor . ($virar ? ' cph-div-flip' : '') . '" aria-hidden="true"></div>';
	}

	/** Capa: uma matéria com foto em tela cheia, título enorme e a skyline rosa no pé. */
	public static function capa($p, $rotulo = '') {
		if (!$p) {
			return '';
		}
		return '<section class="cph-bloco"><div class="cph-wrap"><article class="cph-capa"><a href="' . esc_url($p['u']) . '">' . self::img($p, 'cph-img-capa')
			. '<span class="cph-capa-txt">' . ($rotulo ? self::tag($rotulo, 'cph-tag-ink') : '') . self::tag($p['cat']) . '<h2 class="cph-capa-t">' . esc_html($p['t']) . '</h2>'
			. ($p['ex'] ? '<p>' . esc_html($p['ex']) . '</p>' : '') . '<span class="cph-capa-btn">Ler agora' . self::seta() . '</span></span>' . self::sky('cph-capa-sky') . '</a></article></div></section>';
	}

	/** Mosaico: uma grande à esquerda e quatro menores. */
	public static function mosaico($titulo, $link, $posts) {
		if (!$posts) {
			return '';
		}
		$big = array_shift($posts);
		$h = '<section class="cph-bloco"><div class="cph-wrap">' . self::cabecalho($titulo, $link) . '<div class="cph-mosaico">'
			. '<article class="cph-mos-big"><a href="' . esc_url($big['u']) . '">' . self::img($big, 'cph-img-fill') . '<span class="cph-mos-txt">' . self::tag($big['cat']) . '<strong>' . esc_html($big['t']) . '</strong></span></a></article>';
		foreach ($posts as $p) {
			$h .= '<article class="cph-mos-sm"><a href="' . esc_url($p['u']) . '">' . self::img($p, 'cph-img-fill') . '<span class="cph-mos-txt">' . self::tag($p['cat']) . '<strong>' . esc_html($p['t']) . '</strong></span></a></article>';
		}
		return $h . '</div></div></section>';
	}

	/** Manchetes: só texto, em colunas, estilo revista. */
	public static function manchetes($titulo, $link, $posts) {
		if (!$posts) {
			return '';
		}
		$h = '<section class="cph-bloco"><div class="cph-wrap"><div class="cph-manchetes">' . self::cabecalho($titulo, $link) . '<ol class="cph-man-lista">';
		foreach ($posts as $p) {
			$h .= '<li><a href="' . esc_url($p['u']) . '"><span class="cph-card-meta">' . self::tag($p['cat'], 'cph-tag-ink') . '<span class="cph-quando">' . esc_html($p['quando']) . '</span></span><strong>' . esc_html($p['t']) . '</strong></a></li>';
		}
		return $h . '</ol></div></div></section>';
	}

	/** Sanfona: colunas com título na vertical que abrem ao passar o mouse (ou com o teclado). */
	public static function sanfona($titulo, $link, $posts, $cor = 'preto') {
		if (!$posts) {
			return '';
		}
		$cor = in_array($cor, array('preto', 'rosa', 'azul', 'limao'), true) ? $cor : 'preto';
		$h = '<section class="cph-bloco"><div class="cph-wrap"><div class="cph-sanfona cph-sanfona-' . $cor . '">' . self::cabecalho($titulo, $link) . '<ol class="cph-san-lista">';
		foreach ($posts as $i => $p) {
			$h .= '<li class="cph-san' . ($i === 0 ? ' cph-san-aberta' : '') . '"><a href="' . esc_url($p['u']) . '"><span class="cph-san-v" aria-hidden="true"><span class="cph-num">' . sprintf('%02d', $i + 1) . '</span><span class="cph-san-vt">' . esc_html($p['t']) . '</span></span>'
				. '<span class="cph-san-full">' . self::img($p, 'cph-img-fill') . '<span class="cph-san-txt">' . self::tag($p['cat']) . '<strong>' . esc_html($p['t']) . '</strong></span></span></a></li>';
		}
		return $h . '</ol></div></div></section>';
	}
}
