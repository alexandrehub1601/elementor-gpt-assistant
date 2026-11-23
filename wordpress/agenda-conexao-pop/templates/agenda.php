<?php
/** Marcação da agenda. Variáveis: $o (opções), $cad (link da página de cadastro). */
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="cpa" id="cpa">
	<section class="cpa-hero">
		<img class="cpa-photo" src="<?php echo esc_url(CPA_URL . 'assets/img/plateia.webp'); ?>" alt="" width="1400" height="933">
		<div class="cpa-wrap">
			<h1 class="cpa-title"><span>agenda de</span><br><span>shows <em><?php echo esc_html(wp_date('y') . '/' . wp_date('y', strtotime('+1 year'))); ?></em></span></h1>
			<p>Os shows de pop, pop nacional e R&amp;B, e os festivais que vêm por aí no Brasil. Siga um show e receba um aviso quando abrir a venda, virar o lote ou sair data extra.</p>
			<div class="cpa-hero-actions">
				<?php if ($cad) : ?><a class="cpa-btn cpa-btn-pink" href="<?php echo esc_url($cad); ?>">Cadastrar meu show</a><?php endif; ?>
				<button type="button" class="cpa-btn cpa-btn-ghost" id="cpa-following"><svg class="cpa-i" viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg><span id="cpa-following-label">Meus alertas</span></button>
			</div>
		</div>
	</section>

	<div class="cpa-filters"><div class="cpa-wrap">
		<div class="cpa-row">
			<div class="cpa-tabs" role="tablist" aria-label="O que mostrar">
				<button class="cpa-tab" role="tab" type="button" data-view="shows" aria-selected="true">Shows</button>
				<button class="cpa-tab" role="tab" type="button" data-view="fests" aria-selected="false">Festivais</button>
			</div>
			<label class="cpa-pill cpa-grow" for="cpa-q"><svg class="cpa-i" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg><input id="cpa-q" type="search" placeholder="Buscar artista, cidade ou local" aria-label="Buscar artista, cidade ou local"></label>
			<label class="cpa-pill" for="cpa-uf"><svg class="cpa-i" viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg><select id="cpa-uf" aria-label="Seu estado"><option value="">Todo o Brasil</option></select></label>
			<label class="cpa-pill" for="cpa-when"><svg class="cpa-i" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><select id="cpa-when" aria-label="Quando"></select></label>
			<label class="cpa-pill" for="cpa-genre" id="cpa-genre-wrap"><select id="cpa-genre" aria-label="Gênero"><option value="">Todos os gêneros</option></select></label>
			<label class="cpa-switch" id="cpa-pop-wrap"><input type="checkbox" id="cpa-pop"> Só o pop</label>
		</div>
	</div></div>

	<div class="cpa-main"><div class="cpa-wrap">
		<?php if ($o['banner_imagem']) : ?>
			<a class="cpa-promo" href="<?php echo esc_url($o['banner_link'] ? $o['banner_link'] : '#'); ?>" target="_blank" rel="noopener sponsored"><img src="<?php echo esc_url($o['banner_imagem']); ?>" alt="<?php echo esc_attr($o['banner_alt']); ?>" width="1200" height="250" loading="lazy"></a>
		<?php endif; ?>
		<div class="cpa-count"><span id="cpa-summary"></span><span id="cpa-sort"></span></div>
		<div id="cpa-out" aria-live="polite"><p class="cpa-loading">Carregando a agenda…</p></div>
	</div></div>

	<div class="cpa-sheet" id="cpa-sheet" hidden role="dialog" aria-modal="true" aria-labelledby="cpa-sheet-title">
		<form class="cpa-card" id="cpa-alert-form">
			<h3 id="cpa-sheet-title">Me avisa</h3>
			<p class="cpa-sub" id="cpa-sheet-sub"></p>
			<div class="cpa-checks">
				<label><input type="checkbox" checked value="venda"> Abriu a venda ou a pré-venda</label>
				<label><input type="checkbox" checked value="lote"> Virou o lote ou mudou o preço</label>
				<label><input type="checkbox" checked value="extra"> Saiu data extra</label>
				<label><input type="checkbox" value="volta"> Voltou ingresso de show esgotado</label>
				<label><input type="checkbox" value="lineup"> Saiu o line-up ou o horário de cada show</label>
			</div>
			<div class="cpa-field"><label for="cpa-alert-mail">Seu e-mail</label><input id="cpa-alert-mail" type="email" required placeholder="voce@email.com" autocomplete="email"></div>
			<p class="cpa-err" id="cpa-alert-err" role="alert" hidden></p>
			<div class="cpa-row2"><button class="cpa-btn cpa-btn-pink" type="submit">Seguir</button><button class="cpa-btn" type="button" id="cpa-sheet-close">Cancelar</button></div>
		</form>
	</div>
</div>
