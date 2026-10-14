<?php
namespace DPK;

defined( 'ABSPATH' ) || exit;

/**
 * Three-way merge of Elementor data.
 *
 *   base   = what the kit imported last time (snapshot stored on the post)
 *   local  = what is on the site now (may contain the owner's edits)
 *   remote = what the current kit version would import
 *
 * A value the owner changed (local != base) is always kept. A value the owner did not touch
 * (local == base) follows the kit (remote). Elements the owner added are kept, elements the
 * owner deleted stay deleted, and new kit elements are inserted next to their siblings.
 * No Elementor/WordPress API is used here so the logic can be unit-tested on its own.
 */
class Sync {

	/** @var callable|null fn( array $element ): array control defaults */
	private $defaults_cb;

	/** @var array */
	private $defaults_cache = array();

	/** @var array */
	public $stats = array(
		'updated' => 0, // settings taken from the new kit version.
		'kept'    => 0, // settings the owner changed and that were preserved.
		'added'   => 0, // new kit elements inserted.
		'removed' => 0, // kit elements removed upstream (only when untouched).
	);

	public function __construct( $defaults_cb = null ) {
		$this->defaults_cb = $defaults_cb;
	}

	/* ------------------------------------------------------------------ */

	public static function norm( $v ) {
		if ( is_array( $v ) ) {
			$out = array();
			foreach ( $v as $k => $item ) {
				$out[ $k ] = self::norm( $item );
			}
			return $out;
		}
		if ( null === $v || false === $v ) {
			return '';
		}
		if ( true === $v ) {
			return '1';
		}
		return (string) $v;
	}

