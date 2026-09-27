<?php
/**
 * Page header: script + big title, intro and a row of arch photos ("our Menu").
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class Lilo_Widget_Page_Header extends Lilo_Widget_Base {

	public function get_name() {
		return 'lilo-page-header';
	}

	public function get_title() {
		return __( 'Lilo Page Header', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-header';
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Content', 'lilo-cafe' ) ) );
		$this->add_anchor_control();
		$this->add_image_control( 'icon', __( 'Icon', 'lilo-cafe' ) );
		$this->add_control( 'script', array( 'label' => __( 'Script text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'script' ) ) );
		$this->add_control( 'title', array( 'label' => __( 'Title', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'title' ), 'label_block' => true ) );
		$this->add_control(
			'title_tag',
			array(
				'label'   => __( 'Title HTML tag', 'lilo-cafe' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h1',
				'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'div' => 'div' ),
			)
		);
		$this->add_control( 'description', array( 'label' => __( 'Description', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'default' => $this->d( 'description' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_images', array( 'label' => __( 'Photos', 'lilo-cafe' ) ) );
		$r = new Repeater();
		$r->add_control( 'image', array( 'label' => __( 'Image', 'lilo-cafe' ), 'type' => Controls_Manager::MEDIA ) );
		$r->add_control( 'alt', array( 'label' => __( 'Alt text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT ) );
		$this->add_control(
			'images',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->clean_rows( $this->d_rows( 'images' ) ),
				'title_field' => '{{{ alt }}}',
			)
		);
		$this->end_controls_section();

		$this->add_box_style_section();
		$this->start_controls_section( 'section_style_photos', array( 'label' => __( 'Photos', 'lilo-cafe' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_frame_controls( '.lilo-phead__tile', __( 'Photo frame', 'lilo-cafe' ), false );
		$this->end_controls_section();
		$this->add_texts_style_section(
			array(
				'script' => array( __( 'Script text', 'lilo-cafe' ), '.lilo-script' ),
				'title'  => array( __( 'Title', 'lilo-cafe' ), '.lilo-phead__title' ),
				'desc'   => array( __( 'Description', 'lilo-cafe' ), '.lilo-phead__desc' ),
			)
		);
	}

	protected function render() {
		$s   = $this->get_settings_for_display();
		$tag = in_array( $s['title_tag'], array( 'h1', 'h2', 'div' ), true ) ? $s['title_tag'] : 'h1';
		$n   = count( (array) $s['images'] );
		?>
		<section class="lilo-sec lilo-phead"<?php echo $this->id_attr( $s ); // phpcs:ignore ?>>
			<div class="lilo-sec__inner">
				<div class="lilo-phead__text">
					<div>
						<div class="lilo-phead__script">
							<?php echo ! empty( $s['icon']['url'] ) ? lilo_img( $s['icon'], '', 'thumbnail', array( 'class' => 'lilo-phead__icon' ) ) : ''; // phpcs:ignore ?>
							<?php if ( ! empty( $s['script'] ) ) : ?>
								<span class="lilo-script"><?php echo esc_html( $s['script'] ); ?></span>
							<?php endif; ?>
						</div>
						<<?php echo $tag; // phpcs:ignore ?> class="lilo-phead__title"><?php echo esc_html( $s['title'] ); ?></<?php echo $tag; // phpcs:ignore ?>>
					</div>
					<?php if ( ! empty( $s['description'] ) ) : ?>
						<p class="lilo-phead__desc"><?php echo esc_html( $s['description'] ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( $n ) : ?>
					<div class="lilo-phead__photos" style="--lilo-cols: <?php echo (int) $n; ?>">
						<?php foreach ( $s['images'] as $im ) : ?>
							<div class="lilo-phead__tile elementor-repeater-item-<?php echo esc_attr( $im['_id'] ); ?>"><?php echo lilo_img( $im['image'], $im['alt'], 'medium_large', array( 'loading' => 'eager' ) ); // phpcs:ignore ?></div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
