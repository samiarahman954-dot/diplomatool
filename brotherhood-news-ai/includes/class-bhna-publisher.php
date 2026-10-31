<?php
/**
 * Creates the WordPress post from Claude's rewrite. Posts are only ever created as draft or pending.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Publisher {

	const ALLOWED_STATUSES = array( 'draft', 'pending' );

	/**
	 * @return int|WP_Error Post id.
	 */
	public static function create_post( array $item, array $feed, array $rewrite, array $missing ) {
		$status = BHNA_Settings::get( 'post_status' );
		if ( ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			$status = 'draft';
		}

		$postarr = array(
			'post_title'   => wp_strip_all_tags( $rewrite['titulo'] ),
			'post_content' => self::content( $rewrite['cuerpo'], $item ),
			'post_excerpt' => wp_strip_all_tags( isset( $rewrite['extracto'] ) ? $rewrite['extracto'] : '' ),
			'post_status'  => $status,
			'post_type'    => 'post',
			'post_author'  => self::author_id(),
			'tags_input'   => array_map( 'sanitize_text_field', $rewrite['etiquetas'] ),
			'meta_input'   => array(
				'_bhna_item_id'     => (int) $item['id'],
				'_bhna_feed_id'     => $feed['id'],
				'_bhna_source_name' => $item['source_name'],
				'_bhna_source_url'  => $item['source_url'],
				'_bhna_country'     => $feed['country'],
				'_bhna_model'       => $rewrite['model'],
			),
		);

		if ( ! empty( $feed['category_id'] ) ) {
			$postarr['post_category'] = array( (int) $feed['category_id'] );
		}

		if ( $missing ) {
			$postarr['meta_input']['_bhna_quality_missing'] = $missing;
		}

		if ( BHNA_Settings::get( 'seo_meta' ) && ! empty( $rewrite['meta_descripcion'] ) ) {
			$description = sanitize_text_field( $rewrite['meta_descripcion'] );
			if ( defined( 'WPSEO_VERSION' ) ) {
				$postarr['meta_input']['_yoast_wpseo_metadesc'] = $description;
			}
			if ( defined( 'RANK_MATH_VERSION' ) ) {
				$postarr['meta_input']['rank_math_description'] = $description;
			}
		}

		return wp_insert_post( wp_slash( $postarr ), true );
	}

	public static function content( array $paragraphs, array $item ) {
		$html = '';
		foreach ( $paragraphs as $paragraph ) {
			$html .= "<!-- wp:paragraph -->\n<p>" . esc_html( $paragraph ) . "</p>\n<!-- /wp:paragraph -->\n\n";
		}
		return $html . self::attribution( $item );
	}

	public static function attribution( array $item ) {
		$text = (string) BHNA_Settings::get( 'attribution_text' );
		$name = esc_html( $item['source_name'] );

		if ( BHNA_Settings::get( 'attribution_link' ) && ! empty( $item['source_url'] ) ) {
			$name = sprintf( '<a href="%s" target="_blank" rel="nofollow noopener">%s</a>', esc_url( $item['source_url'] ), $name );
		}

		$text = str_replace( '{medio}', $name, esc_html( $text ) );
		return "<!-- wp:paragraph {\"className\":\"bhna-attribution\"} -->\n<p class=\"bhna-attribution\"><em>" . $text . "</em></p>\n<!-- /wp:paragraph -->";
	}

	/** The configured author, or the first administrator as a fallback (cron runs without a logged-in user). */
	public static function author_id() {
		$author = (int) BHNA_Settings::get( 'post_author' );
		if ( $author && get_userdata( $author ) ) {
			return $author;
		}
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID' ) );
		return $admins ? (int) $admins[0] : 0;
	}
}
