<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Centered statement (La Maison conclusion, "Une maison dédiée", Professionnels introduction).
 */
class Statement extends Base {

	public function get_name() {
		return 'dpk-statement';
	}

	public function get_title() {
		return __( 'DP Centered Statement', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-blockquote';
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
			'ornament',
			array(
				'label'   => __( 'Top ornament', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'none' => __( 'None', 'douce-paresse-kit' ),
					'icon' => __( 'Logo icon', 'douce-paresse-kit' ),
					'line' => __( 'Vertical gold line', 'douce-paresse-kit' ),
				),
				'default' => 'icon',
			)
		);
		$this->add_control(
			'icon',
			array(
				'label'     => __( 'Icon', 'douce-paresse-kit' ),
				'type'      => Controls_Manager::MEDIA,
				'default'   => $this->media_default( 'logo-icon-gold.png' ),
				'condition' => array( 'ornament' => 'icon' ),
			)
		);
		$this->add_control( 'eyebrow', array( 'label' => __( 'Small label', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => '', 'label_block' => true ) );
		$this->add_control( 'title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'default' => 'Le Coffret Luxury en est le premier geste.' ) );
		$this->add_control(
			'title_style',
			array(
				'label'   => __( 'Title style', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'heading' => __( 'Heading (H2)', 'douce-paresse-kit' ),
					'lead'    => __( 'Lead paragraph', 'douce-paresse-kit' ),
				),
				'default' => 'heading',
			)
		);
		$this->add_control( 'subtitle', array( 'label' => __( 'Italic subtitle', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'default' => 'Le premier chapitre d’une Maison imaginée autour de l’attention, du partage et du temps que l’on s’accorde.' ) );
		$this->add_paragraphs_control(
			'paragraphs',
			__( 'Paragraphs', 'douce-paresse-kit' ),
			array(),
			array( 'body' => __( 'Body text', 'douce-paresse-kit' ) )
		);
		$this->add_control( 'closing', array( 'label' => __( 'Italic closing line', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => '' ) );
		$this->add_control( 'button_text', array( 'label' => __( 'Button text', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Découvrir le coffret' ) );
		$this->add_control( 'button_link', array( 'label' => __( 'Button link', 'douce-paresse-kit' ), 'type' => Controls_Manager::URL, 'default' => $this->link_default( '#' ) ) );
		$this->add_control(
			'spacing',
			array(
				'label'   => __( 'Spacing between lines', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'normal' => __( 'Normal', 'douce-paresse-kit' ),
					'airy'   => __( 'Airy', 'douce-paresse-kit' ),
				),
				'default' => 'normal',
			)
		);
		$this->add_control( 'max_width', array( 'label' => __( 'Content max width (px)', 'douce-paresse-kit' ), 'type' => Controls_Manager::NUMBER, 'default' => 820 ) );
		$this->end_controls_section();

		$this->style_box( '.dpk-statement' );
		$this->style_text( 'title', __( 'Title', 'douce-paresse-kit' ), '.dpk-statement__title' );
		$this->style_text( 'subtitle', __( 'Subtitle', 'douce-paresse-kit' ), '.dpk-statement__subtitle' );
		$this->style_text( 'body', __( 'Body text', 'douce-paresse-kit' ), '.dpk-statement .dpk-p' );
		$this->style_text( 'closing', __( 'Closing line', 'douce-paresse-kit' ), '.dpk-statement__closing' );
		$this->style_button( 'button', __( 'Button', 'douce-paresse-kit' ), '.dpk-statement .dpk-btn' );
		$this->style_accents( '.dpk-statement__eyebrow' );
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$scheme = 'dark' === $s['scheme'] ? 'dark' : 'light';
		$max    = ! empty( $s['max_width'] ) ? '--dpk-max:' . absint( $s['max_width'] ) . 'px' : '';
		$tag    = 'lead' === $s['title_style'] ? 'p' : 'h2';
		?>
		<section class="dpk dpk-statement dpk-statement--<?php echo esc_attr( $scheme ); ?><?php echo 'airy' === $s['spacing'] ? ' dpk-statement--airy' : ''; ?>">
			<div class="dpk-statement__inner" style="<?php echo esc_attr( $max ); ?>">
				<?php if ( 'icon' === $s['ornament'] && $this->media_url( $s['icon'] ) ) : ?>
					<span class="dpk-statement__icon"><?php echo $this->img( $s['icon'], '', 'Douce Paresse' ); // phpcs:ignore ?></span>
				<?php elseif ( 'line' === $s['ornament'] ) : ?>
					<span class="dpk-vline" aria-hidden="true"></span>
				<?php endif; ?>
				<?php if ( $s['eyebrow'] ) : ?>
					<span class="dpk-statement__eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span>
				<?php endif; ?>
				<?php if ( $s['title'] ) : ?>
					<<?php echo esc_attr( $tag ); ?> class="dpk-statement__title dpk-statement__title--<?php echo esc_attr( $s['title_style'] ); ?>"><?php echo $this->text( $s['title'] ); // phpcs:ignore ?></<?php echo esc_attr( $tag ); ?>>
				<?php endif; ?>
				<?php if ( $s['subtitle'] ) : ?>
					<p class="dpk-statement__subtitle"><?php echo $this->text( $s['subtitle'] ); // phpcs:ignore ?></p>
				<?php endif; ?>
				<?php $this->render_paragraphs( $s['paragraphs'] ); ?>
				<?php if ( $s['closing'] ) : ?>
					<p class="dpk-statement__closing"><?php echo $this->text( $s['closing'] ); // phpcs:ignore ?></p>
				<?php endif; ?>
				<?php if ( $s['button_text'] ) : ?>
					<a class="dpk-btn dpk-btn--outline dpk-statement__btn"<?php echo $this->link_attrs( $s['button_link'] ); // phpcs:ignore ?>><?php echo esc_html( $s['button_text'] ); ?> <span aria-hidden="true">→</span></a>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
