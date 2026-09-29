<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class JMK_Widget_Intro extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-intro';
	}

	public function get_title() {
		return __( 'JM Intro Text', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-text';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Intro', 'jmk' ) ) );
		$this->add_control(
			'text',
			array(
				'label'   => __( 'Text', 'jmk' ),
				'type'    => Controls_Manager::WYSIWYG,
				'default' => '<p>When you search for a <b>moving company in Grand Prairie</b>, you want someone who actually knows the neighborhoods — not a call center routing you to whoever\'s available that week. Just Move DFW is a locally owned, licensed and insured <b>moving company in Grand Prairie, TX</b>, built to be the <b>movers near you</b> who show up on time and treat your move like it matters, because it does. As trusted <b>movers in Grand Prairie, TX</b>, we serve the whole metroplex — so whether you\'re comparing moving companies near you or looking for reliable <b>long distance movers</b>, you\'ve found your crew.</p>',
			)
		);
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="jmk jmk-intro"><section class="jmk-sec"><div class="jmk-wrap"><div class="jmk-prose" style="max-width:none">
			<?php echo wp_kses_post( $this->parse_text_editor( $s['text'] ) ); ?>
		</div></div></section></div>
		<?php
	}
}
