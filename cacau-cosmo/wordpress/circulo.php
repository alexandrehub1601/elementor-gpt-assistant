<?php
/**
 * Cacau Cosmo — O Círculo (cadastro, curadoria e acesso à loja)
 *
 * Fluxo (reunião de 23/10/2026):
 *  1. Home aberta; Loja, Journal, Quem somos, Contato e Minha conta só para membros.
 *  2. Formulário "Entrar no Círculo": nome, e-mail, WhatsApp, Instagram, estado, cidade, idade.
 *  3. Confirmação na tela.
 *  4. Aviso no WhatsApp (Nicole e Isa) + e-mail; contato salvo aqui e no Mailchimp.
 *  5. Não aprovada  -> e-mail gentil, fica na lista de espera.
 *  6. Aprovada      -> cria o login e envia "Você foi selecionada" com link para criar a senha.
 *  7. Membro logado acessa tudo.
 *  9. Pedido pago   -> aviso no WhatsApp da Nicole com os dados de envio.
 *
 * Como instalar: plugin WPCode → Code Snippets → Add Snippet → "Add Your Custom Code"
 * → PHP Snippet → cole este arquivo SEM a primeira linha "<?php" → Auto Insert /
 * Run Everywhere → preencha a CONFIGURAÇÃO abaixo → ative e salve.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   CONFIGURAÇÃO
   (cada item também pode ir no wp-config.php com define(), que vale mais)
   ===================================================================== */

/* true = só membros aprovados acessam o site além da Home. */
if ( ! defined( 'CC_CIRCULO_FECHADO' ) ) {
	define( 'CC_CIRCULO_FECHADO', true );
}
/* Idade mínima para solicitar (0 = sem mínimo). */
if ( ! defined( 'CC_CIRCULO_IDADE_MIN' ) ) {
	define( 'CC_CIRCULO_IDADE_MIN', 18 );
}
/* E-mail que recebe o aviso de cada solicitação (vazio = não envia). */
if ( ! defined( 'CC_CIRCULO_EMAIL_AVISO' ) ) {
	define( 'CC_CIRCULO_EMAIL_AVISO', 'ola@cacaucosmo.com.br' );
}
/* Páginas abertas para todo mundo, além da Home (slugs). */
if ( ! defined( 'CC_CIRCULO_PAGINAS_LIVRES' ) ) {
	define( 'CC_CIRCULO_PAGINAS_LIVRES', array( 'politica-de-privacidade', 'termos' ) );
}

/* Remetente dos e-mails do Círculo (o que a pessoa vê na caixa de entrada). */
if ( ! defined( 'CC_EMAIL_NOME' ) ) {
	define( 'CC_EMAIL_NOME', 'Cacau Cosmo' );
}
if ( ! defined( 'CC_EMAIL_DE' ) ) {
	define( 'CC_EMAIL_DE', 'ola@cacaucosmo.com.br' );
}
/* true = os e-mails de cadastro, aprovação e lista de espera saem pelo Mailchimp
   (Customer Journeys disparadas pelas tags). false = saem pelo WordPress. */
if ( ! defined( 'CC_EMAILS_PELO_MAILCHIMP' ) ) {
	define( 'CC_EMAILS_PELO_MAILCHIMP', false );
}

/* Mailchimp: Perfil → Extras → Chaves de API → "Criar chave". Cole a chave inteira
   (termina em -us22). O ID da audiência já é o do formulário atual da Home. */
if ( ! defined( 'CC_MC_API_KEY' ) ) {
	define( 'CC_MC_API_KEY', '' );
}
if ( ! defined( 'CC_MC_LIST_ID' ) ) {
	define( 'CC_MC_LIST_ID', '4c0d53a240' );
}

/* WhatsApp via CallMeBot (grátis). Cada pessoa ativa uma vez: salva o número
   +34 694 25 79 57 nos contatos e manda "I allow callmebot to send me messages".
   O bot responde com a apikey. Formato: array( 'nome' => array( 'telefone', 'apikey' ) ). */
if ( ! defined( 'CC_WHATSAPP_AVISOS' ) ) {
	define( 'CC_WHATSAPP_AVISOS', array(
		// 'Nicole' => array( '5521999999999', '123456' ),
		// 'Isa'    => array( '5521988888888', '654321' ),
	) );
}
/* Quem recebe o aviso de pedido pago (nomes da lista acima). */
if ( ! defined( 'CC_WHATSAPP_PEDIDOS' ) ) {
	define( 'CC_WHATSAPP_PEDIDOS', array( 'Nicole' ) );
}
/* Opcional: URL de webhook (Z-API, Make, Zapier…) que também recebe os avisos em JSON. */
if ( ! defined( 'CC_WHATSAPP_WEBHOOK' ) ) {
	define( 'CC_WHATSAPP_WEBHOOK', '' );
}

/* =====================================================================
   BASE
   ===================================================================== */

if ( ! defined( 'CC_CIRCULO_CPT' ) ) {
	define( 'CC_CIRCULO_CPT', 'cc_circulo' );
	define( 'CC_CIRCULO_CAMPOS', array(
		'nome'      => 'Nome',
		'email'     => 'E-mail',
		'whatsapp'  => 'WhatsApp',
		'instagram' => 'Instagram',
		'estado'    => 'Estado',
		'cidade'    => 'Cidade',
		'idade'     => 'Idade',
	) );
	define( 'CC_CIRCULO_STATUS', array(
		'pendente'  => 'Aguardando',
		'aprovada'  => 'Aprovada',
		'espera'    => 'Lista de espera',
	) );
	define( 'CC_UFS', array(
		'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará',
		'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso',
		'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
		'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
		'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
		'SE' => 'Sergipe', 'TO' => 'Tocantins',
	) );
}

add_action( 'init', function () {
	register_post_type( CC_CIRCULO_CPT, array(
		'labels'          => array(
			'name'               => 'Círculo',
			'singular_name'      => 'Solicitação',
			'menu_name'          => 'Círculo',
			'all_items'          => 'Solicitações',
			'edit_item'          => 'Solicitação',
			'search_items'       => 'Buscar solicitações',
			'not_found'          => 'Nenhuma solicitação ainda.',
			'not_found_in_trash' => 'Nenhuma solicitação na lixeira.',
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'show_in_rest'    => false,
		'menu_position'   => 25,
		'menu_icon'       => 'dashicons-star-filled',
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
		'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'    => true,
	) );
} );

function cc_circulo_get( $id, $campo ) {
	return (string) get_post_meta( $id, '_cc_' . $campo, true );
}

function cc_circulo_status( $id ) {
	$s = cc_circulo_get( $id, 'status' );
	return $s ? $s : 'pendente';
}

function cc_circulo_por_email( $email ) {
	$q = get_posts( array(
		'post_type'      => CC_CIRCULO_CPT,
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_cc_email',
		'meta_value'     => strtolower( $email ),
	) );
	return $q ? (int) $q[0] : 0;
}

/* Membro aprovado = meta no usuário. Admin e gerente da loja sempre entram. */
function cc_circulo_membro( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	if ( user_can( $user_id, 'edit_posts' ) || user_can( $user_id, 'manage_woocommerce' ) ) {
		return true;
	}
	return '1' === (string) get_user_meta( $user_id, 'cc_circulo_membro', true );
}

/* =====================================================================
   RECEBER A SOLICITAÇÃO  (POST /wp-json/cacau/v1/circulo)
   ===================================================================== */

add_action( 'rest_api_init', function () {
	register_rest_route( 'cacau/v1', '/circulo', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'cc_circulo_receber',
	) );
} );

