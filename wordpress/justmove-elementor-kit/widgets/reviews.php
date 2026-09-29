<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Repeater;

class JMK_Widget_Reviews extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-reviews';
	}

	public function get_title() {
		return __( 'JM Reviews', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-testimonial-carousel';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Reviews', 'jmk' ) ) );
		$this->add_section_head_controls(
			'Reviews',
			'Rated 4.7★ by 218 DFW neighbors',
			'Sourced from our Google Business Profile — real crews, real moves.'
		);
		$this->add_columns_control( 2, array( 1, 2, 3 ) );
		$rep = new Repeater();
		$rep->add_control(
			'stars',
			array(
				'label'   => __( 'Stars', 'jmk' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 5,
				'default' => 5,
			)
		);
		$rep->add_control(
			'text',
			array(
				'label'   => __( 'Review', 'jmk' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => '',
			)
		);
		$rep->add_control(
			'who',
			array(
				'label'   => __( 'Author line', 'jmk' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '— Verified Google review',
			)
		);
		$this->add_control(
			'reviews',
			array(
				'label'       => __( 'Reviews', 'jmk' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ who }}}',
				'default'     => array(
					array( 'stars' => 5, 'who' => '— Verified Google review', 'text' => 'The crew moved us out of a third-floor apartment without a single complaint — professional, punctual, and incredibly efficient with the tough access.' ),
					array( 'stars' => 5, 'who' => '— Verified Google review', 'text' => 'An outstanding moving experience from start to finish. The team stayed organized and communicative the whole way through our new-home move.' ),
					array( 'stars' => 5, 'who' => '— Verified Google review', 'text' => 'Scheduling was easy and the crew arrived on time, wrapped every piece of furniture, and protected the walls and floors. Pricing was transparent throughout.' ),
					array( 'stars' => 5, 'who' => '— Verified Google review', 'text' => 'The two-person crew avoided any wall damage and cleaned up thoroughly afterward. Absolutely phenomenal service.' ),
				),
			)
		);
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="jmk jmk-reviews"><section class="jmk-sec"><div class="jmk-wrap">';
		$this->render_section_head( $s );
		echo '<div class="jmk-revs" style="--cols:' . (int) $s['columns'] . '">';
		foreach ( $s['reviews'] as $rev ) {
			$n = max( 1, min( 5, (int) $rev['stars'] ) );
			echo '<div class="jmk-rev"><div class="jmk-stars" aria-label="' . esc_attr( $n . ' / 5' ) . '">' . str_repeat( '&#9733;', $n ) . '</div>';
			echo '<p>' . esc_html( $rev['text'] ) . '</p>';
			echo '<div class="who">' . esc_html( $rev['who'] ) . '</div></div>';
		}
		echo '</div></div></section></div>';
	}
}
