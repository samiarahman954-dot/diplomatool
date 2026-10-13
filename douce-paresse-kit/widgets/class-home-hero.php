<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class Home_Hero extends Base {

	public function get_name() {
		return 'dpk-home-hero';
	}

	public function get_title() {
		return __( 'DP Home Hero', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-banner';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Content', 'douce-paresse-kit' ) ) );
		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'centered' => __( 'Centered', 'douce-paresse-kit' ),
					'split'    => __( 'Split', 'douce-paresse-kit' ),
				),
				'default' => 'centered',
			)
		);
		$this->add_control( 'title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => 'DOUCE PARESSE' ) );
		$this->add_control( 'subtitle', array( 'label' => __( 'Subtitle', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'l’art de la courtoisie française', 'label_block' => true ) );
		$this->add_control( 'image', array( 'label' => __( 'Image', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA, 'default' => $this->media_default( 'hero-v2.webp' ) ) );
		$this->add_control( 'image_pos', array( 'label' => __( 'Image focus (object-position)', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => '40% 62%' ) );
		$this->add_control(
			'link_text',
			array(
				'label'     => __( 'Scroll link text', 'douce-paresse-kit' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => 'Chapitre I',
				'condition' => array( 'layout' => 'split' ),
			)
		);
		$this->add_control(
			'link',
			array(
				'label'     => __( 'Scroll link', 'douce-paresse-kit' ),
				'type'      => Controls_Manager::URL,
				'default'   => $this->link_default( '#chapitre-1' ),
				'condition' => array( 'layout' => 'split' ),
			)
		);
		$this->end_controls_section();

		$this->style_box( '.dpk-home-hero' );
		$this->style_text( 'title', __( 'Title', 'douce-paresse-kit' ), '.dpk-home-hero__title' );
		$this->style_text( 'subtitle', __( 'Subtitle', 'douce-paresse-kit' ), '.dpk-home-hero__subtitle' );
		$this->style_accents();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$split = 'split' === $s['layout'];
		?>
		<section class="dpk dpk-home-hero dpk-home-hero--<?php echo $split ? 'split' : 'centered'; ?>">
			<div class="dpk-home-hero__text">
				<span class="dpk-home-hero__line" aria-hidden="true"></span>
				<h1 class="dpk-home-hero__title"><?php echo $this->text( $s['title'] ); // phpcs:ignore ?></h1>
				<?php if ( $s['subtitle'] ) : ?>
					<p class="dpk-home-hero__subtitle"><?php echo esc_html( $s['subtitle'] ); ?></p>
				<?php endif; ?>
				<?php if ( $split && $s['link_text'] ) : ?>
					<a class="dpk-home-hero__link"<?php echo $this->link_attrs( $s['link'] ); // phpcs:ignore ?>><span aria-hidden="true"></span><?php echo esc_html( $s['link_text'] ); ?></a>
				<?php endif; ?>
			</div>
			<div class="dpk-home-hero__media">
				<?php echo $this->img( $s['image'], 'dpk-home-hero__img', 'Douce Paresse', true, $this->pos( $s['image_pos'] ) ); // phpcs:ignore ?>
			</div>
		</section>
		<?php
	}
}
