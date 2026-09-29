<?php
/**
 * "Just Move Kit" admin screen: one-click demo import, template export,
 * lead email setting, plus the post-activation prompt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JMK_Admin {

	const SLUG        = 'jmk-kit';
	const OPT_PROMPT  = 'jmk_show_import_prompt';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		add_action( 'admin_notices', array( __CLASS__, 'activation_notice' ) );
		add_action( 'admin_post_jmk_import_demo', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_jmk_export_template', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_jmk_save_settings', array( __CLASS__, 'handle_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( JMK_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function on_activate() {
		add_option( self::OPT_PROMPT, 1 );
	}

	public static function page_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Import demo', 'jmk' ) . '</a>' );
		return $links;
	}

	public static function menu() {
		add_menu_page(
			__( 'Just Move Kit', 'jmk' ),
			__( 'Just Move Kit', 'jmk' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-car',
			58
		);
		add_submenu_page( self::SLUG, __( 'Demo import', 'jmk' ), __( 'Demo import', 'jmk' ), 'manage_options', self::SLUG, array( __CLASS__, 'render_page' ) );
	}

	public static function activation_notice() {
		if ( ! get_option( self::OPT_PROMPT ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'toplevel_page_' . self::SLUG === $screen->id ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p><strong>%s</strong> %s <a class="button button-primary" href="%s">%s</a></p></div>',
			esc_html__( 'Just Move DFW Template Kit is active.', 'jmk' ),
			esc_html__( 'Import the home page demo in one click:', 'jmk' ),
			esc_url( self::page_url() ),
			esc_html__( 'Go to demo import', 'jmk' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		delete_option( self::OPT_PROMPT );

		$ready    = JMK_Plugin::elementor_ready();
		$page_id  = JMK_Demo_Importer::imported_page_id();
		$tb_ready = $ready && JMK_Demo_Importer::theme_builder_available();
		$parts    = array(
			'header' => JMK_Demo_Importer::template_id( JMK_Demo_Importer::OPT_HEADER_ID ),
			'footer' => JMK_Demo_Importer::template_id( JMK_Demo_Importer::OPT_FOOTER_ID ),
		);
		$email    = get_option( JMK_Leads::OPT_EMAIL, get_option( 'admin_email' ) );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status   = isset( $_GET['jmk'] ) ? sanitize_key( $_GET['jmk'] ) : '';
		$message  = isset( $_GET['jmk_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['jmk_msg'] ) ) : '';
		// phpcs:enable
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Just Move DFW – Elementor Template Kit', 'jmk' ); ?></h1>

			<?php if ( 'imported' === $status && $page_id ) : ?>
				<div class="notice notice-success"><p>
					<?php esc_html_e( 'Demo imported.', 'jmk' ); ?>
					<a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank"><?php esc_html_e( 'View page', 'jmk' ); ?></a> ·
					<a href="<?php echo esc_url( self::edit_url( $page_id ) ); ?>"><?php esc_html_e( 'Edit with Elementor', 'jmk' ); ?></a>
					<?php $quote_page = JMK_Demo_Importer::quote_page_id(); ?>
					<?php if ( $quote_page ) : ?>
						· <a href="<?php echo esc_url( get_permalink( $quote_page ) ); ?>" target="_blank"><?php esc_html_e( 'View quote page', 'jmk' ); ?></a>
					<?php endif; ?>
					<?php if ( $parts['header'] ) : ?>
						· <a href="<?php echo esc_url( self::edit_url( $parts['header'] ) ); ?>"><?php esc_html_e( 'Edit header', 'jmk' ); ?></a>
					<?php endif; ?>
					<?php if ( $parts['footer'] ) : ?>
						· <a href="<?php echo esc_url( self::edit_url( $parts['footer'] ) ); ?>"><?php esc_html_e( 'Edit footer', 'jmk' ); ?></a>
					<?php endif; ?>
				</p></div>
			<?php elseif ( 'error' === $status ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $message ); ?></p></div>
			<?php elseif ( 'saved' === $status ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Settings saved.', 'jmk' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $ready ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php
					printf(
						/* translators: %s: minimum Elementor version */
						esc_html__( 'Install and activate Elementor %s or newer first — the kit widgets and demo import need it.', 'jmk' ),
						esc_html( JMK_MIN_ELEMENTOR )
					);
					?>
				</p></div>
			<?php endif; ?>

			<div class="card" style="max-width:760px">
				<h2><?php esc_html_e( 'One-click demo import', 'jmk' ); ?></h2>
				<p><?php esc_html_e( 'Creates a new page built entirely from the Just Move DFW widgets (hero + quote form, trust strip, services, reviews, FAQ and more).', 'jmk' ); ?></p>
				<?php if ( $page_id ) : ?>
					<p><em>
						<?php esc_html_e( 'Already imported:', 'jmk' ); ?>
						<a href="<?php echo esc_url( self::edit_url( $page_id ) ); ?>"><?php echo esc_html( get_the_title( $page_id ) ); ?></a>.
						<?php esc_html_e( 'Importing again creates another copy.', 'jmk' ); ?>
					</em></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="jmk_import_demo">
					<?php wp_nonce_field( 'jmk_import_demo' ); ?>
					<p><label><input type="checkbox" name="set_front" value="1" checked> <?php esc_html_e( 'Set as the site front page', 'jmk' ); ?></label></p>
					<?php if ( $tb_ready ) : ?>
						<p><label><input type="checkbox" name="theme_parts" value="1" checked>
							<?php esc_html_e( 'Import header & footer into Elementor Pro Theme Builder (Header / Footer, Entire Site)', 'jmk' ); ?></label><br>
							<span class="description">
								<?php
								if ( $parts['header'] || $parts['footer'] ) {
									esc_html_e( 'The kit header/footer templates already exist — they are kept (with your edits) and set to Entire Site again.', 'jmk' );
								} else {
									esc_html_e( 'The footer template also carries the mobile call bar. The page uses the "Elementor Full Width" template so the theme header/footer show.', 'jmk' );
								}
								?>
							</span></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Elementor Pro (Theme Builder) not detected: header and footer are placed inside the page, which uses the blank Elementor Canvas template. Activate Elementor Pro and import again to get Theme Builder header/footer templates.', 'jmk' ); ?></p>
					<?php endif; ?>
					<?php
					$quote_page = JMK_Demo_Importer::quote_page_id();
					if ( ! $quote_page && ! get_page_by_path( JMK_Demo_Importer::QUOTE_SLUG ) ) :
						?>
						<p><label><input type="checkbox" name="quote_page" value="1" checked> <?php esc_html_e( 'Also create a "Get a Quote" page at /quote/ with the quote builder', 'jmk' ); ?></label></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'A /quote/ page already exists, so no quote page will be created. Add the "JM Quote Builder" widget or the [jmk_quote_builder] shortcode to it.', 'jmk' ); ?></p>
					<?php endif; ?>
					<p><label><input type="checkbox" name="library" value="1" checked> <?php esc_html_e( 'Also save to Elementor → Templates → Saved Templates', 'jmk' ); ?></label></p>
					<p><button type="submit" class="button button-primary button-hero" <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Import demo now', 'jmk' ); ?></button></p>
				</form>
			</div>

			<div class="card" style="max-width:760px">
				<h2><?php esc_html_e( 'Elementor template file', 'jmk' ); ?></h2>
				<p><?php esc_html_e( 'Download the layout as an Elementor .json template (Templates → Import Templates). The Just Move Kit plugin must be active on the site you import it into.', 'jmk' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="jmk_export_template">
					<?php wp_nonce_field( 'jmk_export_template' ); ?>
					<p><button type="submit" class="button" <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Download template (.json)', 'jmk' ); ?></button></p>
				</form>
			</div>

			<div class="card" style="max-width:760px">
				<h2><?php esc_html_e( 'Quote form leads', 'jmk' ); ?></h2>
				<p>
					<?php esc_html_e( 'Every hero form submission is stored under', 'jmk' ); ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . JMK_Leads::POST_TYPE ) ); ?>"><?php esc_html_e( 'Just Move Kit → Leads', 'jmk' ); ?></a>
					<?php esc_html_e( 'and emailed to:', 'jmk' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="jmk_save_settings">
					<?php wp_nonce_field( 'jmk_save_settings' ); ?>
					<p><input type="email" class="regular-text" name="lead_email" value="<?php echo esc_attr( $email ); ?>"></p>
					<p><button type="submit" class="button"><?php esc_html_e( 'Save', 'jmk' ); ?></button></p>
				</form>
			</div>

			<div class="card" style="max-width:760px">
				<h2><?php esc_html_e( 'Widgets in this kit', 'jmk' ); ?></h2>
				<p><?php esc_html_e( 'Find them in the Elementor panel under the "Just Move DFW" category. Each one is a full page section with its own content and style controls:', 'jmk' ); ?></p>
				<ol>
					<?php
					foreach ( JMK_Plugin::WIDGETS as $slug => $class ) {
						echo '<li><code>jmk-' . esc_html( $slug ) . '</code></li>';
					}
					?>
				</ol>
			</div>
		</div>
		<?php
	}

	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jmk' ), 403 );
		}
		check_admin_referer( 'jmk_import_demo' );

		$result = JMK_Demo_Importer::import(
			array(
				'set_front'   => ! empty( $_POST['set_front'] ),
				'library'     => ! empty( $_POST['library'] ),
				'theme_parts' => ! empty( $_POST['theme_parts'] ),
				'quote_page'  => ! empty( $_POST['quote_page'] ),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( self::page_url( array( 'jmk' => 'error', 'jmk_msg' => rawurlencode( $result->get_error_message() ) ) ) );
		} else {
			wp_safe_redirect( self::page_url( array( 'jmk' => 'imported' ) ) );
		}
		exit;
	}

	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jmk' ), 403 );
		}
		check_admin_referer( 'jmk_export_template' );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="just-move-dfw-home.json"' );
		echo JMK_Demo_Importer::export_json(); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public static function handle_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jmk' ), 403 );
		}
		check_admin_referer( 'jmk_save_settings' );

		$email = isset( $_POST['lead_email'] ) ? sanitize_email( wp_unslash( $_POST['lead_email'] ) ) : '';
		// Empty or invalid → fall back to the site admin email instead of silently dropping alerts.
		if ( is_email( $email ) ) {
			update_option( JMK_Leads::OPT_EMAIL, $email );
		} else {
			delete_option( JMK_Leads::OPT_EMAIL );
		}
		wp_safe_redirect( self::page_url( array( 'jmk' => 'saved' ) ) );
		exit;
	}

	private static function edit_url( $post_id ) {
		return add_query_arg(
			array(
				'post'   => $post_id,
				'action' => 'elementor',
			),
			admin_url( 'post.php' )
		);
	}
}
