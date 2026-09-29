<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class JMK_Widget_Guide extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-guide';
	}

	public function get_title() {
		return __( 'JM Moving Guide (Article)', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-post-content';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Guide', 'jmk' ) ) );
		$this->add_section_head_controls( 'Moving in North Texas', 'What to know before you move in Dallas–Fort Worth' );
		$this->add_control(
			'body',
			array(
				'label'   => __( 'Article body', 'jmk' ),
				'type'    => Controls_Manager::WYSIWYG,
				'default' => self::default_body(),
			)
		);
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	private static function default_body() {
		return '<p>The Metroplex is one of the fastest-growing regions in the country, and that shows up on move day. Demand peaks from May through August, when leases turn over and families move between school years — so summer weekends book out first and cost more across the industry. If your dates are flexible, a mid-week move in the fall or winter is almost always the smoothest and most affordable option.</p>
<p>Distance matters less here than access does. A ten-mile move from Grand Prairie to Arlington can take less time than a two-mile move into a downtown Dallas high-rise that needs a reserved freight elevator, a certificate of insurance, and a loading-dock window. When you get your estimate, tell us about stairs, elevators, long carries, and gate or HOA rules — those shape your crew size and hours far more than mileage.</p>
<h3>What actually drives your moving cost</h3>
<ul>
<li><b>Crew size and hours.</b> Local moves are billed hourly; a larger crew costs more per hour but usually finishes faster.</li>
<li><b>Access at both ends.</b> Elevators, stairs and long walks from the truck add time to every trip.</li>
<li><b>How well you\'re packed.</b> Boxed and labeled moves load far faster than loose items.</li>
<li><b>Specialty items.</b> Pianos, safes, treadmills and oversized furniture need extra hands and equipment.</li>
<li><b>Timing.</b> Month-end, weekends and summer are the busiest windows across DFW.</li>
</ul>
<h3>A simple two-week moving checklist</h3>
<ul>
<li><b>Two weeks out:</b> confirm your date, pack rarely-used rooms, and order boxes or book packing help.</li>
<li><b>One week out:</b> transfer utilities, file a USPS change of address, and reserve elevators or gate access.</li>
<li><b>Two days out:</b> pack an essentials bag, defrost the fridge, and set aside items movers can\'t take.</li>
<li><b>Move day:</b> keep documents, medications and valuables with you and do a final walkthrough.</li>
</ul>
<p>Not sure where your move lands? Start with the <a href="/quote/">free quote builder</a> or call <a href="tel:+19726387479"><b>(972) 638-7479</b></a> and talk it through with someone in Grand Prairie.</p>';
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="jmk jmk-guide"><section class="jmk-sec jmk-pt0"><div class="jmk-wrap">';
		$this->render_section_head( $s );
		echo '<div class="jmk-prose">' . wp_kses_post( $this->parse_text_editor( $s['body'] ) ) . '</div>';
		echo '</div></section></div>';
	}
}
