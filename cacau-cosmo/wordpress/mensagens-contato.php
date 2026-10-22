<?php
/**
 * Cacau Cosmo — Mensagens do Contato no painel do WordPress
 *
 * O que faz:
 *  - cria o menu "Mensagens" no wp-admin, com contador de mensagens novas;
 *  - recebe o formulário da página /contato/ em /wp-json/cacau/v1/contato;
 *  - guarda cada mensagem (nome, e-mail, WhatsApp, cidade, assunto, texto);
 *  - envia uma cópia por e-mail (responder ao e-mail responde ao cliente);
 *  - exporta tudo em CSV (botão "Exportar CSV" na lista).
 *
 * Como instalar: plugin WPCode → Code Snippets → Add Snippet →
 * "Add Your Custom Code" → tipo PHP Snippet → cole este arquivo SEM a
 * primeira linha "<?php" → Insert Method: Auto Insert / Run Everywhere →
 * ative e salve.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* E-mail que recebe a cópia de cada mensagem. Vazio = só fica no painel. */
if ( ! defined( 'CC_CONTATO_EMAIL' ) ) {
	define( 'CC_CONTATO_EMAIL', 'ola@cacaucosmo.com.br' );
}

if ( ! defined( 'CC_MSG_CPT' ) ) {
	define( 'CC_MSG_CPT', 'cc_mensagem' );
	define( 'CC_MSG_FIELDS', array(
		'nome'     => 'Nome',
		'email'    => 'E-mail',
		'telefone' => 'WhatsApp',
		'cidade'   => 'Cidade',
		'assunto'  => 'Assunto',
	) );
}

/* ===== TIPO DE CONTEUDO "MENSAGENS" ========================= */
add_action( 'init', function () {
	register_post_type( CC_MSG_CPT, array(
		'labels'          => array(
			'name'               => 'Mensagens',
			'singular_name'      => 'Mensagem',
			'menu_name'          => 'Mensagens',
			'all_items'          => 'Todas as mensagens',
			'edit_item'          => 'Mensagem',
			'search_items'       => 'Buscar mensagens',
			'not_found'          => 'Nenhuma mensagem ainda.',
			'not_found_in_trash' => 'Nenhuma mensagem na lixeira.',
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'show_in_rest'    => false,
		'menu_position'   => 26,
		'menu_icon'       => 'dashicons-email-alt',
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
		'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'    => true,
	) );
} );

/* ===== RECEBER O FORMULARIO ================================= */
add_action( 'rest_api_init', function () {
	register_rest_route( 'cacau/v1', '/contato', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'cc_msg_receber',
	) );
} );

function cc_msg_erro( $texto, $status = 400 ) {
	return new WP_REST_Response( array( 'ok' => false, 'message' => $texto ), $status );
}