function cc_circulo_resposta( $ok, $texto, $status = 200, $extra = array() ) {
	return new WP_REST_Response( array_merge( array( 'ok' => $ok, 'message' => $texto ), $extra ), $status );
}

function cc_circulo_receber( WP_REST_Request $req ) {
	if ( '' !== trim( (string) $req->get_param( '_gotcha' ) ) ) {         // robô
		return cc_circulo_resposta( true, 'Solicitação enviada!' );
	}

	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$chave = 'cc_circ_' . md5( $ip );
	$vezes = (int) get_transient( $chave );
	if ( $vezes >= 5 ) {
		return cc_circulo_resposta( false, 'Muitas tentativas em pouco tempo. Tente de novo em alguns minutos.', 429 );
	}

	$d = array(
		'nome'      => mb_substr( sanitize_text_field( (string) $req->get_param( 'nome' ) ), 0, 120 ),
		'email'     => strtolower( sanitize_email( (string) $req->get_param( 'email' ) ) ),
		'whatsapp'  => mb_substr( preg_replace( '/[^\d+() -]/', '', (string) $req->get_param( 'whatsapp' ) ), 0, 30 ),
		'instagram' => mb_substr( ltrim( sanitize_text_field( (string) $req->get_param( 'instagram' ) ), '@ ' ), 0, 60 ),
		'estado'    => strtoupper( sanitize_text_field( (string) $req->get_param( 'estado' ) ) ),
		'cidade'    => mb_substr( sanitize_text_field( (string) $req->get_param( 'cidade' ) ), 0, 120 ),
		'idade'     => (string) absint( $req->get_param( 'idade' ) ),
	);
	$d['instagram'] = preg_replace( '#^(https?://)?(www\.)?instagram\.com/#i', '', $d['instagram'] );
	$d['instagram'] = trim( $d['instagram'], '/ ' );

	if ( '' === $d['nome'] || ! is_email( $d['email'] ) ) {
		return cc_circulo_resposta( false, 'Preencha nome e um e-mail válido.', 400 );
	}
	if ( strlen( preg_replace( '/\D/', '', $d['whatsapp'] ) ) < 10 ) {
		return cc_circulo_resposta( false, 'Informe o WhatsApp com DDD.', 400 );
	}
	if ( ! isset( CC_UFS[ $d['estado'] ] ) || '' === $d['cidade'] ) {
		return cc_circulo_resposta( false, 'Informe estado e cidade.', 400 );
	}
	$idade = (int) $d['idade'];
	if ( $idade < 1 || $idade > 120 ) {
		return cc_circulo_resposta( false, 'Informe sua idade.', 400 );
	}
	if ( CC_CIRCULO_IDADE_MIN && $idade < CC_CIRCULO_IDADE_MIN ) {
		return cc_circulo_resposta( false, 'O Círculo é para maiores de ' . (int) CC_CIRCULO_IDADE_MIN . ' anos.', 400 );
	}
	if ( ! $req->get_param( 'consentimento' ) ) {
		return cc_circulo_resposta( false, 'É preciso aceitar a Política de Privacidade.', 400 );
	}

	set_transient( $chave, $vezes + 1, 10 * MINUTE_IN_SECONDS );

	/* já existe? */
	$existente = cc_circulo_por_email( $d['email'] );
	if ( $existente && 'aprovada' === cc_circulo_status( $existente ) ) {
		return cc_circulo_resposta( true, 'Você já faz parte do Círculo! Entre na sua conta para acessar a loja.', 200,
			array( 'membro' => true, 'login' => cc_circulo_url_login() ) );
	}

	$id = $existente ? $existente : wp_insert_post( array(
		'post_type'   => CC_CIRCULO_CPT,
		'post_status' => 'private',
		'post_title'  => $d['nome'],
	), true );
	if ( is_wp_error( $id ) || ! $id ) {
		return cc_circulo_resposta( false, 'Não foi possível enviar agora. Tente novamente em instantes.', 500 );
	}
	if ( $existente ) {
		wp_update_post( array( 'ID' => $id, 'post_title' => $d['nome'] ) );
	}
	foreach ( $d as $k => $v ) {
		update_post_meta( $id, '_cc_' . $k, $v );
	}
	if ( ! $existente ) {
		update_post_meta( $id, '_cc_status', 'pendente' );
	}
	update_post_meta( $id, '_cc_consentimento', current_time( 'mysql' ) . ' · IP ' . $ip );

	cc_circulo_mailchimp( $id, $existente ? array() : array( 'circulo-pendente' => 'active', 'circulo-site' => 'active' ) );

	if ( ! $existente ) {
		cc_circulo_email_recebida( $id );
		cc_circulo_avisar_nova( $id );
	}

	return cc_circulo_resposta( true, 'Solicitação enviada! Respondemos em até 48h, fique de olho no seu e-mail.' );
}

/* =====================================================================
   MAILCHIMP
   ===================================================================== */

function cc_mc_req( $metodo, $caminho, $corpo = null ) {
	if ( ! CC_MC_API_KEY || false === strpos( CC_MC_API_KEY, '-' ) ) {
		return new WP_Error( 'cc_mc', 'Chave do Mailchimp não configurada.' );
	}
	$dc   = substr( strrchr( CC_MC_API_KEY, '-' ), 1 );
	$args = array(
		'method'  => $metodo,
		'timeout' => 12,
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( 'cacau:' . CC_MC_API_KEY ),
			'Content-Type'  => 'application/json',
		),
	);
	if ( null !== $corpo ) {
		$args['body'] = wp_json_encode( $corpo );
	}
	$r = wp_remote_request( 'https://' . $dc . '.api.mailchimp.com/3.0' . $caminho, $args );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	$json = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( $code >= 300 ) {
		return new WP_Error( 'cc_mc', isset( $json['detail'] ) ? $json['detail'] : 'Erro ' . $code );
	}
	return is_array( $json ) ? $json : array();
}

