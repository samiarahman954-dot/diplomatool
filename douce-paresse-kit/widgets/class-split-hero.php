<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Page hero with text on one side and a full-height image (Le Coffret Luxury, Professionnels).
 */
class Split_Hero extends Base {

	public function get_name() {
		return 'dpk-split-hero';
	}

	public function get_title() {
		return __( 'DP Split Hero', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-column';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Content', 'douce-paresse-kit' ) ) );
		$this->add_control(
			'scheme',
			array(
				'label'   => __( 'Color scheme', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'light' => __( 'Ivory', 'douce-paresse-kit' ),
					'dark'  => __( 'Navy', 'douce-paresse-kit' ),
				),
				'default' => 'light',
			)
		);
		$this->add_control(
			'marker',
			array(
				'label'   => __( 'Label style', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'above' => __( 'Gold line above the label', 'douce-paresse-kit' ),
					'after' => __( 'Gold line after the label', 'douce-paresse-kit' ),
				),
				'default' => 'above',
			)
		);
		$this->add_control( 'eyebrow', array( 'label' => __( 'Small label', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Le premier geste de Douce Paresse', 'label_block' => true ) );
		$this->add_control( 'title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'default' => "Le Coffret\nLuxury" ) );
		$this->add_control(
			'title_size',
			array(
				'label'   => __( 'Title size', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'large'  => __( 'Large (short titles)', 'douce-paresse-kit' ),
					'medium' => __( 'Medium (sentences)', 'douce-paresse-kit' ),
				),
				'default' => 'large',
			)
		);
		$this->add_control( 'subtitle', array( 'label' => __( 'Italic subtitle', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => 'Une nouvelle interprétation du rituel de courtoisie en chambre.' ) );
		$this->add_control( 'button_text', array( 'label' => __( 'Button text', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => '' ) );
		$this->add_control( 'button_link', array( 'label' => __( 'Button link', 'douce-paresse-kit' ), 'type' => Controls_Manager::URL, 'default' => $this->link_default( '#demande' ) ) );
		$this->add_control( 'image', array( 'label' => __( 'Image', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA, 'default' => $this->media_default( 'composition-cover.webp' ), 'separator' => 'before' ) );
		$this->add_control( 'image_pos', array( 'label' => __( 'Image focus (object-position)', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => '' ) );
		$this->add_control(
			'image_size',
			array(
				'label'   => __( 'Image column', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'wide'  => __( 'Wider than text', 'douce-paresse-kit' ),
					'equal' => __( 'Same as text', 'douce-paresse-kit' ),
				),
				'default' => 'wide',
			)
		);
		$this->end_controls_section();

		$this->style_box( '.dpk-shero' );
		$this->style_text( 'title', __( 'Title', 'douce-paresse-kit' ), '.dpk-shero__title' );
		$this->style_text( 'subtitle', __( 'Subtitle', 'douce-paresse-kit' ), '.dpk-shero__subtitle' );
		$this->style_button( 'button', __( 'Button', 'douce-paresse-kit' ), '.dpk-shero .dpk-btn' );
		$this->style_accents( '.dpk-shero .dpk-marker' );
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$scheme = 'dark' === $s['scheme'] ? 'dark' : 'light';
		$size   = 'equal' === $s['image_size'] ? 'equal' : 'wide';
		?>
		<section class="dpk dpk-shero dpk-shero--<?php echo esc_attr( $scheme . ' dpk-shero--' . $size ); ?>">
			<div class="dpk-shero__text">
				<?php if ( 'above' === $s['marker'] ) : ?>
					<span class="dpk-line" aria-hidden="true"></span>
					<?php if ( $s['eyebrow'] ) : ?>
						<span class="dpk-marker"><?php echo esc_html( $s['eyebrow'] ); ?></span>
					<?php endif; ?>
				<?php elseif ( $s['eyebrow'] ) : ?>
					<div class="dpk-marker"><span><?php echo esc_html( $s['eyebrow'] ); ?></span><span class="dpk-rule" aria-hidden="true"></span></div>
				<?php endif; ?>
				<h1 class="dpk-shero__title dpk-shero__title--<?php echo 'medium' === $s['title_size'] ? 'medium' : 'large'; ?>"><?php echo $this->text( $s['title'] ); // phpcs:ignore ?></h1>
				<?php if ( $s['subtitle'] ) : ?>
					<p class="dpk-shero__subtitle"><?php echo $this->text( $s['subtitle'] ); // phpcs:ignore ?></p>
				<?php endif; ?>
				<?php if ( $s['button_text'] ) : ?>
					<div class="dpk-shero__cta"><a class="dpk-btn dpk-btn--gold"<?php echo $this->link_attrs( $s['button_link'] ); // phpcs:ignore ?>><?php echo esc_html( $s['button_text'] ); ?> <span aria-hidden="true">→</span></a></div>
				<?php endif; ?>
			</div>
			<div class="dpk-shero__media">
				<?php echo $this->img( $s['image'], 'dpk-shero__img', '', true, $this->pos( $s['image_pos'] ) ); // phpcs:ignore ?>
			</div>
		</section>
		<?php
	}
}
