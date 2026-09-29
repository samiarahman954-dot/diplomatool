<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Repeater;

class JMK_Widget_Steps extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-steps';
	}

	public function get_title() {
		return __( 'JM How It Works (Steps)', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-number-field';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Steps', 'jmk' ) ) );
		$this->add_section_head_controls( 'How it works', 'Your move in three easy steps' );
		$this->add_columns_control( 3, array( 2, 3, 4 ) );
		$rep = new Repeater();
		$rep->add_control(
			'title',
			array(
				'label'       => __( 'Title', 'jmk' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Step', 'jmk' ),
				'label_block' => true,
			)
		);
		$rep->add_control(
			'text',
			array(
				'label' => __( 'Text', 'jmk' ),
				'type'  => Controls_Manager::TEXTAREA,
			)
		);
		$this->add_control(
			'steps',
			array(
				'label'       => __( 'Steps (numbered automatically)', 'jmk' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ title }}}',
				'default'     => array(
					array(
						'title' => 'Get your free quote',
						'text'  => 'Request a quote online or call (972) 638-7479. We size the crew and hours to your home.',
					),
					array(
						'title' => 'We confirm the details',
						'text'  => 'Lock in your date, crew size, and any special requirements — elevators, stairs, specialty items.',
					),
					array(
						'title' => 'Move day, handled',
						'text'  => 'The crew arrives on time and gets you settled with the same care from start to finish.',
					),
				),
			)
		);
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="jmk jmk-steps-wrap"><section class="jmk-sec jmk-pt0"><div class="jmk-wrap">';
		$this->render_section_head( $s );
		echo '<div class="jmk-steps" style="--cols:' . (int) $s['columns'] . '">';
		foreach ( $s['steps'] as $step ) {
			echo '<div class="jmk-step"><h3>' . esc_html( $step['title'] ) . '</h3>';
			if ( '' !== $step['text'] ) {
				echo '<p>' . esc_html( $step['text'] ) . '</p>';
			}
			echo '</div>';
		}
		echo '</div></div></section></div>';
	}
}
