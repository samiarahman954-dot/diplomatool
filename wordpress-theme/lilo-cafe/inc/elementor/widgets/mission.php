<?php
/**
 * Mission: centered quote, text and a "why" statement.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class Lilo_Widget_Mission extends Lilo_Widget_Base {

	public function get_name() {
		return 'lilo-mission';
	}

	public function get_title() {
		return __( 'Lilo Mission Quote', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-blockquote';
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Content', 'lilo-cafe' ) ) );
		$this->add_anchor_control();
		$this->add_image_control( 'icon', __( 'Icon', 'lilo-cafe' ) );
		$this->add_control( 'quote', array( 'label' => __( 'Quote', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'default' => $this->d( 'quote' ) ) );
		$this->add_control( 'label', array( 'label' => __( 'Label', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'label' ) ) );
		$this->add_control( 'content', array( 'label' => __( 'Text', 'lilo-cafe' ), 'type' => Controls_Manager::WYSIWYG, 'default' => $this->d( 'content' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_why', array( 'label' => __( 'Closing statement', 'lilo-cafe' ) ) );
		$this->add_control( 'sub_script', array( 'label' => __( 'Script text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'sub_script' ) ) );
		$this->add_control( 'sub_text', array( 'label' => __( 'Statement', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'default' => $this->d( 'sub_text' ) ) );
		$this->end_controls_section();

		$this->add_box_style_section( '.lilo-mission', '.lilo-sec__inner' );
		$this->start_controls_section( 'section_style_lines', array( 'label' => __( 'Lines & icon', 'lilo-cafe' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'line_color', array( 'label' => __( 'Line color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-mission, {{WRAPPER}} .lilo-mission__why' => 'border-color: {{VALUE}};' ) ) );
		$this->add_control(
			'icon_size',
			array(
				'label'      => __( 'Icon size', 'lilo-cafe' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 16, 'max' => 200 ) ),
				'selectors'  => array( '{{WRAPPER}} .lilo-mission__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->add_texts_style_section(
			array(
				'quote_s'  => array( __( 'Quote', 'lilo-cafe' ), '.lilo-mission__quote' ),
				'label_s'  => array( __( 'Label', 'lilo-cafe' ), '.lilo-label' ),
				'body'     => array( __( 'Text', 'lilo-cafe' ), '.lilo-mission__body' ),
				'script'   => array( __( 'Script text', 'lilo-cafe' ), '.lilo-script' ),
				'why'      => array( __( 'Statement', 'lilo-cafe' ), '.lilo-mission__why-text' ),
			)
		);
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<section class="lilo-sec lilo-mission"<?php echo $this->id_attr( $s ); // phpcs:ignore ?>>
			<div class="lilo-sec__inner">
				<?php echo ! empty( $s['icon']['url'] ) ? lilo_img( $s['icon'], '', 'thumbnail', array( 'class' => 'lilo-mission__icon' ) ) : ''; // phpcs:ignore ?>
				<?php if ( ! empty( $s['quote'] ) ) : ?>
					<p class="lilo-mission__quote"><?php echo esc_html( $s['quote'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $s['label'] ) ) : ?>
					<span class="lilo-label"><?php echo esc_html( $s['label'] ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $s['content'] ) ) : ?>
					<div class="lilo-mission__body lilo-rich"><?php echo wp_kses_post( $this->parse_text_editor( $s['content'] ) ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $s['sub_script'] ) || ! empty( $s['sub_text'] ) ) : ?>
					<div class="lilo-mission__why">
						<?php if ( ! empty( $s['sub_script'] ) ) : ?>
							<span class="lilo-script"><?php echo esc_html( $s['sub_script'] ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $s['sub_text'] ) ) : ?>
							<p class="lilo-mission__why-text"><?php echo esc_html( $s['sub_text'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
