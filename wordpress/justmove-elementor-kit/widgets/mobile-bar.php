<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JMK_Widget_Mobile_Bar extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-mobile-bar';
	}

	public function get_title() {
		return __( 'JM Mobile Call Bar', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-device-mobile';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Mobile bar', 'jmk' ) ) );
		$this->add_control(
			'note',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => __( 'Sticks to the bottom of the screen on phones (≤760px). Hidden on desktop.', 'jmk' ),
				'content_classes' => 'elementor-descriptor',
			)
		);
		$this->add_button_controls( 'btn1', __( 'Left button', 'jmk' ), '☎ Call', 'tel:+19726387479', 'ghost' );
		$this->add_button_controls( 'btn2', __( 'Right button', 'jmk' ), 'Get my free quote', '/quote/', 'y' );
		$this->end_controls_section();

		$this->add_brand_style_controls( false );
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="jmk jmk-mbar" data-jmk-mbar>';
		$this->render_button( $s, 'btn1' );
		$this->render_button( $s, 'btn2' );
		echo '</div>';
	}
}
