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
	const FREE_TYPE   = 'atp_arte'; // artes avulsas (sem post do site)
	const CRON_HOOK   = 'atp_ig_refresh_token';
	const API_VERSION = 'v23.0';
	const TEMPLATES   = array(
		'pixel1' => 'Pixel 1',
		'pixel2' => 'Pixel 2',
		'pixel3' => 'Pixel 3',
		'card'   => 'Card de post',
	);
	// Modelos da versão anterior → equivalentes novos.
	const OLD_TEMPLATES = array(
		'faixa'  => 'pixel1',
		'escuro' => 'pixel2',
		'tweet'  => 'card',
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
		add_action( 'admin_post_atp_ig_new', array( __CLASS__, 'handle_new' ) );
		add_action( 'admin_post_atp_ig_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'init', array( __CLASS__, 'register_type' ) );
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
			'handle'           => '@conexaopop.com.br',
			'default_template' => 'pixel1',
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
		$tpl                     = isset( $in['default_template'] ) ? (string) $in['default_template'] : '';
		$tpl                     = isset( self::OLD_TEMPLATES[ $tpl ] ) ? self::OLD_TEMPLATES[ $tpl ] : $tpl;
		$out['default_template'] = isset( self::TEMPLATES[ $tpl ] ) ? $tpl : 'pixel1';
		$caption                 = isset( $in['caption'] ) ? sanitize_textarea_field( $in['caption'] ) : '';
		$out['caption']          = '' !== trim( $caption ) ? $caption : $d['caption'];

		// Token novo invalida a conta em cache.
		$old = self::settings();
		if ( $old['token'] !== $out['token'] || $old['ig_user_id'] !== $out['ig_user_id'] ) {
			delete_option( self::ACCOUNT );
		}
		return $out;
	}

	public static function register_type() {
		register_post_type(
			self::FREE_TYPE,
			array(
				'label'           => 'Artes avulsas',
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	public static function is_free( $post ) {
		return $post && self::FREE_TYPE === $post->post_type;
	}

	public static function handle_new() {
		check_admin_referer( 'atp_ig_new' );
		if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( 'Sem permissão.' );
		}
		$id = wp_insert_post(
			array(
				'post_type'   => self::FREE_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Arte avulsa – ' . wp_date( 'd/m/Y H:i' ),
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}
		wp_safe_redirect( self::editor_url( $id ) );
		exit;
	}

	public static function handle_delete() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'atp_ig_delete_' . $id );
		$post = get_post( $id );
		if ( ! self::is_free( $post ) || ! current_user_can( 'delete_post', $id ) ) {
			wp_die( 'Sem permissão.' );
		}
		wp_delete_post( $id, true );
		wp_safe_redirect( admin_url( 'edit.php?page=atp-ig-editor&deleted=1' ) );
		exit;
	}

	public static function admin_menu() {
		self::$settings_hook = add_options_page( 'Instagram – Artes', 'Instagram (artes)', 'manage_options', 'atp-instagram', array( __CLASS__, 'render_settings' ) );
		self::$editor_hook   = add_submenu_page( 'edit.php', 'Arte para Instagram', 'Arte p/ Instagram', 'edit_posts', 'atp-ig-editor', array( __CLASS__, 'render_editor' ) );
	}

	public static function enqueue( $hook ) {
		$url = plugin_dir_url( self::$file );
		$ver = '1.6.0';
		if ( $hook === self::$settings_hook ) {
			wp_enqueue_media();
			wp_enqueue_script( 'atp-ig-settings', $url . 'assets/ig-settings.js', array( 'jquery' ), $ver, true );
		}
		if ( $hook !== self::$editor_hook ) {
			return;
		}
		wp_enqueue_style( 'atp-ig-editor', $url . 'assets/ig-editor.css', array(), $ver );
		$post = self::current_post();
		if ( ! $post ) {
			return;
		}
		$free = self::is_free( $post );
		wp_enqueue_media();
		wp_enqueue_style( 'atp-ig-fonts', 'https://fonts.googleapis.com/css2?family=Anton&family=Archivo:wght@400;700&family=Bricolage+Grotesque:opsz,wdth,wght@12..96,75..100,400..800&family=Geist+Pixel&family=Inter:wght@400;600;700&display=swap', array(), null );
		// Kensington (títulos): kit do Adobe Fonts do site conexaopop.com.br.
		wp_enqueue_style( 'atp-ig-kensington', 'https://use.typekit.net/xqr3xib.css', array(), null );
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
			'free'            => $free,
			'title'           => $free ? '' : ATP_Auto_Tweet::plain( get_the_title( $post ) ),
			'excerpt'         => ! $free && has_excerpt( $post ) ? ATP_Auto_Tweet::plain( $post->post_excerpt ) : '',
			'permalink'       => $free ? '' : get_permalink( $post ),
			'thumbId'         => $free ? 0 : (int) get_post_thumbnail_id( $post ),
			'postImages'      => $free ? array() : self::post_images( $post ),
			'defaultCaption'  => $free ? '' : self::build_caption( $post ),
			'state'           => is_array( $state ) ? $state : null,
			'color'           => $s['color'],
			'brandText'       => $s['brand_text'],
			'logoId'          => (int) $s['logo_id'],
			'defaultLogo'     => $url . 'assets/logo.png',
			'defaultAvatar'   => $url . 'assets/avatar.jpg',
			'avatarPop'       => $url . 'assets/avatar-pop.png',
			'logoBox'         => $url . 'assets/logo-caixas.svg',
			'category'        => $free ? '' : self::category( $post ),
			'avatarId'        => (int) $s['avatar_id'],
			'accountName'     => $s['account_name'],
			'handle'          => $s['handle'],
			'defaultTemplate' => isset( self::OLD_TEMPLATES[ $s['default_template'] ] ) ? self::OLD_TEMPLATES[ $s['default_template'] ] : $s['default_template'],
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
					self::media_field( 'logo_id', 'Outro logo (opcional)', $s['logo_id'], 'Usado só no modelo de Stories. Em branco usa o logo da Conexão POP. PNG com fundo transparente.' );
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
			<p class="atp-ig-back"><a href="<?php echo esc_url( admin_url( 'edit.php?page=atp-ig-editor' ) ); ?>">← Todas as artes</a></p>
			<?php if ( self::is_free( $post ) ) : ?>
				<h1>Arte avulsa</h1>
				<p class="atp-ig-sub">Arte sem post do site: escolha a imagem, escreva o texto e a legenda.</p>
			<?php else : ?>
				<h1>Arte para Instagram</h1>
				<p class="atp-ig-sub">
					<strong><?php echo esc_html( get_the_title( $post ) ); ?></strong> ·
					<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">voltar ao post</a>
					<?php if ( 'publish' === $post->post_status ) : ?>
						· <a href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener">ver matéria</a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
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
					<h3>Formato</h3>
					<label><input type="radio" name="atp-ig-format" value="feed"> Feed (4:5)</label>
					<label style="margin-left:12px"><input type="radio" name="atp-ig-format" value="story"> Stories (9:16)</label>

					<div class="atp-ig-story-only notice notice-info inline">
						<p>Stories é postado pelo app do Instagram, para colocar o <strong>adesivo de link</strong> no espaço vazio embaixo da arte:
						baixe a imagem (dá para abrir esta página no celular), suba no Stories, adicione o adesivo "Link" e cole o link da matéria.</p>
					</div>

					<div class="atp-ig-feed-only">
						<h3>Modelo</h3>
						<div class="atp-ig-templates">
							<?php foreach ( self::TEMPLATES as $k => $label ) : ?>
								<button type="button" class="button" data-template="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></button>
							<?php endforeach; ?>
						</div>
					</div>

					<h3>Fotos</h3>
					<div class="atp-ig-feed-only">
					<div class="atp-ig-templates" id="atp-ig-layout">
						<button type="button" class="button" data-layout="1">1 foto</button>
						<button type="button" class="button" data-layout="2">2 fotos</button>
						<button type="button" class="button" data-layout="3">3 fotos</button>
					</div>
					<div class="atp-ig-templates" id="atp-ig-slots" style="margin-top:8px">
						<span class="description" style="align-self:center">Editando:</span>
						<button type="button" class="button" data-slot="0">Foto 1</button>
						<button type="button" class="button" data-slot="1">Foto 2</button>
						<button type="button" class="button" data-slot="2">Foto 3</button>
					</div>
					</div>
					<p style="margin:8px 0 0"><button type="button" class="button" id="atp-ig-change">Trocar imagem</button></p>
					<label class="atp-ig-range">Zoom <input type="range" id="atp-ig-zoom" min="1" max="3" step="0.01"></label>

					<div id="atp-ig-tag-row">
						<h3>Etiqueta</h3>
						<input type="text" id="atp-ig-tag" class="regular-text" placeholder="Ex.: MÚSICA">
						<p class="description">Faixa verde acima do título. Vem com a categoria da matéria; deixe em branco para não usar.</p>
					</div>

					<h3>Texto da arte</h3>
					<textarea id="atp-ig-text" rows="4"></textarea>
					<p class="description">Emojis não aparecem na arte (só na legenda).</p>
					<label class="atp-ig-range">Altura <input type="range" id="atp-ig-pos" min="0.3" max="0.98" step="0.005"></label>

					<div class="atp-ig-story-only">
						<h3>Descrição</h3>
						<textarea id="atp-ig-desc" rows="3"></textarea>
						<p class="description">Linha fina embaixo do título. Deixe em branco para não usar.</p>
					</div>

					<div class="atp-ig-feed-only">
						<h3>Legenda</h3>
						<textarea id="atp-ig-caption" rows="8"></textarea>
						<p class="description"><span id="atp-ig-count">0</span>/2200</p>
					</div>

					<p class="atp-ig-actions">
						<button type="button" class="button" id="atp-ig-download">Baixar imagem</button>
						<button type="button" class="button atp-ig-story-only" id="atp-ig-copy">Copiar link da matéria</button>
						<button type="button" class="button button-primary atp-ig-feed-only" id="atp-ig-publish">Publicar no feed</button>
					</p>
					<div id="atp-ig-status" aria-live="polite"></div>
					<div id="atp-ig-published"><?php self::render_published( $post->ID ); ?></div>
				</div>
			</div>
		</div>
		<?php
	}

	private static function render_post_picker() {
		// phpcs:disable WordPress.Security.NonceVerification
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status = isset( $_GET['status'] ) && in_array( $_GET['status'], array( 'publish', 'draft', 'future' ), true ) ? sanitize_key( $_GET['status'] ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		$query = new WP_Query(
			array(
				'post_type'      => ATP_Auto_Tweet::settings()['post_types'],
				'post_status'    => $status ? array( $status ) : array( 'publish', 'future', 'draft', 'pending' ),
				'posts_per_page' => 24,
				'paged'          => $paged,
				's'              => $search,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$free = get_posts(
			array(
				'post_type'      => self::FREE_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 12,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$base     = admin_url( 'edit.php?page=atp-ig-editor' );
		$new_url  = wp_nonce_url( admin_url( 'admin-post.php?action=atp_ig_new' ), 'atp_ig_new' );
		$statuses = array(
			''        => 'Todos',
			'publish' => 'Publicados',
			'future'  => 'Agendados',
			'draft'   => 'Rascunhos',
		);
		?>
		<div class="wrap atp-pick">
			<div class="atp-pick-head">
				<div>
					<h1>Artes para Instagram</h1>
					<p class="atp-ig-sub">Escolha uma matéria para criar a arte, ou crie uma arte avulsa sem post do site.</p>
				</div>
				<a class="button button-primary button-hero" href="<?php echo esc_url( $new_url ); ?>">+ Nova arte avulsa</a>
			</div>

			<?php if ( isset( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success inline"><p>Arte avulsa excluída.</p></div>
			<?php endif; ?>

			<?php if ( $free ) : ?>
				<h2 class="atp-pick-title">Artes avulsas</h2>
				<div class="atp-pick-grid">
					<?php foreach ( $free as $p ) : ?>
						<?php self::render_pick_card( $p, true ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<h2 class="atp-pick-title">Matérias</h2>
			<form class="atp-pick-bar" method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
				<input type="hidden" name="page" value="atp-ig-editor">
				<div class="atp-pick-tabs">
					<?php foreach ( $statuses as $key => $label ) : ?>
						<a class="<?php echo $status === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'status' => $key ? $key : false, 's' => $search ? $search : false ), $base ) ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</div>
				<?php if ( $status ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
				<?php endif; ?>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Buscar matéria…">
				<button class="button">Buscar</button>
			</form>

			<?php if ( ! $query->have_posts() ) : ?>
				<p>Nenhuma matéria encontrada.</p>
			<?php else : ?>
				<div class="atp-pick-grid">
					<?php foreach ( $query->posts as $p ) : ?>
						<?php
						if ( current_user_can( 'edit_post', $p->ID ) ) {
							self::render_pick_card( $p, false );
						}
						?>
					<?php endforeach; ?>
				</div>
				<div class="atp-pick-pages">
					<?php
					echo wp_kses_post(
						(string) paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $query->max_num_pages,
								'prev_text' => '‹ Anteriores',
								'next_text' => 'Próximas ›',
							)
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_pick_card( $p, $free ) {
		$state    = json_decode( (string) get_post_meta( $p->ID, self::META_STATE, true ), true );
		$img_id   = $free ? ( isset( $state['photos'][0]['id'] ) ? (int) $state['photos'][0]['id'] : 0 ) : (int) get_post_thumbnail_id( $p );
		$img      = $img_id ? wp_get_attachment_image_url( $img_id, 'medium_large' ) : '';
		$title    = $free ? ( ! empty( $state['text'] ) ? $state['text'] : 'Arte sem texto' ) : get_the_title( $p );
		$ig       = get_post_meta( $p->ID, self::META_POSTS, true );
		$on_ig    = is_array( $ig ) && $ig;
		$has_art  = is_array( $state );
		$st_obj   = get_post_status_object( $p->post_status );
		$date     = $free ? get_the_modified_date( 'd/m/Y H:i', $p ) : get_the_date( 'd/m/Y H:i', $p );
		?>
		<div class="atp-card">
			<a class="atp-card-link" href="<?php echo esc_url( self::editor_url( $p->ID ) ); ?>">
				<span class="atp-card-img"<?php echo $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : ''; ?>>
					<?php if ( ! $img ) : ?><span class="atp-card-noimg">Sem imagem</span><?php endif; ?>
				</span>
				<span class="atp-card-badges">
					<?php if ( ! $free && $st_obj && 'publish' !== $p->post_status ) : ?>
						<span class="atp-badge"><?php echo esc_html( $st_obj->label ); ?></span>
					<?php endif; ?>
					<?php if ( $on_ig ) : ?>
						<span class="atp-badge is-ig">No Instagram</span>
					<?php elseif ( $has_art ) : ?>
						<span class="atp-badge is-draft">Arte em andamento</span>
					<?php endif; ?>
				</span>
				<span class="atp-card-title"><?php echo esc_html( wp_trim_words( $title, 18, '…' ) ); ?></span>
				<span class="atp-card-date"><?php echo esc_html( $date ); ?></span>
			</a>
			<?php if ( $free ) : ?>
				<a class="atp-card-del" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=atp_ig_delete&post=' . $p->ID ), 'atp_ig_delete_' . $p->ID ) ); ?>" onclick="return confirm('Excluir esta arte avulsa?')">Excluir</a>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function clean_template( $t ) {
		if ( isset( self::OLD_TEMPLATES[ $t ] ) ) {
			return self::OLD_TEMPLATES[ $t ];
		}
		return isset( self::TEMPLATES[ $t ] ) ? $t : 'pixel1';
	}

	/** Categoria principal da matéria (para a etiqueta). */
	private static function category( $post ) {
		$cats = get_the_category( $post->ID );
		foreach ( (array) $cats as $c ) {
			if ( ! in_array( $c->slug, array( 'uncategorized', 'sem-categoria' ), true ) ) {
				return ATP_Auto_Tweet::plain( $c->name );
			}
		}
		return '';
	}

	/** Outras imagens do post (para preencher as fotos 2 e 3). */
	private static function post_images( $post ) {
		$thumb = (int) get_post_thumbnail_id( $post );
		$ids   = array_map( 'intval', array_keys( get_attached_media( 'image', $post->ID ) ) );
		if ( preg_match_all( '/wp-image-(\d+)/', $post->post_content, $m ) ) {
			$ids = array_merge( array_map( 'intval', $m[1] ), $ids );
		}
		$ids = array_values( array_unique( array_diff( $ids, array( $thumb, 0 ) ) ) );
		return array_slice( $ids, 0, 2 );
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
		// Rascunhos da versão anterior (uma foto só).
		if ( ! isset( $in['photos'] ) && isset( $in['imageId'] ) ) {
			$in['photos'] = array(
				array(
					'id'   => $in['imageId'],
					'zoom' => $num( 'zoom', 1, 3, 1 ),
					'ox'   => $num( 'ox', 0, 1, 0.5 ),
					'oy'   => $num( 'oy', 0, 1, 0.5 ),
				),
			);
		}
		$photos = array();
		$raw    = isset( $in['photos'] ) && is_array( $in['photos'] ) ? array_slice( $in['photos'], 0, 3 ) : array();
		foreach ( $raw as $p ) {
			$p        = is_array( $p ) ? $p : array();
			$photos[] = array(
				'id'   => isset( $p['id'] ) ? absint( $p['id'] ) : 0,
				'zoom' => isset( $p['zoom'] ) && is_numeric( $p['zoom'] ) ? max( 1, min( 3, (float) $p['zoom'] ) ) : 1,
				'ox'   => isset( $p['ox'] ) && is_numeric( $p['ox'] ) ? max( 0, min( 1, (float) $p['ox'] ) ) : 0.5,
				'oy'   => isset( $p['oy'] ) && is_numeric( $p['oy'] ) ? max( 0, min( 1, (float) $p['oy'] ) ) : 0.5,
			);
		}
		return array(
			'template'  => self::clean_template( isset( $in['template'] ) ? (string) $in['template'] : '' ),
			'format'    => isset( $in['format'] ) && 'story' === $in['format'] ? 'story' : 'feed',
			'layout'    => isset( $in['layout'] ) ? max( 1, min( 3, absint( $in['layout'] ) ) ) : 1,
			'photos'    => $photos ? $photos : null,
			'tag'       => isset( $in['tag'] ) ? sanitize_text_field( $in['tag'] ) : null,
			'fontScale' => $num( 'fontScale', 0.6, 1.6, 1 ),
			'pos'       => isset( $in['pos'] ) && is_numeric( $in['pos'] ) ? max( 0.3, min( 0.98, (float) $in['pos'] ) ) : null,
			'posStory'  => isset( $in['posStory'] ) && is_numeric( $in['posStory'] ) ? max( 0.3, min( 0.98, (float) $in['posStory'] ) ) : null,
			'text'      => isset( $in['text'] ) ? sanitize_textarea_field( $in['text'] ) : '',
			'desc'      => isset( $in['desc'] ) ? sanitize_textarea_field( $in['desc'] ) : null,
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
