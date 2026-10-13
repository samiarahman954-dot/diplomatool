<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

class Footer extends Base {

	public function get_name() {
		return 'dpk-footer';
	}

	public function get_title() {
		return __( 'DP Footer', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-footer';
	}

	private function link_repeater( $id, $label, $default, $with_prefix = false ) {
		$rep = new Repeater();
		if ( $with_prefix ) {
			$rep->add_control( 'prefix', array( 'label' => __( 'Small label', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT ) );
		}
		$rep->add_control( 'text', array( 'label' => __( 'Text', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT ) );
		$rep->add_control( 'link', array( 'label' => __( 'Link', 'douce-paresse-kit' ), 'type' => Controls_Manager::URL ) );
		$this->add_control(
			$id,
			array(
				'label'       => $label,
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'default'     => $default,
				'title_field' => '{{{ text }}}',
			)
		);
	}

	protected function register_controls() {
		$this->start_controls_section( 'brand_section', array( 'label' => __( 'Logo', 'douce-paresse-kit' ) ) );
		$this->add_control( 'logo', array( 'label' => __( 'Logo', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA, 'default' => $this->media_default( 'logo-full-ivory.png' ) ) );
		$this->add_responsive_control(
			'logo_width',
			array(
				'label'      => __( 'Logo width', 'douce-paresse-kit' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 80, 'max' => 500 ) ),
				'selectors'  => array( '{{WRAPPER}} .dpk-footer__logo' => 'width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'links_section', array( 'label' => __( 'Links', 'douce-paresse-kit' ) ) );
		$this->link_repeater(
			'nav',
			__( 'Main links', 'douce-paresse-kit' ),
			array(
				array( 'text' => 'La Maison', 'link' => $this->link_default( '#' ) ),
				array( 'text' => 'Le Coffret Luxury', 'link' => $this->link_default( '#' ) ),
				array( 'text' => 'Professionnels', 'link' => $this->link_default( '#' ) ),
				array( 'text' => 'Contact', 'link' => $this->link_default( '#' ) ),
			)
		);
		$this->link_repeater(
			'legal',
			__( 'Legal links', 'douce-paresse-kit' ),
			array(
				array( 'text' => 'Mentions légales', 'link' => $this->link_default( '#' ) ),
				array( 'text' => 'Politique de confidentialité', 'link' => $this->link_default( '#' ) ),
			)
		);
		$this->link_repeater(
			'contact',
			__( 'Contact links', 'douce-paresse-kit' ),
			array(
				array( 'prefix' => 'Email', 'text' => 'contact@douceparesse.com', 'link' => $this->link_default( 'mailto:contact@douceparesse.com' ) ),
				array(
					'prefix' => '',
					'text'   => 'Instagram',
					'link'   => array( 'url' => 'https://www.instagram.com/douceparesse.officiel/', 'is_external' => 'on', 'nofollow' => '' ),
				),
			),
			true
		);
		$this->end_controls_section();

		$this->start_controls_section( 'bottom_section', array( 'label' => __( 'Bottom line', 'douce-paresse-kit' ) ) );
		$this->add_control(
			'copyright',
			array(
				'label'       => __( 'Copyright', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '© 2026 Douce Paresse',
				'description' => __( 'Use {year} for the current year.', 'douce-paresse-kit' ),
			)
		);
		$this->add_control( 'tagline', array( 'label' => __( 'Tagline', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'L’art de la courtoisie française' ) );
		$this->end_controls_section();

		$this->style_box( '.dpk-footer' );
		$this->style_text( 'nav', __( 'Main links', 'douce-paresse-kit' ), '.dpk-footer__nav a' );
		$this->style_text( 'legal', __( 'Legal & contact links', 'douce-paresse-kit' ), '.dpk-footer__legal a, .dpk-footer__contact a' );
		$this->style_text(
			'bottom',
			__( 'Bottom line', 'douce-paresse-kit' ),
			'.dpk-footer__bottom',
			array(
				'accent' => array( __( 'Accent (labels, tagline, hover)', 'douce-paresse-kit' ), '.dpk-footer', '--dpk-gold-l' ),
				'rule'   => array( __( 'Separator lines', 'douce-paresse-kit' ), '.dpk-footer', '--dpk-footer-rule' ),
			)
		);
	}

	private function links( $items, $with_prefix = false ) {
		foreach ( (array) $items as $item ) {
			if ( empty( $item['text'] ) ) {
				continue;
			}
			echo '<a' . $this->link_attrs( $item['link'] ) . '>'; // phpcs:ignore
			if ( $with_prefix && ! empty( $item['prefix'] ) ) {
				echo '<span class="dpk-footer__prefix">' . esc_html( $item['prefix'] ) . '</span>';
			}
			echo esc_html( $item['text'] ) . '</a>';
		}
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<footer class="dpk dpk-footer">
			<div class="dpk-footer__inner">
				<?php if ( $this->media_url( $s['logo'] ) ) : ?>
					<div class="dpk-footer__brand">
						<span class="dpk-footer__logo"><?php echo $this->img( $s['logo'], '', 'Douce Paresse' ); // phpcs:ignore ?></span>
					</div>
				<?php endif; ?>
				<div class="dpk-footer__row">
					<div class="dpk-footer__nav"><?php $this->links( $s['nav'] ); ?></div>
					<div class="dpk-footer__legal"><?php $this->links( $s['legal'] ); ?></div>
				</div>
				<div class="dpk-footer__contact"><?php $this->links( $s['contact'], true ); ?></div>
				<div class="dpk-footer__bottom">
					<span><?php echo esc_html( str_replace( '{year}', gmdate( 'Y' ), $s['copyright'] ) ); ?></span>
					<span class="dpk-footer__tagline"><?php echo esc_html( $s['tagline'] ); ?></span>
				</div>
			</div>
		</footer>
		<?php
	}
}
