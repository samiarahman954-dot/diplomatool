<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class JMK_Widget_Estimate_Band extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-estimate-band';
	}

	public function get_title() {
		return __( 'JM Estimate Band', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-call-to-action';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Band', 'jmk' ) ) );
		$this->add_control(
			'title',
			array(
				'label'   => __( 'Title', 'jmk' ),
				'type'    => Controls_Manager::TEXTAREA,
				'rows'    => 2,
				'default' => "Ready to book Grand Prairie's moving company?",
			)
		);
		$this->add_control(
			'text',
			array(
				'label'   => __( 'Text', 'jmk' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => 'Get your free quote in minutes and see a real estimate with crew size and hours — no waiting on a callback.',
			)
		);
		$this->add_button_controls( 'btn1', __( 'Primary button', 'jmk' ), 'Get my free quote', '/quote/', 'y' );
		$this->add_button_controls( 'btn2', __( 'Secondary button', 'jmk' ), '☎ (972) 638-7479', 'tel:+19726387479', 'b' );
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="jmk jmk-estimate"><section class="jmk-sec jmk-pt0"><div class="jmk-wrap"><div class="jmk-band">
			<h2><?php echo esc_html( $s['title'] ); ?></h2>
			<?php if ( '' !== $s['text'] ) : ?>
				<p><?php echo esc_html( $s['text'] ); ?></p>
			<?php endif; ?>
			<div class="jmk-cta-row">
				<?php
				$this->render_button( $s, 'btn1' );
				$this->render_button( $s, 'btn2' );
				?>
			</div>
		</div></div></section></div>
		<?php
	}
}
