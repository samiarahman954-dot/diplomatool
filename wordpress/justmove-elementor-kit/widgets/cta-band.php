<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class JMK_Widget_Cta_Band extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-cta-band';
	}

	public function get_title() {
		return __( 'JM Final CTA', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-button';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Call to action', 'jmk' ) ) );
		$this->add_control(
			'title',
			array(
				'label'   => __( 'Title', 'jmk' ),
				'type'    => Controls_Manager::TEXTAREA,
				'rows'    => 2,
				'default' => "Let's make your next move easy",
			)
		);
		$this->add_control(
			'text',
			array(
				'label'   => __( 'Text', 'jmk' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => "Ready to book Grand Prairie's moving company? Get your free quote in minutes or call (972) 638-7479.",
			)
		);
		$this->add_button_controls( 'btn1', __( 'Primary button', 'jmk' ), 'Get my free quote', '/quote/', 'y' );
		$this->add_button_controls( 'btn2', __( 'Secondary button', 'jmk' ), '☎ Call (972) 638-7479', 'tel:+19726387479', 'ghost' );
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="jmk jmk-cta-band"><section class="jmk-sec"><div class="jmk-wrap">
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
		</div></section></div>
		<?php
	}
}
