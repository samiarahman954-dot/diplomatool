<?php
/**
 * One-click demo import (Appearance > Lilo Demo Import).
 *
 * Steps run one AJAX request at a time so slow hosts don't time out:
 *   plugin → media → photos → pages → templates → settings
 *
 * Re-running the import updates the pages/templates it created earlier
 * instead of making duplicates, and reuses already imported images.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

class Lilo_Demo_Importer {

	const NONCE      = 'lilo_demo_import';
	const MEDIA_OPT  = 'lilo_demo_media';
	const STATE_OPT  = 'lilo_demo_state';
	const ELEMENTOR  = 'elementor/elementor.php';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'wp_ajax_lilo_demo_step', array( __CLASS__, 'ajax_step' ) );
	}

	/**
	 * Ordered steps with their progress labels.
	 */
	public static function steps() {
		return array(
			'plugin'    => __( 'Installing & activating Elementor', 'lilo-cafe' ),
			'media'     => __( 'Importing theme images', 'lilo-cafe' ),
			'photos'    => __( 'Downloading demo photos', 'lilo-cafe' ),
			'pages'     => __( 'Building Home and Menu pages', 'lilo-cafe' ),
			'templates' => __( 'Building the Elementor header & footer', 'lilo-cafe' ),
			'settings'  => __( 'Setting homepage, menu, logo and Elementor options', 'lilo-cafe' ),
		);
	}

	public static function menu() {
		add_theme_page(
			__( 'Lilo Demo Import', 'lilo-cafe' ),
			__( 'Lilo Demo Import', 'lilo-cafe' ),
			'edit_theme_options',
			'lilo-demo-import',
			array( __CLASS__, 'page' )
		);
	}

	/* ------------------------------------------------------------------
	 * Admin page
	 * ---------------------------------------------------------------- */

	public static function page() {
		$state     = get_option( self::STATE_OPT, array() );
		$home_id   = ! empty( $state['pages']['home'] ) ? (int) $state['pages']['home'] : 0;
		$imported  = $home_id && get_post( $home_id );
		$has_el    = did_action( 'elementor/loaded' );
		$installed = file_exists( WP_PLUGIN_DIR . '/' . self::ELEMENTOR );

		wp_enqueue_script( 'lilo-importer', LILO_URI . '/assets/admin/importer.js', array(), LILO_VERSION, true );
		wp_localize_script(
			'lilo-importer',
			'liloImporter',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'steps'   => array_keys( self::steps() ),
				'strings' => array(
					'confirm' => $imported ? __( 'Import again? The Lilo demo pages, header and footer will be reset to the demo content. Your other pages are not touched.', 'lilo-cafe' ) : '',
					'failed'  => __( 'Import stopped:', 'lilo-cafe' ),
					'done'    => __( 'All done! Your Lilo Cafe site is ready.', 'lilo-cafe' ),
					'network' => __( 'The server did not answer (it may have timed out). Click Import again to continue where it stopped.', 'lilo-cafe' ),
				),
			)
		);
		?>
		<div class="wrap lilo-import">
			<style>
				.lilo-import{max-width:980px}
				.lilo-import__card{background:#FAFAF8;border:1px solid #E7E4DA;border-radius:20px;padding:32px;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:32px;align-items:start;margin-top:18px}
				@media(max-width:900px){.lilo-import__card{grid-template-columns:1fr}}
				.lilo-import__shot{border-radius:14px;overflow:hidden;border:1px solid #E7E4DA;background:#fff}
				.lilo-import__shot img{display:block;width:100%;height:auto}
				.lilo-import h1{font-size:28px}
				.lilo-import h2{margin:0 0 8px;font-size:22px;color:#464D1D}
				.lilo-import p{font-size:14px;color:#4B5030}
				.lilo-import ul.lilo-list{margin:14px 0 20px 18px;list-style:disc;color:#4B5030}
				.lilo-import .button-hero.button-primary{background:#464D1D;border-color:#464D1D;border-radius:999px;padding:0 28px}
				.lilo-import .button-hero.button-primary:hover{background:#3B4117;border-color:#3B4117}
				.lilo-steps{list-style:none;margin:20px 0 0;padding:0}
				.lilo-steps li{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #EEEBE2;color:#6A6E4B}
				.lilo-steps li::before{content:"";width:14px;height:14px;border-radius:50%;border:2px solid #C9C6B6;flex:none;box-sizing:border-box}
				.lilo-steps li.is-running{color:#2F3313}
				.lilo-steps li.is-running::before{border-color:#E6535F;border-right-color:transparent;animation:lilo-spin .8s linear infinite}
				.lilo-steps li.is-done{color:#2F3313}
				.lilo-steps li.is-done::before{background:#6FA23A;border-color:#6FA23A}
				.lilo-steps li.is-error{color:#b32d2e}
				.lilo-steps li.is-error::before{background:#E6535F;border-color:#E6535F}
				.lilo-steps small{color:#6A6E4B;margin-left:auto;text-align:right}
				.lilo-result{margin-top:18px;display:none}
				.lilo-result.is-visible{display:block}
				.lilo-opts label{display:block;margin:6px 0}
				@keyframes lilo-spin{to{transform:rotate(360deg)}}
			</style>
			<h1><?php esc_html_e( 'Lilo Cafe · One-click demo import', 'lilo-cafe' ); ?></h1>
			<div class="lilo-import__card">
				<div class="lilo-import__shot"><img src="<?php echo esc_url( LILO_URI . '/screenshot.png' ); ?>" alt=""></div>
				<div>
					<h2><?php esc_html_e( 'Build the complete site with one click', 'lilo-cafe' ); ?></h2>
					<p><?php esc_html_e( 'This sets up everything you see in the design:', 'lilo-cafe' ); ?></p>
					<ul class="lilo-list">
						<li><?php echo $has_el ? esc_html__( 'Elementor is active ✓', 'lilo-cafe' ) : ( $installed ? esc_html__( 'Activates Elementor (already installed)', 'lilo-cafe' ) : esc_html__( 'Installs and activates the free Elementor plugin', 'lilo-cafe' ) ); ?></li>
						<li><?php esc_html_e( 'Home page: hero, menu categories, signature lattes, raspados, our story, mission, visit & map, merch and social', 'lilo-cafe' ); ?></li>
						<li><?php esc_html_e( 'Menu page: page header, sticky category tabs and the full menu', 'lilo-cafe' ); ?></li>
						<li><?php esc_html_e( 'Header and footer you can edit in Elementor (Appearance > Header & Footer)', 'lilo-cafe' ); ?></li>
						<li><?php esc_html_e( 'Logo, product images and photos in your Media Library', 'lilo-cafe' ); ?></li>
						<li><?php esc_html_e( 'Homepage, navigation menu and Elementor settings', 'lilo-cafe' ); ?></li>
					</ul>
					<div class="lilo-opts">
						<label><input type="checkbox" id="lilo-opt-front" checked> <?php esc_html_e( 'Use the demo Home page as the site homepage', 'lilo-cafe' ); ?></label>
						<label><input type="checkbox" id="lilo-opt-title" checked> <?php esc_html_e( 'Set site title to “Lilo Cafe” and tagline to “Jasper, GA”', 'lilo-cafe' ); ?></label>
					</div>
					<p>
						<button type="button" class="button button-primary button-hero" id="lilo-import-start"><?php echo $imported ? esc_html__( 'Import again', 'lilo-cafe' ) : esc_html__( 'Import demo', 'lilo-cafe' ); ?></button>
					</p>
					<ol class="lilo-steps" id="lilo-steps">
						<?php foreach ( self::steps() as $key => $label ) : ?>
							<li data-step="<?php echo esc_attr( $key ); ?>"><span><?php echo esc_html( $label ); ?></span><small></small></li>
						<?php endforeach; ?>
					</ol>
					<div class="notice notice-success inline lilo-result" id="lilo-result">
						<p><strong id="lilo-result-text"></strong></p>
						<p>
							<a class="button button-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank"><?php esc_html_e( 'View site', 'lilo-cafe' ); ?></a>
							<a class="button" id="lilo-edit-home" href="<?php echo esc_url( $home_id ? admin_url( 'post.php?post=' . $home_id . '&action=elementor' ) : admin_url( 'edit.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Edit Home with Elementor', 'lilo-cafe' ); ?></a>
							<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=lilo_template' ) ); ?>"><?php esc_html_e( 'Edit header & footer', 'lilo-cafe' ); ?></a>
						</p>
					</div>
					<div class="notice notice-error inline lilo-result" id="lilo-error"><p id="lilo-error-text"></p></div>
				</div>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * AJAX
	 * ---------------------------------------------------------------- */

	public static function ajax_step() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_theme_options' ) || ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to import the demo.', 'lilo-cafe' ) ) );
		}

		$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		if ( ! array_key_exists( $step, self::steps() ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown step.', 'lilo-cafe' ) ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		wp_raise_memory_limit( 'admin' );

		$options = array(
			'front' => ! empty( $_POST['front'] ) && 'false' !== $_POST['front'],
			'title' => ! empty( $_POST['title'] ) && 'false' !== $_POST['title'],
		);

		try {
			$result = call_user_func( array( __CLASS__, 'step_' . $step ), $options );
		} catch ( Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( wp_parse_args( $result, array( 'done' => true, 'note' => '' ) ) );
	}

	/* ------------------------------------------------------------------
	 * Step 1: Elementor
	 * ---------------------------------------------------------------- */

	public static function step_plugin() {
		if ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
			return array( 'note' => __( 'already active', 'lilo-cafe' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . self::ELEMENTOR ) ) {
			if ( ! current_user_can( 'install_plugins' ) ) {
				return new WP_Error( 'lilo_cap', __( 'Please ask an administrator to install the Elementor plugin, then run the import again.', 'lilo-cafe' ) );
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => 'elementor',
					'fields' => array( 'sections' => false ),
				)
			);
			if ( is_wp_error( $api ) ) {
				return self::plugin_error( $api->get_error_message() );
			}

			$skin     = new WP_Ajax_Upgrader_Skin();
			$upgrader = new Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $api->download_link );

			if ( is_wp_error( $result ) ) {
				return self::plugin_error( $result->get_error_message() );
			}
			if ( is_wp_error( $skin->result ) ) {
				return self::plugin_error( $skin->result->get_error_message() );
			}
			if ( $skin->get_errors()->has_errors() ) {
				return self::plugin_error( $skin->get_error_messages() );
			}
			if ( ! $result ) {
				return self::plugin_error( __( 'WordPress could not write to the plugins folder (file permissions or FTP credentials needed).', 'lilo-cafe' ) );
			}
			wp_clean_plugins_cache();
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error( 'lilo_cap', __( 'Please ask an administrator to activate Elementor, then run the import again.', 'lilo-cafe' ) );
		}
		$activated = activate_plugin( self::ELEMENTOR, '', false, true );
		if ( is_wp_error( $activated ) ) {
			return self::plugin_error( $activated->get_error_message() );
		}

		// Elementor redirects to its onboarding after activation; skip it.
		delete_transient( 'elementor_activation_redirect' );

		return array( 'note' => __( 'installed & activated', 'lilo-cafe' ) );
	}

	protected static function plugin_error( $message ) {
		return new WP_Error(
			'lilo_plugin',
			sprintf(
				/* translators: 1: error, 2: plugin install URL */
				__( 'Elementor could not be installed automatically (%1$s). Install it from Plugins > Add New (search “Elementor”), activate it, then click Import again: %2$s', 'lilo-cafe' ),
				html_entity_decode( wp_strip_all_tags( $message ), ENT_QUOTES, 'UTF-8' ),
				admin_url( 'plugin-install.php?s=elementor&tab=search&type=term' )
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Step 2 & 3: media
	 * ---------------------------------------------------------------- */

	/**
	 * Every image referenced by the demo: [ key => [ 'asset' => file, 'remote' => url ] ].
	 */
	public static function demo_images() {
		$found = array();
		$walk  = function ( $value ) use ( &$walk, &$found ) {
			if ( ! is_array( $value ) ) {
				return;
			}
			if ( isset( $value['lilo_asset'] ) ) {
				$key           = ! empty( $value['lilo_remote'] ) ? $value['lilo_remote'] : $value['lilo_asset'];
				$found[ $key ] = array(
					'asset'  => $value['lilo_asset'],
					'remote' => ! empty( $value['lilo_remote'] ) ? $value['lilo_remote'] : '',
				);
				// A remote photo's fallback also needs to exist locally.
				if ( ! empty( $value['lilo_remote'] ) && ! isset( $found[ $value['lilo_asset'] ] ) ) {
					$found[ $value['lilo_asset'] ] = array(
						'asset'  => $value['lilo_asset'],
						'remote' => '',
					);
				}
				return;
			}
			foreach ( $value as $v ) {
				$walk( $v );
			}
		};
		foreach ( lilo_demo_pages() as $page ) {
			$walk( $page );
		}
		$walk( lilo_demo_defaults( 'lilo-header' ) );
		$walk( lilo_demo_defaults( 'lilo-footer' ) );
		return $found;
	}

	protected static function media_map() {
		$map = get_option( self::MEDIA_OPT, array() );
		return is_array( $map ) ? $map : array();
	}

	protected static function media_id( $key ) {
		$map = self::media_map();
		if ( ! empty( $map[ $key ] ) && 'attachment' === get_post_type( $map[ $key ] ) ) {
			return (int) $map[ $key ];
		}
		return 0;
	}

	protected static function remember_media( $key, $id ) {
		$map         = self::media_map();
		$map[ $key ] = (int) $id;
		update_option( self::MEDIA_OPT, $map, false );
	}

	protected static function media_includes() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	/**
	 * Bundled images → Media Library.
	 */
	public static function step_media() {
		self::media_includes();
		$count = 0;
		foreach ( self::demo_images() as $key => $img ) {
			if ( $img['remote'] || self::media_id( $key ) ) {
				continue;
			}
			$id = self::import_local( $img['asset'] );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			self::remember_media( $key, $id );
			$count++;
		}
		/* translators: %d: number of images */
		return array( 'note' => sprintf( _n( '%d image', '%d images', $count, 'lilo-cafe' ), $count ) );
	}

	protected static function import_local( $asset ) {
		$path = LILO_DIR . '/assets/images/' . $asset;
		if ( ! file_exists( $path ) ) {
			return new WP_Error( 'lilo_missing', sprintf( 'Missing theme image: %s', $asset ) );
		}
		$name = 'lilo-' . basename( $asset );
		$tmp  = wp_tempnam( $name );
		if ( ! $tmp || ! copy( $path, $tmp ) ) {
			return new WP_Error( 'lilo_copy', __( 'Could not copy a demo image to the uploads folder. Check that wp-content/uploads is writable.', 'lilo-cafe' ) );
		}
		return self::sideload( $tmp, $name, $asset );
	}

	protected static function sideload( $tmp, $name, $key ) {
		$file = array(
			'name'     => $name,
			'tmp_name' => $tmp,
		);
		$id = media_handle_sideload( $file, 0, self::title_from( $name ) );
		if ( is_wp_error( $id ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return $id;
		}
		update_post_meta( $id, '_lilo_demo_key', $key );
		update_post_meta( $id, '_wp_attachment_image_alt', self::title_from( $name ) );
		return $id;
	}

	protected static function title_from( $name ) {
		$base = preg_replace( '/\.[a-z0-9]+$/i', '', $name );
		$base = preg_replace( '/^(lilo-)?(p-)?/', '', $base );
		return ucwords( str_replace( array( '-', '_' ), ' ', $base ) );
	}

	/**
	 * Stock photos: one download per request; falls back to a bundled image.
	 */
	public static function step_photos() {
		self::media_includes();
		$images  = self::demo_images();
		$pending = array();
		foreach ( $images as $key => $img ) {
			if ( $img['remote'] && ! self::media_id( $key ) ) {
				$pending[ $key ] = $img;
			}
		}
		if ( ! $pending ) {
			return array( 'note' => __( 'ready', 'lilo-cafe' ) );
		}

		$key  = key( $pending );
		$img  = current( $pending );
		$slug = preg_replace( '/[^a-z0-9-]/', '', strtolower( strtok( basename( wp_parse_url( $img['remote'], PHP_URL_PATH ) ), '?' ) ) );
		$tmp  = download_url( $img['remote'], 45 );
		$id   = 0;
		if ( ! is_wp_error( $tmp ) ) {
			$id = self::sideload( $tmp, 'lilo-' . ( $slug ? $slug : 'photo' ) . '.jpg', $key );
		}
		if ( ! $id || is_wp_error( $id ) ) {
			// Offline or blocked: reuse the bundled fallback image.
			$id = self::media_id( $img['asset'] );
			if ( ! $id ) {
				$id = self::import_local( $img['asset'] );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				self::remember_media( $img['asset'], $id );
			}
		}
		self::remember_media( $key, $id );

		$left = count( $pending ) - 1;
		return array(
			'done' => 0 === $left,
			/* translators: %d: photos left */
			'note' => $left ? sprintf( __( '%d left', 'lilo-cafe' ), $left ) : __( 'ready', 'lilo-cafe' ),
		);
	}

	/* ------------------------------------------------------------------
	 * Elementor data
	 * ---------------------------------------------------------------- */

	protected static function el_id() {
		return substr( md5( uniqid( '', true ) . wp_rand() ), 0, 7 );
	}

	/**
	 * Resolve image hints and URL tokens, and give repeater rows an _id.
	 */
	public static function prepare( $value ) {
		if ( is_string( $value ) ) {
			return lilo_expand_tokens( $value );
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( isset( $value['lilo_asset'] ) ) {
			$key = ! empty( $value['lilo_remote'] ) ? $value['lilo_remote'] : $value['lilo_asset'];
			$id  = self::media_id( $key );
			if ( ! $id ) {
				$id = self::media_id( $value['lilo_asset'] );
			}
			if ( $id ) {
				return array(
					'url'    => wp_get_attachment_url( $id ),
					'id'     => $id,
					'alt'    => '',
					'source' => 'library',
				);
			}
			return array(
				'url' => lilo_asset( $value['lilo_asset'] ),
				'id'  => '',
			);
		}
		$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
		foreach ( $value as $k => $v ) {
			$v = self::prepare( $v );
			if ( $is_list && is_array( $v ) && ! isset( $v['_id'] ) && array_keys( $v ) !== range( 0, count( $v ) - 1 ) ) {
				$v['_id'] = self::el_id();
			}
			$value[ $k ] = $v;
		}
		return $value;
	}

	protected static function container( $widgets, $settings = array() ) {
		$zero = array(
			'unit'     => 'px',
			'top'      => '0',
			'right'    => '0',
			'bottom'   => '0',
			'left'     => '0',
			'isLinked' => true,
		);
		$elements = array();
		foreach ( $widgets as $w ) {
			$elements[] = array(
				'id'         => self::el_id(),
				'elType'     => 'widget',
				'widgetType' => $w[0],
				'settings'   => self::prepare( $w[1] ),
				'elements'   => array(),
			);
		}
		return array(
			'id'       => self::el_id(),
			'elType'   => 'container',
			'isInner'  => false,
			'settings' => array_merge(
				array(
					'content_width' => 'full',
					'padding'       => $zero,
					'flex_gap'      => array(
						'unit'     => 'px',
						'size'     => 0,
						'column'   => '0',
						'row'      => '0',
						'isLinked' => true,
					),
				),
				$settings
			),
			'elements' => $elements,
		);
	}

	protected static function save_elementor( $post_id, $data, $type ) {
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_template_type', $type );
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
		update_post_meta( $post_id, '_elementor_page_settings', array( 'hide_title' => 'yes' ) );
		delete_post_meta( $post_id, '_elementor_css' );
		delete_post_meta( $post_id, '_elementor_element_cache' );
	}

	/**
	 * Find the post this import created before, or create it.
	 */
	protected static function upsert( $post_type, $key, $title, $slug ) {
		$state = get_option( self::STATE_OPT, array() );
		$group = 'page' === $post_type ? 'pages' : 'templates';
		$id    = ! empty( $state[ $group ][ $key ] ) ? (int) $state[ $group ][ $key ] : 0;

		if ( $id && get_post_type( $id ) === $post_type && 'trash' !== get_post_status( $id ) ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);
		} else {
			$id = wp_insert_post(
				array(
					'post_type'    => $post_type,
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_status'  => 'publish',
					'post_content' => '',
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				return $id;
			}
		}
		update_post_meta( $id, '_lilo_demo', $key );

		$state[ $group ][ $key ] = $id;
		update_option( self::STATE_OPT, $state, false );
		return $id;
	}

	/* ------------------------------------------------------------------
	 * Step 4: pages
	 * ---------------------------------------------------------------- */

	public static function step_pages() {
		$pages = lilo_demo_pages();

		// Create both first so {menu} links resolve to the real Menu page.
		$ids = array();
		foreach ( $pages as $key => $page ) {
			$id = self::upsert( 'page', $key, $page['title'], $page['slug'] );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$ids[ $key ] = $id;
		}
		update_option( 'lilo_menu_page_id', $ids['menu'] );

		foreach ( $pages as $key => $page ) {
			$data = array();
			foreach ( $page['containers'] as $c ) {
				$data[] = self::container( $c['widgets'], $c['settings'] );
			}
			self::save_elementor( $ids[ $key ], $data, 'wp-page' );
			update_post_meta( $ids[ $key ], '_wp_page_template', 'elementor_header_footer' );
		}

		return array( 'note' => __( 'Home, Menu', 'lilo-cafe' ) );
	}

	/* ------------------------------------------------------------------
	 * Step 5: header & footer templates
	 * ---------------------------------------------------------------- */

	public static function step_templates() {
		$made = array();
		foreach ( array(
			'header' => array( __( 'Site Header', 'lilo-cafe' ), 'lilo-header' ),
			'footer' => array( __( 'Site Footer', 'lilo-cafe' ), 'lilo-footer' ),
		) as $key => $def ) {
			$id = self::upsert( 'lilo_template', $key, $def[0], 'lilo-' . $key );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$data = array( self::container( array( array( $def[1], lilo_demo_defaults( $def[1] ) ) ) ) );
			self::save_elementor( $id, $data, 'wp-post' );
			set_theme_mod( 'lilo_' . $key . '_template', $id );
			$made[] = $def[0];
		}
		return array( 'note' => implode( ', ', $made ) );
	}

	/* ------------------------------------------------------------------
	 * Step 6: settings
	 * ---------------------------------------------------------------- */

	public static function step_settings( $options ) {
		$state = get_option( self::STATE_OPT, array() );
		$home  = ! empty( $state['pages']['home'] ) ? (int) $state['pages']['home'] : 0;
		$menu  = ! empty( $state['pages']['menu'] ) ? (int) $state['pages']['menu'] : 0;

		if ( $options['front'] && $home ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home );
		}
		if ( $options['title'] ) {
			update_option( 'blogname', 'Lilo Cafe' );
			update_option( 'blogdescription', 'Jasper, GA' );
		}

		// Logo.
		$logo = self::media_id( 'lilo-logo.png' );
		if ( $logo ) {
			set_theme_mod( 'custom_logo', $logo );
			if ( ! get_option( 'site_icon' ) ) {
				$icon = self::media_id( 'hibiscus.png' );
				if ( $icon ) {
					update_option( 'site_icon', $icon );
				}
			}
		}

		// Primary navigation (used by the theme header and "WordPress menu" source).
		self::build_menu( $home, $menu );

		// Elementor options.
		$cpt = get_option( 'elementor_cpt_support', array( 'page', 'post' ) );
		$cpt = is_array( $cpt ) ? $cpt : array( 'page', 'post' );
		foreach ( array( 'page', 'post', 'lilo_template' ) as $type ) {
			if ( ! in_array( $type, $cpt, true ) ) {
				$cpt[] = $type;
			}
		}
		update_option( 'elementor_cpt_support', $cpt );
		update_option( 'elementor_disable_color_schemes', 'yes' );
		update_option( 'elementor_disable_typography_schemes', 'yes' );
		if ( 'inactive' === get_option( 'elementor_experiment-container' ) ) {
			update_option( 'elementor_experiment-container', 'active' );
		}
		delete_transient( 'elementor_activation_redirect' );

		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		lilo_register_template_cpt();
		flush_rewrite_rules( false );

		$state['done'] = time();
		update_option( self::STATE_OPT, $state, false );

		return array(
			'note'     => __( 'done', 'lilo-cafe' ),
			'editHome' => $home ? admin_url( 'post.php?post=' . $home . '&action=elementor' ) : '',
		);
	}

	protected static function build_menu( $home, $menu_page ) {
		$name = 'Lilo Primary';
		$menu = wp_get_nav_menu_object( $name );
		if ( $menu ) {
			foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
				wp_delete_post( $item->ID, true );
			}
			$menu_id = $menu->term_id;
		} else {
			$menu_id = wp_create_nav_menu( $name );
			if ( is_wp_error( $menu_id ) ) {
				return;
			}
		}

		$items = array();
		if ( $home ) {
			$items[] = array( 'menu-item-title' => 'Home', 'menu-item-object' => 'page', 'menu-item-object-id' => $home, 'menu-item-type' => 'post_type' );
		}
		if ( $menu_page ) {
			$items[] = array( 'menu-item-title' => 'Menu', 'menu-item-object' => 'page', 'menu-item-object-id' => $menu_page, 'menu-item-type' => 'post_type' );
		}
		$items[] = array( 'menu-item-title' => 'About', 'menu-item-url' => home_url( '/#about' ), 'menu-item-type' => 'custom' );
		$items[] = array( 'menu-item-title' => 'Visit', 'menu-item-url' => home_url( '/#visit' ), 'menu-item-type' => 'custom' );

		foreach ( $items as $item ) {
			$item['menu-item-status'] = 'publish';
			wp_update_nav_menu_item( $menu_id, 0, $item );
		}

		$locations            = get_theme_mod( 'nav_menu_locations', array() );
		$locations['primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}
}

/**
 * {home} / {menu} tokens → real URLs (used when saving demo data).
 */
function lilo_expand_tokens( $value ) {
	if ( false === strpos( $value, '{home}' ) && false === strpos( $value, '{menu}' ) ) {
		return $value;
	}
	return lilo_expand_url( $value );
}

Lilo_Demo_Importer::init();
