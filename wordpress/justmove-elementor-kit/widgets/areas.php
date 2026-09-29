<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Repeater;

class JMK_Widget_Areas extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-areas';
	}

	public function get_title() {
		return __( 'JM Service Areas', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-google-maps';
	}


	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Areas', 'jmk' ) ) );
		$this->add_control(
			'anchor',
			array(
				'label'   => __( 'Section anchor ID', 'jmk' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 'areas',
			)
		);
		$this->add_section_head_controls(
			'Areas we serve',
			'Serving the entire DFW Metroplex',
			'Just Move DFW proudly serves Grand Prairie, Dallas, Arlington, Fort Worth, Irving, Mansfield, Midlothian, and every city within 50 miles of Grand Prairie.'
		);
		$rep = new Repeater();
		$rep->add_control(
			'label',
			array(
				'label'   => __( 'Label', 'jmk' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'City movers', 'jmk' ),
			)
		);
		$rep->add_control(
			'link',
			array(
				'label' => __( 'Link', 'jmk' ),
				'type'  => Controls_Manager::URL,
			)
		);
		$defaults = array();
		foreach ( self::CITIES as $city ) {
			$defaults[] = array(
				'label' => $city . ' movers',
				'link'  => array( 'url' => '/movers-' . sanitize_title( $city ) . '/' ),
			);
		}
		$this->add_control(
			'cities',
			array(
				'label'       => __( 'Cities', 'jmk' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ label }}}',
				'default'     => $defaults,
			)
		);
		$this->add_control(
			'more_text',
			array(
				'label'     => __( '"See all" link text', 'jmk' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => 'See all service areas ➤',
				'separator' => 'before',
			)
		);
		$this->add_control(
			'more_link',
			array(
				'label'   => __( '"See all" link', 'jmk' ),
				'type'    => Controls_Manager::URL,
				'default' => array( 'url' => '/areas/' ),
			)
		);
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	protected function render() {
		$s  = $this->get_settings_for_display();
		$id = '' !== $s['anchor'] ? ' id="' . esc_attr( $s['anchor'] ) . '"' : '';
		echo '<div class="jmk jmk-areas"><section class="jmk-sec jmk-pt0"' . $id . '><div class="jmk-wrap">'; // phpcs:ignore
		$this->render_section_head( $s );
		echo '<div class="jmk-citylinks">';
		foreach ( $s['cities'] as $i => $city ) {
			$this->render_link( 'city' . $i, $city['link'], '', esc_html( $city['label'] ) );
		}
		echo '</div>';
		if ( '' !== $s['more_text'] ) {
			echo '<p class="jmk-more">';
			$this->render_link( 'more', $s['more_link'], '', esc_html( $s['more_text'] ) );
			echo '</p>';
		}
		echo '</div></section></div>';
	}
}
