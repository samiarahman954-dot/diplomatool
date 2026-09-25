<?php
/**
 * Plugin Name:       Simple File Manager
 * Description:       A lightweight file manager for the WordPress admin. Browse, upload, create folders, rename, download and delete files inside your uploads directory.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Simple File Manager
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-file-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Simple_File_Manager {

	const VERSION    = '1.0.0';
	const SLUG       = 'simple-file-manager';
	const CAPABILITY = 'manage_options';
	const NONCE      = 'sfm_action';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_sfm_action', array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_post_sfm_download', array( __CLASS__, 'handle_download' ) );
	}

	/* ------------------------------------------------------------------
	 * Paths
	 * ------------------------------------------------------------------ */

	/**
	 * Root directory the manager is confined to. Defaults to the uploads
	 * directory; override with the SFM_ROOT_DIR constant or the
	 * `sfm_root_dir` filter.
	 */
	public static function root() {
		if ( defined( 'SFM_ROOT_DIR' ) && SFM_ROOT_DIR ) {
			$root = SFM_ROOT_DIR;
		} else {
			$uploads = wp_upload_dir();
			$root    = $uploads['basedir'];
		}
		$root = apply_filters( 'sfm_root_dir', $root );

		if ( ! is_dir( $root ) ) {
			wp_mkdir_p( $root );
		}
		$real = realpath( $root );

		return $real ? untrailingslashit( wp_normalize_path( $real ) ) : '';
	}

	/**
	 * Resolve a path relative to the root. Returns the absolute path, or
	 * false if it does not exist or escapes the root (e.g. via "../" or a
	 * symlink pointing elsewhere).
	 */
	public static function resolve( $relative ) {
		$root = self::root();
		if ( '' === $root ) {
			return false;
		}

		$relative = wp_normalize_path( (string) $relative );
		$relative = trim( $relative, '/' );
		$target   = '' === $relative ? $root : $root . '/' . $relative;
		$real     = realpath( $target );

		if ( false === $real ) {
			return false;
		}
		$real = wp_normalize_path( $real );

		if ( $real !== $root && 0 !== strpos( $real, $root . '/' ) ) {
			return false;
		}

		return $real;
	}

	/** Path of an absolute location relative to the root. */
	public static function relative( $absolute ) {
		$root = self::root();
		return ltrim( substr( wp_normalize_path( $absolute ), strlen( $root ) ), '/' );
	}

	/* ------------------------------------------------------------------
	 * Admin UI
	 * ------------------------------------------------------------------ */

	public static function register_menu() {
		add_menu_page(
			__( 'File Manager', 'simple-file-manager' ),
			__( 'File Manager', 'simple-file-manager' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-portfolio',
			81
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		$url = plugin_dir_url( __FILE__ ) . 'assets/';
		wp_enqueue_style( 'sfm-admin', $url . 'admin.css', array(), self::VERSION );
		wp_enqueue_script( 'sfm-admin', $url . 'admin.js', array(), self::VERSION, true );
		wp_localize_script(
			'sfm-admin',
			'sfmL10n',
			array(
				'renamePrompt'  => __( 'New name:', 'simple-file-manager' ),
				'deleteConfirm' => __( 'Delete "%s"? Folders are deleted with everything inside them. This cannot be undone.', 'simple-file-manager' ),
			)
		);
	}

	private static function page_url( $dir = '', $args = array() ) {
		$args = array_merge( array( 'page' => self::SLUG ), $args );
		if ( '' !== $dir ) {
			$args['dir'] = $dir;
		}
		return add_query_arg( array_map( 'rawurlencode', $args ), admin_url( 'admin.php' ) );
	}

	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-file-manager' ) );
		}

		$rel = isset( $_GET['dir'] ) ? sanitize_text_field( wp_unslash( $_GET['dir'] ) ) : '';
		$abs = self::resolve( $rel );

		if ( false === $abs || ! is_dir( $abs ) ) {
			$abs = self::resolve( '' );
			$rel = '';
			if ( false === $abs ) {
				echo '<div class="wrap"><h1>' . esc_html__( 'File Manager', 'simple-file-manager' ) . '</h1>';
				echo '<div class="notice notice-error"><p>' . esc_html__( 'The file manager root directory is not available.', 'simple-file-manager' ) . '</p></div></div>';
				return;
			}
		}
		$rel     = self::relative( $abs );
		$entries = self::list_dir( $abs );
		?>
		<div class="wrap sfm-wrap">
			<h1><?php esc_html_e( 'File Manager', 'simple-file-manager' ); ?></h1>

			<?php self::render_notice(); ?>

			<nav class="sfm-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumbs', 'simple-file-manager' ); ?>">
				<a href="<?php echo esc_url( self::page_url() ); ?>"><span class="dashicons dashicons-admin-home"></span> <?php esc_html_e( 'Root', 'simple-file-manager' ); ?></a>
				<?php
				$crumb = '';
				foreach ( array_filter( explode( '/', $rel ), 'strlen' ) as $part ) {
					$crumb = ltrim( $crumb . '/' . $part, '/' );
					echo ' <span class="sfm-sep">/</span> <a href="' . esc_url( self::page_url( $crumb ) ) . '">' . esc_html( $part ) . '</a>';
				}
				?>
			</nav>

			<div class="sfm-toolbar">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="sfm-inline">
					<?php self::hidden_fields( 'upload', $rel ); ?>
					<input type="file" name="sfm_files[]" multiple required>
					<button type="submit" class="button button-primary"><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Upload', 'simple-file-manager' ); ?></button>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sfm-inline">
					<?php self::hidden_fields( 'mkdir', $rel ); ?>
					<input type="text" name="sfm_name" placeholder="<?php esc_attr_e( 'New folder name', 'simple-file-manager' ); ?>" required>
					<button type="submit" class="button"><span class="dashicons dashicons-open-folder"></span> <?php esc_html_e( 'Create folder', 'simple-file-manager' ); ?></button>
				</form>
			</div>

			<table class="widefat striped sfm-table">
				<thead>
					<tr>
						<th class="sfm-col-name"><?php esc_html_e( 'Name', 'simple-file-manager' ); ?></th>
						<th class="sfm-col-size"><?php esc_html_e( 'Size', 'simple-file-manager' ); ?></th>
						<th class="sfm-col-date"><?php esc_html_e( 'Modified', 'simple-file-manager' ); ?></th>
						<th class="sfm-col-actions"><?php esc_html_e( 'Actions', 'simple-file-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( '' !== $rel ) : ?>
					<tr>
						<td colspan="4">
							<a href="<?php echo esc_url( self::page_url( self::relative( dirname( $abs ) ) ) ); ?>"><span class="dashicons dashicons-arrow-up-alt"></span> ..</a>
						</td>
					</tr>
				<?php endif; ?>

				<?php if ( empty( $entries ) ) : ?>
					<tr><td colspan="4" class="sfm-empty"><?php esc_html_e( 'This folder is empty.', 'simple-file-manager' ); ?></td></tr>
				<?php endif; ?>

				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td class="sfm-col-name">
							<?php if ( $entry['is_dir'] ) : ?>
								<a href="<?php echo esc_url( self::page_url( $entry['path'] ) ); ?>"><span class="dashicons dashicons-category"></span> <?php echo esc_html( $entry['name'] ); ?></a>
							<?php else : ?>
								<span class="dashicons dashicons-media-default"></span> <?php echo esc_html( $entry['name'] ); ?>
							<?php endif; ?>
						</td>
						<td class="sfm-col-size"><?php echo $entry['is_dir'] ? '&mdash;' : esc_html( size_format( $entry['size'], 1 ) ); ?></td>
						<td class="sfm-col-date"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['mtime'] ) ); ?></td>
						<td class="sfm-col-actions">
							<?php if ( ! $entry['is_dir'] ) : ?>
								<a class="button button-small" href="<?php echo esc_url( self::download_url( $entry['path'] ) ); ?>"><?php esc_html_e( 'Download', 'simple-file-manager' ); ?></a>
							<?php endif; ?>

							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sfm-inline sfm-rename-form">
								<?php self::hidden_fields( 'rename', $rel ); ?>
								<input type="hidden" name="sfm_target" value="<?php echo esc_attr( $entry['name'] ); ?>">
								<input type="hidden" name="sfm_name" value="">
								<button type="submit" class="button button-small"><?php esc_html_e( 'Rename', 'simple-file-manager' ); ?></button>
							</form>

							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sfm-inline sfm-delete-form">
								<?php self::hidden_fields( 'delete', $rel ); ?>
								<input type="hidden" name="sfm_target" value="<?php echo esc_attr( $entry['name'] ); ?>">
								<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'simple-file-manager' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<p class="description">
				<?php
				printf(
					/* translators: %s: root directory path */
					esc_html__( 'Root directory: %s', 'simple-file-manager' ),
					'<code>' . esc_html( self::root() ) . '</code>'
				);
				?>
			</p>
		</div>
		<?php
	}

	private static function hidden_fields( $op, $dir ) {
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="action" value="sfm_action">';
		echo '<input type="hidden" name="sfm_op" value="' . esc_attr( $op ) . '">';
		echo '<input type="hidden" name="sfm_dir" value="' . esc_attr( $dir ) . '">';
	}

	private static function download_url( $path ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'sfm_download',
					'file'   => rawurlencode( $path ),
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE
		);
	}

	private static function list_dir( $abs ) {
		$entries = array();
		$items   = @scandir( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $items ) {
			return $entries;
		}
		foreach ( $items as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$full      = $abs . '/' . $name;
			$entries[] = array(
				'name'   => $name,
				'path'   => self::relative( $full ),
				'is_dir' => is_dir( $full ),
				'size'   => is_file( $full ) ? (int) filesize( $full ) : 0,
				'mtime'  => (int) @filemtime( $full ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			);
		}
		// Folders first, then files, each alphabetically.
		usort(
			$entries,
			function ( $a, $b ) {
				if ( $a['is_dir'] !== $b['is_dir'] ) {
					return $a['is_dir'] ? -1 : 1;
				}
				return strnatcasecmp( $a['name'], $b['name'] );
			}
		);
		return $entries;
	}

	private static function render_notice() {
		if ( empty( $_GET['sfm_msg'] ) ) {
			return;
		}
		$type = ( isset( $_GET['sfm_type'] ) && 'error' === $_GET['sfm_type'] ) ? 'error' : 'success';
		$msg  = sanitize_text_field( wp_unslash( $_GET['sfm_msg'] ) );
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $msg )
		);
	}

	/* ------------------------------------------------------------------
	 * Actions
	 * ------------------------------------------------------------------ */

	private static function guard() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'simple-file-manager' ), 403 );
		}
		check_admin_referer( self::NONCE );
	}

	private static function finish( $dir, $message, $type = 'success' ) {
		wp_safe_redirect(
			self::page_url(
				$dir,
				array(
					'sfm_msg'  => $message,
					'sfm_type' => $type,
				)
			)
		);
		exit;
	}

	public static function handle_action() {
		self::guard();

		$op  = isset( $_POST['sfm_op'] ) ? sanitize_key( $_POST['sfm_op'] ) : '';
		$rel = isset( $_POST['sfm_dir'] ) ? sanitize_text_field( wp_unslash( $_POST['sfm_dir'] ) ) : '';
		$dir = self::resolve( $rel );

		if ( false === $dir || ! is_dir( $dir ) ) {
			self::finish( '', __( 'Invalid folder.', 'simple-file-manager' ), 'error' );
		}
		$rel = self::relative( $dir );

		switch ( $op ) {
			case 'upload':
				self::do_upload( $dir, $rel );
				break;
			case 'mkdir':
				self::do_mkdir( $dir, $rel );
				break;
			case 'rename':
				self::do_rename( $dir, $rel );
				break;
			case 'delete':
				self::do_delete( $dir, $rel );
				break;
		}

		self::finish( $rel, __( 'Unknown action.', 'simple-file-manager' ), 'error' );
	}

	/**
	 * Resolve an entry name posted from the listing to an absolute path
	 * that is a direct child of $dir.
	 */
	private static function posted_target( $dir ) {
		$name = isset( $_POST['sfm_target'] ) ? wp_unslash( $_POST['sfm_target'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$name = basename( wp_normalize_path( (string) $name ) );
		if ( '' === $name || '.' === $name || '..' === $name ) {
			return false;
		}
		$path = $dir . '/' . $name;
		// Allow broken/outside symlinks to be deleted or renamed by name, but
		// never resolve them.
		if ( is_link( $path ) ) {
			return $path;
		}
		$real = self::resolve( self::relative( $path ) );
		return ( false !== $real && dirname( $real ) === $dir ) ? $real : false;
	}

	/**
	 * A file name is acceptable if it is clean and its extension is one
	 * WordPress allows for uploads (blocks .php, .phtml, .htaccess, ...).
	 */
	private static function is_allowed_file_name( $name ) {
		if ( '' === $name || '.' === $name[0] ) {
			return false;
		}
		$check = wp_check_filetype( $name );
		return ! empty( $check['ext'] );
	}

	private static function do_upload( $dir, $rel ) {
		if ( empty( $_FILES['sfm_files'] ) || ! is_array( $_FILES['sfm_files']['name'] ) ) {
			self::finish( $rel, __( 'No files were uploaded.', 'simple-file-manager' ), 'error' );
		}

		$files    = $_FILES['sfm_files']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$uploaded = 0;
		$errors   = array();

		foreach ( $files['name'] as $i => $original ) {
			$error = (int) $files['error'][ $i ];
			if ( UPLOAD_ERR_NO_FILE === $error ) {
				continue;
			}
			$name = sanitize_file_name( wp_unslash( $original ) );
			if ( UPLOAD_ERR_OK !== $error || ! is_uploaded_file( $files['tmp_name'][ $i ] ) ) {
				$errors[] = $name;
				continue;
			}

			$check = wp_check_filetype_and_ext( $files['tmp_name'][ $i ], $name );
			if ( empty( $check['ext'] ) || ! self::is_allowed_file_name( $name ) ) {
				$errors[] = $name;
				continue;
			}
			if ( ! empty( $check['proper_filename'] ) ) {
				$name = $check['proper_filename'];
			}

			$name = wp_unique_filename( $dir, $name );
			if ( move_uploaded_file( $files['tmp_name'][ $i ], $dir . '/' . $name ) ) {
				$uploaded++;
			} else {
				$errors[] = $name;
			}
		}

		if ( $errors ) {
			self::finish(
				$rel,
				/* translators: 1: number uploaded, 2: list of rejected files */
				sprintf( __( 'Uploaded %1$d file(s). Rejected or failed: %2$s', 'simple-file-manager' ), $uploaded, implode( ', ', $errors ) ),
				'error'
			);
		}
		/* translators: %d: number of files */
		self::finish( $rel, sprintf( __( 'Uploaded %d file(s).', 'simple-file-manager' ), $uploaded ) );
	}

	private static function do_mkdir( $dir, $rel ) {
		$name = isset( $_POST['sfm_name'] ) ? sanitize_file_name( wp_unslash( $_POST['sfm_name'] ) ) : '';
		if ( '' === $name || '.' === $name[0] ) {
			self::finish( $rel, __( 'Invalid folder name.', 'simple-file-manager' ), 'error' );
		}
		$path = $dir . '/' . $name;
		if ( file_exists( $path ) ) {
			self::finish( $rel, __( 'A file or folder with that name already exists.', 'simple-file-manager' ), 'error' );
		}
		if ( ! wp_mkdir_p( $path ) ) {
			self::finish( $rel, __( 'Could not create the folder.', 'simple-file-manager' ), 'error' );
		}
		/* translators: %s: folder name */
		self::finish( $rel, sprintf( __( 'Folder "%s" created.', 'simple-file-manager' ), $name ) );
	}

	private static function do_rename( $dir, $rel ) {
		$target = self::posted_target( $dir );
		$name   = isset( $_POST['sfm_name'] ) ? sanitize_file_name( wp_unslash( $_POST['sfm_name'] ) ) : '';

		if ( false === $target ) {
			self::finish( $rel, __( 'Item not found.', 'simple-file-manager' ), 'error' );
		}
		$is_dir = is_dir( $target ) && ! is_link( $target );
		if ( '' === $name || '.' === $name[0] || ( ! $is_dir && ! self::is_allowed_file_name( $name ) ) ) {
			self::finish( $rel, __( 'Invalid name, or that file type is not allowed.', 'simple-file-manager' ), 'error' );
		}
		$dest = $dir . '/' . $name;
		if ( file_exists( $dest ) || is_link( $dest ) ) {
			self::finish( $rel, __( 'A file or folder with that name already exists.', 'simple-file-manager' ), 'error' );
		}
		if ( ! @rename( $target, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			self::finish( $rel, __( 'Could not rename the item.', 'simple-file-manager' ), 'error' );
		}
		/* translators: %s: new name */
		self::finish( $rel, sprintf( __( 'Renamed to "%s".', 'simple-file-manager' ), $name ) );
	}

	private static function do_delete( $dir, $rel ) {
		$target = self::posted_target( $dir );
		if ( false === $target ) {
			self::finish( $rel, __( 'Item not found.', 'simple-file-manager' ), 'error' );
		}
		if ( ! self::delete_path( $target ) ) {
			self::finish( $rel, __( 'Could not delete the item.', 'simple-file-manager' ), 'error' );
		}
		/* translators: %s: deleted item name */
		self::finish( $rel, sprintf( __( 'Deleted "%s".', 'simple-file-manager' ), basename( $target ) ) );
	}

	/** Recursively delete a path. Symlinks are removed, never followed. */
	private static function delete_path( $path ) {
		if ( is_link( $path ) || is_file( $path ) ) {
			return @unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( ! is_dir( $path ) ) {
			return false;
		}
		$items = scandir( $path );
		if ( false === $items ) {
			return false;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			if ( ! self::delete_path( $path . '/' . $item ) ) {
				return false;
			}
		}
		return @rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	public static function handle_download() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'simple-file-manager' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$rel  = isset( $_GET['file'] ) ? wp_unslash( $_GET['file'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$path = self::resolve( $rel );

		if ( false === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'File not found.', 'simple-file-manager' ), 404 );
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . str_replace( array( '"', "\r", "\n" ), '', basename( $path ) ) . '"; filename*=UTF-8\'\'' . rawurlencode( basename( $path ) ) );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}

Simple_File_Manager::init();