/* Cria na audiência os campos que o formulário usa (uma vez só). */
function cc_mc_campos() {
	if ( get_option( 'cc_mc_campos_v2' ) === CC_MC_LIST_ID ) {
		return;
	}
	$atuais = cc_mc_req( 'GET', '/lists/' . CC_MC_LIST_ID . '/merge-fields?count=100&fields=merge_fields.tag' );
	if ( is_wp_error( $atuais ) ) {
		return;
	}
	$tem = wp_list_pluck( isset( $atuais['merge_fields'] ) ? $atuais['merge_fields'] : array(), 'tag' );
	$quer = array( 'FNAME' => 'Nome', 'WHATSAPP' => 'WhatsApp', 'INSTAGRAM' => 'Instagram',
		'ESTADO' => 'Estado', 'CIDADE' => 'Cidade', 'IDADE' => 'Idade', 'LINKSENHA' => 'Link para criar senha' );
	foreach ( $quer as $tag => $nome ) {
		if ( ! in_array( $tag, $tem, true ) ) {
			cc_mc_req( 'POST', '/lists/' . CC_MC_LIST_ID . '/merge-fields', array( 'tag' => $tag, 'name' => $nome, 'type' => 'text' ) );
		}
	}
	update_option( 'cc_mc_campos_v2', CC_MC_LIST_ID, false );
}

function cc_circulo_tags_do_status( $id ) {
	$s = cc_circulo_status( $id );
	return array(
		'circulo-site'     => 'active',
		'circulo-pendente' => 'pendente' === $s ? 'active' : 'inactive',
		'circulo-aprovada' => 'aprovada' === $s ? 'active' : 'inactive',
		'circulo-espera'   => 'espera' === $s ? 'active' : 'inactive',
	);
}

function cc_circulo_mailchimp( $id, $tags = array() ) {
	if ( ! CC_MC_API_KEY ) {
		update_post_meta( $id, '_cc_mc', 'não configurado' );
		return;
	}
	cc_mc_campos();
	$email = cc_circulo_get( $id, 'email' );
	$hash  = md5( strtolower( $email ) );
	$r = cc_mc_req( 'PUT', '/lists/' . CC_MC_LIST_ID . '/members/' . $hash, array(
		'email_address' => $email,
		'status_if_new' => 'subscribed',
		'merge_fields'  => array(
			'FNAME'     => cc_circulo_get( $id, 'nome' ),
			'WHATSAPP'  => cc_circulo_get( $id, 'whatsapp' ),
			'INSTAGRAM' => cc_circulo_get( $id, 'instagram' ),
			'ESTADO'    => cc_circulo_get( $id, 'estado' ),
			'CIDADE'    => cc_circulo_get( $id, 'cidade' ),
			'IDADE'     => cc_circulo_get( $id, 'idade' ),
			'LINKSENHA' => cc_circulo_get( $id, 'link' ),
		),
	) );
	if ( ! is_wp_error( $r ) && $tags ) {
		$lista = array();
		foreach ( $tags as $nome => $estado ) {
			$lista[] = array( 'name' => $nome, 'status' => $estado );
		}
		$r = cc_mc_req( 'POST', '/lists/' . CC_MC_LIST_ID . '/members/' . $hash . '/tags', array( 'tags' => $lista ) );
	}
	update_post_meta( $id, '_cc_mc', is_wp_error( $r ) ? 'erro: ' . $r->get_error_message() : 'ok' );
}

/* =====================================================================
   AVISOS (WhatsApp + e-mail)
   ===================================================================== */

function cc_whatsapp( $texto, $para = null ) {
	$lista = CC_WHATSAPP_AVISOS;
	if ( is_array( $para ) ) {
		$lista = array_intersect_key( $lista, array_flip( $para ) );
	}
	foreach ( $lista as $quem ) {
		if ( empty( $quem[0] ) || empty( $quem[1] ) ) {
			continue;
		}
		wp_remote_get( add_query_arg( array(
			'phone'  => '+' . preg_replace( '/\D/', '', $quem[0] ),
			'text'   => rawurlencode( $texto ),
			'apikey' => rawurlencode( $quem[1] ),
		), 'https://api.callmebot.com/whatsapp.php' ), array( 'timeout' => 8, 'blocking' => false ) );
	}
	if ( CC_WHATSAPP_WEBHOOK ) {
		wp_remote_post( CC_WHATSAPP_WEBHOOK, array(
			'timeout'  => 8,
			'blocking' => false,
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode( array( 'texto' => $texto, 'para' => $para ) ),
		) );
	}
}

function cc_circulo_avisar_nova( $id ) {
	$uf    = cc_circulo_get( $id, 'estado' );
	$insta = cc_circulo_get( $id, 'instagram' );
	$link  = admin_url( 'post.php?post=' . $id . '&action=edit' );
	$texto = "✦ Nova solicitação no Círculo\n\n" .
		'Nome: ' . cc_circulo_get( $id, 'nome' ) . "\n" .
		'Idade: ' . cc_circulo_get( $id, 'idade' ) . "\n" .
		'Cidade: ' . cc_circulo_get( $id, 'cidade' ) . ' / ' . $uf . "\n" .
		'WhatsApp: ' . cc_circulo_get( $id, 'whatsapp' ) . "\n" .
		'E-mail: ' . cc_circulo_get( $id, 'email' ) . "\n" .
		'Instagram: ' . ( $insta ? 'instagram.com/' . $insta : '—' ) . "\n\n" .
		'Aprovar ou não: ' . $link;
	cc_whatsapp( $texto );
	if ( CC_CIRCULO_EMAIL_AVISO ) {
		wp_mail( CC_CIRCULO_EMAIL_AVISO, '[Círculo] Nova solicitação — ' . cc_circulo_get( $id, 'nome' ), $texto,
			array( cc_circulo_remetente(), 'Reply-To: ' . cc_circulo_get( $id, 'nome' ) . ' <' . cc_circulo_get( $id, 'email' ) . '>' ) );
	}
}

/* =====================================================================
   E-MAILS PARA A CLIENTE
   ===================================================================== */

function cc_circulo_url_login() {
	return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
}

function cc_circulo_email_html( $titulo, $corpo_html ) {
	$logo = 'https://cacaucosmo.com.br/wp-content/uploads/2026/08/mark-bege-transparente.png';
	return '<!doctype html><html><body style="margin:0;background:#EFE8DC;font-family:Georgia,serif;color:#00062E">' .
		'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EFE8DC;padding:32px 12px"><tr><td align="center">' .
		'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#F1E8D0;border-radius:8px;overflow:hidden">' .
		'<tr><td align="center" style="background:#00062E;padding:34px 24px 26px">' .
		'<img src="' . esc_url( $logo ) . '" width="52" alt="" style="display:block;margin:0 auto 10px">' .
		'<div style="font-family:Helvetica,Arial,sans-serif;font-size:11px;letter-spacing:5px;color:#F1E8D0">CACAU COSMO</div></td></tr>' .
		'<tr><td style="padding:38px 36px 30px">' .
		'<h1 style="margin:0 0 18px;font-size:28px;line-height:1.2;font-weight:700;color:#00062E">' . esc_html( $titulo ) . '</h1>' .
		'<div style="font-size:16px;line-height:1.7;color:#00062E">' . $corpo_html . '</div></td></tr>' .
		'<tr><td style="padding:0 36px 32px;font-family:Helvetica,Arial,sans-serif;font-size:11px;letter-spacing:2px;color:#4A2C1D">O TEMPO É INGREDIENTE.</td></tr>' .
		'</table></td></tr></table></body></html>';
}

