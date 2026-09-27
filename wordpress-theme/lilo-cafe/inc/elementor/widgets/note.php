<?php
/**
 * Small note line aligned with the page content (e.g. allergy info).
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class Lilo_Widget_Note extends Lilo_Widget_Base {

	public function get_name() {
		return 'lilo-note';
	}

	public function get_title() {
		return __( 'Lilo Small Note', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-text';
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Note', 'lilo-cafe' ) ) );
		$this->add_control( 'text', array( 'label' => __( 'Text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'default' => $this->d( 'text' ) ) );
		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'lilo-cafe' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array( 'title' => __( 'Left', 'lilo-cafe' ), 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => __( 'Center', 'lilo-cafe' ), 'icon' => 'eicon-text-align-center' ),
					'right'  => array( 'title' => __( 'Right', 'lilo-cafe' ), 'icon' => 'eicon-text-align-right' ),
				),
				'selectors' => array( '{{WRAPPER}} .lilo-note__text' => 'text-align: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();

		$this->add_box_style_section( '.lilo-note', '.lilo-sec__inner' );
		$this->add_texts_style_section( array( 'note' => array( __( 'Text', 'lilo-cafe' ), '.lilo-note__text' ) ) );
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		if ( empty( $s['text'] ) ) {
			return;
		}
		?>
		<div class="lilo-sec lilo-note"><div class="lilo-sec__inner"><p class="lilo-note__text"><?php echo lilo_kses( nl2br( $s['text'] ) ); // phpcs:ignore ?></p></div></div>
		<?php
	}
}
