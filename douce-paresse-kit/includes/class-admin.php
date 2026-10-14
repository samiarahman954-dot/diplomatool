<?php
namespace DPK;

defined( 'ABSPATH' ) || exit;

/**
 * "Douce Paresse" admin screen: one-click import / sync, status per item, backups.
 */
final class Admin {

	const SLUG = 'dpk-kit';

	/** @var Admin|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_dpk_import_step', array( $this, 'ajax_step' ) );
		add_action( 'wp_ajax_dpk_restore', array( $this, 'ajax_restore' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DPK_FILE ), array( $this, 'action_link' ) );
	}

	public function action_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Import demo', 'douce-paresse-kit' ) . '</a>' );
		return $links;
	}

	public function menu() {
		add_menu_page(
			__( 'Douce Paresse Kit', 'douce-paresse-kit' ),
			__( 'Douce Paresse', 'douce-paresse-kit' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-coffee',
			59
		);
		add_submenu_page( self::SLUG, __( 'Demo import & sync', 'douce-paresse-kit' ), __( 'Demo import & sync', 'douce-paresse-kit' ), 'manage_options', self::SLUG, array( $this, 'render' ) );
	}

	public function assets( $hook ) {
		if ( false !== strpos( (string) $hook, Spam::PAGE ) || false !== strpos( (string) $hook, Debug::PAGE ) || false !== strpos( (string) $hook, I18n::PAGE ) ) {
			wp_enqueue_style( 'dpk-admin', DPK_URL . 'assets/admin/admin.css', array(), DPK_VERSION );
			return;
		}
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'dpk-admin', DPK_URL . 'assets/admin/admin.css', array(), DPK_VERSION );
		wp_enqueue_script( 'dpk-admin', DPK_URL . 'assets/admin/admin.js', array(), DPK_VERSION, true );
		wp_localize_script(
			'dpk-admin',
			'DPKAdmin',
			array(
				'ajax'   => admin_url( 'admin-ajax.php' ),
				'nonce'  => wp_create_nonce( 'dpk_admin' ),
				'images' => count( ( new Importer() )->image_files() ),
				'i18n'   => array(
					'confirmReset' => __( 'Reset overwrites the selected pages and templates with the kit version. Your current version is kept as a backup you can restore. Continue?', 'douce-paresse-kit' ),
					'nothing'      => __( 'Select at least one item.', 'douce-paresse-kit' ),
					'media'        => __( 'Importing images…', 'douce-paresse-kit' ),
					'done'         => __( 'All done. Reloading…', 'douce-paresse-kit' ),
					'failed'       => __( 'The import stopped because of an error:', 'douce-paresse-kit' ),
					'restore'      => __( 'Restore the version saved before the last sync/reset? (The current version becomes the new backup.)', 'douce-paresse-kit' ),
				),
			)
		);
	}

	private function requirements() {
		$theme = wp_get_theme();
		return array(
			array(
				'ok'    => did_action( 'elementor/loaded' ),
				'label' => __( 'Elementor', 'douce-paresse-kit' ),
				'help'  => __( 'Required for the widgets and the pages.', 'douce-paresse-kit' ),
			),
			array(
				'ok'    => Importer::has_pro_theme_builder(),
				'label' => __( 'Elementor Pro (Theme Builder)', 'douce-paresse-kit' ),
				'help'  => __( 'Required to place the header and footer on every page. Without it they are saved as section templates.', 'douce-paresse-kit' ),
			),
			array(
				'ok'    => ! ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ),
				'label' => __( 'Classic (non-block) theme', 'douce-paresse-kit' ),
				'help'  => __( 'Block themes (Twenty Twenty-Four, Twenty Twenty-Five…) do not support Elementor Theme Builder headers/footers. Use Hello Elementor.', 'douce-paresse-kit' ),
			),
			array(
				'ok'    => in_array( $theme->get_template(), array( 'hello-elementor', 'hello-biz' ), true ),
				'label' => __( 'Hello Elementor theme (recommended)', 'douce-paresse-kit' ),
				'help'  => __( 'Any theme works with the Theme Builder, Hello gives the cleanest result.', 'douce-paresse-kit' ),
				'soft'  => true,
			),
		);
	}

	private function badge( $status ) {
		switch ( $status['state'] ) {
			case 'missing':
				return '<span class="dpk-badge dpk-badge--new">' . esc_html__( 'Not imported', 'douce-paresse-kit' ) . '</span>';
			case 'trashed':
				return '<span class="dpk-badge dpk-badge--warn">' . esc_html__( 'In trash (skipped)', 'douce-paresse-kit' ) . '</span>';
		}
		$html = '<span class="dpk-badge dpk-badge--ok">' . esc_html__( 'Imported', 'douce-paresse-kit' ) . '</span>';
		if ( $status['edits'] ) {
			/* translators: %d: number of edits */
			$html .= ' <span class="dpk-badge dpk-badge--edit">' . esc_html( sprintf( _n( '%d edit of yours (protected)', '%d edits of yours (protected)', $status['edits'], 'douce-paresse-kit' ), $status['edits'] ) ) . '</span>';
		}
		if ( $status['update'] ) {
			$html .= ' <span class="dpk-badge dpk-badge--update">' . esc_html__( 'Kit update available', 'douce-paresse-kit' ) . '</span>';
		}
		return $html;
	}

	public function render() {
		$importer = new Importer();
		$elem     = did_action( 'elementor/loaded' );
		$front    = (int) get_option( 'page_on_front' );
		$home_id  = $importer->find_post( 'home' );
		?>
		<div class="wrap dpk-admin">
			<h1><?php esc_html_e( 'Douce Paresse — Demo import & sync', 'douce-paresse-kit' ); ?></h1>
			<p class="dpk-lead"><?php esc_html_e( 'Imports the Douce Paresse pages (built only with the kit’s own section widgets), the header and the footer. Running it again never creates duplicates, and “Smart sync” applies kit updates without touching the content or styles you changed yourself.', 'douce-paresse-kit' ); ?></p>

			<div class="dpk-card">
				<h2><?php esc_html_e( 'Requirements', 'douce-paresse-kit' ); ?></h2>
				<ul class="dpk-req">
					<?php foreach ( $this->requirements() as $r ) : ?>
						<li class="<?php echo $r['ok'] ? 'is-ok' : ( empty( $r['soft'] ) ? 'is-bad' : 'is-soft' ); ?>">
							<span class="dashicons <?php echo $r['ok'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
							<strong><?php echo esc_html( $r['label'] ); ?></strong> — <?php echo esc_html( $r['help'] ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<?php if ( ! $elem ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'Install and activate Elementor first.', 'douce-paresse-kit' ); ?></p></div>
				<?php
				return;
			endif;
			?>

			<div class="dpk-card">
				<h2><?php esc_html_e( 'What to import', 'douce-paresse-kit' ); ?></h2>
				<table class="widefat striped dpk-table">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" id="dpk-all" checked></td>
							<th><?php esc_html_e( 'Item', 'douce-paresse-kit' ); ?></th>
							<th><?php esc_html_e( 'Goes to', 'douce-paresse-kit' ); ?></th>
							<th><?php esc_html_e( 'Status', 'douce-paresse-kit' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'douce-paresse-kit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<th class="check-column"><input type="checkbox" checked disabled></th>
							<td><strong><?php esc_html_e( 'Images', 'douce-paresse-kit' ); ?></strong></td>
							<td><?php esc_html_e( 'Media Library', 'douce-paresse-kit' ); ?></td>
							<td>
								<?php
								$have = 0;
								foreach ( $importer->image_files() as $file ) {
									$have += $importer->find_image( $file ) ? 1 : 0;
								}
								/* translators: 1: imported 2: total */
								echo '<span class="dpk-badge ' . ( $have === count( $importer->image_files() ) ? 'dpk-badge--ok' : 'dpk-badge--new' ) . '">' . esc_html( sprintf( __( '%1$d / %2$d in the library', 'douce-paresse-kit' ), $have, count( $importer->image_files() ) ) ) . '</span>';
								?>
							</td>
							<td>—</td>
						</tr>
						<?php
						foreach ( $importer->items() as $key => $item ) :
							$status = $importer->status( $key );
							$where  = 'template' === $item['kind']
								? sprintf( __( 'Theme Builder → %s (entire site)', 'douce-paresse-kit' ), ucfirst( $item['type'] ) )
								: ( ! empty( $item['front'] ) ? __( 'Page (front page)', 'douce-paresse-kit' ) : __( 'Page', 'douce-paresse-kit' ) );
							?>
							<tr>
								<th class="check-column"><input type="checkbox" class="dpk-item" value="<?php echo esc_attr( $key ); ?>" checked></th>
								<td><strong><?php echo esc_html( $item['title'] ); ?></strong>
									<div class="dpk-sub"><?php echo esc_html( implode( ' · ', wp_list_pluck( $item['widgets'], 'label' ) ) ); ?></div>
								</td>
								<td><?php echo esc_html( $where ); ?></td>
								<td><?php echo $this->badge( $status ); // phpcs:ignore ?></td>
								<td class="dpk-actions">
									<?php if ( $status['id'] && 'imported' === $status['state'] ) : ?>
										<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $status['id'] . '&action=elementor' ) ); ?>"><?php esc_html_e( 'Edit with Elementor', 'douce-paresse-kit' ); ?></a>
										<?php if ( 'page' === $item['kind'] ) : ?>
											| <a href="<?php echo esc_url( get_permalink( $status['id'] ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'douce-paresse-kit' ); ?></a>
										<?php endif; ?>
										<?php if ( $status['backup'] ) : ?>
											| <a href="#" class="dpk-restore" data-key="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Restore backup', 'douce-paresse-kit' ); ?></a>
										<?php endif; ?>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="dpk-card">
				<h2><?php esc_html_e( 'When an item already exists', 'douce-paresse-kit' ); ?></h2>
				<fieldset class="dpk-modes">
					<label><input type="radio" name="dpk-mode" value="sync" checked>
						<strong><?php esc_html_e( 'Smart sync (recommended)', 'douce-paresse-kit' ); ?></strong>
						<span><?php esc_html_e( 'Applies what changed in the kit. Every text, image, link or style you changed in Elementor is kept, sections you deleted stay deleted, sections you added stay in place.', 'douce-paresse-kit' ); ?></span>
					</label>
					<label><input type="radio" name="dpk-mode" value="skip">
						<strong><?php esc_html_e( 'Only import what is missing', 'douce-paresse-kit' ); ?></strong>
						<span><?php esc_html_e( 'Existing pages and templates are not touched at all.', 'douce-paresse-kit' ); ?></span>
					</label>
					<label><input type="radio" name="dpk-mode" value="reset">
						<strong><?php esc_html_e( 'Reset to the kit version', 'douce-paresse-kit' ); ?></strong>
						<span><?php esc_html_e( 'Overwrites the selected items. Your current version is saved and can be restored with “Restore backup”.', 'douce-paresse-kit' ); ?></span>
					</label>
				</fieldset>
				<p>
					<label><input type="checkbox" id="dpk-front" <?php checked( ! $front || ( $home_id && $front === $home_id ) ); ?>>
						<?php esc_html_e( 'Use “Accueil” as the site front page', 'douce-paresse-kit' ); ?></label>
				</p>
				<p class="dpk-go">
					<button type="button" class="button button-primary button-hero" id="dpk-run"><?php esc_html_e( 'Import / Sync now', 'douce-paresse-kit' ); ?></button>
				</p>
				<div class="dpk-progress" hidden><div class="dpk-progress__bar"></div></div>
				<ol class="dpk-log" hidden></ol>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */

	private function guard() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'dpk_admin', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'douce-paresse-kit' ) ), 403 );
		}
		if ( ! did_action( 'elementor/loaded' ) ) {
			wp_send_json_error( array( 'message' => __( 'Elementor is not active.', 'douce-paresse-kit' ) ) );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}

	public function ajax_step() {
		$this->guard();
		$importer = new Importer();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in guard().
		$step  = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		$mode  = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'sync';
		$mode  = in_array( $mode, array( 'sync', 'skip', 'reset' ), true ) ? $mode : 'sync';
		$keys  = isset( $_POST['keys'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['keys'] ) ) : array();
		$keys  = array_values( array_intersect( $keys, array_keys( $importer->items() ) ) );
		$front = ! empty( $_POST['front'] );
		// phpcs:enable

		switch ( $step ) {
			case 'media':
				$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$files  = array_slice( $importer->image_files(), $offset, 4 );
				$log    = array();
				foreach ( $files as $file ) {
					if ( ! $importer->ensure_image( $file ) ) {
						$log[] = '✗ ' . $file;
					}
				}
				wp_send_json_success(
					array(
						'next' => $offset + count( $files ),
						'done' => $offset + count( $files ) >= count( $importer->image_files() ),
						'log'  => $log,
					)
				);
				break;

			case 'prepare':
				wp_send_json_success( array( 'log' => $importer->prepare( $keys, $front ) ) );
				break;

			case 'item':
				$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				wp_send_json_success( array( 'log' => array( $importer->import_item( $key, $mode ) ) ) );
				break;

			case 'finalize':
				$importer->clear_cache();
				update_option(
					Importer::OPTION_LOG,
					array(
						'time'    => time(),
						'version' => DPK_VERSION,
						'mode'    => $mode,
					),
					false
				);
				wp_send_json_success( array( 'log' => array( '✓ ' . __( 'Elementor CSS cache cleared', 'douce-paresse-kit' ) ) ) );
				break;
		}
		wp_send_json_error( array( 'message' => 'Unknown step' ) );
	}

	public function ajax_restore() {
		$this->guard();
		$key    = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result = ( new Importer() )->restore( $key );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success();
	}
}
