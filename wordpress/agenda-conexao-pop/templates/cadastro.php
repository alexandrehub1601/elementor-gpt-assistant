<?php
/** Formulário "Cadastrar meu show". Variáveis: $o, $enviado, $erro. */
if (!defined('ABSPATH')) {
	exit;
}
$erros = array(
	'campos'  => 'Faltou preencher algum campo obrigatório, ou o e-mail está incompleto. Confira e envie de novo.',
	'expirou' => 'A página ficou aberta por muito tempo. Recarregue e envie de novo.',
	'limite'  => 'Recebemos vários envios seguidos deste endereço. Tente de novo daqui a uma hora.',
	'falha'   => 'Não foi possível salvar agora. Tente de novo em alguns minutos.',
);
$generos = get_terms(array('taxonomy' => Agenda_Conexao_Pop::TAX, 'hide_empty' => false));
?>
<div class="cpa cpa-cadastro" id="cpa-form">
	<div class="cpa-wrap">
		<p class="cpa-eyebrow"><span>Para artistas e produtoras</span></p>
		<h2 class="cpa-h2">Coloque seu show na agenda</h2>
		<p class="cpa-intro">É de graça. A redação do Conexão Pop revisa cada envio antes de publicar.</p>

		<?php if ($enviado) : ?>
			<div class="cpa-ok" role="status">Show enviado. Ele entra na agenda depois da revisão da redação, e a gente avisa por e-mail se precisar de alguma informação.</div>
		<?php elseif ($erro && isset($erros[$erro])) : ?>
			<div class="cpa-ok cpa-bad" role="alert"><?php echo esc_html($erros[$erro]); ?></div>
		<?php endif; ?>

		<form class="cpa-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="cpa_enviar">
			<?php wp_nonce_field('cpa_enviar', 'cpa_nonce'); ?>
			<div class="cpa-hp" aria-hidden="true"><label>Não preencha<input type="text" name="cpa_site" tabindex="-1" autocomplete="off"></label></div>

			<div class="cpa-field"><label for="cpa_artista">Artista ou evento</label><input id="cpa_artista" name="cpa_artista" required></div>
			<div class="cpa-field"><label for="cpa_turne">Nome da turnê <i>(opcional)</i></label><input id="cpa_turne" name="cpa_turne"></div>
			<div class="cpa-field"><label for="cpa_data">Data</label><input id="cpa_data" name="cpa_data" type="date" required></div>
			<div class="cpa-field"><label for="cpa_hora">Horário <i>(opcional)</i></label><input id="cpa_hora" name="cpa_hora" placeholder="21h"></div>
			<div class="cpa-field"><label for="cpa_cidade">Cidade</label><input id="cpa_cidade" name="cpa_cidade" required autocomplete="address-level2"></div>
			<div class="cpa-field"><label for="cpa_uf">Estado</label><select id="cpa_uf" name="cpa_uf" required><option value="">Escolha</option><?php foreach (Agenda_Conexao_Pop::ufs() as $uf => $nome) : ?><option value="<?php echo esc_attr($uf); ?>"><?php echo esc_html($nome); ?></option><?php endforeach; ?></select></div>
			<div class="cpa-field"><label for="cpa_local">Local</label><input id="cpa_local" name="cpa_local" required></div>
			<div class="cpa-field"><label for="cpa_genero">Gênero</label><select id="cpa_genero" name="cpa_genero"><option value="">Escolha</option><?php foreach ((array) $generos as $g) : ?><option value="<?php echo esc_attr($g->slug); ?>"><?php echo esc_html($g->name); ?></option><?php endforeach; ?><option value="festival">É um festival</option></select></div>
			<div class="cpa-field cpa-full"><label for="cpa_link">Link dos ingressos <i>(opcional)</i></label><input id="cpa_link" name="cpa_link" type="url" placeholder="https://"><span class="cpa-hint">Ticketmaster, Eventim, Ticket360, Ingresse, Sympla ou o site da produtora.</span></div>
			<div class="cpa-field cpa-full"><label for="cpa_arte">Arte de divulgação <i>(opcional)</i></label><input id="cpa_arte" name="cpa_arte" type="file" accept="image/jpeg,image/png,image/webp"><span class="cpa-hint">JPG, PNG ou WebP de até 5 MB. Vira a capa do show. Formato deitado (16:9) fica melhor.</span></div>
			<div class="cpa-field"><label for="cpa_nome">Seu nome ou produtora</label><input id="cpa_nome" name="cpa_nome" required autocomplete="name"></div>
			<div class="cpa-field"><label for="cpa_email">E-mail para contato</label><input id="cpa_email" name="cpa_email" type="email" required autocomplete="email"><span class="cpa-hint">Só a redação vê.</span></div>
			<div class="cpa-field cpa-full"><button class="cpa-btn cpa-btn-dark" type="submit">Enviar para revisão</button></div>
		</form>
	</div>
</div>