function cc_circulo_botao( $url, $texto ) {
	return '<p style="margin:28px 0"><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#00062E;color:#F1E8D0;' .
		'text-decoration:none;font-family:Helvetica,Arial,sans-serif;font-size:13px;letter-spacing:2px;padding:16px 28px;border-radius:4px">' .
		esc_html( $texto ) . '</a></p>';
}

function cc_circulo_remetente() {
	return 'From: ' . CC_EMAIL_NOME . ' <' . CC_EMAIL_DE . '>';
}

function cc_circulo_enviar( $para, $assunto, $titulo, $corpo, $id = 0, $tipo = '' ) {
	if ( CC_EMAILS_PELO_MAILCHIMP && $tipo ) {
		/* quem envia é a jornada do Mailchimp, disparada pela tag */
		if ( $id ) {
			update_post_meta( $id, '_cc_email_' . $tipo, 'pelo Mailchimp ' . current_time( 'd/m/Y H:i' ) );
		}
		return true;
	}
	$ok = wp_mail( $para, $assunto, cc_circulo_email_html( $titulo, $corpo ),
		array( 'Content-Type: text/html; charset=UTF-8', cc_circulo_remetente() ) );
	if ( $id && $tipo ) {
		update_post_meta( $id, '_cc_email_' . $tipo, ( $ok ? '' : 'FALHOU ' ) . current_time( 'd/m/Y H:i' ) );
	}
	return $ok;
}

/* Todos os e-mails do site que sairiam como "WordPress" passam a sair como Cacau Cosmo */
add_filter( 'wp_mail_from_name', function ( $nome ) {
	return ( '' === $nome || 'WordPress' === $nome ) ? CC_EMAIL_NOME : $nome;
} );
add_filter( 'wp_mail_from', function ( $email ) {
	return 0 === strpos( $email, 'wordpress@' ) ? CC_EMAIL_DE : $email;
} );

/* [ TEXTO ] e-mail de cadastro recebido — ajuste à vontade */
function cc_circulo_email_recebida( $id ) {
	$nome  = esc_html( strtok( cc_circulo_get( $id, 'nome' ), ' ' ) );
	$corpo = '<p>Olá, ' . $nome . '.</p>' .
		'<p>Recebemos sua solicitação para entrar no <strong>Círculo</strong>. Que bom ter você por aqui.</p>' .
		'<p>Lemos cada pedido com calma e respondemos em até <strong>48 horas</strong>, por este e-mail. Se for aprovado, você recebe o link para criar sua senha e acessar a loja, o Journal e os próximos lotes.</p>' .
		'<p>Enquanto isso, acompanhe o que estamos criando no Instagram: <a href="https://instagram.com/cacaucosmo" style="color:#4A2C1D">@cacaucosmo</a>.</p>' .
		'<p>Com carinho,<br>Cacau Cosmo</p>';
	return cc_circulo_enviar( cc_circulo_get( $id, 'email' ), 'Recebemos sua solicitação para o Círculo ✦', 'Recebemos seu pedido.', $corpo, $id, 'recebida' );
}

/* [ TEXTO ] e-mail de aprovação — ajuste à vontade */
function cc_circulo_email_aprovada( $id, $user ) {
	$nome  = esc_html( strtok( cc_circulo_get( $id, 'nome' ), ' ' ) );
	$chave = get_password_reset_key( $user );
	$link  = is_wp_error( $chave ) ? cc_circulo_url_login() : ( function_exists( 'wc_get_endpoint_url' )
		? add_query_arg( array( 'key' => $chave, 'id' => $user->ID ), wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) )
		: network_site_url( 'wp-login.php?action=rp&key=' . $chave . '&login=' . rawurlencode( $user->user_login ), 'login' ) );
	update_post_meta( $id, '_cc_link', $link );
	$corpo = '<p>Olá, ' . $nome . '.</p>' .
		'<p>Que alegria: sua entrada no <strong>Círculo</strong> foi aprovada. A partir de agora, a loja, o Journal e os próximos lotes estão abertos para você.</p>' .
		'<p>Para entrar, crie sua senha no botão abaixo. Seu login é este e-mail: <strong>' . esc_html( $user->user_email ) . '</strong>.</p>' .
		cc_circulo_botao( $link, 'CRIAR MINHA SENHA' ) .
		'<p style="font-size:14px;opacity:.8">O link vale por 24 horas. Se expirar, é só acessar <a href="' . esc_url( cc_circulo_url_login() ) . '" style="color:#4A2C1D">' .
		esc_html( preg_replace( '#^https?://#', '', cc_circulo_url_login() ) ) . '</a> e clicar em "Perdeu sua senha?".</p>' .
		'<p>Boas-vindas ao Círculo. O próximo ritual começa agora.</p>';
	return cc_circulo_enviar( $user->user_email, 'Você foi selecionada para o Círculo ✦', 'Você foi selecionada.', $corpo, $id, 'aprovada' );
}

/* [ TEXTO A DEFINIR ] e-mail de lista de espera — ajuste à vontade */
function cc_circulo_email_espera( $id ) {
	$nome  = esc_html( strtok( cc_circulo_get( $id, 'nome' ), ' ' ) );
	$corpo = '<p>Olá, ' . $nome . '.</p>' .
		'<p>Obrigada por querer fazer parte do Círculo. Cada lote nosso é pequeno, feito à mão e no tempo do cacau &mdash; por isso as vagas também são poucas.</p>' .
		'<p>Neste momento não conseguimos abrir espaço para todo mundo, mas <strong>seu nome ficou na nossa lista de espera</strong>. Quando abrirmos novas vagas, você estará entre as primeiras pessoas a saber.</p>' .
		'<p>Enquanto isso, acompanhe o que estamos criando no Instagram: <a href="https://instagram.com/cacaucosmo" style="color:#4A2C1D">@cacaucosmo</a>.</p>' .
		'<p>Com carinho,<br>Cacau Cosmo</p>';
	return cc_circulo_enviar( cc_circulo_get( $id, 'email' ), 'Sua solicitação para o Círculo', 'Você está na nossa lista.', $corpo, $id, 'espera' );
}

/* =====================================================================
   DECISÃO: APROVAR / NÃO APROVAR
   ===================================================================== */

