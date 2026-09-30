<?php
/**
 * Shared base for every kit widget: category, assets, brand/section style
 * controls and small render helpers (section heading, buttons, icons).
 *
 * Keeping site owners' edits across plugin updates depends on one rule:
 * never rename or remove a control ID (or a widget name). Elementor stores
 * edited values by control ID, so a renamed control silently drops them.
 * Add new controls instead; changing a default only affects untouched fields.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;

abstract class JMK_Widget_Base extends Widget_Base {

	/**
	 * Default DFW cities (service areas list + business schema).
	 */
	const CITIES = array( 'Grand Prairie', 'Arlington', 'Irving', 'Dallas', 'Fort Worth', 'Mansfield', 'Cedar Hill', 'Duncanville', 'DeSoto', 'Grapevine', 'Euless', 'Bedford', 'Hurst', 'Coppell', 'Carrollton', 'Garland', 'Mesquite', 'Richardson', 'Plano', 'Frisco', 'Lewisville' );

	public function get_categories() {
		return array( 'jmk' );
	}

	public function get_style_depends() {
		return array( 'jmk-fonts', 'jmk-kit' );
	}

	public function get_script_depends() {
		return array( 'jmk-kit' );
	}

	public function get_keywords() {
		return array( 'just move', 'moving', 'movers', 'jmk' );
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	/* ------------------------------------------------------------------
	 * Controls
	 * ------------------------------------------------------------------ */

	/**
	 * Eyebrow / title / description block used by most sections.
	 */
	protected function add_section_head_controls( $eyebrow, $title, $desc = '' ) {
		$this->add_control(
			'eyebrow',
			array(
				'label'       => __( 'Eyebrow', 'jmk' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $eyebrow,
				'label_block' => true,
			)
		);
		$this->add_control(
			'title',
			array(
				'label'       => __( 'Title', 'jmk' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'default'     => $title,
			)
		);
		$this->add_control(
			'description',
			array(
				'label'   => __( 'Description', 'jmk' ),
				'type'    => Controls_Manager::TEXTAREA,
				'rows'    => 3,
				'default' => $desc,
			)
		);
	}

	/**
	 * Text + link + style for one button.
	 */
	protected function add_button_controls( $prefix, $label, $text, $url, $style = 'y', $condition = array() ) {
		$this->add_control(
			$prefix . '_heading',
			array(
				'label'     => $label,
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => $condition,
			)
		);
		$this->add_control(
			$prefix . '_text',
			array(
				'label'       => __( 'Text', 'jmk' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $text,
				'label_block' => true,
				'condition'   => $condition,
			)
		);
		$this->add_control(
			$prefix . '_link',
			array(
				'label'     => __( 'Link', 'jmk' ),
				'type'      => Controls_Manager::URL,
				'default'   => array( 'url' => $url ),
				'condition' => $condition,
			)
		);
		$this->add_control(
			$prefix . '_style',
			array(
				'label'     => __( 'Style', 'jmk' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => $style,
				'options'   => array(
					'y'     => __( 'Yellow', 'jmk' ),
					'b'     => __( 'Blue', 'jmk' ),
					'ghost' => __( 'Outline', 'jmk' ),
				),
				'condition' => $condition,
			)
		);
	}

	/**
	 * Emoji-or-icon picker for repeater items.
	 */
	protected function icon_fields( $emoji ) {
		return array(
			array(
				'name'    => 'emoji',
				'label'   => __( 'Emoji / text icon', 'jmk' ),
				'type'    => Controls_Manager::TEXT,
				'default' => $emoji,
			),
			array(
				'name'        => 'icon',
				'label'       => __( 'Or pick an icon', 'jmk' ),
				'type'        => Controls_Manager::ICONS,
				'description' => __( 'Overrides the emoji when set.', 'jmk' ),
				'skin'        => 'inline',
				'label_block' => false,
			),
		);
	}

	/**
	 * Column-count select; rendered as the --cols CSS variable.
	 */
	protected function add_columns_control( $default, array $options = array( 2, 3, 4 ) ) {
		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Columns (desktop)', 'jmk' ),
				'type'    => Controls_Manager::SELECT,
				'default' => (string) $default,
				'options' => array_combine( array_map( 'strval', $options ), array_map( 'strval', $options ) ),
			)
		);
	}

	/**
	 * Repeater of icon / title / text cards (Services, Why us).
	 */
	protected function add_cards_controls( array $defaults, $with_link ) {
		$rep = new Repeater();
		foreach ( $this->icon_fields( '🏠' ) as $field ) {
			$name = $field['name'];
			unset( $field['name'] );
			$rep->add_control( $name, $field );
		}
		$rep->add_control(
			'title',
			array(
				'label'       => __( 'Title', 'jmk' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Card title', 'jmk' ),
				'label_block' => true,
			)
		);
		$rep->add_control(
			'text',
			array(
				'label'   => __( 'Text', 'jmk' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => '',
			)
		);
		if ( $with_link ) {
			$rep->add_control(
				'link',
				array(
					'label' => __( 'Link (optional)', 'jmk' ),
					'type'  => Controls_Manager::URL,
				)
			);
		}
		$this->add_control(
			'cards',
			array(
				'label'       => __( 'Cards', 'jmk' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ title }}}',
				'default'     => $defaults,
			)
		);
	}

	protected function render_cards( array $s ) {
		echo '<div class="jmk-grid" style="--cols:' . (int) $s['columns'] . '">';
		foreach ( $s['cards'] as $i => $card ) {
			ob_start();
			$this->render_icon( $card );
			echo '<h3>' . esc_html( $card['title'] ) . '</h3>';
			if ( '' !== $card['text'] ) {
				echo '<p>' . esc_html( $card['text'] ) . '</p>';
			}
			$inner = ob_get_clean();

			if ( ! empty( $card['link']['url'] ) ) {
				$this->render_link( 'card' . $i, $card['link'], 'jmk-card', $inner );
			} else {
				echo '<div class="jmk-card">' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
		echo '</div>';
	}

	/**
	 * Style tab: brand colours (CSS variables), section spacing, typography.
	 * Call at the end of register_controls(). Pass false for widgets without a
	 * .jmk-sec box (header, mobile bar) so no dead "Section layout" controls show.
	 */
	protected function add_brand_style_controls( $with_layout = true ) {
		$this->start_controls_section(
			'jmk_style_brand',
			array(
				'label' => __( 'Brand colours', 'jmk' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$vars = array(
			'bg'     => array( __( 'Background', 'jmk' ), '#0B0C0E' ),
			'panel'  => array( __( 'Card / panel', 'jmk' ), '#14161B' ),
			'text'   => array( __( 'Text', 'jmk' ), '#ECEEF1' ),
			'muted'  => array( __( 'Muted text', 'jmk' ), '#9AA2AD' ),
			'yellow' => array( __( 'Accent yellow', 'jmk' ), '#FFD21E' ),
			'blue'   => array( __( 'Accent blue', 'jmk' ), '#36ABE6' ),
			'line'   => array( __( 'Borders', 'jmk' ), 'rgba(255,255,255,.09)' ),
		);
		foreach ( $vars as $var => $def ) {
			$this->add_control(
				'jmk_c_' . $var,
				array(
					'label'       => $def[0],
					'type'        => Controls_Manager::COLOR,
					'placeholder' => $def[1],
					'selectors'   => array( '{{WRAPPER}} .jmk' => '--' . $var . ': {{VALUE}};' ),
				)
			);
		}

		$this->end_controls_section();

		if ( $with_layout ) {
			$this->add_layout_style_controls();
		}

		$this->start_controls_section(
			'jmk_style_type',
			array(
				'label' => __( 'Typography', 'jmk' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'jmk_heading_typo',
				'label'    => __( 'Headings', 'jmk' ),
				'selector' => '{{WRAPPER}} .jmk h1, {{WRAPPER}} .jmk h2, {{WRAPPER}} .jmk h3',
			)
		);
		$this->add_control(
			'jmk_heading_color',
			array(
				'label'     => __( 'Heading colour', 'jmk' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .jmk h1, {{WRAPPER}} .jmk h2, {{WRAPPER}} .jmk h3' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'jmk_body_typo',
				'label'    => __( 'Body text', 'jmk' ),
				'selector' => '{{WRAPPER}} .jmk p, {{WRAPPER}} .jmk li',
			)
		);
		$this->end_controls_section();
	}

	private function add_layout_style_controls() {
		$this->start_controls_section(
			'jmk_style_layout',
			array(
				'label' => __( 'Section layout', 'jmk' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			'jmk_padding',
			array(
				'label'      => __( 'Section padding', 'jmk' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'vh' ),
				'selectors'  => array(
					'{{WRAPPER}} .jmk-sec' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'jmk_maxw',
			array(
				'label'      => __( 'Content max width', 'jmk' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 600, 'max' => 1600 ) ),
				'selectors'  => array( '{{WRAPPER}} .jmk' => '--maxw: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'jmk_sec_bg',
			array(
				'label'     => __( 'Section background', 'jmk' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .jmk-sec' => 'background: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------
	 * Render helpers
	 * ------------------------------------------------------------------ */

	protected function render_section_head( array $s ) {
		if ( '' === trim( $s['eyebrow'] . $s['title'] . $s['description'] ) ) {
			return;
		}
		echo '<div class="jmk-sec-head">';
		if ( '' !== $s['eyebrow'] ) {
			echo '<span class="jmk-eyebrow">' . esc_html( $s['eyebrow'] ) . '</span>';
		}
		if ( '' !== $s['title'] ) {
			echo '<h2>' . esc_html( $s['title'] ) . '</h2>';
		}
		if ( '' !== $s['description'] ) {
			echo '<p>' . esc_html( $s['description'] ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Echo an <a> built from a URL control value.
	 *
	 * @param string $key   Unique render-attribute key.
	 * @param array  $link  URL control value.
	 * @param string $class CSS classes.
	 * @param string $inner Already-escaped inner HTML.
	 */
	protected function render_link( $key, $link, $class, $inner ) {
		$this->add_render_attribute( $key, 'class', $class );
		if ( ! empty( $link['url'] ) ) {
			$this->add_link_attributes( $key, $link );
		}
		echo '<a ' . $this->get_render_attribute_string( $key ) . '>' . $inner . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	protected function render_button( array $s, $prefix, $extra_class = '' ) {
		if ( empty( $s[ $prefix . '_text' ] ) ) {
			return;
		}
		$style = isset( $s[ $prefix . '_style' ] ) ? $s[ $prefix . '_style' ] : 'y';
		$this->render_link(
			'btn_' . $prefix,
			$s[ $prefix . '_link' ],
			trim( 'jmk-btn jmk-btn-' . $style . ' ' . $extra_class ),
			esc_html( $s[ $prefix . '_text' ] )
		);
	}

	protected function render_icon( array $item, $class = 'jmk-ico' ) {
		echo '<div class="' . esc_attr( $class ) . '" aria-hidden="true">';
		if ( ! empty( $item['icon']['value'] ) ) {
			Icons_Manager::render_icon( $item['icon'], array( 'aria-hidden' => 'true' ) );
		} elseif ( isset( $item['emoji'] ) ) {
			echo esc_html( $item['emoji'] );
		}
		echo '</div>';
	}

	/**
	 * Allow simple inline formatting (<b>, <a>, <br>, <em>) in text fields.
	 */
	protected static function inline_kses( $text ) {
		return wp_kses(
			$text,
			array(
				'b'      => array(),
				'strong' => array(),
				'em'     => array(),
				'br'     => array(),
				'a'      => array(
					'href'   => true,
					'target' => true,
					'rel'    => true,
				),
			)
		);
	}
}
