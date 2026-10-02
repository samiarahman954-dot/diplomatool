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
		add_action( 'admin_post_jmk_sync', array( __CLASS__, 'handle_sync' ) );
		add_action( 'admin_post_jmk_reset', array( __CLASS__, 'handle_reset' ) );
		add_action( 'admin_post_jmk_restore', array( __CLASS__, 'handle_restore' ) );
		add_action( 'admin_post_jmk_global_styles', array( __CLASS__, 'handle_global_styles' ) );
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
			<?php elseif ( 'synced' === $status ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( self::sync_summary() ); ?></p></div>
			<?php elseif ( 'globals' === $status ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( self::globals_summary() ); ?></p></div>
			<?php elseif ( 'reset' === $status ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Reset to the demo layout. Your previous version was backed up — see Backups below to restore it.', 'jmk' ); ?></p></div>
			<?php elseif ( 'restored' === $status ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Backup restored. The version you had just before restoring was backed up too.', 'jmk' ); ?></p></div>
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

			<?php
			if ( $page_id ) {
				self::render_sync_cards( $ready );
			}
			?>

			<?php if ( ! $page_id ) : ?>
			<div class="card" style="max-width:760px">
				<h2><?php esc_html_e( 'One-click demo import', 'jmk' ); ?></h2>
				<p><?php esc_html_e( 'Creates a new page built entirely from the Just Move DFW widgets (hero + quote form, trust strip, services, reviews, FAQ and more). Run it once — afterwards use Sync, which keeps your edits.', 'jmk' ); ?></p>
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
					<?php self::render_global_checkboxes(); ?>
					<p><label><input type="checkbox" name="library" value="1" checked> <?php esc_html_e( 'Also save to Elementor → Templates → Saved Templates', 'jmk' ); ?></label></p>
					<p><button type="submit" class="button button-primary button-hero" <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Import demo now', 'jmk' ); ?></button></p>
				</form>
			</div>
			<?php endif; ?>

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

		// Already imported (double click, second tab…): sync instead of building a copy.
		if ( JMK_Demo_Importer::imported_page_id() && JMK_Plugin::elementor_ready() ) {
			set_transient( 'jmk_sync_report_' . get_current_user_id(), JMK_Demo_Importer::sync(), 120 );
			wp_safe_redirect( self::page_url( array( 'jmk' => 'synced' ) ) );
			exit;
		}

		$result = JMK_Demo_Importer::import(
			array(
				'set_front'   => ! empty( $_POST['set_front'] ),
				'library'     => ! empty( $_POST['library'] ),
				'theme_parts' => ! empty( $_POST['theme_parts'] ),
				'quote_page'    => ! empty( $_POST['quote_page'] ),
				'global_styles' => ! empty( $_POST['global_styles'] ),
				'global_system' => ! empty( $_POST['global_system'] ),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( self::page_url( array( 'jmk' => 'error', 'jmk_msg' => rawurlencode( $result->get_error_message() ) ) ) );
		} else {
			wp_safe_redirect( self::page_url( array( 'jmk' => 'imported' ) ) );
		}
		exit;
	}

	/**
	 * Sync / reset / backups, shown once the kit is on the site.
	 */
	private static function render_sync_cards( $ready ) {
		$targets = JMK_Sync::targets();
		$post    = esc_url( admin_url( 'admin-post.php' ) );
		?>
		<div class="card" style="max-width:760px">
			<h2><?php esc_html_e( 'Sync with the latest kit (safe)', 'jmk' ); ?></h2>
			<p><?php esc_html_e( 'Use this after updating the plugin. Design and feature updates already apply as soon as the plugin is updated; Sync adds any new sections a newer version introduced.', 'jmk' ); ?></p>
			<ul style="list-style:disc;margin-left:20px">
				<li><?php esc_html_e( 'Your text, images, links, colours and style settings are never changed.', 'jmk' ); ?></li>
				<li><?php esc_html_e( 'Your section order and anything you added yourself stay as they are.', 'jmk' ); ?></li>
				<li><?php esc_html_e( 'Sections or pages you deleted are not brought back.', 'jmk' ); ?></li>
				<li><?php esc_html_e( 'A backup is taken before anything is changed.', 'jmk' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'Kit content on this site:', 'jmk' ); ?>
				<?php
				$links = array();
				foreach ( $targets as $t ) {
					$links[] = '<a href="' . esc_url( self::edit_url( $t[1] ) ) . '">' . esc_html( $t[0] ) . '</a>';
				}
				echo implode( ' · ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
				?>
			</p>
			<form method="post" action="<?php echo $post; // phpcs:ignore ?>">
				<input type="hidden" name="action" value="jmk_sync">
				<?php wp_nonce_field( 'jmk_sync' ); ?>
				<p><button type="submit" class="button button-primary button-hero" <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Sync now', 'jmk' ); ?></button></p>
			</form>
		</div>

		<div class="card" style="max-width:760px">
			<h2><?php esc_html_e( 'Elementor Global styles', 'jmk' ); ?></h2>
			<p><?php esc_html_e( 'Adds the kit\'s colours (JM Yellow, JM Blue, JM Navy…) and fonts (Anton, Inter, Archivo, Hanken Grotesk) to Elementor → Site Settings → Global Colors / Global Fonts. The kit then follows them: change "JM Yellow" there and every kit section updates. You can also pick them in any other Elementor widget.', 'jmk' ); ?></p>
			<p><em>
				<?php
				echo esc_html(
					JMK_Global_Styles::applied()
						? __( 'Already added. Running it again only adds what is missing — colours and fonts you edited are kept.', 'jmk' )
						: __( 'Not added yet.', 'jmk' )
				);
				?>
			</em></p>
			<form method="post" action="<?php echo $post; // phpcs:ignore ?>">
				<input type="hidden" name="action" value="jmk_global_styles">
				<?php wp_nonce_field( 'jmk_global_styles' ); ?>
				<?php self::render_global_checkboxes( false ); ?>
				<p><button type="submit" class="button button-primary" <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Add to Elementor Global styles', 'jmk' ); ?></button></p>
			</form>
		</div>

		<div class="card" style="max-width:760px;border-left:4px solid #d63638">
			<h2><?php esc_html_e( 'Reset to demo layout', 'jmk' ); ?></h2>
			<p><?php esc_html_e( 'Replaces the selected page/template with the original demo layout. This removes your edits on it — a backup is taken first so you can restore it below. The page keeps its address and front-page setting.', 'jmk' ); ?></p>
			<form method="post" action="<?php echo $post; // phpcs:ignore ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Replace the selected content with the demo layout? A backup is taken first.', 'jmk' ) ); ?>');">
				<input type="hidden" name="action" value="jmk_reset">
				<?php wp_nonce_field( 'jmk_reset' ); ?>
				<p>
					<?php foreach ( $targets as $key => $t ) : ?>
						<label style="margin-right:14px"><input type="checkbox" name="targets[]" value="<?php echo esc_attr( $key ); ?>"> <?php echo esc_html( $t[0] ); ?></label>
					<?php endforeach; ?>
				</p>
				<p><label><input type="checkbox" name="confirm" value="1" required> <?php esc_html_e( 'I understand my edits on the selected items will be replaced.', 'jmk' ); ?></label></p>
				<p><button type="submit" class="button" style="color:#b32d2e;border-color:#b32d2e" <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Reset selected', 'jmk' ); ?></button></p>
			</form>
		</div>

		<div class="card" style="max-width:760px">
			<h2><?php esc_html_e( 'Backups', 'jmk' ); ?></h2>
			<p><?php esc_html_e( 'Taken automatically before every sync, reset or restore (last 5 per item). Your own edits in Elementor are also kept in Elementor\'s Revisions panel.', 'jmk' ); ?></p>
			<?php
			$any = false;
			foreach ( JMK_Sync::backup_targets() as $key => $t ) :
				$backups = JMK_Sync::backups( $t[1] );
				if ( ! $backups ) {
					continue;
				}
				$any = true;
				?>
				<h4 style="margin-bottom:4px"><?php echo esc_html( $t[0] ); ?></h4>
				<table class="widefat striped" style="margin-bottom:12px"><tbody>
				<?php foreach ( $backups as $i => $b ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $b['time'] ) ); ?></td>
						<td><?php echo esc_html( self::reason_label( $b['reason'] ) ); ?></td>
						<td style="text-align:right">
							<form method="post" action="<?php echo $post; // phpcs:ignore ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Restore this backup? Your current version is backed up first.', 'jmk' ) ); ?>');">
								<input type="hidden" name="action" value="jmk_restore">
								<input type="hidden" name="target" value="<?php echo esc_attr( $key ); ?>">
								<input type="hidden" name="index" value="<?php echo (int) $i; ?>">
								<?php wp_nonce_field( 'jmk_restore' ); ?>
								<button type="submit" class="button button-small"><?php esc_html_e( 'Restore', 'jmk' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php endforeach; ?>
			<?php if ( ! $any ) : ?>
				<p><em><?php esc_html_e( 'No backups yet.', 'jmk' ); ?></em></p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function reason_label( $reason ) {
		$labels = array(
			'sync'           => __( 'Before sync', 'jmk' ),
			'reset'          => __( 'Before reset to demo', 'jmk' ),
			'before-restore' => __( 'Before restoring a backup', 'jmk' ),
			'global-styles'  => __( 'Before adding global styles', 'jmk' ),
		);
		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : $reason;
	}

	private static function sync_summary() {
		$report = get_transient( 'jmk_sync_report_' . get_current_user_id() );
		if ( ! is_array( $report ) ) {
			return __( 'Sync complete. Your content and styles were not changed.', 'jmk' );
		}
		$added = array();
		foreach ( $report as $key => $types ) {
			if ( 'created' !== $key && $types ) {
				$added[] = count( $types );
			}
		}
		$parts = array();
		if ( $added ) {
			/* translators: %d: number of sections */
			$parts[] = sprintf( _n( '%d new section added.', '%d new sections added.', array_sum( $added ), 'jmk' ), array_sum( $added ) );
		}
		if ( ! empty( $report['created'] ) ) {
			/* translators: %s: list of created items */
			$parts[] = sprintf( __( 'Created: %s.', 'jmk' ), implode( ', ', $report['created'] ) );
		}
		if ( ! $parts ) {
			$parts[] = __( 'Everything is already up to date.', 'jmk' );
		}
		$parts[] = __( 'Your content and styles were not changed.', 'jmk' );
		return __( 'Sync complete.', 'jmk' ) . ' ' . implode( ' ', $parts );
	}

	public static function handle_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jmk' ), 403 );
		}
		check_admin_referer( 'jmk_sync' );
		if ( ! JMK_Plugin::elementor_ready() ) {
			wp_safe_redirect( self::page_url( array( 'jmk' => 'error', 'jmk_msg' => rawurlencode( __( 'Elementor must be active.', 'jmk' ) ) ) ) );
			exit;
		}
		set_transient( 'jmk_sync_report_' . get_current_user_id(), JMK_Demo_Importer::sync(), 120 );
		wp_safe_redirect( self::page_url( array( 'jmk' => 'synced' ) ) );
		exit;
	}

	public static function handle_reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jmk' ), 403 );
		}
		check_admin_referer( 'jmk_reset' );
		$chosen  = isset( $_POST['targets'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['targets'] ) ) : array();
		$targets = JMK_Sync::targets();
		if ( empty( $_POST['confirm'] ) || ! array_intersect( $chosen, array_keys( $targets ) ) ) {
			wp_safe_redirect( self::page_url( array( 'jmk' => 'error', 'jmk_msg' => rawurlencode( __( 'Nothing was reset: choose what to reset and tick the confirmation box.', 'jmk' ) ) ) ) );
			exit;
		}
		foreach ( $chosen as $key ) {
			if ( isset( $targets[ $key ] ) ) {
				JMK_Sync::reset_document( $key, $targets[ $key ][1] );
			}
		}
		wp_safe_redirect( self::page_url( array( 'jmk' => 'reset' ) ) );
		exit;
	}

	public static function handle_restore() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jmk' ), 403 );
		}
		check_admin_referer( 'jmk_restore' );
		$key     = isset( $_POST['target'] ) ? sanitize_key( wp_unslash( $_POST['target'] ) ) : '';
		$index   = isset( $_POST['index'] ) ? (int) $_POST['index'] : -1;
		$targets = JMK_Sync::backup_targets();
		$ok      = isset( $targets[ $key ] ) && JMK_Sync::restore( $targets[ $key ][1], $index );
		wp_safe_redirect( self::page_url( $ok ? array( 'jmk' => 'restored' ) : array( 'jmk' => 'error', 'jmk_msg' => rawurlencode( __( 'That backup could not be restored.', 'jmk' ) ) ) ) );
		exit;
	}

	/**
	 * Global-styles checkboxes, shared by the import form and the card.
	 *
	 * @param bool $with_main Include the main "add globals" checkbox (import form only).
	 */
	private static function render_global_checkboxes( $with_main = true ) {
		if ( $with_main ) :
			?>
			<p><label><input type="checkbox" name="global_styles" value="1" checked> <?php esc_html_e( 'Add the kit\'s colours & fonts to Elementor Global styles (Site Settings)', 'jmk' ); ?></label></p>
		<?php else : ?>
			<input type="hidden" name="global_styles" value="1">
		<?php endif; ?>
		<p style="margin-left:<?php echo $with_main ? '24px' : '0'; ?>"><label><input type="checkbox" name="global_system" value="1">
			<?php esc_html_e( 'Also set Elementor\'s default Primary / Secondary / Text / Accent colours and fonts', 'jmk' ); ?></label><br>
			<span class="description"><?php esc_html_e( 'Off by default: these defaults are used by every Elementor widget on the site that is left on "Default", so other pages may change too. A backup is taken first.', 'jmk' ); ?></span></p>
		<?php
	}

	private static function globals_summary() {
		$r = get_transient( 'jmk_globals_report_' . get_current_user_id() );
		if ( ! is_array( $r ) ) {
			return __( 'Global styles updated.', 'jmk' );
		}
		/* translators: 1: number added, 2: number kept */
		$msg = sprintf( __( 'Elementor Global styles: %1$d added, %2$d already there (kept as they are).', 'jmk' ), $r['added'], $r['kept'] );
		if ( $r['system'] ) {
			$msg .= ' ' . __( 'Default Primary/Secondary/Text/Accent colours and fonts were set; a backup was taken.', 'jmk' );
		}
		return $msg;
	}

	public static function handle_global_styles() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jmk' ), 403 );
		}
		check_admin_referer( 'jmk_global_styles' );
		$result = JMK_Plugin::elementor_ready()
			? JMK_Global_Styles::apply( ! empty( $_POST['global_system'] ) )
			: new WP_Error( 'jmk', __( 'Elementor must be active.', 'jmk' ) );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( self::page_url( array( 'jmk' => 'error', 'jmk_msg' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}
		set_transient( 'jmk_globals_report_' . get_current_user_id(), $result, 120 );
		wp_safe_redirect( self::page_url( array( 'jmk' => 'globals' ) ) );
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