	public static function same( $a, $b ) {
		return json_encode( self::norm( $a ) ) === json_encode( self::norm( $b ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	private function defaults( $el ) {
		if ( ! $this->defaults_cb || ! is_array( $el ) ) {
			return array();
		}
		$type = isset( $el['widgetType'] ) ? $el['widgetType'] : ( isset( $el['elType'] ) ? $el['elType'] : '' );
		if ( ! isset( $this->defaults_cache[ $type ] ) ) {
			$this->defaults_cache[ $type ] = (array) call_user_func( $this->defaults_cb, $el );
		}
		return $this->defaults_cache[ $type ];
	}

	/** Value of a setting, falling back to the control default when the key is absent. */
	private function value( $settings, $key, $defaults ) {
		if ( is_array( $settings ) && array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}
		return array_key_exists( $key, $defaults ) ? $defaults[ $key ] : null;
	}

	private static function settings_of( $el ) {
		return is_array( $el ) && isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();
	}

	/* ------------------------------------------------------------------ */

	/**
	 * Merge two-level settings arrays.
	 *
	 * @param array $base     Settings at last import.
	 * @param array $local    Current settings.
	 * @param array $remote   New kit settings.
	 * @param array $defaults Control defaults (missing key == default).
	 */
	public function merge_settings( $base, $local, $remote, $defaults = array() ) {
		$base   = is_array( $base ) ? $base : array();
		$local  = is_array( $local ) ? $local : array();
		$remote = is_array( $remote ) ? $remote : array();
		$out    = $local;

		$keys = array_unique( array_merge( array_keys( $base ), array_keys( $remote ) ) );
		foreach ( $keys as $key ) {
			$bv = $this->value( $base, $key, $defaults );
			$lv = $this->value( $local, $key, $defaults );
			$rv = $this->value( $remote, $key, $defaults );

			if ( ! self::same( $lv, $bv ) ) {
				if ( self::is_rows( $lv ) && self::is_rows( $bv ) && self::is_rows( $rv ) ) {
					$out[ $key ] = $this->merge_rows( $bv, $lv, $rv ); // Repeater: merge row by row.
					continue;
				}
				if ( ! self::same( $lv, $rv ) ) {
					++$this->stats['kept'];
				}
				continue; // Owner's edit wins.
			}
			if ( self::same( $lv, $rv ) ) {
				continue; // Nothing to do.
			}
			if ( array_key_exists( $key, $remote ) ) {
				$out[ $key ] = $remote[ $key ];
			} else {
				unset( $out[ $key ] );
			}
			++$this->stats['updated'];
		}
		return $out;
	}

	/** A repeater value: a list of rows that all carry an _id. */
	private static function is_rows( $v ) {
		if ( ! is_array( $v ) || ! $v || array_keys( $v ) !== range( 0, count( $v ) - 1 ) ) {
			return false;
		}
		foreach ( $v as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['_id'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Repeater rows the owner changed: their rows (and their edits) are kept, untouched rows follow the
	 * kit, rows the owner deleted stay deleted, new kit rows are inserted after their previous sibling.
	 */
	/** What a repeater row is about: its link, else its first text. */
	private static function row_key( $row ) {
		foreach ( $row as $field => $v ) {
			if ( is_array( $v ) && isset( $v['url'] ) && is_string( $v['url'] ) && '' !== trim( $v['url'] ) && '#' !== trim( $v['url'] ) && '_' !== $field[0] ) {
				return 'u:' . strtolower( trim( $v['url'] ) );
			}
		}
		foreach ( array( 'text', 'title', 'label', 'name', 'card_title', 'value', 'key' ) as $field ) {
			if ( isset( $row[ $field ] ) && is_scalar( $row[ $field ] ) && '' !== trim( (string) $row[ $field ] ) ) {
				return 't:' . strtolower( trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $row[ $field ] ) ) ) );
			}
		}
		return null;
	}

	/**
	 * Gives $rows the _ids of the matching rows of $ref. Kit row _ids come from the row position, so a row
	 * inserted or removed in the middle of a kit list shifts the _ids of the rows after it: rows are
	 * matched by what they are (link / text) first, by position only when that row was edited in place.
	 * Unmatched rows whose _id is already taken get a new one.
	 */
	private static function align_rows( $ref, $rows, $taken = array() ) {
		$ref_id_key = array();
		$ref_key_id = array();
		foreach ( $ref as $r ) {
			$k                          = self::row_key( $r );
			$ref_id_key[ $r['_id'] ]    = $k;
			if ( null !== $k && ! isset( $ref_key_id[ $k ] ) ) {
				$ref_key_id[ $k ] = $r['_id'];
			}
		}
		$row_keys = array();
		foreach ( $rows as $r ) {
			$k = self::row_key( $r );
			if ( null !== $k ) {
				$row_keys[ $k ] = true;
			}
		}
		$used = array();
		$out  = array();
		foreach ( $rows as $r ) {
			$id     = $r['_id'];
			$k      = self::row_key( $r );
			$target = null;
			if ( null !== $k && isset( $ref_key_id[ $k ] ) && ! isset( $used[ $ref_key_id[ $k ] ] ) ) {
				$target = $ref_key_id[ $k ]; // Same row, possibly moved.
			} elseif ( array_key_exists( $id, $ref_id_key ) && ! isset( $used[ $id ] ) && ( null === $ref_id_key[ $id ] || ! isset( $row_keys[ $ref_id_key[ $id ] ] ) ) ) {
				$target = $id; // Same place, content edited.
			} else {
				$target = $id;
				if ( array_key_exists( $id, $ref_id_key ) || isset( $taken[ $id ] ) || isset( $used[ $id ] ) ) {
					$target = $id . '-' . substr( md5( (string) $k . json_encode( $r ) ), 0, 5 );
				}
			}
			$used[ $target ] = true;
			$r['_id']        = $target;
			$out[]           = $r;
		}
		return $out;
	}

	private function merge_rows( $base, $local, $remote ) {
		$base   = self::align_rows( $local, $base );
		$remote = self::align_rows( $base, $remote, array_column( $local, null, '_id' ) );
		$bmap = array_column( $base, null, '_id' );
		$lmap = array_column( $local, null, '_id' );
		$rmap = array_column( $remote, null, '_id' );
		$out  = array();
		foreach ( $local as $row ) {
			$id = $row['_id'];
			if ( isset( $bmap[ $id ], $rmap[ $id ] ) ) {
				$out[] = $this->merge_settings( $bmap[ $id ], $row, $rmap[ $id ] );
			} elseif ( isset( $bmap[ $id ] ) && self::same( $row, $bmap[ $id ] ) ) {
				++$this->stats['removed']; // Removed from the kit, never touched by the owner.
			} else {
				$out[] = $row;
			}
		}
		$remote = array_values( $remote );
		foreach ( $remote as $i => $row ) {
			$id = $row['_id'];
			if ( isset( $bmap[ $id ] ) || isset( $lmap[ $id ] ) ) {
				continue;
			}
			$pos = 0;
			for ( $j = $i - 1; $j >= 0; $j-- ) {
				$found = array_search( $remote[ $j ]['_id'], array_column( $out, '_id' ), true );
				if ( false !== $found ) {
					$pos = $found + 1;
					break;
				}
			}
			array_splice( $out, $pos, 0, array( $row ) );
			++$this->stats['added'];
		}
		return $out;
	}

	private static function index( $list ) {
		$map = array();
		foreach ( (array) $list as $i => $el ) {
			if ( is_array( $el ) && isset( $el['id'] ) ) {
				$map[ $el['id'] ] = $el;
			}
		}
		return $map;
	}

	/** True when $a and $b render the same (settings incl. defaults + children). */
	public function same_tree( $a, $b ) {
		$defaults = $this->defaults( $a );
		$sa       = self::settings_of( $a );
		$sb       = self::settings_of( $b );
		foreach ( array_unique( array_merge( array_keys( $sa ), array_keys( $sb ) ) ) as $key ) {
			if ( ! self::same( $this->value( $sa, $key, $defaults ), $this->value( $sb, $key, $defaults ) ) ) {
				return false;
			}
		}
		$ca = isset( $a['elements'] ) ? array_values( (array) $a['elements'] ) : array();
		$cb = isset( $b['elements'] ) ? array_values( (array) $b['elements'] ) : array();
		if ( count( $ca ) !== count( $cb ) ) {
			return false;
		}
		foreach ( $ca as $i => $child ) {
			if ( ! isset( $cb[ $i ]['id'], $child['id'] ) || $cb[ $i ]['id'] !== $child['id'] || ! $this->same_tree( $child, $cb[ $i ] ) ) {
				return false;
			}
		}
		return true;
	}

	public function merge_element( $base, $local, $remote ) {
		if ( null === $base ) {
			return $local; // Unknown history: never touch.
		}
		$out             = $local;
		$out['settings'] = $this->merge_settings( self::settings_of( $base ), self::settings_of( $local ), self::settings_of( $remote ), $this->defaults( $local ) );
		$out['elements'] = $this->merge_list(
			isset( $base['elements'] ) ? $base['elements'] : array(),
			isset( $local['elements'] ) ? $local['elements'] : array(),
			isset( $remote['elements'] ) ? $remote['elements'] : array()
		);
		return $out;
	}

	public function merge_list( $base, $local, $remote ) {
		$bmap   = self::index( $base );
		$lmap   = self::index( $local );
		$rmap   = self::index( $remote );
		$result = array();

		foreach ( (array) $local as $el ) {
			$id = isset( $el['id'] ) ? $el['id'] : null;
			if ( null !== $id && isset( $rmap[ $id ] ) ) {
				$result[] = $this->merge_element( isset( $bmap[ $id ] ) ? $bmap[ $id ] : null, $el, $rmap[ $id ] );
			} elseif ( null !== $id && isset( $bmap[ $id ] ) && $this->same_tree( $el, $bmap[ $id ] ) ) {
				++$this->stats['removed']; // Removed from the kit and never touched by the owner.
			} else {
				$result[] = $el; // Owner's own element, or kit element the owner edited.
			}
		}

		$remote = array_values( (array) $remote );
		foreach ( $remote as $i => $el ) {
			if ( ! isset( $el['id'] ) || isset( $bmap[ $el['id'] ] ) || isset( $lmap[ $el['id'] ] ) ) {
				continue; // Existed before: if it is missing locally the owner deleted it.
			}
			$pos = 0;
			for ( $j = $i - 1; $j >= 0; $j-- ) {
				$found = self::position( $result, isset( $remote[ $j ]['id'] ) ? $remote[ $j ]['id'] : null );
				if ( null !== $found ) {
					$pos = $found + 1;
					break;
				}
			}
			array_splice( $result, $pos, 0, array( $el ) );
			++$this->stats['added'];
		}
		return $result;
	}

	private static function position( $list, $id ) {
		if ( null === $id ) {
			return null;
		}
		foreach ( $list as $i => $el ) {
			if ( isset( $el['id'] ) && $el['id'] === $id ) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * Number of differences between the last import and the current content
	 * (changed settings + added/removed elements). 0 = untouched.
	 */
	public function count_changes( $base, $local ) {
		$count = 0;
		$bmap  = self::index( $base );
		$lmap  = self::index( $local );
		foreach ( $lmap as $id => $el ) {
			if ( ! isset( $bmap[ $id ] ) ) {
				++$count;
				continue;
			}
			$defaults = $this->defaults( $el );
			$sl       = self::settings_of( $el );
			$sb       = self::settings_of( $bmap[ $id ] );
			foreach ( array_unique( array_merge( array_keys( $sl ), array_keys( $sb ) ) ) as $key ) {
				if ( ! self::same( $this->value( $sl, $key, $defaults ), $this->value( $sb, $key, $defaults ) ) ) {
					++$count;
				}
			}
			$count += $this->count_changes(
				isset( $bmap[ $id ]['elements'] ) ? $bmap[ $id ]['elements'] : array(),
				isset( $el['elements'] ) ? $el['elements'] : array()
			);
		}
		$count += count( array_diff_key( $bmap, $lmap ) );
		return $count;
	}
}
