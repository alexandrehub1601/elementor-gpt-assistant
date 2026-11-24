<?php
/**
 * HTML da página de matéria (single) e da página de categoria.
 * Recebe dados já prontos e só monta a marcação.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class CPH_Paginas {

	private static function ic($nome) {
		$p = array(
			'whats' => '<path d="M3 21l1.6-4.6A8.5 8.5 0 1 1 8 20z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1.5-1.5-2-1-1 1c-1-.4-2-1.4-2.5-2.5l1-1-1-2z"/>',
			'x'     => '<path d="M4 4l16 16M20 4 4 20"/>',
			'face'  => '<path d="M15 3h-2.5A3.5 3.5 0 0 0 9 6.5V10H6.5v3.5H9V21h3.5v-7.5H15l.5-3.5h-3V7a1 1 0 0 1 1-1H15z"/>',
			'link'  => '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
			'relogio' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		);
		return '<svg class="cph-i" viewBox="0 0 24 24" aria-hidden="true">' . $p[$nome] . '</svg>';
	}

	private static function tag($txt, $url = '', $cls = '') {
		if ($txt === '') {
			return '';
		}
		$t = esc_html($txt);
		return $url ? '<a class="cph-tag ' . esc_attr($cls) . '" href="' . esc_url($url) . '">' . $t . '</a>' : '<span class="cph-tag ' . esc_attr($cls) . '">' . $t . '</span>';
	}

	/**
	 * Matéria. $m: t, sub, cat, cat_url, data, data_iso, autor, leitura, img, legenda, conteudo (HTML já filtrado),
	 * tags [[nome, url]], url. $lateral e $depois: HTML pronto (em alta, relacionadas, comentários).
	 */
	public static function materia($m, $lateral = '', $depois = '') {
		$u = rawurlencode($m['url']);
		$t = rawurlencode($m['t']);
		$share = '<div class="cph-share" aria-label="Compartilhar">'
			. '<a href="https://api.whatsapp.com/send?text=' . $t . '%20' . $u . '" target="_blank" rel="noopener" aria-label="Compartilhar no WhatsApp">' . self::ic('whats') . '</a>'
			. '<a href="https://twitter.com/intent/tweet?url=' . $u . '&amp;text=' . $t . '" target="_blank" rel="noopener" aria-label="Compartilhar no X">' . self::ic('x') . '</a>'
			. '<a href="https://www.facebook.com/sharer/sharer.php?u=' . $u . '" target="_blank" rel="noopener" aria-label="Compartilhar no Facebook">' . self::ic('face') . '</a>'
			. '<button type="button" class="cph-copiar" data-url="' . esc_attr($m['url']) . '" aria-label="Copiar link">' . self::ic('link') . '<span>Copiar link</span></button>'
			. '</div>';
		$tags = '';
		foreach ($m['tags'] as $tg) {
			$tags .= '<a href="' . esc_url($tg[1]) . '">' . esc_html($tg[0]) . '</a>';
		}
		$h = '<article class="cph cph-materia">'
			. '<header class="cph-mat-head"><div class="cph-wrap cph-mat-head-in">'
			. '<div class="cph-mat-meta">' . self::tag($m['cat'], $m['cat_url']) . '<time datetime="' . esc_attr($m['data_iso']) . '">' . esc_html($m['data']) . '</time><span class="cph-mat-leitura">' . self::ic('relogio') . esc_html($m['leitura']) . '</span></div>'
			. '<h1 class="cph-mat-t">' . esc_html($m['t']) . '</h1>'
			. ($m['sub'] ? '<p class="cph-mat-sub">' . esc_html($m['sub']) . '</p>' : '')
			. '<div class="cph-mat-autor"><span>Por <strong>' . esc_html($m['autor']) . '</strong></span>' . $share . '</div>'
			. '</div></header>';
		if ($m['img']) {
			$h .= '<figure class="cph-wrap cph-mat-capa"><img src="' . esc_url($m['img']) . '" alt="' . esc_attr($m['t']) . '" fetchpriority="high">' . ($m['legenda'] ? '<figcaption>' . esc_html($m['legenda']) . '</figcaption>' : '') . '</figure>';
		}
		$h .= '<div class="cph-wrap cph-mat-corpo"><div class="cph-mat-col">'
			. '<div class="cph-texto">' . $m['conteudo'] . '</div>'
			. self::caixas_seguir()
			. ($tags ? '<div class="cph-mat-tags"><span class="cph-tag cph-tag-ink">Tags</span>' . $tags . '</div>' : '')
			. '<div class="cph-mat-fim"><span>Gostou? Manda pra alguém</span>' . $share . '</div>'
			. '</div>'
			. ($lateral ? '<aside class="cph-mat-lateral">' . $lateral . '</aside>' : '')
			. '</div>'
			. CPH_Render::divisor(2, 'rosa')
			. $depois
			. '</article>';
		return $h;
	}

	/** Caixinhas depois do texto: fonte preferida no Google e Instagram. */
	public static function caixas_seguir() {
		$dominio = wp_parse_url(home_url('/'), PHP_URL_HOST);
		$dominio = preg_replace('/^www\./', '', (string) $dominio);
		return '<div class="cph-seguir">'
			. '<a class="cph-seguir-box cph-seguir-google" href="https://www.google.com/preferences/source?q=' . rawurlencode($dominio) . '" target="_blank" rel="noopener">'
			. '<span class="cph-seguir-ic" aria-hidden="true"><svg class="cph-i" viewBox="0 0 24 24"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg></span>'
			. '<span class="cph-seguir-txt"><span class="cph-tag cph-tag-ink">Google</span><strong>Adicione o Conexão Pop como fonte preferida</strong><span>Nossas matérias aparecem primeiro para você nas notícias do Google.</span></span>'
			. '<span class="cph-seguir-btn">Adicionar</span></a>'
			. '<a class="cph-seguir-box cph-seguir-insta" href="https://www.instagram.com/siteconexaopop" target="_blank" rel="noopener">'
			. '<span class="cph-seguir-ic" aria-hidden="true"><svg class="cph-i" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor"/></svg></span>'
			. '<span class="cph-seguir-txt"><span class="cph-tag">Instagram</span><strong>@siteconexaopop</strong><span>Bastidores, coberturas e as notícias do dia no seu feed.</span></span>'
			. '<span class="cph-seguir-btn">Seguir</span></a>'
			. '</div>';
	}

	/** Lateral da matéria: as mais lidas em lista compacta e os próximos shows. */
	public static function lateral($alta, $shows, $link_agenda) {
		$h = '';
		if ($alta) {
			$h .= '<section class="cph-lat-box cph-lat-alta"><h2 class="cph-lat-h">Em alta</h2><ol>';
			foreach ($alta as $i => $p) {
				$h .= '<li><a href="' . esc_url($p['u']) . '"><span class="cph-num">' . sprintf('%02d', $i + 1) . '</span><strong>' . esc_html($p['t']) . '</strong></a></li>';
			}
			$h .= '</ol></section>';
		}
		if ($shows) {
			$mes = array('jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez');
			$h .= '<section class="cph-lat-box cph-lat-shows"><h2 class="cph-lat-h">Próximos shows</h2><ul>';
			foreach ($shows as $s) {
				$ts = strtotime($s['data']);
				$h .= '<li><a href="' . esc_url($link_agenda) . '"><span class="cph-stub"><b>' . esc_html(gmdate('d', $ts)) . '</b><small>' . esc_html($mes[(int) gmdate('n', $ts) - 1]) . '</small></span><span><strong>' . esc_html($s['n']) . '</strong><em>' . esc_html($s['cidade']) . '</em></span></a></li>';
			}
			$h .= '</ul><a class="cph-more cph-more-lime" href="' . esc_url($link_agenda) . '">Ver a agenda</a></section>';
		}
		return $h;
	}

	/** Categoria. $c: nome, desc, total, posts (cards), paginas (array de links da paginação), pagina (número). */
	public static function categoria($c) {
		$posts = $c['posts'];
		$h = '<div class="cph cph-cat">'
			. '<header class="cph-cat-head"><div class="cph-wrap"><span class="cph-tag cph-tag-ink">' . esc_html(sprintf('%s matérias', number_format_i18n($c['total']))) . '</span>'
			. '<h1 class="cph-cat-t">' . esc_html($c['nome']) . '</h1>'
			. ($c['desc'] ? '<p class="cph-cat-desc">' . esc_html($c['desc']) . '</p>' : '')
			. '</div>' . CPH_Render::sky('cph-cat-sky') . '</header>';
		if (!$posts) {
			return $h . '<div class="cph-wrap cph-cat-vazio"><p>Ainda não tem matéria aqui.</p></div></div>';
		}
		if ((int) $c['pagina'] <= 1 && count($posts) >= 3) {
			$abre = array_splice($posts, 0, 3);
			$h .= '<section class="cph-bloco"><div class="cph-wrap cph-cat-abre">';
			foreach ($abre as $i => $p) {
				$h .= '<article class="' . ($i === 0 ? 'cph-cat-big' : 'cph-cat-side') . '"><a href="' . esc_url($p['u']) . '">'
					. '<span class="cph-img"><img src="' . esc_url($p['img']) . '" alt=""' . ($i ? ' loading="lazy"' : '') . '></span>'
					. '<span class="cph-cat-abre-txt"><span class="cph-card-meta">' . self::tag($p['cat']) . '<span class="cph-quando">' . esc_html($p['quando']) . '</span></span><strong>' . esc_html($p['t']) . '</strong>'
					. ($i === 0 && $p['ex'] ? '<span class="cph-ex">' . esc_html($p['ex']) . '</span>' : '') . '</span></a></article>';
			}
			$h .= '</div></section>' . CPH_Render::divisor(1, 'preto');
		}
		$h .= '<section class="cph-bloco"><div class="cph-wrap"><div class="cph-cat-grid">';
		foreach ($posts as $p) {
			$h .= '<article class="cph-card"><a href="' . esc_url($p['u']) . '">'
				. ($p['img'] ? '<span class="cph-img cph-img-card"><img src="' . esc_url($p['img']) . '" alt="" loading="lazy"></span>' : '<span class="cph-img cph-img-card cph-semfoto">' . CPH_Render::sky() . '</span>')
				. '<span class="cph-card-meta">' . self::tag($p['cat']) . '<span class="cph-quando">' . esc_html($p['quando']) . '</span></span><h2 class="cph-card-t">' . esc_html($p['t']) . '</h2>'
				. ($p['ex'] ? '<p class="cph-ex">' . esc_html($p['ex']) . '</p>' : '') . '</a></article>';
		}
		$h .= '</div>';
		if ($c['paginas']) {
			$h .= '<nav class="cph-pag" aria-label="Páginas">' . implode('', $c['paginas']) . '</nav>';
		}
		return $h . '</div></section></div>';
	}
}
