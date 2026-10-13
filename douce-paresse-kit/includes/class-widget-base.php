<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Shared helpers for every Douce Paresse section widget.
 * Each widget renders one full section of the original design with its own markup,
 * so no default Elementor widget is needed anywhere in the kit.
 */
abstract class Base extends Widget_Base {

	public function get_categories() {
		return array( 'douce-paresse' );
	}

	public function get_style_depends() {
		return array( 'dpk-fonts', 'dpk-frontend' );
	}

	public function get_script_depends() {
		return array( 'dpk-frontend' );
	}

	public function get_keywords() {
		return array( 'douce', 'paresse', 'dpk', 'section' );
	}

	/* ------------------------------------------------------------------
	 * Content helpers
	 * ------------------------------------------------------------------ */

	public static function asset( $file ) {
		return DPK_URL . 'demo/images/' . $file;
	}

	protected function media_default( $file ) {
		return array( 'url' => self::asset( $file ) );
	}

	protected function link_default( $url ) {
		return array(
			'url'         => $url,
			'is_external' => '',
			'nofollow'    => '',
		);
	}

	/**
	 * href/target/rel attributes for an Elementor URL control value.
	 */
	protected function link_attrs( $link ) {
		$url = is_array( $link ) ? ( isset( $link['url'] ) ? $link['url'] : '' ) : (string) $link;
		$url = \DPK\Links::url( $url );
		if ( '' === $url ) {
			return '';
		}
		$out = ' href="' . esc_url( $url ) . '"';
		if ( is_array( $link ) && ! empty( $link['is_external'] ) ) {
			$out .= ' target="_blank"';
		}
		$rel = array();
		if ( is_array( $link ) && ! empty( $link['is_external'] ) ) {
			$rel[] = 'noopener';
		}
		if ( is_array( $link ) && ! empty( $link['nofollow'] ) ) {
			$rel[] = 'nofollow';
		}
		if ( $rel ) {
			$out .= ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"';
		}
		return $out;
	}

	protected function media_url( $media ) {
		return is_array( $media ) && ! empty( $media['url'] ) ? $media['url'] : '';
	}