function cc_circulo_aprovar( $id ) {
	$email = cc_circulo_get( $id, 'email' );
	$user  = get_user_by( 'email', $email );
	if ( ! $user ) {
		$login = sanitize_user( current( explode( '@', $email ) ), true );
		$base  = $login ? $login : 'membro';
		$n     = 1;
		while ( username_exists( $login ) || '' === $login ) {
			$login = $base . ( ++$n );
		}
		$nome    = cc_circulo_get( $id, 'nome' );
		$partes  = explode( ' ', $nome, 2 );
		$user_id = wp_insert_user( array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 24, true, true ),
			'first_name'   => $partes[0],
			'last_name'    => isset( $partes[1] ) ? $partes[1] : '',
			'display_name' => $nome,
			'role'         => get_role( 'customer' ) ? 'customer' : 'subscriber',
		) );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		/* já deixa os dados prontos para o checkout */
		update_user_meta( $user_id, 'billing_first_name', $partes[0] );
		update_user_meta( $user_id, 'billing_last_name', isset( $partes[1] ) ? $partes[1] : '' );
		update_user_meta( $user_id, 'billing_email', $email );
		update_user_meta( $user_id, 'billing_phone', cc_circulo_get( $id, 'whatsapp' ) );
		update_user_meta( $user_id, 'billing_state', cc_circulo_get( $id, 'estado' ) );
		update_user_meta( $user_id, 'billing_city', cc_circulo_get( $id, 'cidade' ) );
		update_user_meta( $user_id, 'billing_country', 'BR' );
		$user = get_user_by( 'id', $user_id );
	}
	update_user_meta( $user->ID, 'cc_circulo_membro', '1' );
	update_post_meta( $id, '_cc_status', 'aprovada' );
	update_post_meta( $id, '_cc_user', $user->ID );
	cc_circulo_historico( $id, 'Aprovada' );
	cc_circulo_email_aprovada( $id, $user );
	cc_circulo_mailchimp( $id, array( 'circulo-aprovada' => 'active', 'circulo-pendente' => 'inactive', 'circulo-espera' => 'inactive' ) );
	return true;
}

function cc_circulo_recusar( $id ) {
	update_post_meta( $id, '_cc_status', 'espera' );
	$uid = (int) cc_circulo_get( $id, 'user' );
	if ( $uid ) {
		delete_user_meta( $uid, 'cc_circulo_membro' );
	}
	cc_circulo_historico( $id, 'Lista de espera' );
	cc_circulo_email_espera( $id );
	cc_circulo_mailchimp( $id, array( 'circulo-espera' => 'active', 'circulo-pendente' => 'inactive', 'circulo-aprovada' => 'inactive' ) );
	return true;
}

function cc_circulo_historico( $id, $acao ) {
	$h   = get_post_meta( $id, '_cc_historico', true );
	$h   = is_array( $h ) ? $h : array();
	$eu  = wp_get_current_user();
	$h[] = current_time( 'd/m/Y H:i' ) . ' — ' . $acao . ( $eu && $eu->ID ? ' por ' . $eu->display_name : '' );
	update_post_meta( $id, '_cc_historico', $h );
}

function cc_circulo_url_acao( $id, $acao ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=cc_circulo_decidir&d=' . $acao . '&id=' . (int) $id ), 'cc_circulo_' . $id );
}

add_action( 'admin_post_cc_circulo_decidir', function () {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	$d  = isset( $_GET['d'] ) ? sanitize_key( $_GET['d'] ) : '';
	if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! check_admin_referer( 'cc_circulo_' . $id ) ) {
		wp_die( 'Sem permissão.' );
	}
	$r = 'aprovar' === $d ? cc_circulo_aprovar( $id ) : ( 'reenviar' === $d
		? cc_circulo_email_aprovada( $id, get_user_by( 'id', (int) cc_circulo_get( $id, 'user' ) ) )
		: cc_circulo_recusar( $id ) );
	$msg = is_wp_error( $r ) ? 'erro' : $d;
	wp_safe_redirect( add_query_arg( 'cc_msg', $msg, wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . CC_CIRCULO_CPT ) ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( empty( $_GET['cc_msg'] ) ) {
		return;
	}
	$m = array(
		'aprovar'  => 'Aprovada! O login foi criado e o e-mail "Você foi selecionada" foi enviado.',
		'recusar'  => 'Enviada para a lista de espera. O e-mail de retorno foi enviado.',
		'reenviar' => 'E-mail de aprovação reenviado.',
		'erro'     => 'Não foi possível concluir. Verifique se o e-mail já pertence a outro usuário.',
		'mailchimp' => 'Enviado para o Mailchimp. Veja o resultado de cada pessoa na coluna "Envios".',
		'recebida'  => 'E-mail de cadastro recebido enviado.',
		'sync'      => 'Sincronização com o Mailchimp concluída. Veja o resultado na coluna "Envios".',
	);
	$k = sanitize_key( $_GET['cc_msg'] );
	if ( isset( $m[ $k ] ) ) {
		echo '<div class="notice notice-' . ( 'erro' === $k ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $m[ $k ] ) . '</p></div>';
	}
} );

/* =====================================================================
   PAINEL: LISTA, FILTROS, AÇÕES
   ===================================================================== */

add_filter( 'manage_' . CC_CIRCULO_CPT . '_posts_columns', function () {
	return array(
		'cb'          => '<input type="checkbox">',
		'title'       => 'Nome',
		'cc_status'   => 'Status',
		'cc_contato'  => 'Contato',
		'cc_insta'    => 'Instagram',
		'cc_local'    => 'Cidade',
		'cc_idade'    => 'Idade',
		'cc_data'     => 'Recebida em',
		'cc_envios'   => 'Envios',
	);
} );

function cc_circulo_pill( $s ) {
	$cores = array( 'pendente' => '#E9D9D2;color:#7a3550', 'aprovada' => '#d8e8dc;color:#2f6b45', 'espera' => '#ece6d4;color:#6d5512' );
	return '<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;background:' .
		( isset( $cores[ $s ] ) ? $cores[ $s ] : '#eee' ) . '">' . esc_html( isset( CC_CIRCULO_STATUS[ $s ] ) ? CC_CIRCULO_STATUS[ $s ] : $s ) . '</span>';
}