function cc_msg_receber( WP_REST_Request $req ) {
	/* robo: campo escondido preenchido -> finge sucesso e descarta */
	if ( '' !== trim( (string) $req->get_param( '_gotcha' ) ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/* no maximo 5 envios a cada 10 minutos por IP */
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$chave = 'cc_msg_' . md5( $ip );
	$vezes = (int) get_transient( $chave );
	if ( $vezes >= 5 ) {
		return cc_msg_erro( 'Muitas mensagens em pouco tempo. Tente de novo em alguns minutos.', 429 );
	}

	$d = array(
		'nome'     => sanitize_text_field( (string) $req->get_param( 'nome' ) ),
		'email'    => sanitize_email( (string) $req->get_param( 'email' ) ),
		'telefone' => sanitize_text_field( (string) $req->get_param( 'telefone' ) ),
		'cidade'   => sanitize_text_field( (string) $req->get_param( 'cidade' ) ),
		'assunto'  => sanitize_text_field( (string) $req->get_param( 'assunto' ) ),
	);
	$mensagem = sanitize_textarea_field( (string) $req->get_param( 'mensagem' ) );

	if ( '' === $d['nome'] || ! is_email( $d['email'] ) || '' === $mensagem ) {
		return cc_msg_erro( 'Preencha nome, e-mail válido e mensagem.' );
	}
	if ( ! $req->get_param( 'consentimento' ) ) {
		return cc_msg_erro( 'É preciso aceitar a Política de Privacidade.' );
	}

	$d['nome']     = mb_substr( $d['nome'], 0, 120 );
	$d['telefone'] = mb_substr( $d['telefone'], 0, 40 );
	$d['cidade']   = mb_substr( $d['cidade'], 0, 120 );
	$d['assunto']  = mb_substr( $d['assunto'] ? $d['assunto'] : 'Contato', 0, 80 );
	$mensagem      = mb_substr( $mensagem, 0, 5000 );

	$id = wp_insert_post( array(
		'post_type'    => CC_MSG_CPT,
		'post_status'  => 'private',
		'post_title'   => $d['assunto'] . ' — ' . $d['nome'],
		'post_content' => $mensagem,
	), true );
	if ( is_wp_error( $id ) ) {
		return cc_msg_erro( 'Não foi possível salvar a mensagem agora.', 500 );
	}
	foreach ( $d as $k => $v ) {
		update_post_meta( $id, '_cc_' . $k, $v );
	}
	update_post_meta( $id, '_cc_ip', $ip );

	set_transient( $chave, $vezes + 1, 10 * MINUTE_IN_SECONDS );

	if ( CC_CONTATO_EMAIL ) {
		$corpo = '';
		foreach ( CC_MSG_FIELDS as $k => $rotulo ) {
			if ( '' !== $d[ $k ] ) {
				$corpo .= $rotulo . ': ' . $d[ $k ] . "\n";
			}
		}
		$corpo .= "\n" . $mensagem . "\n\n—\nVer no painel: " . admin_url( 'post.php?post=' . $id . '&action=edit' );
		wp_mail(
			CC_CONTATO_EMAIL,
			'[Site] ' . $d['assunto'] . ' — ' . $d['nome'],
			$corpo,
			array( 'Reply-To: ' . $d['nome'] . ' <' . $d['email'] . '>' )
		);
	}

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/* ===== LISTA NO PAINEL ====================================== */
add_filter( 'manage_' . CC_MSG_CPT . '_posts_columns', function () {
	return array(
		'cb'         => '<input type="checkbox">',
		'title'      => 'Mensagem',
		'cc_nome'    => 'Nome',
		'cc_email'   => 'E-mail',
		'cc_tel'     => 'WhatsApp',
		'cc_assunto' => 'Assunto',
		'cc_data'    => 'Recebida em',
	);
} );

add_action( 'manage_' . CC_MSG_CPT . '_posts_custom_column', function ( $col, $id ) {
	switch ( $col ) {
		case 'cc_nome':
			echo esc_html( get_post_meta( $id, '_cc_nome', true ) );
			if ( ! get_post_meta( $id, '_cc_lida', true ) ) {
				echo ' <span style="background:#D293A8;color:#00062E;border-radius:10px;padding:1px 8px;font-size:11px;font-weight:600">nova</span>';
			}
			break;
		case 'cc_email':
			$e = get_post_meta( $id, '_cc_email', true );
			echo '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>';
			break;
		case 'cc_tel':
			echo esc_html( get_post_meta( $id, '_cc_telefone', true ) );
			break;
		case 'cc_assunto':
			echo esc_html( get_post_meta( $id, '_cc_assunto', true ) );
			break;
		case 'cc_data':
			echo esc_html( get_the_date( 'd/m/Y H:i', $id ) );
			break;
	}
}, 10, 2 );

/* Mostra as mensagens privadas na aba "Todas" */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() && $q->is_main_query() && CC_MSG_CPT === $q->get( 'post_type' ) && ! $q->get( 'post_status' ) ) {
		$q->set( 'post_status', array( 'private' ) );
	}
} );

/* Contador de mensagens novas no menu */
add_action( 'admin_menu', function () {
	global $menu;
	$novas = get_posts( array(
		'post_type'      => CC_MSG_CPT,
		'post_status'    => 'private',
		'fields'         => 'ids',
		'posts_per_page' => 99,
		'meta_query'     => array( array( 'key' => '_cc_lida', 'compare' => 'NOT EXISTS' ) ),
	) );
	$n = count( $novas );
	if ( ! $n ) {
		return;
	}
	foreach ( $menu as $i => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=' . CC_MSG_CPT === $item[2] ) {
			$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $n . '</span></span>';
		}
	}
}, 99 );

