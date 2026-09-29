<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class JMK_Widget_Route_Divider extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-route-divider';
	}

	public function get_title() {
		return __( 'JM Route Divider', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-divider';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Divider', 'jmk' ) ) );
		$this->add_control(
			'from',
			array(
				'label'   => __( 'Start label', 'jmk' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 'Your old place',
			)
		);
		$this->add_control(
			'to',
			array(
				'label'   => __( 'End label', 'jmk' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 'Your new place',
			)
		);
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="jmk jmk-divider jmk-sec" role="presentation"><div class="jmk-wrap">
			<?php if ( '' !== $s['from'] . $s['to'] ) : ?>
				<div class="jmk-divider-lbl"><span><?php echo esc_html( $s['from'] ); ?></span><span><?php echo esc_html( $s['to'] ); ?></span></div>
			<?php endif; ?>
			<div class="jmk-route"><span class="dot"></span><span class="seg"></span><span class="chev">&#10148;</span><span class="seg"></span><span class="pin"></span></div>
		</div></div>
		<?php
	}
}