	/**
	 * <img> tag for a media control. Alt text comes from the attachment, then the fallback.
	 */
	protected function img( $media, $class = '', $alt = '', $eager = false, $style = '' ) {
		$url = $this->media_url( $media );
		if ( ! $url ) {
			return '';
		}
		$attrs = array(
			'src'      => $url,
			'class'    => $class,
			'alt'      => $alt,
			'decoding' => 'async',
			'loading'  => $eager ? 'eager' : 'lazy',
		);
		if ( ! empty( $media['id'] ) ) {
			$att_alt = get_post_meta( (int) $media['id'], '_wp_attachment_image_alt', true );
			if ( $att_alt ) {
				$attrs['alt'] = $att_alt;
			}
			$meta = wp_get_attachment_metadata( (int) $media['id'] );
			if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				$attrs['width']  = (int) $meta['width'];
				$attrs['height'] = (int) $meta['height'];
			}
		}
		if ( $style ) {
			$attrs['style'] = $style;
		}
		$html = '<img';
		foreach ( $attrs as $k => $v ) {
			if ( '' === $v && 'alt' !== $k ) {
				continue;
			}
			$html .= ' ' . $k . '="' . ( 'src' === $k ? esc_url( $v ) : esc_attr( $v ) ) . '"';
		}
		return $html . '>';
	}

	protected function text( $value ) {
		return \DPK\Links::html( wp_kses_post( nl2br( (string) $value ) ) );
	}

	/** Plain one-liners (labels, buttons). Allows inline tags only. */
	protected function inline( $value ) {
		return \DPK\Links::html( $this->inline_kses( $value ) );
	}

	private function inline_kses( $value ) {
		return wp_kses(
			(string) $value,
			array(
				'em'     => array(),
				'strong' => array(),
				'br'     => array(),
				'span'   => array( 'class' => array() ),
				'a'      => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			)
		);
	}

	/** Object-position style fragment from a "50% 60%" text control. */
	protected function pos( $value ) {
		$value = trim( (string) $value );
		return $value ? 'object-position:' . preg_replace( '/[^0-9a-z%\s.\-]/i', '', $value ) : '';
	}

	/**
	 * Paragraph repeater (body text, italic quotes, bordered closing lines...).
	 */
	protected function add_paragraphs_control( $id, $label, $default, $styles = null ) {
		if ( null === $styles ) {
			$styles = array(
				'body'          => __( 'Body text', 'douce-paresse-kit' ),
				'quote'         => __( 'Italic quote', 'douce-paresse-kit' ),
				'quote-lg'      => __( 'Italic quote (large)', 'douce-paresse-kit' ),
				'closing'       => __( 'Closing line (italic, top border)', 'douce-paresse-kit' ),
				'closing-plain' => __( 'Closing line (top border)', 'douce-paresse-kit' ),
				'body-bordered' => __( 'Body text with top border', 'douce-paresse-kit' ),
			);
		}
		$rep = new Repeater();
		$rep->add_control(
			'text',
			array(
				'label'       => __( 'Text', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 4,
				'label_block' => true,
			)
		);
		$rep->add_control(
			'style',
			array(
				'label'   => __( 'Style', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $styles,
				'default' => 'body',
			)
		);
		$this->add_control(
			$id,
			array(
				'label'       => $label,
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'default'     => $default,
				'title_field' => '{{{ text.substring(0, 40) }}}',
			)
		);
	}

	protected function render_paragraphs( $items ) {
		if ( empty( $items ) || ! is_array( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( empty( $item['text'] ) ) {
				continue;
			}
			$style = ! empty( $item['style'] ) ? sanitize_html_class( $item['style'] ) : 'body';
			echo '<p class="dpk-p dpk-p--' . esc_attr( $style ) . '">' . $this->text( $item['text'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/** Splits "a | b | c" lines of a textarea into arrays. */
	protected function lines( $value, $cols = 1 ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $value ) as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line, $cols ) );
			$out[] = array_pad( $parts, $cols, '' );
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Style helpers
	 * ------------------------------------------------------------------ */

	/** Style tab section with background + padding for the section wrapper. */
	protected function style_box( $selector, $label = null, $with_bg = true ) {
		$this->start_controls_section(
			'dpk_box_style',
			array(
				'label' => $label ? $label : __( 'Section', 'douce-paresse-kit' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		if ( $with_bg ) {
			$this->add_control(
				'dpk_box_bg',
				array(
					'label'     => __( 'Background color', 'douce-paresse-kit' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} ' . $selector => 'background-color: {{VALUE}};' ),
				)
			);
		}
		$this->add_responsive_control(
			'dpk_box_padding',
			array(
				'label'      => __( 'Padding', 'douce-paresse-kit' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'vw', '%' ),
				'selectors'  => array( '{{WRAPPER}} ' . $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}

	/** Style tab section with color + typography (+ optional extra color props). */
	protected function style_text( $id, $label, $selector, $extra = array() ) {
		$this->start_controls_section(
			$id . '_style',
			array(
				'label' => $label,
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			$id . '_color',
			array(
				'label'     => __( 'Color', 'douce-paresse-kit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} ' . $selector => 'color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => $id . '_typo',
				'selector' => '{{WRAPPER}} ' . $selector,
			)
		);
		foreach ( $extra as $key => $conf ) {
			$this->add_control(
				$id . '_' . $key,
				array(
					'label'     => $conf[0],
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} ' . $conf[1] => $conf[2] . ': {{VALUE}};' ),
				)
			);
		}
		$this->end_controls_section();
	}

	/** Colors for an outline/solid button incl. hover state. */
	protected function style_button( $id, $label, $selector ) {
		$this->start_controls_section(
			$id . '_style',
			array(
				'label' => $label,
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => $id . '_typo',
				'selector' => '{{WRAPPER}} ' . $selector,
			)
		);
		$this->start_controls_tabs( $id . '_tabs' );
		foreach ( array(
			'normal' => array( __( 'Normal', 'douce-paresse-kit' ), '' ),
			'hover'  => array( __( 'Hover', 'douce-paresse-kit' ), ':hover' ),
		) as $state => $conf ) {
			$this->start_controls_tab( $id . '_tab_' . $state, array( 'label' => $conf[0] ) );
			foreach ( array(
				'color'  => array( __( 'Text color', 'douce-paresse-kit' ), 'color' ),
				'bg'     => array( __( 'Background', 'douce-paresse-kit' ), 'background-color' ),
				'border' => array( __( 'Border color', 'douce-paresse-kit' ), 'border-color' ),
			) as $key => $prop ) {
				$this->add_control(
					$id . '_' . $state . '_' . $key,
					array(
						'label'     => $prop[0],
						'type'      => Controls_Manager::COLOR,
						'selectors' => array( '{{WRAPPER}} ' . $selector . $conf[1] => $prop[1] . ': {{VALUE}};' ),
					)
				);
			}
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	/** Accent colors shared by most sections (thin gold lines, eyebrow labels). */
	protected function style_accents( $eyebrow_selector = '' ) {
		$this->start_controls_section(
			'dpk_accents_style',
			array(
				'label' => __( 'Accents', 'douce-paresse-kit' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'dpk_line_color',
			array(
				'label'     => __( 'Lines / dividers', 'douce-paresse-kit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dpk' => '--dpk-line: {{VALUE}};' ),
			)
		);
		if ( $eyebrow_selector ) {
			$this->add_control(
				'dpk_eyebrow_color',
				array(
					'label'     => __( 'Eyebrow / small labels', 'douce-paresse-kit' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} ' . $eyebrow_selector => 'color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => 'dpk_eyebrow_typo',
					'selector' => '{{WRAPPER}} ' . $eyebrow_selector,
				)
			);
		}
		$this->end_controls_section();
	}
}
