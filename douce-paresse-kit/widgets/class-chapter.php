<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class Chapter extends Base {

	public function get_name() {
		return 'dpk-chapter';
	}

	public function get_title() {
		return __( 'DP Chapter', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-image-box';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Content', 'douce-paresse-kit' ) ) );
		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'split' => __( 'Split', 'douce-paresse-kit' ),
					'full'  => __( 'Full-bleed image', 'douce-paresse-kit' ),
				),
				'default' => 'split',
			)
		);
		$this->add_control( 'anchor', array( 'label' => __( 'Section ID (anchor)', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'chapitre-1' ) );
		$this->add_control( 'image', array( 'label' => __( 'Image', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA, 'default' => $this->media_default( 'premier-geste.webp' ) ) );
		$this->add_control( 'image_pos', array( 'label' => __( 'Image focus (object-position)', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => '50% 60%' ) );
		$this->add_control( 'label', array( 'label' => __( 'Label', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Chapitre I' ) );
		$this->add_control( 'title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => 'Le Premier Geste' ) );
		$this->add_control( 'subtitle', array( 'label' => __( 'Subtitle', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => 'Et si un détail faisait toute la différence ?' ) );
		$this->add_control( 'button_text', array( 'label' => __( 'Button text', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Découvrir le Coffret' ) );
		$this->add_control( 'button_link', array( 'label' => __( 'Button link', 'douce-paresse-kit' ), 'type' => Controls_Manager::URL, 'default' => $this->link_default( '#' ) ) );
		$this->end_controls_section();

		$this->style_box( '.dpk-chapter__panel', __( 'Text panel', 'douce-paresse-kit' ) );
		$this->style_text( 'label', __( 'Label', 'douce-paresse-kit' ), '.dpk-chapter__label' );
		$this->style_text( 'title', __( 'Title', 'douce-paresse-kit' ), '.dpk-chapter__title' );
		$this->style_text( 'subtitle', __( 'Subtitle', 'douce-paresse-kit' ), '.dpk-chapter__subtitle' );
		$this->style_button( 'button', __( 'Button', 'douce-paresse-kit' ), '.dpk-chapter .dpk-btn' );
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$layout = 'full' === $s['layout'] ? 'full' : 'split';
		$id     = $s['anchor'] ? ' id="' . esc_attr( sanitize_title( $s['anchor'] ) ) . '"' : '';
		?>
		<section class="dpk dpk-chapter dpk-chapter--<?php echo esc_attr( $layout ); ?>"<?php echo $id; // phpcs:ignore ?>>
			<div class="dpk-chapter__media">
				<?php echo $this->img( $s['image'], 'dpk-chapter__img', '', false, $this->pos( $s['image_pos'] ) ); // phpcs:ignore ?>
			</div>
			<div class="dpk-chapter__panel">
				<?php if ( $s['label'] ) : ?>
					<div class="dpk-chapter__label"><span><?php echo esc_html( $s['label'] ); ?></span><span class="dpk-rule" aria-hidden="true"></span></div>
				<?php endif; ?>
				<h2 class="dpk-chapter__title"><?php echo $this->text( $s['title'] ); // phpcs:ignore ?></h2>
				<?php if ( $s['subtitle'] ) : ?>
					<p class="dpk-chapter__subtitle"><?php echo $this->text( $s['subtitle'] ); // phpcs:ignore ?></p>
				<?php endif; ?>
				<?php if ( $s['button_text'] ) : ?>
					<div class="dpk-chapter__cta"><a class="dpk-btn dpk-btn--gold"<?php echo $this->link_attrs( $s['button_link'] ); // phpcs:ignore ?>><?php echo esc_html( $s['button_text'] ); ?> <span aria-hidden="true">→</span></a></div>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