add_action( 'manage_' . CC_CIRCULO_CPT . '_posts_custom_column', function ( $col, $id ) {
	switch ( $col ) {
		case 'cc_status':
			echo cc_circulo_pill( cc_circulo_status( $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'cc_contato':
			$e  = cc_circulo_get( $id, 'email' );
			$w  = cc_circulo_get( $id, 'whatsapp' );
			$wn = preg_replace( '/\D/', '', $w );
			$wn = strlen( $wn ) <= 11 ? '55' . $wn : $wn;
			echo '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a><br><a href="https://wa.me/' . esc_attr( $wn ) . '" target="_blank" rel="noopener">' . esc_html( $w ) . '</a>';
			break;
		case 'cc_insta':
			$i = cc_circulo_get( $id, 'instagram' );
			echo $i ? '<a href="https://instagram.com/' . esc_attr( $i ) . '" target="_blank" rel="noopener">@' . esc_html( $i ) . '</a>' : '—';
			break;
		case 'cc_local':
			echo esc_html( cc_circulo_get( $id, 'cidade' ) . ' / ' . cc_circulo_get( $id, 'estado' ) );
			break;
		case 'cc_idade':
			echo esc_html( cc_circulo_get( $id, 'idade' ) );
			break;
		case 'cc_data':
			echo esc_html( get_the_date( 'd/m/Y H:i', $id ) );
			break;
		case 'cc_envios':
			$mc  = cc_circulo_get( $id, 'mc' );
			$cor = 'ok' === $mc ? '#2f6b45' : ( $mc ? '#a3404f' : '#888' );
			echo '<span style="color:' . esc_attr( $cor ) . '" title="' . esc_attr( $mc ) . '">Mailchimp: ' . esc_html( 'ok' === $mc ? 'ok' : ( $mc ? 'erro' : '—' ) ) . '</span><br>';
			foreach ( array( 'recebida' => 'Cadastro', 'aprovada' => 'Aprovação', 'espera' => 'Lista de espera' ) as $tipo => $rotulo ) {
				$quando = cc_circulo_get( $id, 'email_' . $tipo );
				if ( $quando ) {
					$falhou = 0 === strpos( $quando, 'FALHOU' );
					echo '<span style="color:' . ( $falhou ? '#a3404f' : '#555' ) . '" title="' . esc_attr( $quando ) . '">' . ( $falhou ? '✕ ' : '✓ ' ) . esc_html( $rotulo ) . '</span><br>';
				}
			}
			break;
	}
}, 10, 2 );

/* Aprovar / Não aprovar direto na lista */
add_filter( 'post_row_actions', function ( $acoes, $post ) {
	if ( CC_CIRCULO_CPT !== $post->post_type ) {
		return $acoes;
	}
	$s     = cc_circulo_status( $post->ID );
	$novas = array( 'edit' => '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">Ver</a>' );
	if ( 'aprovada' !== $s ) {
		$novas['cc_aprovar'] = '<a style="color:#2f6b45;font-weight:600" href="' . esc_url( cc_circulo_url_acao( $post->ID, 'aprovar' ) ) . '">Aprovar</a>';
	}
	if ( 'espera' !== $s ) {
		$novas['cc_recusar'] = '<a style="color:#a3404f" href="' . esc_url( cc_circulo_url_acao( $post->ID, 'recusar' ) ) .
			'" onclick="return confirm(\'Enviar para a lista de espera? A pessoa recebe o e-mail de retorno.\')">Não aprovar</a>';
	}
	if ( isset( $acoes['trash'] ) ) {
		$novas['trash'] = $acoes['trash'];
	}
	return $novas;
}, 10, 2 );

/* Filtros por status (Aguardando / Aprovadas / Lista de espera) */
add_filter( 'views_edit-' . CC_CIRCULO_CPT, function ( $views ) {
	$atual = isset( $_GET['cc_status'] ) ? sanitize_key( $_GET['cc_status'] ) : '';
	$base  = admin_url( 'edit.php?post_type=' . CC_CIRCULO_CPT );
	$novas = array( 'all' => '<a href="' . esc_url( $base ) . '"' . ( $atual ? '' : ' class="current"' ) . '>Todas</a>' );
	foreach ( CC_CIRCULO_STATUS as $k => $rotulo ) {
		$n = count( get_posts( array( 'post_type' => CC_CIRCULO_CPT, 'post_status' => 'private', 'posts_per_page' => -1, 'fields' => 'ids',
			'meta_key' => '_cc_status', 'meta_value' => $k ) ) );
		$novas[ $k ] = '<a href="' . esc_url( add_query_arg( 'cc_status', $k, $base ) ) . '"' . ( $atual === $k ? ' class="current"' : '' ) . '>' .
			esc_html( $rotulo ) . ' <span class="count">(' . $n . ')</span></a>';
	}
	if ( isset( $views['trash'] ) ) {
		$novas['trash'] = $views['trash'];
	}
	return $novas;
} );

add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || CC_CIRCULO_CPT !== $q->get( 'post_type' ) ) {
		return;
	}
	if ( ! $q->get( 'post_status' ) || 'all' === $q->get( 'post_status' ) ) {
		$q->set( 'post_status', array( 'private' ) );
	}
	if ( ! empty( $_GET['cc_status'] ) && isset( CC_CIRCULO_STATUS[ sanitize_key( $_GET['cc_status'] ) ] ) ) {
		$q->set( 'meta_key', '_cc_status' );
		$q->set( 'meta_value', sanitize_key( $_GET['cc_status'] ) );
	}
	if ( $q->get( 's' ) ) {   // busca também por e-mail, cidade e Instagram
		$s = $q->get( 's' );
		$q->set( 's', '' );
		$q->set( 'meta_query', array( 'relation' => 'OR',
			array( 'key' => '_cc_nome', 'value' => $s, 'compare' => 'LIKE' ),
			array( 'key' => '_cc_email', 'value' => $s, 'compare' => 'LIKE' ),
			array( 'key' => '_cc_cidade', 'value' => $s, 'compare' => 'LIKE' ),
			array( 'key' => '_cc_instagram', 'value' => $s, 'compare' => 'LIKE' ),
		) );
	}
} );

/* Ações em massa */
add_filter( 'bulk_actions-edit-' . CC_CIRCULO_CPT, function ( $a ) {
	return array(
		'cc_aprovar'   => 'Aprovar',
		'cc_recusar'   => 'Não aprovar (lista de espera)',
		'cc_mailchimp' => 'Enviar para o Mailchimp',
		'cc_recebida'  => 'Enviar e-mail de cadastro recebido',
	) + $a;
} );
add_filter( 'handle_bulk_actions-edit-' . CC_CIRCULO_CPT, function ( $volta, $acao, $ids ) {
	$msgs = array( 'cc_aprovar' => 'aprovar', 'cc_recusar' => 'recusar', 'cc_mailchimp' => 'mailchimp', 'cc_recebida' => 'recebida' );
	if ( ! isset( $msgs[ $acao ] ) ) {
		return $volta;
	}
	foreach ( $ids as $id ) {
		if ( ! current_user_can( 'edit_post', $id ) ) {
			continue;
		}
		if ( 'cc_aprovar' === $acao ) {
			cc_circulo_aprovar( $id );
		} elseif ( 'cc_recusar' === $acao ) {
			cc_circulo_recusar( $id );
		} elseif ( 'cc_mailchimp' === $acao ) {
			cc_circulo_mailchimp( $id, cc_circulo_tags_do_status( $id ) );
		} else {
			cc_circulo_email_recebida( $id );
		}
	}
	return add_query_arg( array( 'cc_msg' => $msgs[ $acao ], 'cc_n' => count( $ids ) ), $volta );
}, 10, 3 );

/* Contador de solicitações aguardando no menu */
add_action( 'admin_menu', function () {
	global $menu;
	$n = count( get_posts( array( 'post_type' => CC_CIRCULO_CPT, 'post_status' => 'private', 'posts_per_page' => 99, 'fields' => 'ids',
		'meta_key' => '_cc_status', 'meta_value' => 'pendente' ) ) );
	if ( ! $n ) {
		return;
	}
	foreach ( $menu as $i => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=' . CC_CIRCULO_CPT === $item[2] ) {
			$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $n . '</span></span>';
		}
	}
}, 99 );

/* Tela da solicitação */
add_action( 'add_meta_boxes_' . CC_CIRCULO_CPT, function () {
	remove_meta_box( 'submitdiv', CC_CIRCULO_CPT, 'side' );
	add_meta_box( 'cc_circ_dados', 'Dados da solicitação', 'cc_circulo_box', CC_CIRCULO_CPT, 'normal', 'high' );
	add_meta_box( 'cc_circ_acoes', 'Curadoria', 'cc_circulo_box_acoes', CC_CIRCULO_CPT, 'side', 'high' );
} );

