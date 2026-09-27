<?php
/**
 * Social: handle, profile buttons and a photo grid.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class Lilo_Widget_Social extends Lilo_Widget_Base {

	public function get_name() {
		return 'lilo-social';
	}

	public function get_title() {
		return __( 'Lilo Social Feed', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-instagram-gallery';
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_heading', array( 'label' => __( 'Heading', 'lilo-cafe' ) ) );
		$this->add_anchor_control();
		$this->add_heading_controls();
		$this->end_controls_section();

		$this->start_controls_section( 'section_buttons', array( 'label' => __( 'Profile buttons', 'lilo-cafe' ) ) );
		$r = new Repeater();
		$r->add_control( 'text', array( 'label' => __( 'Text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT ) );
		$r->add_control( 'link', array( 'label' => __( 'Link', 'lilo-cafe' ), 'type' => Controls_Manager::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control(
			'buttons',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->d_rows( 'buttons' ),
				'title_field' => '{{{ text }}}',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'section_images', array( 'label' => __( 'Photos', 'lilo-cafe' ) ) );
		$r = new Repeater();
		$r->add_control( 'image', array( 'label' => __( 'Image', 'lilo-cafe' ), 'type' => Controls_Manager::MEDIA ) );
		$r->add_control( 'alt', array( 'label' => __( 'Alt text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT ) );
		$r->add_control( 'link', array( 'label' => __( 'Link (optional)', 'lilo-cafe' ), 'type' => Controls_Manager::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control(
			'images',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->clean_rows( $this->d_rows( 'images' ) ),
				'title_field' => '{{{ alt }}}',
			)
		);
		$this->add_grid_controls( '.lilo-social__grid', 220 );
		$this->add_control(
			'tile_radius',
			array(
				'label'      => __( 'Photo radius', 'lilo-cafe' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .lilo-social__tile' => 'border-radius: {{SIZE}}{{UNIT}};' ),
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
		$this->add_button_style_section( 'btn', __( 'Buttons', 'lilo-cafe' ), '.lilo-btn' );
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<section class="lilo-sec lilo-social"<?php echo $this->id_attr( $s ); // phpcs:ignore ?>>
			<div class="lilo-sec__inner">
				<div class="lilo-sec__head">
					<?php echo $this->heading_html( $s ); // phpcs:ignore ?>
					<div class="lilo-social__buttons">
						<?php
						foreach ( (array) $s['buttons'] as $b ) {
							echo $this->button_html( $b['text'], $b['link'], 'lilo-btn lilo-btn--outline lilo-btn--sm elementor-repeater-item-' . $b['_id'] ); // phpcs:ignore
						}
						?>
					</div>
				</div>
				<div class="lilo-social__grid">
					<?php
					foreach ( (array) $s['images'] as $im ) :
						$tag   = ! empty( $im['link']['url'] ) ? 'a' : 'div';
						$attrs = 'a' === $tag ? lilo_link_attrs( $im['link'] ) : '';
						?>
						<<?php echo $tag . $attrs; // phpcs:ignore ?> class="lilo-social__tile elementor-repeater-item-<?php echo esc_attr( $im['_id'] ); ?>"><?php echo lilo_img( $im['image'], $im['alt'], 'medium_large' ); // phpcs:ignore ?></<?php echo $tag; // phpcs:ignore ?>>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
	}
}
