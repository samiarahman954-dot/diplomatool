<?php
/**
 * Shared base for all Lilo Cafe widgets.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Widget_Base;

abstract class Lilo_Widget_Base extends Widget_Base {

	public function get_categories() {
		return array( 'lilo-cafe' );
	}

	public function get_keywords() {
		return array( 'lilo', 'cafe' );
	}

	public function get_style_depends() {
		return array( 'lilo-main' );
	}

	public function get_script_depends() {
		return array( 'lilo-main' );
	}

	/**
	 * Default value from the demo content.
	 */
	protected function d( $key, $fallback = '' ) {
		$defaults = lilo_demo_defaults( $this->get_name() );
		return array_key_exists( $key, $defaults ) ? $defaults[ $key ] : $fallback;
	}

	/**
	 * Repeater defaults need an _id per row.
	 */
	protected function d_rows( $key ) {
		$rows = $this->d( $key, array() );
		foreach ( $rows as $i => $row ) {
			if ( empty( $row['_id'] ) ) {
				$rows[ $i ]['_id'] = substr( md5( $this->get_name() . $key . $i ), 0, 7 );
			}
		}
		return $rows;
	}

	/* ---------------------------------------------------------------------
	 * Content controls
	 * ------------------------------------------------------------------- */

	protected function add_anchor_control() {
		$this->add_control(
			'anchor_id',
			array(
				'label'       => __( 'Section ID (anchor)', 'lilo-cafe' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $this->d( 'anchor_id' ),
				'description' => __( 'Link to this section with #id, e.g. #about. Letters, numbers and dashes only.', 'lilo-cafe' ),
				'ai'          => array( 'active' => false ),
			)
		);
	}

	/**
	 * Script eyebrow + serif title (+ optional small caps note).
	 */
	protected function add_heading_controls( $with_note = false ) {
		$this->add_control(
			'script',
			array(
				'label'       => __( 'Script text', 'lilo-cafe' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $this->d( 'script' ),
				'label_block' => true,
			)
		);
		$this->add_control(
			'title',
			array(
				'label'       => __( 'Title', 'lilo-cafe' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'default'     => $this->d( 'title' ),
			)
		);
		$this->add_control(
			'title_tag',
			array(
				'label'   => __( 'Title HTML tag', 'lilo-cafe' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h2',
				'options' => array(
					'h1'  => 'H1',
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'div' => 'div',
				),
			)
		);
		if ( $with_note ) {
			$this->add_control(
				'note',
				array(
					'label'       => __( 'Note', 'lilo-cafe' ),
					'type'        => Controls_Manager::TEXT,
					'default'     => $this->d( 'note' ),
					'label_block' => true,
				)
			);
		}
	}

	protected function add_image_control( $id, $label, $condition = array() ) {
		$args = array(
			'label'   => $label,
			'type'    => Controls_Manager::MEDIA,
			'default' => $this->clean_media( $this->d( $id, array( 'url' => '', 'id' => '' ) ) ),
		);
		if ( $condition ) {
			$args['condition'] = $condition;
		}
		$this->add_control( $id, $args );
	}

	protected function add_link_control( $id, $label, $condition = array() ) {
		$args = array(
			'label'       => $label,
			'type'        => Controls_Manager::URL,
			'default'     => $this->d( $id, array( 'url' => '' ) ),
			'placeholder' => 'https://',
			'description' => __( 'Tip: {home} = site address, {menu} = Menu page.', 'lilo-cafe' ),
			'dynamic'     => array( 'active' => true ),
		);
		if ( $condition ) {
			$args['condition'] = $condition;
		}
		$this->add_control( $id, $args );
	}

	/**
	 * Media defaults without import hints.
	 */
	protected function clean_media( $media ) {
		if ( ! is_array( $media ) ) {
			return array( 'url' => '', 'id' => '' );
		}
		return array(
			'url' => isset( $media['url'] ) ? $media['url'] : '',
			'id'  => isset( $media['id'] ) ? $media['id'] : '',
		);
	}

	/**
	 * Strips import hints from repeater rows with images.
	 */
	protected function clean_rows( $rows, $media_keys = array( 'image' ) ) {
		foreach ( $rows as $i => $row ) {
			foreach ( $media_keys as $key ) {
				if ( isset( $row[ $key ] ) ) {
					$rows[ $i ][ $key ] = $this->clean_media( $row[ $key ] );
				}
			}
		}
		return $rows;
	}

	/* ---------------------------------------------------------------------
	 * Style controls
	 * ------------------------------------------------------------------- */

	/**
	 * Section box: background, padding, inner width, borders.
	 *
	 * @param string $outer Selector of the full-width band.
	 * @param string $inner Selector of the centered inner wrapper.
	 */
	protected function add_box_style_section( $outer = '.lilo-sec', $inner = '.lilo-sec__inner' ) {
		$this->start_controls_section(
			'section_style_box',
			array(
				'label' => __( 'Section', 'lilo-cafe' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'box_bg',
			array(
				'label'     => __( 'Background color', 'lilo-cafe' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} ' . $outer => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'box_padding',
			array(
				'label'      => __( 'Padding', 'lilo-cafe' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%', 'vw' ),
				'selectors'  => array( '{{WRAPPER}} ' . $inner => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'box_max_width',
			array(
				'label'      => __( 'Content max width', 'lilo-cafe' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 600, 'max' => 1920 ) ),
				'selectors'  => array( '{{WRAPPER}} ' . $inner => 'max-width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'box_border',
				'selector' => '{{WRAPPER}} ' . $inner,
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Heading + color + typography for one text element. Call inside an open section.
	 */
	protected function add_text_style( $id, $label, $selector, $with_typography = true ) {
		$this->add_control(
			$id . '_heading',
			array(
				'label'     => $label,
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);
		$this->add_control(
			$id . '_color',
			array(
				'label'     => __( 'Color', 'lilo-cafe' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} ' . $selector => 'color: {{VALUE}};' ),
			)
		);
		if ( $with_typography ) {
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => $id . '_typo',
					'selector' => '{{WRAPPER}} ' . $selector,
				)
			);
		}
	}

	/**
	 * A "Texts" style section from [ id => [ label, selector ] ].
	 */
	protected function add_texts_style_section( $items, $label = '' ) {
		$this->start_controls_section(
			'section_style_texts',
			array(
				'label' => $label ? $label : __( 'Texts', 'lilo-cafe' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		foreach ( $items as $id => $item ) {
			$this->add_text_style( $id, $item[0], $item[1] );
		}
		$this->end_controls_section();
	}

	/**
	 * Normal/hover colors for a button.
	 */
	protected function add_button_style_section( $id, $label, $selector ) {
		$this->start_controls_section(
			'section_style_' . $id,
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
		foreach ( array( 'normal' => __( 'Normal', 'lilo-cafe' ), 'hover' => __( 'Hover', 'lilo-cafe' ) ) as $state => $state_label ) {
			$sel = '{{WRAPPER}} ' . $selector . ( 'hover' === $state ? ':hover' : '' );
			$this->start_controls_tab( $id . '_tab_' . $state, array( 'label' => $state_label ) );
			$this->add_control( $id . '_' . $state . '_color', array( 'label' => __( 'Text color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $sel => 'color: {{VALUE}};' ) ) );
			$this->add_control( $id . '_' . $state . '_bg', array( 'label' => __( 'Background', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $sel => 'background-color: {{VALUE}};' ) ) );
			$this->add_control( $id . '_' . $state . '_border', array( 'label' => __( 'Border color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $sel => 'border-color: {{VALUE}};' ) ) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->add_responsive_control(
			$id . '_padding',
			array(
				'label'      => __( 'Padding', 'lilo-cafe' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'separator'  => 'before',
				'selectors'  => array( '{{WRAPPER}} ' . $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			$id . '_radius',
			array(
				'label'      => __( 'Border radius', 'lilo-cafe' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
				'selectors'  => array( '{{WRAPPER}} ' . $selector => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Slider for the minimum card width of an auto-fill grid (sets --lilo-min).
	 */
	protected function add_grid_controls( $selector, $default_min, $default_gap = null ) {
		$this->add_responsive_control(
			'grid_min',
			array(
				'label'       => __( 'Card min width', 'lilo-cafe' ),
				'description' => __( 'Cards fill the row; lower = more per row.', 'lilo-cafe' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 120, 'max' => 600 ) ),
				'placeholder' => $default_min,
				'selectors'   => array( '{{WRAPPER}} ' . $selector => '--lilo-min: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'grid_gap',
			array(
				'label'      => __( 'Gap', 'lilo-cafe' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
				'selectors'  => array( '{{WRAPPER}} ' . $selector => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);
	}

	/**
	 * Image radius / height controls for an arch or photo frame.
	 */
	protected function add_frame_controls( $selector, $label, $with_height = true ) {
		$this->add_control(
			'frame_heading',
			array(
				'label'     => $label,
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);
		if ( $with_height ) {
			$this->add_responsive_control(
				'frame_height',
				array(
					'label'      => __( 'Height', 'lilo-cafe' ),
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'vh', 'vw' ),
					'range'      => array( 'px' => array( 'min' => 200, 'max' => 1000 ) ),
					'selectors'  => array( '{{WRAPPER}} ' . $selector => 'height: {{SIZE}}{{UNIT}};' ),
				)
			);
		}
		$this->add_responsive_control(
			'frame_radius',
			array(
				'label'      => __( 'Corner radius', 'lilo-cafe' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} ' . $selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Render helpers
	 * ------------------------------------------------------------------- */

	protected function id_attr( $s ) {
		$id = isset( $s['anchor_id'] ) ? sanitize_html_class( $s['anchor_id'] ) : '';
		return $id ? ' id="' . esc_attr( $id ) . '"' : '';
	}

	protected function heading_html( $s, $modifier = '' ) {
		$tag = isset( $s['title_tag'] ) && in_array( $s['title_tag'], array( 'h1', 'h2', 'h3', 'h4', 'div' ), true ) ? $s['title_tag'] : 'h2';
		$out = '<div class="lilo-heading' . ( $modifier ? ' ' . esc_attr( $modifier ) : '' ) . '">';
		if ( ! empty( $s['script'] ) ) {
			$out .= '<div class="lilo-script">' . esc_html( $s['script'] ) . '</div>';
		}
		if ( ! empty( $s['title'] ) ) {
			$out .= '<' . $tag . ' class="lilo-title">' . nl2br( esc_html( $s['title'] ) ) . '</' . $tag . '>';
		}
		if ( ! empty( $s['note'] ) ) {
			$out .= '<p class="lilo-note-caps">' . esc_html( $s['note'] ) . '</p>';
		}
		return $out . '</div>';
	}

	protected function button_html( $text, $link, $class = 'lilo-btn' ) {
		if ( '' === trim( (string) $text ) ) {
			return '';
		}
		return '<a class="' . esc_attr( $class ) . '"' . lilo_link_attrs( $link ) . '>' . esc_html( $text ) . '</a>';
	}

	protected function img( $media, $alt = '', $size = 'large' ) {
		return lilo_img( $media, $alt, $size );
	}
}
