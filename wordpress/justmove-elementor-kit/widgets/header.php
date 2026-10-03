<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Repeater;

class JMK_Widget_Header extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-header';
	}

	public function get_title() {
		return __( 'JM Header / Nav', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-header';
	}

	protected function register_controls() {
		$this->start_controls_section( 'logo', array( 'label' => __( 'Logo', 'jmk' ) ) );
		self::add_logo_controls( $this );
		$this->end_controls_section();

		$this->start_controls_section( 'menu', array( 'label' => __( 'Menu', 'jmk' ) ) );
		$rep = new Repeater();
		$rep->add_control(
			'label',
			array(
				'label'   => __( 'Label', 'jmk' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Link', 'jmk' ),
			)
		);
		$rep->add_control(
			'link',
			array(
				'label'   => __( 'Link', 'jmk' ),
				'type'    => Controls_Manager::URL,
				'default' => array( 'url' => '#' ),
			)
		);
		$this->add_control(
			'links',
			array(
				'label'       => __( 'Menu items', 'jmk' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ label }}}',
				'default'     => array(
					array( 'label' => 'Local', 'link' => array( 'url' => '/local-moving/' ) ),
					array( 'label' => 'Long-Distance Moving', 'link' => array( 'url' => '/long-distance-moving/' ) ),
					array( 'label' => 'Commercial', 'link' => array( 'url' => '/commercial-moving/' ) ),
					array( 'label' => 'Packing', 'link' => array( 'url' => '/packing-services/' ) ),
					array( 'label' => 'Areas', 'link' => array( 'url' => '/areas/' ) ),
					array( 'label' => 'About', 'link' => array( 'url' => '/about/' ) ),
				),
			)
		);
		$this->add_control(
			'mobile_menu',
			array(
				'label'        => __( 'Mobile menu (hamburger)', 'jmk' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => __( 'On tablets and phones the menu items move into a ☰ button.', 'jmk' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'cta', array( 'label' => __( 'Phone & button', 'jmk' ) ) );
		$this->add_control(
			'phone',
			array(
				'label'   => __( 'Phone (display)', 'jmk' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '(972) 638-7479',
			)
		);
		$this->add_control(
			'phone_link',
			array(
				'label'   => __( 'Phone link', 'jmk' ),
				'type'    => Controls_Manager::URL,
				'default' => array( 'url' => 'tel:+19726387479' ),
			)
		);
		$this->add_button_controls( 'btn', __( 'Button', 'jmk' ), 'Get a quote', '/quote/' );
		$this->add_control(
			'sticky',
			array(
				'label'        => __( 'Sticky on scroll', 'jmk' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'separator'    => 'before',
				'return_value' => 'yes',
			)
		);
		$this->end_controls_section();

		$this->add_brand_style_controls( false );
	}

	/**
	 * Logo controls shared with the footer widget.
	 *
	 * @param JMK_Widget_Base $w Widget.
	 */
	public static function add_logo_controls( $w ) {
		$w->add_control(
			'logo_image',
			array(
				'label'       => __( 'Logo image (optional)', 'jmk' ),
				'type'        => Controls_Manager::MEDIA,
				'description' => __( 'Leave empty to use the text logo below.', 'jmk' ),
			)
		);
		$w->add_control(
			'logo_main',
			array(
				'label'   => __( 'Logo text', 'jmk' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 'JUST MOVE',
			)
		);
		$w->add_control(
			'logo_badge',
			array(
				'label'   => __( 'Logo badge', 'jmk' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 'DFW',
			)
		);
		$w->add_control(
			'logo_link',
			array(
				'label'   => __( 'Logo link', 'jmk' ),
				'type'    => Controls_Manager::URL,
				'default' => array( 'url' => '/' ),
			)
		);
	}

	/**
	 * @param array $s Widget settings containing the logo controls.
	 * @return string Escaped logo markup.
	 */
	public static function logo_html( $s ) {
		if ( ! empty( $s['logo_image']['url'] ) ) {
			return '<img src="' . esc_url( $s['logo_image']['url'] ) . '" alt="' . esc_attr( $s['logo_main'] . ' ' . $s['logo_badge'] ) . '">';
		}
		$html = '<span class="mv">' . esc_html( $s['logo_main'] ) . '</span>';
		if ( '' !== $s['logo_badge'] ) {
			$html .= '<span class="dfw">' . esc_html( $s['logo_badge'] ) . '</span>';
		}
		return $html . '<span class="arw">&#10148;</span>';
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$sticky = 'yes' === $s['sticky'] ? ' data-jmk-sticky="1"' : '';
		$burger = ! empty( $s['links'] ) && 'yes' === $s['mobile_menu'];
		$panel  = 'jmk-mnav-' . $this->get_id();
		?>
		<header class="jmk jmk-header"<?php echo $sticky; // phpcs:ignore ?>><div class="jmk-wrap jmk-nav-in">
			<?php $this->render_link( 'logo', $s['logo_link'], 'jmk-logo', self::logo_html( $s ) ); ?>
			<?php if ( ! empty( $s['links'] ) ) : ?>
				<nav class="jmk-nav-links" aria-label="<?php esc_attr_e( 'Main', 'jmk' ); ?>">
					<?php
					foreach ( $s['links'] as $i => $item ) {
						$this->render_link( 'nav' . $i, $item['link'], '', esc_html( $item['label'] ) );
					}
					?>
				</nav>
			<?php endif; ?>
			<div class="jmk-nav-cta">
				<?php
				if ( '' !== $s['phone'] ) {
					$this->render_link( 'phone', $s['phone_link'], 'jmk-nav-phone', '<span>&#9742;</span> ' . esc_html( $s['phone'] ) );
				}
				$this->render_button( $s, 'btn' );
				?>
			</div>
			<?php if ( $burger ) : ?>
				<button type="button" class="jmk-burger" aria-expanded="false" aria-controls="<?php echo esc_attr( $panel ); ?>" aria-label="<?php esc_attr_e( 'Open menu', 'jmk' ); ?>" data-label-open="<?php esc_attr_e( 'Open menu', 'jmk' ); ?>" data-label-close="<?php esc_attr_e( 'Close menu', 'jmk' ); ?>"><span></span><span></span><span></span></button>
			<?php endif; ?>
		</div>
		<?php if ( $burger ) : ?>
			<div class="jmk-mnav" id="<?php echo esc_attr( $panel ); ?>" hidden>
				<nav class="jmk-mnav-links" aria-label="<?php esc_attr_e( 'Main', 'jmk' ); ?>">
					<?php
					foreach ( $s['links'] as $i => $item ) {
						$this->render_link( 'mnav' . $i, $item['link'], '', esc_html( $item['label'] ) . '<span aria-hidden="true">&rsaquo;</span>' );
					}
					?>
				</nav>
				<div class="jmk-mnav-cta">
					<?php
					if ( '' !== $s['phone'] ) {
						$this->render_link( 'mphone', $s['phone_link'], 'jmk-btn jmk-btn-ghost', '<span>&#9742;</span> ' . esc_html( $s['phone'] ) );
					}
					if ( ! empty( $s['btn_text'] ) ) {
						$this->render_link( 'mbtn', $s['btn_link'], 'jmk-btn jmk-btn-' . ( isset( $s['btn_style'] ) ? $s['btn_style'] : 'y' ), esc_html( $s['btn_text'] ) );
					}
					?>
				</div>
			</div>
		<?php endif; ?>
		</header>
		<?php
	}
}
