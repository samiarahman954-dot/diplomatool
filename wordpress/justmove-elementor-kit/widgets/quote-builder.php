<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class JMK_Widget_Quote_Builder extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-quote-builder';
	}

	public function get_title() {
		return __( 'JM Quote Builder', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	public function get_keywords() {
		return array( 'quote', 'estimate', 'form', 'price', 'just move' );
	}

	public function get_style_depends() {
		return array( 'jmk-quote-fonts', 'jmk-quote' );
	}

	public function get_script_depends() {
		return array( 'jmk-quote' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Quote builder', 'jmk' ) ) );
		$this->add_control(
			'info',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: %s: settings screen URL */
					__( 'Prices, company details and emails are set in <a href="%s" target="_blank">Just Move Kit → Quote Builder</a>. Submissions appear under Just Move Kit → Leads.', 'jmk' ),
					esc_url( admin_url( 'admin.php?page=' . JMK_Quote::SETTINGS_SLUG ) )
				),
				'content_classes' => 'elementor-descriptor',
			)
		);
		$this->add_control(
			'brand',
			array(
				'label'       => __( 'Header name', 'jmk' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => __( 'Company name from settings', 'jmk' ),
			)
		);
		$this->add_control(
			'tagline',
			array(
				'label'       => __( 'Header tagline', 'jmk' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => __( 'Tagline from settings', 'jmk' ),
			)
		);
		$this->add_control(
			'logo',
			array(
				'label'       => __( 'Logo (optional)', 'jmk' ),
				'type'        => Controls_Manager::MEDIA,
				'description' => __( 'Use a JPG or PNG so it also appears on the PDF estimate.', 'jmk' ),
			)
		);
		$this->add_control(
			'background',
			array(
				'label'   => __( 'Soft page background', 'jmk' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'style',
			array(
				'label' => __( 'Colours', 'jmk' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$vars = array(
			'navy'   => array( __( 'Navy', 'jmk' ), '#0c3a52' ),
			'blue'   => array( __( 'Blue', 'jmk' ), '#27a7e0' ),
			'yellow' => array( __( 'Yellow', 'jmk' ), '#f6de2d' ),
			'ink'    => array( __( 'Text', 'jmk' ), '#0b1620' ),
		);
		foreach ( $vars as $var => $def ) {
			$this->add_control(
				'c_' . $var,
				array(
					'label'       => $def[0],
					'type'        => Controls_Manager::COLOR,
					'placeholder' => $def[1],
					'selectors'   => array( '{{WRAPPER}} .jmq' => '--' . $var . ': {{VALUE}};' ),
				)
			);
		}
		$this->add_control(
			'bg_color',
			array(
				'label'     => __( 'Outer background', 'jmk' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .jmq' => 'background: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo JMK_Quote::render( // phpcs:ignore WordPress.Security.EscapeOutput -- markup is escaped inside render().
			array(
				'brand'      => $s['brand'],
				'tagline'    => $s['tagline'],
				'logo'       => ! empty( $s['logo']['url'] ) ? $s['logo']['url'] : '',
				'background' => 'yes' === $s['background'],
			)
		);
	}
}
