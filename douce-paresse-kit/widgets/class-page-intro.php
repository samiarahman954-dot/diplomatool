<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Centered page opening (La Maison, Contact, legal pages).
 */
class Page_Intro extends Base {

	public function get_name() {
		return 'dpk-page-intro';
	}

	public function get_title() {
		return __( 'DP Page Intro', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-heading';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Content', 'douce-paresse-kit' ) ) );
		$this->add_control( 'eyebrow', array( 'label' => __( 'Small label', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Temps · Courtoisie · Hospitalité', 'label_block' => true ) );
		$this->add_control( 'title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => 'La Maison' ) );
		$this->add_control(
			'size',
			array(
				'label'   => __( 'Title size', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'xl' => __( 'Extra large', 'douce-paresse-kit' ),
					'l'  => __( 'Large', 'douce-paresse-kit' ),
					'm'  => __( 'Medium', 'douce-paresse-kit' ),
				),
				'default' => 'xl',
			)
		);
		$this->add_control(
			'line',
			array(
				'label'        => __( 'Gold line under the title', 'douce-paresse-kit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control( 'subtitle', array( 'label' => __( 'Italic subtitle', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => 'Tout est parti d’une attention.' ) );
		$this->add_control( 'text', array( 'label' => __( 'Text', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => '' ) );
		$this->add_control(
			'pb',
			array(
				'label'   => __( 'Space below', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'm' => __( 'Medium', 'douce-paresse-kit' ),
					'l' => __( 'Large', 'douce-paresse-kit' ),
				),
				'default' => 'l',
			)
		);
		$this->end_controls_section();

		$this->style_box( '.dpk-intro' );
		$this->style_text( 'title', __( 'Title', 'douce-paresse-kit' ), '.dpk-intro__title' );
		$this->style_text( 'subtitle', __( 'Subtitle', 'douce-paresse-kit' ), '.dpk-intro__subtitle' );
		$this->style_text( 'text', __( 'Text', 'douce-paresse-kit' ), '.dpk-intro__text' );
		$this->style_accents( '.dpk-intro__eyebrow' );
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$size = in_array( $s['size'], array( 'xl', 'l', 'm' ), true ) ? $s['size'] : 'xl';
		$pb   = 'm' === $s['pb'] ? 'm' : 'l';
		?>
		<section class="dpk dpk-intro dpk-intro--<?php echo esc_attr( $size ); ?> dpk-intro--pb-<?php echo esc_attr( $pb ); ?>">
			<div class="dpk-intro__inner">
				<?php if ( $s['eyebrow'] ) : ?>
					<span class="dpk-intro__eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span>
				<?php endif; ?>
				<h1 class="dpk-intro__title"><?php echo $this->text( $s['title'] ); // phpcs:ignore ?></h1>
				<?php if ( 'yes' === $s['line'] ) : ?>
					<span class="dpk-vline" aria-hidden="true"></span>
				<?php endif; ?>
				<?php if ( $s['subtitle'] ) : ?>
					<p class="dpk-intro__subtitle"><?php echo $this->text( $s['subtitle'] ); // phpcs:ignore ?></p>
				<?php endif; ?>
				<?php if ( $s['text'] ) : ?>
					<p class="dpk-intro__text"><?php echo $this->text( $s['text'] ); // phpcs:ignore ?></p>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