function cc_circulo_box( $post ) {
	echo '<table class="widefat striped"><tbody>';
	foreach ( CC_CIRCULO_CAMPOS as $k => $rotulo ) {
		$v = cc_circulo_get( $post->ID, $k );
		if ( 'instagram' === $k && $v ) {
			$v = '<a href="https://instagram.com/' . esc_attr( $v ) . '" target="_blank" rel="noopener">@' . esc_html( $v ) . ' ↗</a>';
		} elseif ( 'whatsapp' === $k && $v ) {
			$n = preg_replace( '/\D/', '', $v );
			$v = esc_html( $v ) . ' &nbsp;<a href="https://wa.me/' . esc_attr( strlen( $n ) <= 11 ? '55' . $n : $n ) . '" target="_blank" rel="noopener">abrir no WhatsApp ↗</a>';
		} elseif ( 'email' === $k ) {
			$v = '<a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>';
		} elseif ( 'estado' === $k ) {
			$v = esc_html( isset( CC_UFS[ $v ] ) ? CC_UFS[ $v ] . ' (' . $v . ')' : $v );
		} else {
			$v = esc_html( $v );
		}
		echo '<tr><th style="width:140px"><strong>' . esc_html( $rotulo ) . '</strong></th><td>' . $v . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<tr><th><strong>Recebida em</strong></th><td>' . esc_html( get_the_date( 'd/m/Y \à\s H:i', $post ) ) . '</td></tr>';
	echo '<tr><th><strong>Consentimento</strong></th><td>' . esc_html( cc_circulo_get( $post->ID, 'consentimento' ) ) . '</td></tr>';
	echo '<tr><th><strong>Mailchimp</strong></th><td>' . esc_html( cc_circulo_get( $post->ID, 'mc' ) ) . '</td></tr>';
	foreach ( array( 'recebida' => 'E-mail de cadastro', 'aprovada' => 'E-mail de aprovação', 'espera' => 'E-mail de lista de espera' ) as $tipo => $rotulo ) {
		$q = cc_circulo_get( $post->ID, 'email_' . $tipo );
		if ( $q ) {
			echo '<tr><th><strong>' . esc_html( $rotulo ) . '</strong></th><td>' . esc_html( $q ) . '</td></tr>';
		}
	}
	echo '</tbody></table>';
	$h = get_post_meta( $post->ID, '_cc_historico', true );
	if ( is_array( $h ) && $h ) {
		echo '<p style="margin-top:14px"><strong>Histórico</strong><br>' . implode( '<br>', array_map( 'esc_html', $h ) ) . '</p>';
	}
}

function cc_circulo_box_acoes( $post ) {
	$s = cc_circulo_status( $post->ID );
	echo '<p>Status: ' . cc_circulo_pill( $s ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( 'aprovada' !== $s ) {
		echo '<p><a class="button button-primary" style="width:100%;text-align:center" href="' . esc_url( cc_circulo_url_acao( $post->ID, 'aprovar' ) ) . '">✓ Aprovar e enviar acesso</a></p>';
	} else {
		echo '<p><a class="button" style="width:100%;text-align:center" href="' . esc_url( cc_circulo_url_acao( $post->ID, 'reenviar' ) ) . '">Reenviar e-mail de acesso</a></p>';
	}
	if ( 'espera' !== $s ) {
		echo '<p><a class="button" style="width:100%;text-align:center;color:#a3404f;border-color:#a3404f" href="' . esc_url( cc_circulo_url_acao( $post->ID, 'recusar' ) ) .
			'" onclick="return confirm(\'Enviar para a lista de espera? A pessoa recebe o e-mail de retorno.\')">Não aprovar (lista de espera)</a></p>';
	}
	echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=' . CC_CIRCULO_CPT ) ) . '">← Voltar para a lista</a></p>';
}

/* Exportar CSV */
add_action( 'manage_posts_extra_tablenav', function ( $which ) {
	if ( 'top' !== $which || CC_CIRCULO_CPT !== get_current_screen()->post_type ) {
		return;
	}
	echo '<div class="alignleft actions"><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cc_circulo_csv' ), 'cc_circulo_csv' ) ) . '">Exportar CSV</a> ' .
		'<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cc_circulo_sync' ), 'cc_circulo_sync' ) ) . '">Sincronizar todas com o Mailchimp</a></div>';
} );

/* Leva ao Mailchimp todas as solicitações, com a tag do status atual */
add_action( 'admin_post_cc_circulo_sync', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'cc_circulo_sync' ) ) {
		wp_die( 'Sem permissão.' );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$ids = get_posts( array( 'post_type' => CC_CIRCULO_CPT, 'post_status' => 'private', 'posts_per_page' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		cc_circulo_mailchimp( $id, cc_circulo_tags_do_status( $id ) );
	}
	wp_safe_redirect( add_query_arg( array( 'cc_msg' => 'sync', 'cc_n' => count( $ids ) ), admin_url( 'edit.php?post_type=' . CC_CIRCULO_CPT ) ) );
	exit;
} );

/* Aviso no topo do menu Círculo quando o Mailchimp não está conectado */
add_action( 'admin_notices', function () {
	$tela = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $tela || CC_CIRCULO_CPT !== $tela->post_type ) {
		return;
	}
	if ( ! CC_MC_API_KEY ) {
		echo '<div class="notice notice-warning"><p><strong>Mailchimp ainda não está conectado.</strong> As solicitações ficam salvas aqui, mas não vão para a audiência. ' .
			'Cole a chave em <code>CC_MC_API_KEY</code>, no topo do snippet "Círculo" do WPCode, e depois clique em <em>Sincronizar todas com o Mailchimp</em>.</p></div>';
		return;
	}
	$ok = get_transient( 'cc_mc_ping' );
	if ( false === $ok ) {
		$r  = cc_mc_req( 'GET', '/lists/' . CC_MC_LIST_ID . '?fields=name' );
		$ok = is_wp_error( $r ) ? 'erro: ' . $r->get_error_message() : 'ok:' . ( isset( $r['name'] ) ? $r['name'] : '' );
		set_transient( 'cc_mc_ping', $ok, 10 * MINUTE_IN_SECONDS );
	}
	if ( 0 === strpos( $ok, 'ok:' ) ) {
		echo '<div class="notice notice-success"><p>Mailchimp conectado à audiência <strong>' . esc_html( substr( $ok, 3 ) ) . '</strong>.</p></div>';
	} else {
		echo '<div class="notice notice-error"><p><strong>O Mailchimp recusou a conexão</strong> (' . esc_html( $ok ) . '). Confira se a chave em <code>CC_MC_API_KEY</code> está completa e se o ID da audiência <code>' .
			esc_html( CC_MC_LIST_ID ) . '</code> é o da audiência do site.</p></div>';
	}
} );
add_action( 'admin_post_cc_circulo_csv', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'cc_circulo_csv' ) ) {
		wp_die( 'Sem permissão.' );
	}
	$ids = get_posts( array( 'post_type' => CC_CIRCULO_CPT, 'post_status' => 'private', 'posts_per_page' => -1, 'fields' => 'ids' ) );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=circulo-cacau-cosmo-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array_merge( array( 'Data', 'Status' ), array_values( CC_CIRCULO_CAMPOS ) ), ';' );
	foreach ( $ids as $id ) {
		$linha = array( get_the_date( 'd/m/Y H:i', $id ), CC_CIRCULO_STATUS[ cc_circulo_status( $id ) ] ?? '' );
		foreach ( array_keys( CC_CIRCULO_CAMPOS ) as $k ) {
			$linha[] = cc_circulo_get( $id, $k );
		}
		$linha = array_map( function ( $v ) {
			return preg_match( '/^[=+\-@]/', (string) $v ) ? "'" . $v : $v;
		}, $linha );
		fputcsv( $out, $linha, ';' );
	}
	fclose( $out );
	exit;
} );

