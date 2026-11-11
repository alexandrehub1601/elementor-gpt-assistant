<?php
/**
 * Plugin Name:       Auto Tweet de Posts
 * Description:       Publica automaticamente no X (Twitter) cada post publicado no WordPress, com a imagem destacada, o título e o link. O texto do tweet pode ser personalizado por post.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            HUB1601
 * License:           GPL-2.0-or-later
 * Text Domain:       auto-tweet-posts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ATP_Auto_Tweet {

	const OPTION        = 'atp_settings';
	const LOG_OPTION    = 'atp_log';
	const CRON_HOOK     = 'atp_send_tweet';
	const META_SHARE    = '_atp_share';
	const META_TEXT     = '_atp_text';
	const META_TWEET_ID = '_atp_tweet_id';
	const META_SENT_AT  = '_atp_sent_at';
	const META_ERROR    = '_atp_error';
	const MAX_LENGTH    = 280;
	const URL_LENGTH    = 23; // O X conta todo link como 23 caracteres.
	const MAX_IMAGE     = 5242880; // 5 MB, limite do X para imagens.

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ), 10, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_status_change' ), 10, 3 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_send' ) );
		add_action( 'admin_post_atp_send_now', array( __CLASS__, 'handle_send_now' ) );
		add_action( 'admin_post_atp_test', array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'action_links' ) );
	}

	/* ---------------------------------------------------------------------
	 * Configurações
	 * ------------------------------------------------------------------ */

	public static function defaults() {
		return array(
			'api_key'       => '',
			'api_secret'    => '',
			'access_token'  => '',
			'access_secret' => '',
			'template'      => "{title}\n\n{url}",
			'post_types'    => array( 'post' ),
			'default_share' => 1,
			'with_image'    => 1,
			'delay'         => 20,
		);
	}

	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function has_credentials() {
		$s = self::settings();
		return '' !== $s['api_key'] && '' !== $s['api_secret'] && '' !== $s['access_token'] && '' !== $s['access_secret'];
	}

	public static function admin_menu() {
		add_options_page( 'Auto Tweet', 'Auto Tweet (X)', 'manage_options', 'auto-tweet-posts', array( __CLASS__, 'render_settings_page' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=auto-tweet-posts' ) ) . '">Configurações</a>' );
		return $links;
	}

	public static function register_settings() {
		register_setting(
			'atp_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);
	}

	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = self::defaults();

		foreach ( array( 'api_key', 'api_secret', 'access_token', 'access_secret' ) as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $input[ $key ] ) ) ) : '';
		}

		$template        = isset( $input['template'] ) ? sanitize_textarea_field( wp_unslash( $input['template'] ) ) : '';
		$out['template'] = '' !== trim( $template ) ? $template : self::defaults()['template'];

		$types = isset( $input['post_types'] ) && is_array( $input['post_types'] ) ? array_map( 'sanitize_key', $input['post_types'] ) : array();
		$out['post_types'] = array_values( array_filter( $types, 'post_type_exists' ) );

		$out['default_share'] = empty( $input['default_share'] ) ? 0 : 1;
		$out['with_image']    = empty( $input['with_image'] ) ? 0 : 1;
		$out['delay']         = isset( $input['delay'] ) ? max( 5, min( 600, absint( $input['delay'] ) ) ) : 20;

		return $out;
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = self::settings();
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		$log   = get_option( self::LOG_OPTION, array() );
		?>
		<div class="wrap">
			<h1>Auto Tweet de Posts (X / Twitter)</h1>
			<p>Quando um post é publicado, ele é enviado ao X com a imagem destacada e o texto definido abaixo (ou o texto personalizado no próprio post).</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'atp_settings_group' ); ?>

				<h2>Credenciais da API do X</h2>
				<p class="description">
					Crie um app em <a href="https://developer.x.com/en/portal/dashboard" target="_blank" rel="noopener">developer.x.com</a>,
					em <em>User authentication settings</em> defina a permissão como <strong>Read and write</strong> e depois gere
					<em>API Key and Secret</em> e <em>Access Token and Secret</em> (gere o Access Token <strong>depois</strong> de mudar a permissão).
				</p>
				<table class="form-table" role="presentation">
					<?php
					$fields = array(
						'api_key'       => 'API Key (Consumer Key)',
						'api_secret'    => 'API Key Secret (Consumer Secret)',
						'access_token'  => 'Access Token',
						'access_secret' => 'Access Token Secret',
					);
					foreach ( $fields as $key => $label ) :
						?>
						<tr>
							<th scope="row"><label for="atp_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input type="password" autocomplete="off" class="regular-text" id="atp_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>"></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2>Tweet</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="atp_template">Modelo padrão do tweet</label></th>
						<td>
							<textarea id="atp_template" name="<?php echo esc_attr( self::OPTION ); ?>[template]" rows="5" class="large-text"><?php echo esc_textarea( $s['template'] ); ?></textarea>
							<p class="description">
								Variáveis: <code>{title}</code> título, <code>{url}</code> link do post, <code>{excerpt}</code> resumo,
								<code>{tags}</code> tags como #hashtags, <code>{categories}</code> categorias como #hashtags,
								<code>{author}</code> autor, <code>{site}</code> nome do site.<br>
								Limite de 280 caracteres (todo link conta como 23). Se passar, o resumo e depois o título são encurtados.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Tipos de conteúdo</th>
						<td>
							<?php foreach ( $types as $type ) : ?>
								<label style="display:block">
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[post_types][]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $s['post_types'], true ) ); ?>>
									<?php echo esc_html( $type->labels->name ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">Opções</th>
						<td>
							<label style="display:block">
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[default_share]" value="1" <?php checked( $s['default_share'] ); ?>>
								Marcar "Publicar no X" por padrão em todo post novo
							</label>
							<label style="display:block">
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[with_image]" value="1" <?php checked( $s['with_image'] ); ?>>
								Anexar a imagem destacada (thumb) no tweet
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="atp_delay">Atraso do envio (segundos)</label></th>
						<td>
							<input type="number" min="5" max="600" id="atp_delay" name="<?php echo esc_attr( self::OPTION ); ?>[delay]" value="<?php echo esc_attr( $s['delay'] ); ?>" class="small-text">
							<p class="description">Espera um pouco depois de publicar para o WordPress terminar de salvar a imagem e os campos do post.</p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Salvar configurações' ); ?>
			</form>

			<hr>
			<h2>Testar conexão</h2>
			<p>Salve as credenciais e clique para verificar se o WordPress consegue falar com a sua conta do X.</p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=atp_test' ), 'atp_test' ) ); ?>">Testar conexão com o X</a>

			<hr>
			<h2>Últimos envios</h2>
			<?php if ( empty( $log ) ) : ?>
				<p>Nenhum envio ainda.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th>Data</th><th>Post</th><th>Resultado</th></tr></thead>
					<tbody>
					<?php foreach ( array_reverse( $log ) as $row ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'd/m/Y H:i', $row['time'] ) ); ?></td>
							<td><?php echo $row['post'] ? '<a href="' . esc_url( get_edit_post_link( $row['post'] ) ) . '">' . esc_html( get_the_title( $row['post'] ) ) . '</a>' : '—'; ?></td>
							<td><?php echo $row['ok'] ? '✅ ' : '❌ '; ?><?php echo wp_kses( $row['msg'], array( 'a' => array( 'href' => array(), 'target' => array() ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Caixa no editor do post
	 * ------------------------------------------------------------------ */

	public static function add_meta_box() {
		foreach ( self::settings()['post_types'] as $type ) {
			add_meta_box( 'atp_box', 'Publicar no X (Twitter)', array( __CLASS__, 'render_meta_box' ), $type, 'side', 'high' );
		}
	}

	public static function render_meta_box( $post ) {
		$s        = self::settings();
		$share    = get_post_meta( $post->ID, self::META_SHARE, true );
		$share    = '' === $share ? (int) $s['default_share'] : (int) $share;
		$text     = get_post_meta( $post->ID, self::META_TEXT, true );
		$tweet_id = get_post_meta( $post->ID, self::META_TWEET_ID, true );
		$sent_at  = get_post_meta( $post->ID, self::META_SENT_AT, true );
		$error    = get_post_meta( $post->ID, self::META_ERROR, true );

		wp_nonce_field( 'atp_save_' . $post->ID, 'atp_nonce' );

		if ( ! self::has_credentials() ) {
			echo '<p style="color:#b32d2e">Configure as credenciais em <a href="' . esc_url( admin_url( 'options-general.php?page=auto-tweet-posts' ) ) . '">Configurações › Auto Tweet</a>.</p>';
		}

		if ( $tweet_id ) {
			echo '<p>✅ Publicado no X' . ( $sent_at ? ' em ' . esc_html( wp_date( 'd/m/Y H:i', (int) $sent_at ) ) : '' ) . '.<br><a href="' . esc_url( self::tweet_url( $tweet_id ) ) . '" target="_blank" rel="noopener">Ver tweet</a></p>';
		} elseif ( $error ) {
			echo '<p style="color:#b32d2e">❌ Erro no último envio: ' . esc_html( $error ) . '</p>';
		}
		?>
		<p>
			<label>
				<input type="checkbox" name="atp_share" value="1" <?php checked( $share ); ?>>
				<?php echo $tweet_id ? 'Já enviado (não envia de novo automaticamente)' : 'Publicar no X ao publicar o post'; ?>
			</label>
		</p>
		<p>
			<label for="atp_text"><strong>Texto do tweet</strong> (opcional)</label>
			<textarea id="atp_text" name="atp_text" rows="5" style="width:100%" placeholder="<?php echo esc_attr( $s['template'] ); ?>"><?php echo esc_textarea( $text ); ?></textarea>
			<span class="description">Em branco usa o modelo padrão. Aceita <code>{title}</code>, <code>{url}</code>, <code>{excerpt}</code>, <code>{tags}</code>… Se não tiver <code>{url}</code>, o link é adicionado no final.</span>
		</p>
		<?php
		if ( 'publish' === $post->post_status ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=atp_send_now&post=' . $post->ID ), 'atp_send_now_' . $post->ID );
			echo '<p><a class="button" href="' . esc_url( $url ) . '" onclick="return confirm(\'Salve o post antes se alterou o texto. Enviar agora para o X?\')">' . ( $tweet_id ? 'Enviar de novo agora' : 'Enviar agora para o X' ) . '</a></p>';
		}
	}

	public static function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['atp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atp_nonce'] ) ), 'atp_save_' . $post_id ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, self::META_SHARE, empty( $_POST['atp_share'] ) ? '0' : '1' );
		$text = isset( $_POST['atp_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['atp_text'] ) ) : '';
		if ( '' === trim( $text ) ) {
			delete_post_meta( $post_id, self::META_TEXT );
		} else {
			update_post_meta( $post_id, self::META_TEXT, $text );
		}
	}

	/* ---------------------------------------------------------------------
	 * Disparo automático
	 * ------------------------------------------------------------------ */

	public static function on_status_change( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( wp_is_post_revision( $post ) || ! in_array( $post->post_type, self::settings()['post_types'], true ) ) {
			return;
		}
		if ( get_post_meta( $post->ID, self::META_TWEET_ID, true ) ) {
			return;
		}
		// Agendado para daqui a alguns segundos: no editor de blocos os campos
		// do box e a imagem destacada são salvos numa requisição separada.
		if ( ! wp_next_scheduled( self::CRON_HOOK, array( $post->ID ) ) ) {
			wp_schedule_single_event( time() + (int) self::settings()['delay'], self::CRON_HOOK, array( $post->ID ) );
		}
	}

	public static function cron_send( $post_id ) {
		self::send( (int) $post_id, false );
	}

	public static function handle_send_now() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'atp_send_now_' . $post_id );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( 'Sem permissão.' );
		}
		$result = self::send( $post_id, true );
		$back   = add_query_arg(
			array(
				'atp_msg' => is_wp_error( $result ) ? 'error' : 'sent',
			),
			get_edit_post_link( $post_id, 'url' )
		);
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Envia o post para o X.
	 *
	 * @param int  $post_id ID do post.
	 * @param bool $manual  true quando o envio foi pedido pelo botão (ignora o checkbox e permite reenviar).
	 * @return string|WP_Error ID do tweet ou erro.
	 */
	public static function send( $post_id, $manual ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return new WP_Error( 'atp_not_published', 'O post não está publicado.' );
		}

		if ( ! $manual ) {
			if ( get_post_meta( $post_id, self::META_TWEET_ID, true ) ) {
				return new WP_Error( 'atp_already', 'Já enviado.' );
			}
			$share = get_post_meta( $post_id, self::META_SHARE, true );
			$share = '' === $share ? (int) self::settings()['default_share'] : (int) $share;
			if ( ! $share ) {
				return new WP_Error( 'atp_disabled', 'Envio desmarcado neste post.' );
			}
		}

		// Trava simples contra envio duplicado.
		$lock = 'atp_lock_' . $post_id;
		if ( get_transient( $lock ) ) {
			return new WP_Error( 'atp_locked', 'Envio já em andamento.' );
		}
		set_transient( $lock, 1, 2 * MINUTE_IN_SECONDS );

		$result = self::publish_tweet( $post );

		delete_transient( $lock );

		if ( is_wp_error( $result ) ) {
			update_post_meta( $post_id, self::META_ERROR, $result->get_error_message() );
			self::log( $post_id, false, $result->get_error_message() );
			return $result;
		}

		update_post_meta( $post_id, self::META_TWEET_ID, $result );
		update_post_meta( $post_id, self::META_SENT_AT, time() );
		delete_post_meta( $post_id, self::META_ERROR );
		self::log( $post_id, true, '<a href="' . esc_url( self::tweet_url( $result ) ) . '" target="_blank">Ver tweet</a>' );
		return $result;
	}

	private static function publish_tweet( $post ) {
		if ( ! self::has_credentials() ) {
			return new WP_Error( 'atp_no_credentials', 'Credenciais da API do X não configuradas.' );
		}

		$payload = array( 'text' => self::build_text( $post ) );

		if ( self::settings()['with_image'] && has_post_thumbnail( $post ) ) {
			$media_id = self::upload_image( get_post_thumbnail_id( $post ) );
			if ( is_wp_error( $media_id ) ) {
				// Não deixa de publicar por causa da imagem; registra o aviso.
				self::log( $post->ID, false, 'Imagem não enviada (tweet segue sem imagem): ' . $media_id->get_error_message() );
			} else {
				$payload['media'] = array( 'media_ids' => array( (string) $media_id ) );
			}
		}

		$response = self::request( 'POST', 'https://api.x.com/2/tweets', wp_json_encode( $payload ), 'application/json' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( empty( $response['data']['id'] ) ) {
			return new WP_Error( 'atp_bad_response', 'Resposta inesperada do X.' );
		}
		return (string) $response['data']['id'];
	}

	/* ---------------------------------------------------------------------
	 * Texto do tweet
	 * ------------------------------------------------------------------ */

	public static function build_text( $post ) {
		$custom   = trim( (string) get_post_meta( $post->ID, self::META_TEXT, true ) );
		$template = '' !== $custom ? $custom : self::settings()['template'];

		// Garante o link do post quando o texto personalizado não tem {url}.
		if ( false === strpos( $template, '{url}' ) && ! preg_match( '~https?://~i', $template ) ) {
			$template .= "\n\n{url}";
		}

		$vars = array(
			'{title}'      => self::plain( get_the_title( $post ) ),
			'{url}'        => get_permalink( $post ),
			'{excerpt}'    => self::excerpt( $post ),
			'{tags}'       => self::hashtags( get_the_tags( $post->ID ) ),
			'{categories}' => self::hashtags( get_the_category( $post->ID ) ),
			'{author}'     => self::plain( get_the_author_meta( 'display_name', $post->post_author ) ),
			'{site}'       => self::plain( get_bloginfo( 'name' ) ),
		);
		$vars = apply_filters( 'atp_tweet_vars', $vars, $post );

		$text = self::render( $template, $vars );

		// Encurta resumo e depois título se passar do limite.
		foreach ( array( '{excerpt}', '{title}' ) as $ph ) {
			$over = self::weighted_length( $text ) - self::MAX_LENGTH;
			if ( $over <= 0 ) {
				break;
			}
			if ( false === strpos( $template, $ph ) || '' === $vars[ $ph ] ) {
				continue;
			}
			$keep         = mb_strlen( $vars[ $ph ], 'UTF-8' ) - $over - 1;
			$vars[ $ph ]  = $keep > 0 ? rtrim( mb_substr( $vars[ $ph ], 0, $keep, 'UTF-8' ) ) . '…' : '';
			$text         = self::render( $template, $vars );
		}

		// Último recurso: corta o texto mantendo os links.
		if ( self::weighted_length( $text ) > self::MAX_LENGTH ) {
			preg_match_all( '~https?://\S+~iu', $text, $m );
			$urls  = $m[0];
			$plain = trim( preg_replace( '~https?://\S+~iu', '', $text ) );
			$room  = self::MAX_LENGTH - count( $urls ) * ( self::URL_LENGTH + 1 ) - 1;
			$plain = $room > 0 ? rtrim( mb_substr( $plain, 0, $room, 'UTF-8' ) ) . '…' : '';
			$text  = trim( $plain . ( $urls ? ' ' . implode( ' ', $urls ) : '' ) );
		}

		return apply_filters( 'atp_tweet_text', $text, $post );
	}

	private static function render( $template, $vars ) {
		$text = strtr( $template, $vars );
		$text = preg_replace( '/[ \t]{2,}/', ' ', $text );     // espaços duplos de variáveis vazias
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );     // linhas em branco em excesso
		return trim( $text );
	}

	public static function weighted_length( $text ) {
		$links = preg_match_all( '~https?://\S+~iu', $text );
		$rest  = preg_replace( '~https?://\S+~iu', '', $text );
		return $links * self::URL_LENGTH + mb_strlen( $rest, 'UTF-8' );
	}

	private static function plain( $text ) {
		return trim( html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) );
	}

	private static function excerpt( $post ) {
		$raw = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( strip_shortcodes( $post->post_content ), 30, '…' );
		return self::plain( preg_replace( '/\s+/', ' ', $raw ) );
	}

	private static function hashtags( $terms ) {
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		$tags = array();
		foreach ( $terms as $term ) {
			if ( 'uncategorized' === $term->slug || 'sem-categoria' === $term->slug ) {
				continue;
			}
			$tag = preg_replace( '/[^\p{L}\p{N}_]/u', '', self::plain( $term->name ) );
			if ( '' !== $tag ) {
				$tags[] = '#' . $tag;
			}
		}
		return implode( ' ', array_unique( $tags ) );
	}

	/* ---------------------------------------------------------------------
	 * Imagem
	 * ------------------------------------------------------------------ */

	private static function upload_image( $attachment_id ) {
		$path = self::image_path( $attachment_id );
		if ( ! $path ) {
			return new WP_Error( 'atp_no_image', 'Arquivo da imagem destacada não encontrado ou maior que 5 MB.' );
		}
		$mime = wp_check_filetype( $path )['type'];
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) {
			return new WP_Error( 'atp_bad_image', 'Formato de imagem não aceito pelo X: ' . ( $mime ? $mime : 'desconhecido' ) );
		}
		$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $data ) {
			return new WP_Error( 'atp_read_image', 'Não foi possível ler a imagem.' );
		}

		$boundary = 'atp' . wp_generate_password( 24, false, false );
		$body     = '--' . $boundary . "\r\n"
			. "Content-Disposition: form-data; name=\"media_category\"\r\n\r\n"
			. "tweet_image\r\n"
			. '--' . $boundary . "\r\n"
			. 'Content-Disposition: form-data; name="media"; filename="' . basename( $path ) . "\"\r\n"
			. 'Content-Type: ' . $mime . "\r\n\r\n"
			. $data . "\r\n"
			. '--' . $boundary . "--\r\n";
		$type = 'multipart/form-data; boundary=' . $boundary;

		// Endpoint atual (v2).
		$response = self::request( 'POST', 'https://api.x.com/2/media/upload', $body, $type );
		if ( ! is_wp_error( $response ) && ! empty( $response['data']['id'] ) ) {
			return (string) $response['data']['id'];
		}

		// Fallback para o endpoint antigo (v1.1), caso a conta ainda use.
		$legacy = self::request( 'POST', 'https://upload.twitter.com/1.1/media/upload.json', $body, $type );
		if ( ! is_wp_error( $legacy ) && ! empty( $legacy['media_id_string'] ) ) {
			return (string) $legacy['media_id_string'];
		}

		return is_wp_error( $response ) ? $response : new WP_Error( 'atp_upload', 'Falha no upload da imagem.' );
	}

	private static function image_path( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( $file && file_exists( $file ) && filesize( $file ) <= self::MAX_IMAGE ) {
			return $file;
		}
		// Original muito grande (ou ausente): tenta tamanhos menores gerados pelo WP.
		$uploads = wp_get_upload_dir();
		foreach ( array( '2048x2048', '1536x1536', 'large', 'medium_large' ) as $size ) {
			$info = image_get_intermediate_size( $attachment_id, $size );
			if ( ! $info || empty( $info['path'] ) ) {
				continue;
			}
			$candidate = trailingslashit( $uploads['basedir'] ) . $info['path'];
			if ( file_exists( $candidate ) && filesize( $candidate ) <= self::MAX_IMAGE ) {
				return $candidate;
			}
		}
		return false;
	}

	/* ---------------------------------------------------------------------
	 * API do X (OAuth 1.0a, contexto de usuário)
	 * ------------------------------------------------------------------ */

	private static function request( $method, $url, $body = null, $content_type = null ) {
		$headers = array( 'Authorization' => self::oauth_header( $method, $url ) );
		if ( $content_type ) {
			$headers['Content-Type'] = $content_type;
		}
		$response = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => $body,
				'timeout' => 45,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'atp_http', 'Falha de conexão com o X: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$json = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'atp_api', 'X respondeu ' . $code . ': ' . self::api_error( $json, $raw ) );
		}
		return is_array( $json ) ? $json : array();
	}

	private static function api_error( $json, $raw ) {
		if ( is_array( $json ) ) {
			if ( ! empty( $json['detail'] ) ) {
				return $json['detail'];
			}
			if ( ! empty( $json['errors'][0]['message'] ) ) {
				return $json['errors'][0]['message'];
			}
			if ( ! empty( $json['title'] ) ) {
				return $json['title'];
			}
		}
		return mb_substr( wp_strip_all_tags( (string) $raw ), 0, 200, 'UTF-8' );
	}

	private static function oauth_header( $method, $url ) {
		$s     = self::settings();
		$oauth = array(
			'oauth_consumer_key'     => $s['api_key'],
			'oauth_nonce'            => wp_generate_password( 32, false, false ),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp'        => (string) time(),
			'oauth_token'            => $s['access_token'],
			'oauth_version'          => '1.0',
		);

		// Parâmetros da query string entram na assinatura (corpo JSON/multipart não).
		$params = $oauth;
		$parts  = wp_parse_url( $url );
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
			$params = array_merge( $params, $query );
		}
		$base_url = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['path'] ) ? $parts['path'] : '' );

		$encoded = array();
		foreach ( $params as $k => $v ) {
			$encoded[ rawurlencode( $k ) ] = rawurlencode( (string) $v );
		}
		ksort( $encoded, SORT_STRING );
		$pairs = array();
		foreach ( $encoded as $k => $v ) {
			$pairs[] = $k . '=' . $v;
		}

		$base_string = strtoupper( $method ) . '&' . rawurlencode( $base_url ) . '&' . rawurlencode( implode( '&', $pairs ) );
		$key         = rawurlencode( $s['api_secret'] ) . '&' . rawurlencode( $s['access_secret'] );

		$oauth['oauth_signature'] = base64_encode( hash_hmac( 'sha1', $base_string, $key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

		$header = array();
		foreach ( $oauth as $k => $v ) {
			$header[] = rawurlencode( $k ) . '="' . rawurlencode( $v ) . '"';
		}
		return 'OAuth ' . implode( ', ', $header );
	}

	public static function handle_test() {
		check_admin_referer( 'atp_test' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}
		if ( ! self::has_credentials() ) {
			$msg = 'Preencha e salve as 4 credenciais primeiro.';
			$ok  = 0;
		} else {
			$r = self::request( 'GET', 'https://api.x.com/2/users/me' );
			if ( is_wp_error( $r ) ) {
				$msg = $r->get_error_message();
				$ok  = 0;
			} else {
				$msg = 'Conectado como @' . ( isset( $r['data']['username'] ) ? $r['data']['username'] : '?' );
				$ok  = 1;
			}
		}
		set_transient( 'atp_test_result_' . get_current_user_id(), array( 'ok' => $ok, 'msg' => $msg ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'options-general.php?page=auto-tweet-posts' ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Avisos e log
	 * ------------------------------------------------------------------ */

	public static function admin_notices() {
		$test = get_transient( 'atp_test_result_' . get_current_user_id() );
		if ( $test ) {
			delete_transient( 'atp_test_result_' . get_current_user_id() );
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $test['ok'] ? 'success' : 'error', esc_html( $test['msg'] ) );
		}
		if ( isset( $_GET['atp_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$sent = 'sent' === $_GET['atp_msg']; // phpcs:ignore WordPress.Security.NonceVerification
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				$sent ? 'success' : 'error',
				$sent ? 'Post enviado para o X.' : 'Não foi possível enviar para o X. Veja o erro na caixa "Publicar no X".'
			);
		}
	}

	private static function log( $post_id, $ok, $msg ) {
		$log   = get_option( self::LOG_OPTION, array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array(
			'time' => time(),
			'post' => (int) $post_id,
			'ok'   => (bool) $ok,
			'msg'  => $msg,
		);
		update_option( self::LOG_OPTION, array_slice( $log, -30 ), false );
	}

	private static function tweet_url( $id ) {
		return 'https://x.com/i/web/status/' . rawurlencode( $id );
	}
}

ATP_Auto_Tweet::init();

register_deactivation_hook(
	__FILE__,
	function () {
		wp_unschedule_hook( ATP_Auto_Tweet::CRON_HOOK );
	}
);
