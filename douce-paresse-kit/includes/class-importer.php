<?php
namespace DPK;

defined( 'ABSPATH' ) || exit;

/**
 * One-click demo import with duplicate protection and safe sync.
 *
 * Every imported post/attachment carries `_dpk_demo_key`, so a second run finds and reuses it
 * instead of creating a copy. On existing items the import runs in one of three modes:
 *   - sync  : three-way merge (DPK\Sync) – new kit content is applied, the owner's edits are kept.
 *   - skip  : existing items are left untouched, only missing ones are created.
 *   - reset : existing items are overwritten with the kit content (previous version kept as backup).
 */
class Importer {

	const META_KEY      = '_dpk_demo_key';
	const META_SNAPSHOT = '_dpk_snapshot';
	const META_SOURCE   = '_dpk_source_hash';
	const META_BACKUP   = '_dpk_backup';
	const META_PENDING  = '_dpk_pending';
	const OPTION_LOG    = 'dpk_import_state';

	/** @var array|null */
	private $demo = null;

	/** @var array */
	private $image_ids = array();

	public function demo() {
		if ( null === $this->demo ) {
			$this->demo = include DPK_PATH . 'demo/demo-data.php';
		}
		return $this->demo;
	}

	public function items() {
		$demo = $this->demo();
		return $demo['items'];
	}

	/* ------------------------------------------------------------------
	 * Lookup
	 * ------------------------------------------------------------------ */