/* Marcar membro à mão no perfil do usuário (clientes antigos, por exemplo) */
function cc_circulo_campo_perfil( $user ) {
	if ( ! current_user_can( 'edit_users' ) ) {
		return;
	}
	echo '<h2>Círculo</h2><table class="form-table"><tr><th>Membro do Círculo</th><td><label><input type="checkbox" name="cc_circulo_membro" value="1" ' .
		checked( '1', get_user_meta( $user->ID, 'cc_circulo_membro', true ), false ) . '> Pode acessar a loja e as páginas do Círculo</label></td></tr></table>';
}
add_action( 'show_user_profile', 'cc_circulo_campo_perfil' );
add_action( 'edit_user_profile', 'cc_circulo_campo_perfil' );
function cc_circulo_salvar_perfil( $uid ) {
	if ( current_user_can( 'edit_users' ) && isset( $_POST['_wpnonce'] ) ) {
		if ( ! empty( $_POST['cc_circulo_membro'] ) ) {
			update_user_meta( $uid, 'cc_circulo_membro', '1' );
		} else {
			delete_user_meta( $uid, 'cc_circulo_membro' );
		}
	}
}
add_action( 'personal_options_update', 'cc_circulo_salvar_perfil' );
add_action( 'edit_user_profile_update', 'cc_circulo_salvar_perfil' );
add_filter( 'manage_users_columns', function ( $c ) {
	$c['cc_circulo'] = 'Círculo';
	return $c;
} );
add_filter( 'manage_users_custom_column', function ( $v, $col, $uid ) {
	return 'cc_circulo' === $col ? ( cc_circulo_membro( $uid ) ? '✦ Membro' : '—' ) : $v;
}, 10, 3 );

/* =====================================================================
   PORTA DO SITE: só a Home é aberta
   ===================================================================== */

function cc_circulo_pagina_livre() {
	if ( is_front_page() ) {
		return true;
	}
	if ( is_customize_preview() || isset( $_GET['elementor-preview'] ) || get_query_var( 'sitemap' ) ) {
		return true;
	}
	/* Minha conta só deslogado (entrar, recuperar e criar senha); sair vale para todos */
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		if ( ! is_user_logged_in() || ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'customer-logout' ) ) ) {
			return true;
		}
	}
	$privacidade = (int) get_option( 'wp_page_for_privacy_policy' );
	if ( $privacidade && is_page( $privacidade ) ) {
		return true;
	}
	foreach ( (array) CC_CIRCULO_PAGINAS_LIVRES as $slug ) {
		if ( is_page( $slug ) ) {
			return true;
		}
	}
	return false;
}

add_action( 'template_redirect', function () {
	if ( ! CC_CIRCULO_FECHADO || cc_circulo_membro() || cc_circulo_pagina_livre() ) {
		return;
	}
	if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() || is_robots() ) {
		return;
	}
	$motivo = is_user_logged_in() ? 'analise' : 'membros';
	wp_safe_redirect( add_query_arg( 'acesso', $motivo, home_url( '/' ) ) . '#circulo', 302 );
	exit;
}, 5 );

/* Depois de entrar, membro vai direto para a loja */
add_filter( 'woocommerce_login_redirect', function ( $url, $user ) {
	return cc_circulo_membro( $user->ID ) && function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : $url;
}, 10, 2 );

/* Ninguém cria conta sozinho: a conta nasce da aprovação */
add_filter( 'pre_option_woocommerce_enable_myaccount_registration', function () {
	return CC_CIRCULO_FECHADO ? 'no' : false;
} );

/* =====================================================================
   PEDIDO PAGO → aviso no WhatsApp da Nicole (passo 9)
   ===================================================================== */

add_action( 'woocommerce_order_status_processing', 'cc_circulo_aviso_pedido' );
add_action( 'woocommerce_order_status_completed', 'cc_circulo_aviso_pedido' );
function cc_circulo_aviso_pedido( $order_id ) {
	$o = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
	if ( ! $o || $o->get_meta( '_cc_wa_avisado' ) ) {
		return;
	}
	$itens = array();
	foreach ( $o->get_items() as $item ) {
		$itens[] = $item->get_quantity() . '× ' . $item->get_name();
	}
	$endereco = trim( implode( ', ', array_filter( array(
		$o->get_shipping_address_1() ? $o->get_shipping_address_1() : $o->get_billing_address_1(),
		$o->get_meta( '_shipping_number' ) ? $o->get_meta( '_shipping_number' ) : $o->get_meta( '_billing_number' ),
		$o->get_shipping_address_2() ? $o->get_shipping_address_2() : $o->get_billing_address_2(),
		$o->get_meta( '_shipping_neighborhood' ) ? $o->get_meta( '_shipping_neighborhood' ) : $o->get_meta( '_billing_neighborhood' ),
		( $o->get_shipping_city() ? $o->get_shipping_city() : $o->get_billing_city() ) . ' / ' . ( $o->get_shipping_state() ? $o->get_shipping_state() : $o->get_billing_state() ),
		$o->get_shipping_postcode() ? $o->get_shipping_postcode() : $o->get_billing_postcode(),
	) ) ) );
	$texto = '🛍 Pedido #' . $o->get_order_number() . " pago\n\n" .
		'Cliente: ' . $o->get_formatted_billing_full_name() . "\n" .
		'WhatsApp: ' . $o->get_billing_phone() . "\n" .
		'E-mail: ' . $o->get_billing_email() . "\n\n" .
		implode( "\n", $itens ) . "\n\n" .
		'Entrega: ' . $o->get_shipping_method() . "\n" .
		'Endereço: ' . $endereco . "\n" .
		'Total: ' . wp_strip_all_tags( wc_price( $o->get_total(), array( 'currency' => $o->get_currency() ) ) ) . "\n\n" .
		$o->get_edit_order_url();
	cc_whatsapp( html_entity_decode( $texto, ENT_QUOTES, 'UTF-8' ), (array) CC_WHATSAPP_PEDIDOS );
	$o->update_meta_data( '_cc_wa_avisado', current_time( 'mysql' ) );
	$o->save();
}
