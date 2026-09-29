<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class JMK_Widget_Urgency extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-urgency';
	}

	public function get_title() {
		return __( 'JM Same-Day Banner', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-alert';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Banner', 'jmk' ) ) );
		$this->add_control(
			'title',
			array(
				'label'       => __( 'Title', 'jmk' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'Need same-day movers in DFW?',
				'label_block' => true,
			)
		);
		$this->add_control(
			'text',
			array(
				'label'   => __( 'Text', 'jmk' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => "We keep crew capacity open for last-minute and emergency moves across Grand Prairie and the metroplex. Call now to check today's availability.",
			)
		);
		$this->add_button_controls( 'btn', __( 'Button', 'jmk' ), '☎ Call (972) 638-7479', 'tel:+19726387479', 'y' );
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="jmk jmk-urgency-wrap"><section class="jmk-sec jmk-pt0"><div class="jmk-wrap"><div class="jmk-urgency">
			<div><h3><?php echo esc_html( $s['title'] ); ?></h3>
				<?php if ( '' !== $s['text'] ) : ?>
					<p><?php echo esc_html( $s['text'] ); ?></p>
				<?php endif; ?></div>
			<?php $this->render_button( $s, 'btn' ); ?>
		</div></div></section></div>
		<?php
	}
}