	public function find_post( $key, $statuses = array( 'publish', 'draft', 'pending', 'private', 'future' ) ) {
		$ids = get_posts(
			array(
				'post_type'        => array( 'page', 'elementor_library' ),
				'post_status'      => $statuses,
				'meta_key'         => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'numberposts'      => 1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	public function find_image( $file ) {
		if ( isset( $this->image_ids[ $file ] ) ) {
			return $this->image_ids[ $file ];
		}
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'meta_key'         => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => 'img:' . $file, // phpcs:ignore WordPress.DB.SlowDBQuery
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		$this->image_ids[ $file ] = $ids ? (int) $ids[0] : 0;
		return $this->image_ids[ $file ];
	}

	/* ------------------------------------------------------------------
	 * Status (admin table)
	 * ------------------------------------------------------------------ */

	public function source_hash( $key ) {
		$items = $this->items();
		return md5( wp_json_encode( $items[ $key ] ) );
	}

	public function status( $key ) {
		$id = $this->find_post( $key );
		if ( ! $id ) {
			return array(
				'state' => $this->find_post( $key, array( 'trash' ) ) ? 'trashed' : 'missing',
				'id'    => 0,
			);
		}
		if ( get_post_meta( $id, self::META_PENDING, true ) ) {
			return array( 'state' => 'missing', 'id' => $id );
		}
		$snapshot = $this->get_json_meta( $id, self::META_SNAPSHOT );
		$edits    = 0;
		if ( $snapshot ) {
			$sync  = new Sync( array( $this, 'element_defaults' ) );
			$edits = $sync->count_changes( $snapshot['elements'], $this->get_elements( $id ) );
		}
		return array(
			'state'   => 'imported',
			'id'      => $id,
			'edits'   => $edits,
			'update'  => get_post_meta( $id, self::META_SOURCE, true ) !== $this->source_hash( $key ),
			'backup'  => (bool) get_post_meta( $id, self::META_BACKUP, true ),
		);
	}

	/* ------------------------------------------------------------------
	 * Media
	 * ------------------------------------------------------------------ */

	public function image_files() {
		$demo = $this->demo();
		return array_keys( $demo['images'] );
	}

	/**
	 * @return int attachment id (0 on failure)
	 */
	public function ensure_image( $file ) {
		$existing = $this->find_image( $file );
		if ( $existing ) {
			return $existing;
		}
		$src = DPK_PATH . 'demo/images/' . $file;
		if ( ! file_exists( $src ) ) {
			return 0;
		}
		$upload = wp_upload_bits( $file, null, file_get_contents( $src ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}
		$type = wp_check_filetype( $upload['file'] );
		$demo = $this->demo();
		$id   = wp_insert_attachment(
			array(
				'post_mime_type' => $type['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', $file ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		update_post_meta( $id, '_wp_attachment_image_alt', $demo['images'][ $file ] );
		update_post_meta( $id, self::META_KEY, 'img:' . $file );
		$this->image_ids[ $file ] = $id;
		return $id;
	}

	/* ------------------------------------------------------------------
	 * Building Elementor data from the demo definition
	 * ------------------------------------------------------------------ */

	public static function eid( $seed ) {
		return substr( md5( 'dpk|' . $seed ), 0, 7 );
	}

	public function containers_active() {
		$plugin = \Elementor\Plugin::$instance;
		return isset( $plugin->experiments ) && method_exists( $plugin->experiments, 'is_feature_active' ) && $plugin->experiments->is_feature_active( 'container' );
	}

	public function page_url( $key ) {
		$items = $this->items();
		$id    = $this->find_post( $key );
		if ( $id ) {
			return get_permalink( $id );
		}
		return isset( $items[ $key ]['slug'] ) ? home_url( '/' . $items[ $key ]['slug'] . '/' ) : home_url( '/' );
	}

	/** Replace {{img:file}}, {{page:key}} and {{home}} tokens; give repeater rows stable _ids. */
	public function resolve( $value, $seed ) {
		if ( is_array( $value ) ) {
			$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
			$out     = array();
			foreach ( $value as $k => $v ) {
				$item = $this->resolve( $v, $seed . '/' . $k );
				if ( $is_list && is_array( $item ) && ! isset( $item['_id'] ) && ! isset( $item['id'] ) && ! isset( $item['url'] ) ) {
					$item = array( '_id' => self::eid( $seed . '/' . $k ) ) + $item;
				}
				$out[ $k ] = $item;
			}
			return $out;
		}
		if ( ! is_string( $value ) ) {
			return $value;
		}
		if ( preg_match( '/^\{\{img:([^}]+)\}\}$/', $value, $m ) ) {
			$id = $this->find_image( $m[1] );
			return array(
				'url'    => $id ? wp_get_attachment_url( $id ) : DPK_URL . 'demo/images/' . $m[1],
				'id'     => $id ? $id : '',
				'alt'    => '',
				'source' => 'library',
				'size'   => '',
			);
		}
		$self  = $this;
		$value = preg_replace_callback(
			'/\{\{page:([a-z0-9\-]+)\}\}/',
			function ( $m ) use ( $self ) {
				return $self->page_url( $m[1] );
			},
			$value
		);
		return str_replace( '{{home}}', home_url( '/' ), $value );
	}

	public function build( $key ) {
		$items      = $this->items();
		$item       = $items[ $key ];
		$containers = $this->containers_active();
		$zero       = array(
			'unit'     => 'px',
			'top'      => '0',
			'right'    => '0',
			'bottom'   => '0',
			'left'     => '0',
			'isLinked' => true,
		);
		$elements   = array();

		foreach ( $item['widgets'] as $w ) {
			$wid    = self::eid( $key . '/' . $w['key'] );
			$widget = array(
				'id'         => $wid,
				'elType'     => 'widget',
				'widgetType' => $w['type'],
				'settings'   => $this->resolve( isset( $w['settings'] ) ? $w['settings'] : array(), $key . '/' . $w['key'] ),
				'elements'   => array(),
			);
			if ( $containers ) {
				$elements[] = array(
					'id'       => self::eid( $key . '/' . $w['key'] . '/container' ),
					'elType'   => 'container',
					'isInner'  => false,
					'settings' => array(
						'content_width'  => 'full',
						'flex_direction' => 'column',
						'padding'        => $zero,
						'flex_gap'       => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ),
						'_title'         => $w['label'],
					),
					'elements' => array( $widget ),
				);
			} else {
				$elements[] = array(
					'id'       => self::eid( $key . '/' . $w['key'] . '/section' ),
					'elType'   => 'section',
					'isInner'  => false,
					'settings' => array(
						'layout'  => 'full_width',
						'gap'     => 'no',
						'padding' => $zero,
						'_title'  => $w['label'],
					),
					'elements' => array(
						array(
							'id'       => self::eid( $key . '/' . $w['key'] . '/column' ),
							'elType'   => 'column',
							'isInner'  => false,
							'settings' => array( '_column_size' => 100, '_inline_size' => null ),
							'elements' => array( $widget ),
						),
					),
				);
			}
		}

		$page_settings = isset( $item['page_settings'] ) ? $this->resolve( $item['page_settings'], $key . '/page' ) : array();
		return array(
			'elements'      => $elements,
			'page_settings' => $page_settings,
		);
	}

	/** Control defaults of an element type (used by Sync to understand "unchanged"). */
	public function element_defaults( $el ) {
		$plugin = \Elementor\Plugin::$instance;
		$out    = array();
		try {
			$obj = null;
			if ( isset( $el['elType'] ) && 'widget' === $el['elType'] && ! empty( $el['widgetType'] ) ) {
				$obj = $plugin->widgets_manager->get_widget_types( $el['widgetType'] );
			} elseif ( isset( $el['elType'] ) && method_exists( $plugin->elements_manager, 'get_element_types' ) ) {
				$obj = $plugin->elements_manager->get_element_types( $el['elType'] );
			}
			if ( $obj && method_exists( $obj, 'get_controls' ) ) {
				foreach ( (array) $obj->get_controls() as $name => $control ) {
					if ( is_array( $control ) && array_key_exists( 'default', $control ) ) {
						$out[ $name ] = $control['default'];
					}
				}
			}
		} catch ( \Throwable $e ) {
			$out = array(); // Without defaults the merge is simply more conservative.
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Reading / writing post data
	 * ------------------------------------------------------------------ */

	private function get_json_meta( $id, $key ) {
		$raw = get_post_meta( $id, $key, true );
		if ( is_array( $raw ) ) {
			return $raw;
		}
		$data = $raw ? json_decode( $raw, true ) : null;
		return is_array( $data ) ? $data : null;
	}

	private function set_json_meta( $id, $key, $value ) {
		update_post_meta( $id, $key, wp_slash( wp_json_encode( $value ) ) );
	}

	public function get_elements( $id ) {
		$data = $this->get_json_meta( $id, '_elementor_data' );
		return is_array( $data ) ? $data : array();
	}

	public function get_page_settings( $id ) {
		$ps = get_post_meta( $id, '_elementor_page_settings', true );
		return is_array( $ps ) ? $ps : array();
	}

	private function write( $id, $elements, $page_settings, $template_type ) {
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', $template_type );
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		}
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			update_post_meta( $id, '_elementor_pro_version', ELEMENTOR_PRO_VERSION );
		}
		$this->set_json_meta( $id, '_elementor_data', $elements );
		if ( $page_settings ) {
			update_post_meta( $id, '_elementor_page_settings', $page_settings );
		} else {
			delete_post_meta( $id, '_elementor_page_settings' );
		}
		// Make Elementor regenerate this post's CSS and element cache.
		delete_post_meta( $id, '_elementor_css' );
		delete_post_meta( $id, '_elementor_element_cache' );
		delete_post_meta( $id, '_elementor_page_assets' );
	}

	private function backup( $id ) {
		$this->set_json_meta(
			$id,
			self::META_BACKUP,
			array(
				'time'          => time(),
				'elements'      => $this->get_elements( $id ),
				'page_settings' => $this->get_page_settings( $id ),
			)
		);
	}

	public function restore( $key ) {
		$id     = $this->find_post( $key );
		$backup = $id ? $this->get_json_meta( $id, self::META_BACKUP ) : null;
		if ( ! $backup ) {
			return new \WP_Error( 'dpk_no_backup', __( 'No backup available.', 'douce-paresse-kit' ) );
		}
		$this->backup( $id ); // Current version becomes the new backup (restore can be undone).
		$this->write( $id, $backup['elements'], $backup['page_settings'], get_post_meta( $id, '_elementor_template_type', true ) );
		$this->clear_cache();
		return true;
	}

	/* ------------------------------------------------------------------
	 * Theme Builder (Elementor Pro)
	 * ------------------------------------------------------------------ */

	public static function has_pro_theme_builder() {
		return class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' );
	}

	private function template_type( $item ) {
		return self::has_pro_theme_builder() ? $item['type'] : 'section';
	}

	private function apply_conditions( $id ) {
		$conditions = array(
			array(
				'type' => 'include',
				'name' => 'general',
			),
		);
		if ( self::has_pro_theme_builder() ) {
			try {
				$manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
				$manager->save_conditions( $id, $conditions );
				return;
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				// Fall through to the raw meta below.
			}
		}
		update_post_meta( $id, '_elementor_conditions', array( 'include/general' ) );
	}

	/* ------------------------------------------------------------------
	 * Steps
	 * ------------------------------------------------------------------ */

	/** Create (empty) pages that are about to be imported so every internal link can be resolved. */
	public function prepare( $keys, $set_front ) {
		$items = $this->items();
		$log   = array();
		foreach ( $keys as $key ) {
			if ( empty( $items[ $key ] ) || 'page' !== $items[ $key ]['kind'] || $this->find_post( $key ) ) {
				continue;
			}
			if ( $this->find_post( $key, array( 'trash' ) ) ) {
				continue; // Owner trashed it on purpose: do not resurrect.
			}
			$item = $items[ $key ];
			$id   = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $item['title'],
					'post_name'    => $item['slug'],
					'post_content' => '',
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				$log[] = sprintf( '✗ %s: %s', $item['title'], $id->get_error_message() );
				continue;
			}
			update_post_meta( $id, self::META_KEY, $key );
			update_post_meta( $id, self::META_PENDING, 1 );
			update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
		}

		if ( $set_front ) {
			foreach ( $items as $key => $item ) {
				if ( ! empty( $item['front'] ) && in_array( $key, $keys, true ) ) {
					$front = $this->find_post( $key );
					if ( $front ) {
						update_option( 'show_on_front', 'page' );
						update_option( 'page_on_front', $front );
						$log[] = sprintf( '✓ %s', __( 'Home page set as the site front page', 'douce-paresse-kit' ) );
					}
				}
			}
		}
		return $log;
	}

	/**
	 * Import or sync one demo item.
	 *
	 * @return string log line
	 */
	public function import_item( $key, $mode ) {
		$items = $this->items();
		if ( empty( $items[ $key ] ) ) {
			return '✗ ' . $key;
		}
		$item    = $items[ $key ];
		$type    = 'template' === $item['kind'] ? $this->template_type( $item ) : 'wp-page';
		$id      = $this->find_post( $key );
		$pending = $id && get_post_meta( $id, self::META_PENDING, true );

		if ( ! $id && $this->find_post( $key, array( 'trash' ) ) ) {
			return sprintf( '• %s — %s', $item['title'], __( 'in the trash: skipped (restore it or empty the trash to import again)', 'douce-paresse-kit' ) );
		}

		$remote = $this->build( $key );

		// New item.
		if ( ! $id || $pending ) {
			if ( ! $id ) {
				$args = array(
					'post_type'    => 'template' === $item['kind'] ? 'elementor_library' : 'page',
					'post_status'  => 'publish',
					'post_title'   => $item['title'],
					'post_content' => '',
				);
				if ( 'page' === $item['kind'] ) {
					$args['post_name'] = $item['slug'];
				}
				$id = wp_insert_post( $args, true );
				if ( is_wp_error( $id ) ) {
					return sprintf( '✗ %s: %s', $item['title'], $id->get_error_message() );
				}
				update_post_meta( $id, self::META_KEY, $key );
				if ( 'page' === $item['kind'] ) {
					update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
				}
			}
			if ( 'template' === $item['kind'] ) {
				wp_set_object_terms( $id, $type, 'elementor_library_type' );
			}
			$this->write( $id, $remote['elements'], $remote['page_settings'], $type );
			$this->set_json_meta( $id, self::META_SNAPSHOT, $remote );
			update_post_meta( $id, self::META_SOURCE, $this->source_hash( $key ) );
			delete_post_meta( $id, self::META_PENDING );
			if ( 'template' === $item['kind'] && 'section' !== $type ) {
				$this->apply_conditions( $id );
			}
			$where = 'template' === $item['kind']
				? ( 'section' === $type ? __( 'saved as a section template (activate Elementor Pro and sync again to place it in the Theme Builder)', 'douce-paresse-kit' ) : sprintf( __( 'added to Theme Builder → %s (entire site)', 'douce-paresse-kit' ), ucfirst( $type ) ) )
				: __( 'created', 'douce-paresse-kit' );
			return sprintf( '✓ %s — %s', $item['title'], $where );
		}

		// Existing item.
		if ( 'template' === $item['kind'] ) {
			$current_type = get_post_meta( $id, '_elementor_template_type', true );
			if ( 'section' === $current_type && 'section' !== $type ) {
				// Elementor Pro was activated after the first import: promote to a real header/footer.
				update_post_meta( $id, '_elementor_template_type', $type );
				wp_set_object_terms( $id, $type, 'elementor_library_type' );
				$this->apply_conditions( $id );
			} else {
				$type = $current_type ? $current_type : $type;
			}
		}

		if ( 'skip' === $mode ) {
			return sprintf( '• %s — %s', $item['title'], __( 'already imported, left untouched', 'douce-paresse-kit' ) );
		}

		$local          = $this->get_elements( $id );
		$local_settings = $this->get_page_settings( $id );
		$snapshot       = $this->get_json_meta( $id, self::META_SNAPSHOT );

		if ( 'reset' === $mode ) {
			$this->backup( $id );
			$this->write( $id, $remote['elements'], $remote['page_settings'], $type );
			$this->set_json_meta( $id, self::META_SNAPSHOT, $remote );
			update_post_meta( $id, self::META_SOURCE, $this->source_hash( $key ) );
			return sprintf( '✓ %s — %s', $item['title'], __( 'reset to the kit version (previous version saved as backup)', 'douce-paresse-kit' ) );
		}

		// Smart sync.
		if ( ! $snapshot ) {
			return sprintf( '• %s — %s', $item['title'], __( 'no import history found, left untouched (use “Reset” to overwrite it)', 'douce-paresse-kit' ) );
		}
		$sync   = new Sync( array( $this, 'element_defaults' ) );
		$base   = $snapshot;
		$merged = $sync->merge_list( $base['elements'], $local, $remote['elements'] );
		$ps     = $sync->merge_settings( isset( $base['page_settings'] ) ? $base['page_settings'] : array(), $local_settings, $remote['page_settings'] );

		$changed = ! Sync::same( $merged, $local ) || ! Sync::same( $ps, $local_settings );
		if ( $changed ) {
			$this->backup( $id );
			$this->write( $id, $merged, $ps, $type );
		}
		$this->set_json_meta( $id, self::META_SNAPSHOT, $remote );
		update_post_meta( $id, self::META_SOURCE, $this->source_hash( $key ) );

		$st = $sync->stats;
		if ( ! $changed ) {
			return sprintf(
				'✓ %s — %s',
				$item['title'],
				$st['kept'] ? sprintf( __( 'already up to date (%d of your edits kept)', 'douce-paresse-kit' ), $st['kept'] ) : __( 'already up to date', 'douce-paresse-kit' )
			);
		}
		return sprintf(
			/* translators: 1: title 2: updated 3: kept 4: added */
			__( '✓ %1$s — synced: %2$d kit updates applied, %3$d of your edits kept, %4$d new sections added', 'douce-paresse-kit' ),
			$item['title'],
			$st['updated'] + $st['removed'],
			$st['kept'],
			$st['added']
		);
	}

	public function clear_cache() {
		if ( did_action( 'elementor/loaded' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}
}
