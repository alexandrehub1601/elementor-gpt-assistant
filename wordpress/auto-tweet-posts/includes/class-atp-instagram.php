<?php
/**
 * Artes para o Instagram: editor de templates dentro do WordPress e
 * publicação no feed / Stories pela API oficial (gratuita) do Instagram.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ATP_Instagram {

	const OPTION      = 'atp_ig_settings';
	const ACCOUNT     = 'atp_ig_account';
	const META_STATE  = '_atp_ig_state';
	const META_POSTS  = '_atp_ig_posts';
	const CRON_HOOK   = 'atp_ig_refresh_token';
	const API_VERSION = 'v23.0';
	const TEMPLATES   = array(
		'faixa'  => 'Faixa rosa',
		'escuro' => 'Gradiente escuro',
		'tweet'  => 'Card de post',
	);

	private static $file;
	private static $editor_hook   = '';
	private static $settings_hook = '';

	public static function init( $file ) {
		self::$file = $file;
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 11, 2 );
		add_filter( 'page_row_actions', array( __CLASS__, 'row_actions' ), 11, 2 );
		add_action( 'wp_ajax_atp_ig_img', array( __CLASS__, 'ajax_image' ) );
		add_action( 'wp_ajax_atp_ig_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_atp_ig_publish', array( __CLASS__, 'ajax_publish' ) );
		add_action( 'admin_post_atp_ig_test', array( __CLASS__, 'handle_test' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'refresh_token' ) );
		add_action( 'init', array( __CLASS__, 'schedule_refresh' ) );
		register_deactivation_hook( $file, array( __CLASS__, 'deactivate' ) );
	}

	/* ---------------------------------------------------------------------
	 * Configurações
	 * ------------------------------------------------------------------ */

	public static function defaults() {
		return array(
			'token'            => '',
			'ig_user_id'       => '',
			'color'            => '#ff59a5',
			'brand_text'       => 'conexãopop',
			'logo_id'          => 0,
			'avatar_id'        => 0,
			'account_name'     => 'Conexão POP',
			'handle'           => '@conexaopopsite',
			'default_template' => 'faixa',
			'caption'          => "{title}\n\n🔗 Leia a matéria completa no link da bio!\n\n{tags}",
		);
	}

	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function register_settings() {
		register_setting(
			'atp_ig_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	public static function sanitize( $in ) {
		$in  = is_array( $in ) ? wp_unslash( $in ) : array();
		$d   = self::defaults();
		$out = array();

		$out['token']      = isset( $in['token'] ) ? preg_replace( '/\s+/', '', (string) $in['token'] ) : '';
		$out['ig_user_id'] = isset( $in['ig_user_id'] ) ? preg_replace( '/\D+/', '', (string) $in['ig_user_id'] ) : '';
		$color             = isset( $in['color'] ) ? sanitize_hex_color( $in['color'] ) : '';
		$out['color']      = $color ? $color : $d['color'];
		foreach ( array( 'brand_text', 'account_name', 'handle' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : $d[ $k ];
		}
		$out['logo_id']          = isset( $in['logo_id'] ) ? absint( $in['logo_id'] ) : 0;
		$out['avatar_id']        = isset( $in['avatar_id'] ) ? absint( $in['avatar_id'] ) : 0;
		$out['default_template'] = isset( $in['default_template'] ) && isset( self::TEMPLATES[ $in['default_template'] ] ) ? $in['default_template'] : 'faixa';
		$caption                 = isset( $in['caption'] ) ? sanitize_textarea_field( $in['caption'] ) : '';
		$out['caption']          = '' !== trim( $caption ) ? $caption : $d['caption'];

		// Token novo invalida a conta em cache.
		$old = self::settings();
		if ( $old['token'] !== $out['token'] || $old['ig_user_id'] !== $out['ig_user_id'] ) {
			delete_option( self::ACCOUNT );
		}
		return $out;
	}

	public static function admin_menu() {
		self::$settings_hook = add_options_page( 'Instagram – Artes', 'Instagram (artes)', 'manage_options', 'atp-instagram', array( __CLASS__, 'render_settings' ) );
		self::$editor_hook   = add_submenu_page( 'edit.php', 'Arte para Instagram', 'Arte p/ Instagram', 'edit_posts', 'atp-ig-editor', array( __CLASS__, 'render_editor' ) );
	}

	public static function enqueue( $hook ) {
		$url = plugin_dir_url( self::$file );
		$ver = '1.2.0';
		if ( $hook === self::$settings_hook ) {
			wp_enqueue_media();
			wp_enqueue_script( 'atp-ig-settings', $url . 'assets/ig-settings.js', array( 'jquery' ), $ver, true );
		}
		if ( $hook !== self::$editor_hook ) {
			return;
		}
		$post = self::current_post();
		if ( ! $post ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'atp-ig-fonts', 'https://fonts.googleapis.com/css2?family=Archivo:wght@400;700&family=Bricolage+Grotesque:opsz,wdth,wght@12..96,75..100,700..800&display=swap', array(), null );
		wp_enqueue_style( 'atp-ig-editor', $url . 'assets/ig-editor.css', array(), $ver );
		wp_enqueue_script( 'atp-ig-editor', $url . 'assets/ig-editor.js', array( 'jquery' ), $ver, true );

		$s     = self::settings();
		$state = json_decode( (string) get_post_meta( $post->ID, self::META_STATE, true ), true );
		$data  = array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'atp_ig' ),
			'imgUrl'          => add_query_arg(
				array(
					'action'   => 'atp_ig_img',
					'_wpnonce' => wp_create_nonce( 'atp_ig' ),
				),
				admin_url( 'admin-ajax.php' )
			),
			'postId'          => $post->ID,
			'slug'            => $post->post_name ? $post->post_name : 'arte-' . $post->ID,
			'title'           => ATP_Auto_Tweet::plain( get_the_title( $post ) ),
			'thumbId'         => (int) get_post_thumbnail_id( $post ),
			'defaultCaption'  => self::build_caption( $post ),
			'state'           => is_array( $state ) ? $state : null,
			'color'           => $s['color'],
			'brandText'       => $s['brand_text'],
			'logoId'          => (int) $s['logo_id'],
			'defaultLogo'     => $url . 'assets/logo.png',
			'defaultAvatar'   => $url . 'assets/avatar.jpg',
			'avatarId'        => (int) $s['avatar_id'],
			'accountName'     => $s['account_name'],
			'handle'          => $s['handle'],
			'defaultTemplate' => $s['default_template'],
			'canPublish'      => '' !== $s['token'] && 'publish' === $post->post_status,
		);
		wp_add_inline_script( 'atp-ig-editor', 'window.ATP_IG = ' . wp_json_encode( $data ) . ';', 'before' );
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s   = self::settings();
		$opt = self::OPTION;
		$acc = get_option( self::ACCOUNT );
		?>
		<div class="wrap">
			<h1>Instagram – artes e publicação</h1>
			<p>Cada post ganha o editor <strong>Posts › Arte p/ Instagram</strong> (ou o botão na lateral do post) com os 3 modelos. Lá você ajusta imagem, texto e legenda, baixa a arte ou publica direto no feed / Stories.</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'atp_ig_group' ); ?>

				<h2>Conexão com o Instagram</h2>
				<p class="description" style="max-width:780px">
					A API do Instagram é gratuita. A conta precisa ser <strong>Profissional</strong> (Comercial ou Criador de conteúdo).<br>
					1) Em <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">developers.facebook.com/apps</a> crie um app (tipo <em>Empresa</em>) e adicione o produto <strong>Instagram</strong> › <em>API setup with Instagram login</em>.<br>
					2) Em <em>Generate access tokens</em>, adicione a conta do Instagram e clique em <strong>Generate token</strong> (permissões <code>instagram_business_basic</code> e <code>instagram_business_content_publish</code>).<br>
					3) Cole o token abaixo. Ele vale 60 dias e o plugin renova sozinho toda semana.
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="atp_ig_token">Access token</label></th>
						<td><input type="password" autocomplete="off" class="large-text" id="atp_ig_token" name="<?php echo esc_attr( $opt ); ?>[token]" value="<?php echo esc_attr( $s['token'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="atp_ig_uid">ID da conta do Instagram</label></th>
						<td>
							<input type="text" class="regular-text" id="atp_ig_uid" name="<?php echo esc_attr( $opt ); ?>[ig_user_id]" value="<?php echo esc_attr( $s['ig_user_id'] ); ?>">
							<p class="description">Opcional com token do Instagram (descobre sozinho). Obrigatório se usar um token do Facebook (começa com EAA).</p>
						</td>
					</tr>
				</table>

				<h2>Identidade das artes</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="atp_ig_color">Cor da marca</label></th>
						<td><input type="color" id="atp_ig_color" name="<?php echo esc_attr( $opt ); ?>[color]" value="<?php echo esc_attr( $s['color'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="atp_ig_brand">Marca (texto)</label></th>
						<td>
							<input type="text" class="regular-text" id="atp_ig_brand" name="<?php echo esc_attr( $opt ); ?>[brand_text]" value="<?php echo esc_attr( $s['brand_text'] ); ?>">
							<p class="description">Só é usado se o logo for removido; por padrão as artes usam o logo da Conexão POP que vem no plugin.</p>
						</td>
					</tr>
					<?php
					self::media_field( 'logo_id', 'Outro logo (opcional)', $s['logo_id'], 'Em branco usa o logo da Conexão POP. PNG com fundo transparente; ele é pintado de branco na faixa rosa e na cor da marca no gradiente escuro.' );
					self::media_field( 'avatar_id', 'Outra foto de perfil (opcional)', $s['avatar_id'], 'A bolinha do modelo "Card de post". Em branco usa a foto de perfil da Conexão POP.' );
					?>
					<tr>
						<th scope="row"><label for="atp_ig_name">Nome no card</label></th>
						<td><input type="text" class="regular-text" id="atp_ig_name" name="<?php echo esc_attr( $opt ); ?>[account_name]" value="<?php echo esc_attr( $s['account_name'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="atp_ig_handle">@ no card</label></th>
						<td><input type="text" class="regular-text" id="atp_ig_handle" name="<?php echo esc_attr( $opt ); ?>[handle]" value="<?php echo esc_attr( $s['handle'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="atp_ig_tpl">Modelo padrão</label></th>
						<td>
							<select id="atp_ig_tpl" name="<?php echo esc_attr( $opt ); ?>[default_template]">
								<?php foreach ( self::TEMPLATES as $k => $label ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['default_template'], $k ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="atp_ig_caption">Legenda padrão</label></th>
						<td>
							<textarea id="atp_ig_caption" rows="5" class="large-text" name="<?php echo esc_attr( $opt ); ?>[caption]"><?php echo esc_textarea( $s['caption'] ); ?></textarea>
							<p class="description">Variáveis: <code>{title}</code> <code>{excerpt}</code> <code>{tags}</code> <code>{categories}</code> <code>{url}</code> <code>{site}</code>. Links na legenda do Instagram não são clicáveis.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Salvar' ); ?>
			</form>

			<hr>
			<h2>Testar conexão</h2>
			<?php if ( is_array( $acc ) && ! empty( $acc['username'] ) ) : ?>
				<p>Conta conectada: <strong>@<?php echo esc_html( $acc['username'] ); ?></strong></p>
			<?php endif; ?>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=atp_ig_test' ), 'atp_ig_test' ) ); ?>">Testar conexão com o Instagram</a>
		</div>
		<?php
	}

	private static function media_field( $key, $label, $id, $help ) {
		$src = $id ? wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td class="atp-media-field">
				<input type="hidden" name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $id ); ?>">
				<img src="<?php echo esc_url( $src ); ?>" alt="" style="max-width:80px;max-height:80px;background:#ddd;vertical-align:middle;<?php echo $src ? '' : 'display:none'; ?>">
				<button type="button" class="button atp-media-pick">Escolher imagem</button>
				<button type="button" class="button-link atp-media-clear" style="<?php echo $id ? '' : 'display:none'; ?>">Remover</button>
				<p class="description"><?php echo esc_html( $help ); ?></p>
			</td>
		</tr>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Acesso a partir do post
	 * ------------------------------------------------------------------ */

	public static function editor_url( $post_id ) {
		return admin_url( 'edit.php?page=atp-ig-editor&post=' . (int) $post_id );
	}

	public static function add_meta_box() {
		foreach ( ATP_Auto_Tweet::settings()['post_types'] as $type ) {
			add_meta_box( 'atp_ig_box', 'Instagram', array( __CLASS__, 'render_meta_box' ), $type, 'side', 'high' );
		}
	}

	public static function render_meta_box( $post ) {
		echo '<p><a class="button button-primary" target="_blank" rel="noopener" href="' . esc_url( self::editor_url( $post->ID ) ) . '">Criar arte para o Instagram</a></p>';
		self::render_published( $post->ID );
	}

	private static function render_published( $post_id ) {
		$list = get_post_meta( $post_id, self::META_POSTS, true );
		if ( empty( $list ) || ! is_array( $list ) ) {
			return;
		}
		echo '<p><strong>Já publicado:</strong></p><ul style="margin-top:0">';
		foreach ( array_reverse( $list ) as $row ) {
			$label = ( 'story' === $row['format'] ? 'Stories' : 'Feed' ) . ' – ' . wp_date( 'd/m H:i', $row['time'] );
			echo '<li>' . ( $row['permalink'] ? '<a href="' . esc_url( $row['permalink'] ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>' : esc_html( $label ) ) . '</li>';
		}
		echo '</ul>';
	}

	public static function row_actions( $actions, $post ) {
		if ( in_array( $post->post_type, ATP_Auto_Tweet::settings()['post_types'], true ) && current_user_can( 'edit_post', $post->ID ) ) {
			$actions['atp_ig'] = '<a href="' . esc_url( self::editor_url( $post->ID ) ) . '">Arte Instagram</a>';
		}
		return $actions;
	}

	private static function current_post() {
		$id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$post = $id ? get_post( $id ) : null;
		return ( $post && current_user_can( 'edit_post', $post->ID ) ) ? $post : null;
	}

	/* ---------------------------------------------------------------------
	 * Editor
	 * ------------------------------------------------------------------ */

	public static function render_editor() {
		$post = self::current_post();
		if ( ! $post ) {
			self::render_post_picker();
			return;
		}
		$s = self::settings();
		?>
		<div class="wrap atp-ig">
			<h1>Arte para Instagram</h1>
			<p class="atp-ig-sub">
				<strong><?php echo esc_html( get_the_title( $post ) ); ?></strong> ·
				<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">voltar ao post</a>
				<?php if ( 'publish' === $post->post_status ) : ?>
					· <a href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener">ver matéria</a>
				<?php endif; ?>
			</p>
			<?php if ( '' === $s['token'] ) : ?>
				<div class="notice notice-warning inline"><p>Para publicar direto, conecte a conta em <a href="<?php echo esc_url( admin_url( 'options-general.php?page=atp-instagram' ) ); ?>">Configurações › Instagram (artes)</a>. Enquanto isso, dá para baixar a arte.</p></div>
			<?php elseif ( 'publish' !== $post->post_status ) : ?>
				<div class="notice notice-info inline"><p>Publique o post para liberar o botão "Publicar no Instagram". Dá para montar e baixar a arte antes.</p></div>
			<?php endif; ?>

			<div class="atp-ig-grid">
				<div class="atp-ig-preview">
					<canvas id="atp-ig-canvas" width="1080" height="1350"></canvas>
					<p class="description">Arraste a imagem para reenquadrar.</p>
				</div>

				<div class="atp-ig-controls">
					<h3>Modelo</h3>
					<div class="atp-ig-templates">
						<?php foreach ( self::TEMPLATES as $k => $label ) : ?>
							<button type="button" class="button" data-template="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</div>

					<h3>Formato</h3>
					<label><input type="radio" name="atp-ig-format" value="feed"> Feed (4:5)</label>
					<label style="margin-left:12px"><input type="radio" name="atp-ig-format" value="story"> Stories (9:16)</label>

					<h3>Imagem</h3>
					<button type="button" class="button" id="atp-ig-change">Trocar imagem</button>
					<label class="atp-ig-range">Zoom <input type="range" id="atp-ig-zoom" min="1" max="3" step="0.01"></label>

					<h3>Texto da arte</h3>
					<textarea id="atp-ig-text" rows="4"></textarea>
					<p class="description">Emojis não aparecem na arte (só na legenda).</p>
					<label class="atp-ig-range">Tamanho <input type="range" id="atp-ig-size" min="0.6" max="1.6" step="0.02"></label>
					<label class="atp-ig-range">Altura <input type="range" id="atp-ig-pos" min="0.3" max="0.98" step="0.005"></label>

					<h3>Legenda</h3>
					<textarea id="atp-ig-caption" rows="8"></textarea>
					<p class="description"><span id="atp-ig-count">0</span>/2200 · <span id="atp-ig-story-note">No Stories a legenda não é usada.</span></p>

					<p class="atp-ig-actions">
						<button type="button" class="button" id="atp-ig-download">Baixar imagem</button>
						<button type="button" class="button button-primary" id="atp-ig-publish">Publicar no Instagram</button>
					</p>
					<div id="atp-ig-status" aria-live="polite"></div>
					<div id="atp-ig-published"><?php self::render_published( $post->ID ); ?></div>
				</div>
			</div>
		</div>
		<?php
	}

	private static function render_post_picker() {
		$posts = get_posts(
			array(
				'post_type'      => ATP_Auto_Tweet::settings()['post_types'],
				'post_status'    => array( 'publish', 'future', 'draft' ),
				'posts_per_page' => 30,
			)
		);
		echo '<div class="wrap"><h1>Arte para Instagram</h1><p>Escolha o post:</p><ul>';
		foreach ( $posts as $p ) {
			if ( current_user_can( 'edit_post', $p->ID ) ) {
				echo '<li><a href="' . esc_url( self::editor_url( $p->ID ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a> <span class="description">(' . esc_html( get_post_status_object( $p->post_status )->label ) . ')</span></li>';
			}
		}
		echo '</ul></div>';
	}

	public static function build_caption( $post ) {
		$vars = array(
			'{title}'      => ATP_Auto_Tweet::plain( get_the_title( $post ) ),
			'{excerpt}'    => ATP_Auto_Tweet::excerpt( $post ),
			'{tags}'       => ATP_Auto_Tweet::hashtags( get_the_tags( $post->ID ) ),
			'{categories}' => ATP_Auto_Tweet::hashtags( get_the_category( $post->ID ) ),
			'{url}'        => get_permalink( $post ),
			'{site}'       => ATP_Auto_Tweet::plain( get_bloginfo( 'name' ) ),
		);
		$text = strtr( self::settings()['caption'], $vars );
		$text = preg_replace( '/[ \t]{2,}/', ' ', $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		return trim( $text );
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------ */

	/** Entrega a imagem pelo próprio admin para o canvas (evita bloqueio de CORS de CDN). */
	public static function ajax_image() {
		check_ajax_referer( 'atp_ig' );
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! current_user_can( 'upload_files' ) || ! $id || ! wp_attachment_is_image( $id ) ) {
			status_header( 404 );
			exit;
		}
		$file = get_attached_file( $id );
		if ( $file && file_exists( $file ) && filesize( $file ) > 15 * MB_IN_BYTES ) {
			$large = image_get_intermediate_size( $id, 'large' );
			if ( $large && ! empty( $large['path'] ) ) {
				$file = trailingslashit( wp_get_upload_dir()['basedir'] ) . $large['path'];
			}
		}
		if ( ! $file || ! file_exists( $file ) ) {
			// Sem arquivo local (ex.: mídia offload em nuvem): redireciona para a URL.
			$url = wp_get_attachment_url( $id );
			if ( $url ) {
				wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect
				exit;
			}
			status_header( 404 );
			exit;
		}
		$type = wp_check_filetype( $file )['type'];
		header( 'Content-Type: ' . ( $type ? $type : 'application/octet-stream' ) );
		header( 'Content-Length: ' . filesize( $file ) );
		header( 'Cache-Control: private, max-age=3600' );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	private static function clean_state( $raw ) {
		$in = json_decode( (string) $raw, true );
		if ( ! is_array( $in ) ) {
			return null;
		}
		$num = function ( $k, $min, $max, $def ) use ( $in ) {
			return isset( $in[ $k ] ) && is_numeric( $in[ $k ] ) ? max( $min, min( $max, (float) $in[ $k ] ) ) : $def;
		};
		return array(
			'template'  => isset( $in['template'] ) && isset( self::TEMPLATES[ $in['template'] ] ) ? $in['template'] : 'faixa',
			'format'    => isset( $in['format'] ) && 'story' === $in['format'] ? 'story' : 'feed',
			'imageId'   => isset( $in['imageId'] ) ? absint( $in['imageId'] ) : 0,
			'zoom'      => $num( 'zoom', 1, 3, 1 ),
			'ox'        => $num( 'ox', 0, 1, 0.5 ),
			'oy'        => $num( 'oy', 0, 1, 0.5 ),
			'fontScale' => $num( 'fontScale', 0.6, 1.6, 1 ),
			'pos'       => isset( $in['pos'] ) && is_numeric( $in['pos'] ) ? max( 0.3, min( 0.98, (float) $in['pos'] ) ) : null,
			'text'      => isset( $in['text'] ) ? sanitize_textarea_field( $in['text'] ) : '',
			'caption'   => isset( $in['caption'] ) ? sanitize_textarea_field( $in['caption'] ) : '',
		);
	}

	public static function ajax_save() {
		check_ajax_referer( 'atp_ig' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}
		$state = self::clean_state( isset( $_POST['state'] ) ? wp_unslash( $_POST['state'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( $state ) {
			update_post_meta( $post_id, self::META_STATE, wp_slash( wp_json_encode( $state ) ) );
		}
		wp_send_json_success();
	}

	public static function ajax_publish() {
		check_ajax_referer( 'atp_ig' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}
		if ( 'publish' !== $post->post_status ) {
			wp_send_json_error( array( 'message' => 'Publique o post antes.' ) );
		}
		if ( empty( $_FILES['image']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'A imagem não chegou ao servidor.' ) );
		}
		$info = @getimagesize( $_FILES['image']['tmp_name'] ); // phpcs:ignore
		if ( ! $info || 'image/jpeg' !== $info['mime'] ) {
			wp_send_json_error( array( 'message' => 'A arte precisa ser JPEG.' ) );
		}

		$state = self::clean_state( isset( $_POST['state'] ) ? wp_unslash( $_POST['state'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( $state ) {
			update_post_meta( $post_id, self::META_STATE, wp_slash( wp_json_encode( $state ) ) );
		}
		$format  = isset( $_POST['format'] ) && 'story' === $_POST['format'] ? 'story' : 'feed';
		$caption = isset( $_POST['caption'] ) ? sanitize_textarea_field( wp_unslash( $_POST['caption'] ) ) : '';
		$caption = mb_substr( $caption, 0, 2200, 'UTF-8' );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$_FILES['image']['name'] = sanitize_file_name( 'instagram-' . ( $post->post_name ? $post->post_name : $post_id ) . '-' . $format . '-' . time() . '.jpg' );
		$attachment_id           = media_handle_upload( 'image', $post_id, array( 'post_title' => 'Instagram (' . $format . ') – ' . get_the_title( $post ) ) );
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => 'Erro ao salvar a arte: ' . $attachment_id->get_error_message() ) );
		}

		$result = self::publish( wp_get_attachment_url( $attachment_id ), $caption, $format );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$list   = get_post_meta( $post_id, self::META_POSTS, true );
		$list   = is_array( $list ) ? $list : array();
		$list[] = array(
			'time'       => time(),
			'format'     => $format,
			'media_id'   => $result['id'],
			'permalink'  => $result['permalink'],
			'attachment' => $attachment_id,
		);
		update_post_meta( $post_id, self::META_POSTS, $list );

		ob_start();
		self::render_published( $post_id );
		wp_send_json_success(
			array(
				'permalink' => $result['permalink'],
				'list'      => ob_get_clean(),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * API do Instagram
	 * ------------------------------------------------------------------ */

	private static function is_ig_token( $token ) {
		return 0 === strpos( $token, 'IG' );
	}

	private static function base() {
		$token = self::settings()['token'];
		return ( self::is_ig_token( $token ) ? 'https://graph.instagram.com/' : 'https://graph.facebook.com/' ) . self::API_VERSION . '/';
	}

	private static function api( $method, $path, $params = array() ) {
		$params['access_token'] = self::settings()['token'];
		$url                    = self::base() . ltrim( $path, '/' );
		$args                   = array(
			'method'  => $method,
			'timeout' => 60,
		);
		if ( 'GET' === $method ) {
			$url = add_query_arg( array_map( 'rawurlencode', $params ), $url );
		} else {
			$args['body'] = $params;
		}
		$res = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'atp_ig_http', 'Falha de conexão com o Instagram: ' . $res->get_error_message() );
		}
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 || isset( $json['error'] ) ) {
			$err = isset( $json['error'] ) ? $json['error'] : array();
			$msg = ! empty( $err['error_user_msg'] ) ? $err['error_user_msg'] : ( ! empty( $err['message'] ) ? $err['message'] : 'HTTP ' . $code );
			return new WP_Error( 'atp_ig_api', 'Instagram: ' . $msg );
		}
		return is_array( $json ) ? $json : array();
	}

	/** Descobre (e guarda) o ID e o @ da conta. */
	private static function account() {
		$s      = self::settings();
		$cached = get_option( self::ACCOUNT );
		if ( is_array( $cached ) && ! empty( $cached['id'] ) ) {
			return $cached;
		}
		if ( '' === $s['token'] ) {
			return new WP_Error( 'atp_ig_token', 'Token do Instagram não configurado.' );
		}
		if ( '' !== $s['ig_user_id'] ) {
			$r = self::api( 'GET', $s['ig_user_id'], array( 'fields' => 'username' ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$acc = array(
				'id'       => $s['ig_user_id'],
				'username' => isset( $r['username'] ) ? $r['username'] : '',
			);
		} elseif ( self::is_ig_token( $s['token'] ) ) {
			$r = self::api( 'GET', 'me', array( 'fields' => 'user_id,username' ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$acc = array(
				'id'       => ! empty( $r['user_id'] ) ? (string) $r['user_id'] : (string) $r['id'],
				'username' => isset( $r['username'] ) ? $r['username'] : '',
			);
		} else {
			return new WP_Error( 'atp_ig_id', 'Com token do Facebook, preencha o ID da conta do Instagram nas configurações.' );
		}
		update_option( self::ACCOUNT, $acc, false );
		return $acc;
	}

	/**
	 * Publica uma imagem (URL pública JPEG) no feed ou Stories.
	 *
	 * @return array|WP_Error { id, permalink }
	 */
	public static function publish( $image_url, $caption, $format ) {
		$acc = self::account();
		if ( is_wp_error( $acc ) ) {
			return $acc;
		}
		$params = array( 'image_url' => $image_url );
		if ( 'story' === $format ) {
			$params['media_type'] = 'STORIES';
		} else {
			$params['caption'] = $caption;
		}
		$container = self::api( 'POST', $acc['id'] . '/media', $params );
		if ( is_wp_error( $container ) ) {
			return $container;
		}
		if ( empty( $container['id'] ) ) {
			return new WP_Error( 'atp_ig_container', 'Instagram não aceitou a imagem.' );
		}

		// Espera o Instagram processar a imagem.
		for ( $i = 0; $i < 15; $i++ ) {
			$st = self::api( 'GET', $container['id'], array( 'fields' => 'status_code' ) );
			if ( is_wp_error( $st ) ) {
				return $st;
			}
			$code = isset( $st['status_code'] ) ? $st['status_code'] : 'FINISHED';
			if ( 'FINISHED' === $code ) {
				break;
			}
			if ( 'ERROR' === $code || 'EXPIRED' === $code ) {
				return new WP_Error( 'atp_ig_processing', 'O Instagram não conseguiu processar a imagem (' . $code . ').' );
			}
			sleep( 2 );
		}

		$pub = self::api( 'POST', $acc['id'] . '/media_publish', array( 'creation_id' => $container['id'] ) );
		if ( is_wp_error( $pub ) ) {
			return $pub;
		}
		$media_id  = isset( $pub['id'] ) ? (string) $pub['id'] : '';
		$permalink = '';
		if ( $media_id ) {
			$m = self::api( 'GET', $media_id, array( 'fields' => 'permalink' ) );
			if ( ! is_wp_error( $m ) && ! empty( $m['permalink'] ) ) {
				$permalink = $m['permalink'];
			}
		}
		return array(
			'id'        => $media_id,
			'permalink' => $permalink,
		);
	}

	public static function handle_test() {
		check_admin_referer( 'atp_ig_test' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}
		delete_option( self::ACCOUNT );
		$acc = self::account();
		$msg = is_wp_error( $acc ) ? $acc->get_error_message() : 'Conectado como @' . $acc['username'];
		set_transient(
			'atp_test_result_' . get_current_user_id(),
			array(
				'ok'  => ! is_wp_error( $acc ),
				'msg' => $msg,
			),
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect( admin_url( 'options-general.php?page=atp-instagram' ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Renovação do token (tokens do Instagram valem 60 dias)
	 * ------------------------------------------------------------------ */

	public static function schedule_refresh() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::CRON_HOOK );
		}
	}

	public static function refresh_token() {
		$s = self::settings();
		if ( '' === $s['token'] || ! self::is_ig_token( $s['token'] ) ) {
			return;
		}
		$res = wp_remote_get(
			add_query_arg(
				array(
					'grant_type'   => 'ig_refresh_token',
					'access_token' => rawurlencode( $s['token'] ),
				),
				'https://graph.instagram.com/refresh_access_token'
			),
			array( 'timeout' => 30 )
		);
		$json = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! empty( $json['access_token'] ) ) {
			$s['token'] = $json['access_token'];
			update_option( self::OPTION, $s );
		}
	}

	public static function deactivate() {
		wp_unschedule_hook( self::CRON_HOOK );
	}
}
