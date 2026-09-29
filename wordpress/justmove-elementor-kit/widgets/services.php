<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JMK_Widget_Services extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-services';
	}

	public function get_title() {
		return __( 'JM Services Grid', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Services', 'jmk' ) ) );
		$this->add_section_head_controls(
			'Our services',
			'Moving services built for every DFW move',
			'From a one-bedroom apartment to a full office relocation — pick the service that fits and get an upfront price.'
		);
		$this->add_columns_control( 4 );
		$this->add_cards_controls(
			array(
				array(
					'emoji' => '🏠',
					'title' => 'Local Moving',
					'text'  => 'Fast, careful local moves throughout Grand Prairie and the surrounding DFW metroplex — hourly rates with the truck included.',
					'link'  => array( 'url' => '/local-moving/' ),
				),
				array(
					'emoji' => '🚚',
					'title' => 'Long-Distance Moving',
					'text'  => 'Relocating out of state or across Texas? We plan every leg of the trip with a clear, per-move price.',
					'link'  => array( 'url' => '/long-distance-moving/' ),
				),
				array(
					'emoji' => '🏢',
					'title' => 'Commercial Moving',
					'text'  => 'Office and business relocations scheduled around your hours, not ours — after-hours and weekends, COI provided.',
					'link'  => array( 'url' => '/commercial-moving/' ),
				),
				array(
					'emoji' => '📦',
					'title' => 'Packing Services',
					'text'  => 'Full packing and unpacking for anyone who wants the move handled start to finish, materials included.',
					'link'  => array( 'url' => '/packing-services/' ),
				),
			),
			true
		);
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="jmk jmk-services"><section class="jmk-sec jmk-pt0"><div class="jmk-wrap">';
		$this->render_section_head( $s );
		$this->render_cards( $s );
		echo '</div></section></div>';
	}
}
