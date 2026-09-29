<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Repeater;

class JMK_Widget_Faq extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-faq';
	}

	public function get_title() {
		return __( 'JM FAQ', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-accordion';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'FAQ', 'jmk' ) ) );
		$this->add_section_head_controls( 'Good to know', 'Frequently asked questions' );
		$rep = new Repeater();
		$rep->add_control(
			'q',
			array(
				'label'       => __( 'Question', 'jmk' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Question?', 'jmk' ),
				'label_block' => true,
			)
		);
		$rep->add_control(
			'a',
			array(
				'label' => __( 'Answer (HTML allowed: b, a, br)', 'jmk' ),
				'type'  => Controls_Manager::TEXTAREA,
				'rows'  => 5,
			)
		);
		$this->add_control(
			'faqs',
			array(
				'label'       => __( 'Questions', 'jmk' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ q }}}',
				'default'     => self::defaults(),
			)
		);
		$this->add_control(
			'first_open',
			array(
				'label'   => __( 'Open the first question', 'jmk' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => '',
			)
		);
		$this->add_control(
			'schema',
			array(
				'label'       => __( 'Output FAQPage schema (JSON-LD)', 'jmk' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => __( 'Helps Google show these answers as rich results.', 'jmk' ),
			)
		);
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	private static function defaults() {
		$rows = array(
			array( 'How much does a local move cost in DFW?', 'The average cost of a local move in Dallas–Fort Worth depends on your home size, the distance, and the crew needed. Local moves are billed by the hour with the truck, fuel and equipment included, and we give you an upfront, all-in estimate before move day — no surprise fees. The fastest way to a real number is our <a href="/quote/">free quote builder</a>.' ),
			array( 'What should I look for in a moving company?', 'Look for licensing, insurance, transparent pricing, and real local reviews. Just Move DFW is a fully licensed and insured moving company in Grand Prairie with a 4.7★ Google rating, upfront rates, and crews that know the DFW metroplex.' ),
			array( 'Are you licensed and insured?', "Yes — Just Move DFW is a fully licensed and insured moving company for both local and long-distance moves. We're glad to share our coverage details before you book, and we can provide a certificate of insurance (COI) when your building requires one." ),
			array( 'Do movers pack boxes for you?', 'Yes. Our full packing services cover partial or complete packing and unpacking, with all materials included. You can have us pack the whole home, just the kitchen and fragile items, or simply deliver supplies — see <a href="/packing-services/">packing services</a>.' ),
			array( 'Is tipping movers required?', "Tipping isn't required, but 10–15% of the total is a common guideline for crews who go above and beyond. It's always your call based on the service you receive." ),
			array( 'How far in advance should I book my movers?', "We recommend 2–3 weeks ahead, especially in the busy May–August season. That said, we keep crew capacity open for last-minute and same-day moves whenever possible — call <a href=\"tel:+19726387479\">(972) 638-7479</a> to check today's availability." ),
			array( 'Do you handle long-distance and out-of-state moves?', 'Absolutely. Along with local DFW moves, we handle long-distance and out-of-state relocations with a clear per-move price agreed up front — no surprise weight tickets. See <a href="/long-distance-moving/">long-distance moving</a> for how it works.' ),
			array( 'What areas do you serve?', "We're based in Grand Prairie and serve every city within about 50 miles — Dallas, Arlington, Fort Worth, Irving, Mansfield, Plano, Frisco and more. Browse <a href=\"/areas/\">all service areas</a> to find your city." ),
		);
		return array_map(
			static function ( $r ) {
				return array( 'q' => $r[0], 'a' => $r[1] );
			},
			$rows
		);
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="jmk jmk-faq-wrap"><section class="jmk-sec jmk-pt0"><div class="jmk-wrap">';
		$this->render_section_head( $s );
		echo '<div class="jmk-faq">';
		foreach ( $s['faqs'] as $i => $f ) {
			$open = ( 0 === $i && 'yes' === $s['first_open'] ) ? ' open' : '';
			echo '<details' . $open . '><summary>' . esc_html( $f['q'] ) . '<span class="pm" aria-hidden="true">+</span></summary>'; // phpcs:ignore
			echo '<div class="ans">' . self::inline_kses( $f['a'] ) . '</div></details>'; // phpcs:ignore
		}
		echo '</div></div></section></div>';

		if ( 'yes' === $s['schema'] && ! empty( $s['faqs'] ) ) {
			$entities = array();
			foreach ( $s['faqs'] as $f ) {
				$entities[] = array(
					'@type'          => 'Question',
					'name'           => wp_strip_all_tags( $f['q'] ),
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => wp_strip_all_tags( $f['a'] ),
					),
				);
			}
			echo '<script type="application/ld+json">' . wp_json_encode(
				array(
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'mainEntity' => $entities,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
			) . '</script>';
		}
	}
}
