<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JMK_Widget_Why_Us extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-why-us';
	}

	public function get_title() {
		return __( 'JM Why Choose Us', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-star-o';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Why choose us', 'jmk' ) ) );
		$this->add_section_head_controls(
			'Why choose us',
			'The pros DFW trusts to handle the heavy lifting',
			"Finding reputable movers in DFW shouldn't feel like a gamble. Here's what you get with Just Move DFW."
		);
		$this->add_columns_control( 4 );
		$this->add_cards_controls(
			array(
				array(
					'emoji' => '📍',
					'title' => 'Locally owned in Grand Prairie',
					'text'  => "Faster response times and crews who know DFW traffic — a business personally invested in this community's reputation.",
				),
				array(
					'emoji' => '💰',
					'title' => 'Straightforward pricing',
					'text'  => 'Clear hourly and flat rates with truck and equipment included, and no surprise fees at your door.',
				),
				array(
					'emoji' => '🛡',
					'title' => 'Licensed & insured',
					'text'  => 'A fully licensed, insured Texas mover. Your belongings are protected from the moment we load the truck.',
				),
				array(
					'emoji' => '⭐',
					'title' => 'Rated 4.7★ by neighbors',
					'text'  => '218 Google reviews from real DFW customers — reputable movers you can check before you book.',
				),
			),
			true
		);
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="jmk jmk-why"><section class="jmk-sec jmk-pt0"><div class="jmk-wrap">';
		$this->render_section_head( $s );
		$this->render_cards( $s );
		echo '</div></section></div>';
	}
}