/* ===== TELA DA MENSAGEM ===================================== */
add_action( 'add_meta_boxes_' . CC_MSG_CPT, function ( $post ) {
	update_post_meta( $post->ID, '_cc_lida', 1 );
	remove_meta_box( 'submitdiv', CC_MSG_CPT, 'side' );
	add_meta_box( 'cc_msg_detalhes', 'Mensagem recebida', 'cc_msg_box', CC_MSG_CPT, 'normal', 'high' );
	add_meta_box( 'cc_msg_acoes', 'Ações', 'cc_msg_acoes', CC_MSG_CPT, 'side', 'high' );
} );

function cc_msg_box( $post ) {
	echo '<table class="widefat striped" style="margin-bottom:16px"><tbody>';
	foreach ( CC_MSG_FIELDS as $k => $rotulo ) {
		$v = get_post_meta( $post->ID, '_cc_' . $k, true );
		if ( 'email' === $k ) {
			$v = '<a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>';
		} elseif ( 'telefone' === $k && $v ) {
			$num = preg_replace( '/\D/', '', $v );
			if ( strlen( $num ) <= 11 ) {
				$num = '55' . $num;
			}
			$v = esc_html( $v ) . ' &nbsp;<a href="https://wa.me/' . esc_attr( $num ) . '" target="_blank" rel="noopener">abrir no WhatsApp ↗</a>';
		} else {
			$v = esc_html( $v );
		}
		echo '<tr><th style="width:140px"><strong>' . esc_html( $rotulo ) . '</strong></th><td>' . $v . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<tr><th><strong>Recebida em</strong></th><td>' . esc_html( get_the_date( 'd/m/Y \à\s H:i', $post ) ) . '</td></tr>';
	echo '</tbody></table>';
	echo '<div style="background:#F1E8D0;border-left:3px solid #C9A84C;padding:16px 20px;font-size:14px;line-height:1.7">' . nl2br( esc_html( $post->post_content ) ) . '</div>';
}

function cc_msg_acoes( $post ) {
	$email   = get_post_meta( $post->ID, '_cc_email', true );
	$assunto = rawurlencode( 'Re: ' . get_post_meta( $post->ID, '_cc_assunto', true ) . ' — Cacau Cosmo' );
	echo '<p><a class="button button-primary" style="width:100%;text-align:center" href="mailto:' . esc_attr( $email ) . '?subject=' . esc_attr( $assunto ) . '">Responder por e-mail</a></p>';
	echo '<p><a class="button" style="width:100%;text-align:center" href="' . esc_url( admin_url( 'edit.php?post_type=' . CC_MSG_CPT ) ) . '">← Voltar para a lista</a></p>';
	if ( current_user_can( 'delete_post', $post->ID ) ) {
		echo '<p><a style="color:#b32d2e" href="' . esc_url( get_delete_post_link( $post->ID ) ) . '">Mover para a lixeira</a></p>';
	}
}

/* ===== EXPORTAR CSV ========================================= */
add_action( 'manage_posts_extra_tablenav', function ( $which ) {
	if ( 'top' !== $which || CC_MSG_CPT !== get_current_screen()->post_type ) {
		return;
	}
	$url = wp_nonce_url( admin_url( 'admin-post.php?action=cc_msg_csv' ), 'cc_msg_csv' );
	echo '<div class="alignleft actions"><a class="button" href="' . esc_url( $url ) . '">Exportar CSV</a></div>';
} );

add_action( 'admin_post_cc_msg_csv', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'cc_msg_csv' ) ) {
		wp_die( 'Sem permissão.' );
	}
	$ids = get_posts( array(
		'post_type'      => CC_MSG_CPT,
		'post_status'    => 'private',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=mensagens-cacau-cosmo-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // acentos certos no Excel
	fputcsv( $out, array_merge( array( 'Data' ), array_values( CC_MSG_FIELDS ), array( 'Mensagem' ) ), ';' );
	foreach ( $ids as $id ) {
		$linha = array( get_the_date( 'd/m/Y H:i', $id ) );
		foreach ( array_keys( CC_MSG_FIELDS ) as $k ) {
			$linha[] = get_post_meta( $id, '_cc_' . $k, true );
		}
		$linha[] = get_post_field( 'post_content', $id );
		/* evita que o Excel execute formulas vindas do formulario */
		$linha = array_map( function ( $v ) {
			return preg_match( '/^[=+\-@]/', (string) $v ) ? "'" . $v : $v;
		}, $linha );
		fputcsv( $out, $linha, ';' );
	}
	fclose( $out );
	exit;
} );
