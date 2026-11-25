<?php
/**
 * Newsletter do Conexão Pop feita no próprio WordPress:
 * inscrição com confirmação por e-mail (LGPD), lista de inscritos, montagem da edição e envio em lotes.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class CPH_News {

	const DB_VER   = '1';
	const ENVIO    = 'cph_news_envio';
	const EDICAO   = 'cph_news_edicao';
	const LOTE     = 40;   // e-mails por lote
	const GANCHO   = 'cph_news_lote';
	public static $interesses = array('musica' => 'Música', 'shows' => 'Shows', 'festivais' => 'Festivais', 'marcas' => 'Marcas');

	public static function tabela() {
		global $wpdb;
		return $wpdb->prefix . 'cph_inscritos';
	}

	public function __construct() {
		add_action('admin_init', array(__CLASS__, 'instalar'));
		add_action('rest_api_init', array($this, 'rotas'));
		add_action('template_redirect', array($this, 'links'));
		add_action('admin_menu', array($this, 'menu'));
		add_action('admin_post_cph_news_acao', array($this, 'acao'));
		add_action('admin_post_cph_news_csv', array($this, 'csv'));
		add_filter('cron_schedules', function ($s) {
			$s['cph_minuto'] = array('interval' => 60, 'display' => 'A cada minuto (newsletter)');
			return $s;
		});
		add_action(self::GANCHO, array($this, 'enviar_lote'));
	}

	public static function instalar() {
		if (get_option('cph_news_db') === self::DB_VER) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::tabela();
		dbDelta("CREATE TABLE $t (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			nome varchar(120) NOT NULL DEFAULT '',
			interesses varchar(120) NOT NULL DEFAULT '',
			status varchar(12) NOT NULL DEFAULT 'pendente',
			token varchar(64) NOT NULL,
			criado datetime NOT NULL,
			confirmado datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY status (status)
		) " . $wpdb->get_charset_collate() . ';');
		update_option('cph_news_db', self::DB_VER);
	}

	public static function contar($status = 'ativo') {
		global $wpdb;
		if (get_option('cph_news_db') !== self::DB_VER) {
			return 0;
		}
		return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::tabela() . ' WHERE status = %s', $status)); // phpcs:ignore
	}

	/* ------------------------------------------------------------------ */
	/* Inscrição                                                           */
	/* ------------------------------------------------------------------ */

	public function rotas() {
		register_rest_route('cph/v1', '/news', array(
			'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array($this, 'inscrever'),
		));
	}

	public function inscrever(WP_REST_Request $r) {
		self::instalar();
		if (trim((string) $r['site']) !== '') { // armadilha para robôs
			return array('ok' => true);
		}
		$email = sanitize_email((string) $r['email']);
		if (!is_email($email)) {
			return new WP_Error('cph_email', 'Esse e-mail parece incompleto.', array('status' => 400));
		}
		$ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
		$k = 'cph_news_' . md5($ip);
		if ((int) get_transient($k) >= 6) {
			return new WP_Error('cph_limite', 'Muitas tentativas seguidas. Tente de novo mais tarde.', array('status' => 429));
		}
		set_transient($k, (int) get_transient($k) + 1, HOUR_IN_SECONDS);
		$int = array_values(array_intersect((array) $r['interesses'], array_keys(self::$interesses)));
		global $wpdb;
		$t = self::tabela();
		$ja = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE email = %s", $email)); // phpcs:ignore
		if ($ja && $ja->status === 'ativo') {
			return array('ok' => true, 'ja' => true);
		}
		$token = $ja ? $ja->token : wp_generate_password(40, false);
		$dados = array('nome' => sanitize_text_field((string) $r['nome']), 'interesses' => implode(',', $int), 'status' => 'pendente');
		if ($ja) {
			$wpdb->update($t, $dados, array('id' => $ja->id));
		} else {
			$wpdb->insert($t, $dados + array('email' => $email, 'token' => $token, 'criado' => current_time('mysql')));
		}
		$link = add_query_arg(array('cph_news' => 'confirmar', 't' => $token), home_url('/'));
		$html = self::email_html('Confirme sua inscrição', '<p style="margin:0 0 18px">Oi' . ($dados['nome'] ? ', ' . esc_html($dados['nome']) : '') . '! Falta só um clique para você receber a newsletter do Conexão Pop.</p>'
			. self::botao('Confirmar inscrição', $link) . '<p style="margin:18px 0 0;font-size:13px;color:#5E5A55">Se não foi você, é só ignorar este e-mail.</p>', array(), array(), '');
		wp_mail($email, 'Confirme sua inscrição na newsletter do Conexão Pop', $html, array('Content-Type: text/html; charset=UTF-8'));
		return array('ok' => true);
	}

	/** Links de confirmar e de sair, que chegam por e-mail. */
	public function links() {
		if (empty($_GET['cph_news']) || empty($_GET['t'])) { // phpcs:ignore
			return;
		}
		self::instalar();
		global $wpdb;
		$acao = sanitize_key($_GET['cph_news']); // phpcs:ignore
		$token = sanitize_text_field(wp_unslash($_GET['t'])); // phpcs:ignore
		$t = self::tabela();
		$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE token = %s", $token)); // phpcs:ignore
		if (!$row) {
			self::pagina('Link inválido', 'Esse link não vale mais. Se quiser, assine de novo pela home do Conexão Pop.');
		}
		if ($acao === 'confirmar') {
			$wpdb->update($t, array('status' => 'ativo', 'confirmado' => current_time('mysql')), array('id' => $row->id));
			self::pagina('Inscrição confirmada!', 'Pronto: a partir da próxima edição, o melhor do pop chega no seu e-mail.');
		}
		if ($acao === 'sair') {
			$wpdb->update($t, array('status' => 'saiu'), array('id' => $row->id));
			self::pagina('Você saiu da lista', 'Não vai mais receber a newsletter. Mudou de ideia? Assine de novo quando quiser.');
		}
	}

	private static function pagina($titulo, $texto) {
		status_header(200);
		nocache_headers();
		echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($titulo) . ' · Conexão Pop</title>'
			. '<link rel="stylesheet" href="https://use.typekit.net/xqr3xib.css"><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#FF1574;font-family:system-ui,sans-serif;color:#111;padding:20px}main{background:#F4F1EC;border-radius:18px;padding:36px 32px;max-width:520px;text-align:center}img{width:220px;height:auto}h1{font:700 52px/.9 kensington,Impact,sans-serif;text-transform:uppercase;margin:22px 0 12px}p{font-size:17px;line-height:1.5;margin:0 0 22px}a{display:inline-block;background:#111;color:#D7FF47;border-radius:999px;padding:12px 22px;font-weight:700;text-decoration:none}</style></head>'
			. '<body><main><img src="' . esc_url(CPH_URL . 'assets/email/logo.png') . '" alt="Conexão Pop" style="background:#111;padding:14px;border-radius:12px"><h1>' . esc_html($titulo) . '</h1><p>' . esc_html($texto) . '</p><a href="' . esc_url(home_url('/')) . '">Ir para o Conexão Pop</a></main></body></html>';
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* E-mail                                                              */
	/* ------------------------------------------------------------------ */

	private static function botao($txt, $url) {
		return '<table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="background:#111;border-radius:999px"><a href="' . esc_url($url) . '" style="display:inline-block;padding:14px 26px;font:700 16px/1 Arial,sans-serif;color:#D7FF47;text-decoration:none">' . esc_html($txt) . '</a></td></tr></table>';
	}

	/**
	 * Monta o HTML do e-mail (tabelas e estilos inline, para funcionar no Gmail e no Outlook).
	 * $posts: [t, u, img, cat, ex]; $shows: [n, data, local, cidade]; $sair: link de descadastro ('' para não mostrar).
	 */
	public static function email_html($titulo, $abertura_html, $posts, $shows, $sair) {
		$disp = "Impact,'Arial Narrow Bold','Arial Black',sans-serif";
		$h = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($titulo) . '</title></head>'
			. '<body style="margin:0;padding:0;background:#F4F1EC"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F1EC"><tr><td align="center" style="padding:24px 12px">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#FFFFFF;border-radius:16px;overflow:hidden">'
			. '<tr><td style="background:#111;padding:26px 28px" align="left"><img src="' . esc_url(CPH_URL . 'assets/email/logo.png') . '" width="190" alt="Conexão Pop" style="display:block;width:190px;height:auto;border:0"></td></tr>'
			. '<tr><td style="background:#FF1574;padding:26px 28px 18px"><div style="font:400 12px/1 \'Courier New\',monospace;text-transform:uppercase;color:#111;background:#D7FF47;display:inline-block;padding:5px 8px">' . esc_html(date_i18n('j \d\e F \d\e Y')) . '</div>'
			. '<h1 style="margin:14px 0 0;font:700 44px/.95 ' . $disp . ';text-transform:uppercase;color:#FFFFFF">' . esc_html($titulo) . '</h1></td></tr>'
			. '<tr><td style="line-height:0;background:#FFFFFF"><img src="' . esc_url(CPH_URL . 'assets/email/pixels.png') . '" width="600" alt="" style="display:block;width:100%;height:auto;border:0"></td></tr>'
			. '<tr><td style="padding:22px 28px 6px;font:16px/1.6 Arial,sans-serif;color:#1E1C1A">' . $abertura_html . '</td></tr>';
		foreach ($posts as $i => $p) {
			$h .= '<tr><td style="padding:16px 28px 8px">'
				. ($i === 0 && $p['img'] ? '<a href="' . esc_url($p['u']) . '"><img src="' . esc_url($p['img']) . '" width="544" alt="" style="display:block;width:100%;height:auto;border-radius:12px;border:0"></a>' : '')
				. '<div style="margin:' . ($i === 0 ? '14px' : '0') . ' 0 8px"><span style="font:400 11px/1 \'Courier New\',monospace;text-transform:uppercase;background:#D7FF47;color:#111;padding:4px 7px">' . esc_html($p['cat']) . '</span></div>'
				. '<a href="' . esc_url($p['u']) . '" style="font:700 ' . ($i === 0 ? '32px' : '24px') . '/1 ' . $disp . ';text-transform:uppercase;color:#111;text-decoration:none">' . esc_html($p['t']) . '</a>'
				. ($p['ex'] ? '<p style="margin:8px 0 0;font:15px/1.5 Arial,sans-serif;color:#5E5A55">' . esc_html($p['ex']) . '</p>' : '')
				. '</td></tr><tr><td style="padding:8px 28px"><div style="height:2px;background:#111"></div></td></tr>';
		}
		if ($shows) {
			$mes = array('jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez');
			$h .= '<tr><td style="padding:18px 28px 6px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#111;border-radius:14px"><tr><td style="padding:20px 20px 8px;font:700 30px/1 ' . $disp . ';text-transform:uppercase;color:#F4F1EC">Próximos shows</td></tr>';
			foreach ($shows as $s) {
				$ts = strtotime($s['data']);
				$h .= '<tr><td style="padding:6px 20px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F1EC;border-radius:10px"><tr>'
					. '<td width="64" align="center" style="background:#FF1574;border-radius:10px 0 0 10px;padding:8px 0;font:700 28px/1 ' . $disp . ';color:#FFFFFF">' . esc_html(gmdate('d', $ts)) . '<div style="font:400 11px/1 \'Courier New\',monospace;text-transform:uppercase">' . esc_html($mes[(int) gmdate('n', $ts) - 1]) . '</div></td>'
					. '<td style="padding:8px 14px;font:700 20px/1 ' . $disp . ';text-transform:uppercase;color:#111">' . esc_html($s['n']) . '<div style="font:400 13px/1.4 Arial,sans-serif;text-transform:none;color:#5E5A55">' . esc_html($s['local'] . ', ' . $s['cidade']) . '</div></td></tr></table></td></tr>';
			}
			$h .= '<tr><td style="padding:12px 20px 22px">' . self::botao('Ver a agenda completa', home_url('/agenda/')) . '</td></tr></table></td></tr>';
		}
		$h .= '<tr><td style="padding:26px 28px;background:#111;font:13px/1.6 Arial,sans-serif;color:#BDB7AE">'
			. '<a href="https://www.instagram.com/siteconexaopop" style="color:#D7FF47;font-weight:700;text-decoration:none">Instagram</a> · <a href="https://www.tiktok.com/@siteconexaopop" style="color:#D7FF47;font-weight:700;text-decoration:none">TikTok</a> · <a href="' . esc_url(home_url('/')) . '" style="color:#D7FF47;font-weight:700;text-decoration:none">conexaopop.com.br</a><br>'
			. 'Você recebe este e-mail porque assinou a newsletter do Conexão Pop.' . ($sair ? ' <a href="' . esc_url($sair) . '" style="color:#FFFFFF">Sair da lista</a>.' : '') . '</td></tr>'
			. '</table></td></tr></table></body></html>';
		return $h;
	}

	/* ------------------------------------------------------------------ */
	/* Painel: montar e enviar                                             */
	/* ------------------------------------------------------------------ */

	public function menu() {
		add_menu_page('Newsletter', 'Newsletter', 'manage_options', 'cph-news', array($this, 'pagina_admin'), 'dashicons-email-alt', 27);
	}

	private static function posts_recentes() {
		return get_posts(array('post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 20, 'date_query' => array(array('after' => '14 days ago'))));
	}

	private static function montar($dados) {
		$posts = array();
		foreach ((array) $dados['posts'] as $id) {
			$p = get_post((int) $id);
			if ($p && $p->post_status === 'publish') {
				$posts[] = CPH::card($p);
			}
		}
		$shows = !empty($dados['shows']) ? CPH::proximos_lista(5) : array();
		$abre = wpautop(esc_html($dados['abertura']));
		return array($posts, $shows, $abre);
	}

	public function pagina_admin() {
		self::instalar();
		$envio = get_option(self::ENVIO);
		$num = (int) get_option(self::EDICAO, 0) + 1;
		$rec = self::posts_recentes();
		?>
		<div class="wrap">
			<h1>Newsletter do Conexão Pop</h1>
			<?php if (!empty($_GET['cph_msg'])) : // phpcs:ignore ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html(array('teste' => 'Teste enviado para o seu e-mail.', 'fila' => 'Envio começou. Os e-mails saem em lotes de ' . self::LOTE . ' por minuto.', 'parado' => 'Envio cancelado.')[sanitize_key($_GET['cph_msg'])] ?? ''); // phpcs:ignore ?></p></div>
			<?php endif; ?>
			<p style="font-size:15px"><strong><?php echo esc_html(number_format_i18n(self::contar('ativo'))); ?></strong> inscritos confirmados · <?php echo esc_html(number_format_i18n(self::contar('pendente'))); ?> aguardando confirmação · <?php echo esc_html(number_format_i18n(self::contar('saiu'))); ?> saíram
				· <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cph_news_csv'), 'cph_news_csv')); ?>">Baixar lista (CSV)</a></p>

			<?php if ($envio && empty($envio['fim'])) : ?>
				<div class="notice notice-info"><p><strong>Enviando "<?php echo esc_html($envio['assunto']); ?>":</strong> <?php echo esc_html(number_format_i18n($envio['enviados'])); ?> de <?php echo esc_html(number_format_i18n($envio['total'])); ?> e-mails.
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline"><?php wp_nonce_field('cph_news_acao'); ?><input type="hidden" name="action" value="cph_news_acao"><button class="button" name="botao" value="parar">Cancelar envio</button></form></p></div>
			<?php endif; ?>

			<h2>Nova edição</h2>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_self">
				<?php wp_nonce_field('cph_news_acao'); ?><input type="hidden" name="action" value="cph_news_acao">
				<table class="form-table">
					<tr><th><label for="cph_as">Assunto</label></th><td><input class="large-text" id="cph_as" name="assunto" value="<?php echo esc_attr('A semana pop #' . $num); ?>" required></td></tr>
					<tr><th><label for="cph_ab">Texto de abertura</label></th><td><textarea class="large-text" rows="4" id="cph_ab" name="abertura">Oi! Separamos o que mais bombou no pop esta semana, mais os shows que vêm por aí.</textarea></td></tr>
					<tr><th>Matérias</th><td>
						<p class="description">As primeiras 6 já vêm marcadas. A primeira marcada sai com foto grande.</p>
						<?php foreach ($rec as $i => $p) : ?>
							<label style="display:block;margin:4px 0"><input type="checkbox" name="posts[]" value="<?php echo esc_attr($p->ID); ?>" <?php checked($i < 6); ?>> <?php echo esc_html(get_the_title($p)); ?> <span style="color:#888">(<?php echo esc_html(get_the_date('d/m', $p)); ?>)</span></label>
						<?php endforeach; ?>
					</td></tr>
					<tr><th>Agenda</th><td><label><input type="checkbox" name="shows" value="1" checked> Incluir os próximos shows da agenda</label></td></tr>
				</table>
				<p>
					<button class="button" name="botao" value="previa" formtarget="_blank">Pré-visualizar</button>
					<button class="button" name="botao" value="teste">Enviar teste para <?php echo esc_html(wp_get_current_user()->user_email); ?></button>
					<button class="button button-primary" name="botao" value="enviar" onclick="return confirm('Enviar para <?php echo esc_attr(self::contar('ativo')); ?> inscritos?')" <?php disabled(($envio && empty($envio['fim'])) || !self::contar('ativo')); ?>>Enviar para todos os inscritos</button>
				</p>
			</form>
			<h2>Boas práticas</h2>
			<p>Para os e-mails não caírem no spam, use um plugin de SMTP (WP Mail SMTP, FluentSMTP) ligado a um serviço de envio, como Brevo, Amazon SES ou SendGrid, e configure SPF e DKIM do domínio. Muitas hospedagens limitam o envio direto do servidor.</p>
		</div>
		<?php
	}

	public function acao() {
		check_admin_referer('cph_news_acao');
		if (!current_user_can('manage_options')) {
			wp_die('Sem permissão.');
		}
		$botao = sanitize_key($_POST['botao'] ?? '');
		if ($botao === 'parar') {
			$e = get_option(self::ENVIO);
			if ($e) {
				$e['fim'] = current_time('mysql');
				update_option(self::ENVIO, $e, false);
			}
			wp_clear_scheduled_hook(self::GANCHO);
			wp_safe_redirect(admin_url('admin.php?page=cph-news&cph_msg=parado'));
			exit;
		}
		$dados = array(
			'assunto'  => sanitize_text_field(wp_unslash($_POST['assunto'] ?? '')),
			'abertura' => sanitize_textarea_field(wp_unslash($_POST['abertura'] ?? '')),
			'posts'    => array_map('absint', (array) ($_POST['posts'] ?? array())),
			'shows'    => !empty($_POST['shows']),
		);
		list($posts, $shows, $abre) = self::montar($dados);
		if ($botao === 'previa') {
			echo self::email_html($dados['assunto'], $abre, $posts, $shows, home_url('/')); // phpcs:ignore
			exit;
		}
		if ($botao === 'teste') {
			$u = wp_get_current_user();
			wp_mail($u->user_email, '[Teste] ' . $dados['assunto'], self::email_html($dados['assunto'], $abre, $posts, $shows, home_url('/')), array('Content-Type: text/html; charset=UTF-8'));
			wp_safe_redirect(admin_url('admin.php?page=cph-news&cph_msg=teste'));
			exit;
		}
		if ($botao === 'enviar') {
			update_option(self::ENVIO, array(
				'assunto' => $dados['assunto'], 'html' => self::email_html($dados['assunto'], $abre, $posts, $shows, '{{SAIR}}'),
				'ultimo' => 0, 'enviados' => 0, 'total' => self::contar('ativo'), 'inicio' => current_time('mysql'), 'fim' => '',
			), false);
			update_option(self::EDICAO, (int) get_option(self::EDICAO, 0) + 1);
			if (!wp_next_scheduled(self::GANCHO)) {
				wp_schedule_event(time(), 'cph_minuto', self::GANCHO);
			}
			$this->enviar_lote();
			wp_safe_redirect(admin_url('admin.php?page=cph-news&cph_msg=fila'));
			exit;
		}
		wp_safe_redirect(admin_url('admin.php?page=cph-news'));
		exit;
	}

	/** Manda um lote de e-mails da edição em andamento. Roda pelo agendador do WordPress. */
	public function enviar_lote() {
		$e = get_option(self::ENVIO);
		if (!$e || !empty($e['fim'])) {
			wp_clear_scheduled_hook(self::GANCHO);
			return;
		}
		global $wpdb;
		$t = self::tabela();
		$lista = $wpdb->get_results($wpdb->prepare("SELECT id, email, token FROM $t WHERE status = 'ativo' AND id > %d ORDER BY id ASC LIMIT %d", $e['ultimo'], self::LOTE)); // phpcs:ignore
		if (!$lista) {
			$e['fim'] = current_time('mysql');
			update_option(self::ENVIO, $e, false);
			wp_clear_scheduled_hook(self::GANCHO);
			return;
		}
		foreach ($lista as $s) {
			$sair = add_query_arg(array('cph_news' => 'sair', 't' => $s->token), home_url('/'));
			wp_mail($s->email, $e['assunto'], str_replace('{{SAIR}}', esc_url($sair), $e['html']), array('Content-Type: text/html; charset=UTF-8', 'List-Unsubscribe: <' . esc_url_raw($sair) . '>'));
			$e['ultimo'] = (int) $s->id;
			$e['enviados']++;
		}
		update_option(self::ENVIO, $e, false);
	}

	public function csv() {
		check_admin_referer('cph_news_csv');
		if (!current_user_can('manage_options')) {
			wp_die('Sem permissão.');
		}
		global $wpdb;
		$rows = $wpdb->get_results('SELECT email, nome, interesses, status, criado, confirmado FROM ' . self::tabela() . ' ORDER BY id ASC', ARRAY_A); // phpcs:ignore
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=newsletter-conexao-pop-' . gmdate('Y-m-d') . '.csv');
		$out = fopen('php://output', 'w');
		fputcsv($out, array('email', 'nome', 'interesses', 'status', 'criado', 'confirmado'));
		foreach ((array) $rows as $r) {
			fputcsv($out, $r);
		}
		fclose($out);
		exit;
	}
}
