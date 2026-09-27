<?php
/**
 * Tag pills band ("Lilo merch · coming soon").
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class Lilo_Widget_Tags extends Lilo_Widget_Base {

	public function get_name() {
		return 'lilo-tags';
	}

	public function get_title() {
		return __( 'Lilo Merch / Tag Pills', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-tags';
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_heading', array( 'label' => __( 'Heading', 'lilo-cafe' ) ) );
		$this->add_anchor_control();
		$this->add_heading_controls();
		$this->end_controls_section();

		$this->start_controls_section( 'section_items', array( 'label' => __( 'Pills', 'lilo-cafe' ) ) );
		$r = new Repeater();
		$r->add_control( 'text', array( 'label' => __( 'Text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$r->add_control( 'link', array( 'label' => __( 'Link (optional)', 'lilo-cafe' ), 'type' => Controls_Manager::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control(
			'tags',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->d_rows( 'tags' ),
				'title_field' => '{{{ text }}}',
			)
		);
		$this->end_controls_section();

		$this->add_box_style_section();
		$this->add_texts_style_section(
			array(
				'script' => array( __( 'Script text', 'lilo-cafe' ), '.lilo-script' ),
				'title'  => array( __( 'Title', 'lilo-cafe' ), '.lilo-title' ),
			)
		);
		$this->add_button_style_section( 'pill', __( 'Pills', 'lilo-cafe' ), '.lilo-pill' );
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<section class="lilo-sec lilo-tags"<?php echo $this->id_attr( $s ); // phpcs:ignore ?>>
			<div class="lilo-sec__inner">
				<?php echo $this->heading_html( $s ); // phpcs:ignore ?>
				<div class="lilo-tags__list">
					<?php
					foreach ( (array) $s['tags'] as $t ) {
						if ( ! empty( $t['link']['url'] ) ) {
							printf( '<a class="lilo-pill elementor-repeater-item-%s"%s>%s</a>', esc_attr( $t['_id'] ), lilo_link_attrs( $t['link'] ), esc_html( $t['text'] ) ); // phpcs:ignore
						} else {
							printf( '<span class="lilo-pill elementor-repeater-item-%s">%s</span>', esc_attr( $t['_id'] ), esc_html( $t['text'] ) );
						}
					}
					?>
				</div>
			</div>
		</section>
		<?php
	}
}
