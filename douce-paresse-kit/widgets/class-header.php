<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

class Header extends Base {

	public function get_name() {
		return 'dpk-header';
	}

	public function get_title() {
		return __( 'DP Header', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-header';
	}

	private function menu_repeater( $id, $label, $default ) {
		$rep = new Repeater();
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
		$this->start_controls_section( 'logo_section', array( 'label' => __( 'Logo', 'douce-paresse-kit' ) ) );
		$this->add_control( 'logo', array( 'label' => __( 'Logo icon', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA, 'default' => $this->media_default( 'logo-icon-navy.png' ) ) );
		$this->add_control( 'logo_hover', array( 'label' => __( 'Logo icon on hover', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA, 'default' => $this->media_default( 'logo-icon-gold.png' ) ) );
		$this->add_control( 'logo_link', array( 'label' => __( 'Logo link', 'douce-paresse-kit' ), 'type' => Controls_Manager::URL, 'placeholder' => home_url( '/' ) ) );
		$this->add_control( 'logo_label', array( 'label' => __( 'Logo accessible label', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Douce Paresse — Accueil' ) );
		$this->add_control( 'brand_text', array( 'label' => __( 'Brand name (mobile)', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'DOUCE PARESSE' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'menu_section', array( 'label' => __( 'Menu', 'douce-paresse-kit' ) ) );
		$this->menu_repeater(
			'menu_left',
			__( 'Links left of the logo', 'douce-paresse-kit' ),
			array(
				array( 'text' => 'La Maison', 'link' => $this->link_default( '#' ) ),
				array( 'text' => 'Le Coffret Luxury', 'link' => $this->link_default( '#' ) ),
			)
		);
		$this->menu_repeater(
			'menu_right',
			__( 'Links right of the logo', 'douce-paresse-kit' ),
			array(
				array( 'text' => 'Professionnels', 'link' => $this->link_default( '#' ) ),
				array( 'text' => 'Contact', 'link' => $this->link_default( '#' ) ),
			)
		);
		// Not "sticky": Elementor Pro adds its own "sticky" control (Advanced → Motion Effects) to every
		// widget, and that one (default: none) replaced this switch, so the header was never fixed.
		$this->add_control(
			'dpk_fixed',
			array(
				'label'        => __( 'Fixed on scroll', 'douce-paresse-kit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'mobile_section', array( 'label' => __( 'Mobile menu', 'douce-paresse-kit' ) ) );
		$this->add_control( 'menu_label', array( 'label' => __( 'Menu button', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Menu' ) );
		$this->add_control( 'close_label', array( 'label' => __( 'Close button', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Fermer ✕' ) );
		$this->add_control( 'overlay_logo', array( 'label' => __( 'Overlay logo icon', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA, 'default' => $this->media_default( 'logo-icon-ivory.png' ) ) );
		$this->add_control( 'overlay_tagline', array( 'label' => __( 'Overlay tagline', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'l’art de la courtoisie française' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'lang_section', array( 'label' => __( 'Languages (GTranslate)', 'douce-paresse-kit' ) ) );
		$this->add_control(
			'lang_mode',
			array(
				'label'       => __( 'Show the FR | EN switcher', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'auto',
				'options'     => array(
					'auto' => __( 'Automatic (when GTranslate is active)', 'douce-paresse-kit' ),
					'show' => __( 'Always', 'douce-paresse-kit' ),
					'hide' => __( 'Never', 'douce-paresse-kit' ),
				),
				'description' => __( 'Desktop: next to the menu. Tablet / mobile: inside the menu.', 'douce-paresse-kit' ),
			)
		);
		$this->add_control(
			'lang_items',
			array(
				'label'       => __( 'Languages (Label | code, one per line)', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 3,
				'default'     => "FR | fr\nEN | en",
				'description' => __( 'The first line is the original language of the site. Codes as in GTranslate (fr, en, de, es…).', 'douce-paresse-kit' ),
				'condition'   => array( 'lang_mode!' => 'hide' ),
			)
		);
		$this->add_control(
			'lang_hide_float',
			array(
				'label'        => __( 'Hide the GTranslate floating button', 'douce-paresse-kit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'lang_mode!' => 'hide' ),
			)
		);
		$this->end_controls_section();

		// Style.
		$this->start_controls_section( 'bar_style', array( 'label' => __( 'Bar', 'douce-paresse-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'bar_bg', array( 'label' => __( 'Background', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .dpk-header__bar' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'bar_border', array( 'label' => __( 'Bottom border', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .dpk-header__bar' => 'border-bottom-color: {{VALUE}};' ) ) );
		$this->add_responsive_control(
			'bar_height',
			array(
				'label'      => __( 'Height', 'douce-paresse-kit' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 48, 'max' => 160 ) ),
				'selectors'  => array( '{{WRAPPER}} .dpk-header__desktop, {{WRAPPER}} .dpk-header__mobile' => 'height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'logo_size',
			array(
				'label'      => __( 'Logo size', 'douce-paresse-kit' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 24, 'max' => 140 ) ),
				'selectors'  => array( '{{WRAPPER}} .dpk-header__logo' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'links_style', array( 'label' => __( 'Menu links', 'douce-paresse-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'links_typo', 'selector' => '{{WRAPPER}} .dpk-header__menu a' ) );
		$this->add_control( 'links_color', array( 'label' => __( 'Color', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .dpk-header__menu a, {{WRAPPER}} .dpk-header__brand, {{WRAPPER}} .dpk .dpk-header__toggle' => 'color: {{VALUE}};', '{{WRAPPER}} .dpk-header__burger span' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'links_hover_line', array( 'label' => __( 'Hover / active underline', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .dpk-header__menu a:hover, {{WRAPPER}} .dpk-header__menu a[aria-current="page"]' => 'border-bottom-color: {{VALUE}};' ) ) );
		$this->add_control( 'lang_color', array( 'label' => __( 'Language switcher', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'separator' => 'before', 'selectors' => array( '{{WRAPPER}} .dpk-lang--bar .dpk-lang__btn, {{WRAPPER}} .dpk-lang--bar .dpk-lang__sep' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'lang_active_color', array( 'label' => __( 'Language switcher: current language', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .dpk-lang--bar .dpk-lang__btn[aria-pressed="true"], {{WRAPPER}} .dpk-lang--bar .dpk-lang__btn:hover' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'overlay_style', array( 'label' => __( 'Mobile overlay', 'douce-paresse-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'overlay_bg', array( 'label' => __( 'Background', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .dpk-header__overlay' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'overlay_color', array( 'label' => __( 'Link color', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .dpk-header__overlay-links a' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'overlay_accent', array( 'label' => __( 'Accent (active, close, tagline)', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .dpk-header__overlay' => '--dpk-gold-l: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'overlay_typo', 'selector' => '{{WRAPPER}} .dpk-header__overlay-links a' ) );
		$this->end_controls_section();
	}

	private function links( $items ) {
		foreach ( (array) $items as $item ) {
			if ( empty( $item['text'] ) ) {
				continue;
			}
			echo '<a' . $this->link_attrs( $item['link'] ) . '>' . esc_html( $item['text'] ) . '</a>'; // phpcs:ignore
		}
	}

	/** [ [label, code], … ] or empty when the switcher is off. */
	private function languages( $s ) {
		$mode = isset( $s['lang_mode'] ) ? $s['lang_mode'] : 'auto';
		if ( 'hide' === $mode || ( 'auto' === $mode && ! ( class_exists( 'DPK\\I18n' ) && \DPK\I18n::gtranslate_active() ) ) ) {
			return array();
		}
		$langs = array();
		foreach ( $this->lines( isset( $s['lang_items'] ) ? $s['lang_items'] : "FR | fr\nEN | en", 2 ) as $l ) {
			$code = strtolower( preg_replace( '/[^A-Za-z-]/', '', $l[1] ) );
			if ( '' !== trim( $l[0] ) && '' !== $code ) {
				$langs[] = array( trim( $l[0] ), 'zh-cn' === $code ? 'zh-CN' : ( 'zh-tw' === $code ? 'zh-TW' : $code ) );
			}
		}
		return count( $langs ) > 1 ? $langs : array();
	}

	private function lang_switch( $langs, $variant, $hide_float ) {
		$classes = 'dpk-lang dpk-lang--' . $variant . ' notranslate' . ( $hide_float ? ' dpk-lang--hidegt' : '' );
		echo '<div class="' . esc_attr( $classes ) . '" translate="no" role="group" aria-label="' . esc_attr__( 'Langue', 'douce-paresse-kit' ) . '" data-dpk-lang-switch data-source="' . esc_attr( $langs[0][1] ) . '">';
		foreach ( $langs as $i => $l ) {
			if ( $i ) {
				echo '<span class="dpk-lang__sep" aria-hidden="true">|</span>';
			}
			echo '<button type="button" class="dpk-lang__btn" data-dpk-lang="' . esc_attr( $l[1] ) . '" lang="' . esc_attr( $l[1] ) . '" aria-pressed="' . ( $i ? 'false' : 'true' ) . '">' . esc_html( $l[0] ) . '</button>';
		}
		echo '</div>';
	}

	protected function render() {
		$s         = $this->get_settings_for_display();
		$home      = ! empty( $s['logo_link']['url'] ) ? $s['logo_link'] : array( 'url' => home_url( '/' ) );
		$logo      = $this->media_url( $s['logo'] );
		$logo_h    = $this->media_url( $s['logo_hover'] );
		$logo_h    = $logo_h ? $logo_h : $logo;
		$overlay   = 'dpk-menu-' . $this->get_id();
		$all_links = array_merge( (array) $s['menu_left'], (array) $s['menu_right'] );
		$classes   = 'dpk dpk-header' . ( 'yes' === ( isset( $s['dpk_fixed'] ) ? $s['dpk_fixed'] : 'yes' ) ? ' dpk-header--fixed' : '' );
		$langs     = $this->languages( $s );
		$hide_gt   = 'yes' === ( isset( $s['lang_hide_float'] ) ? $s['lang_hide_float'] : 'yes' );
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" data-dpk-header>
			<header class="dpk-header__bar">
				<nav class="dpk-header__desktop" aria-label="<?php esc_attr_e( 'Navigation principale', 'douce-paresse-kit' ); ?>">
					<div class="dpk-header__menu"><?php $this->links( $s['menu_left'] ); ?></div>
					<a class="dpk-header__logo"<?php echo $this->link_attrs( $home ); // phpcs:ignore ?> aria-label="<?php echo esc_attr( $s['logo_label'] ); ?>" style="<?php echo esc_attr( '--dpk-logo:url(' . esc_url( $logo ) . ');--dpk-logo-hover:url(' . esc_url( $logo_h ) . ')' ); ?>"></a>
					<div class="dpk-header__menu dpk-header__menu--right">
						<?php
						$this->links( $s['menu_right'] );
						if ( $langs ) {
							$this->lang_switch( $langs, 'bar', $hide_gt );
						}
						?>
					</div>
				</nav>
				<nav class="dpk-header__mobile" aria-label="<?php esc_attr_e( 'Navigation', 'douce-paresse-kit' ); ?>">
					<a class="dpk-header__brand"<?php echo $this->link_attrs( $home ); // phpcs:ignore ?> aria-label="<?php echo esc_attr( $s['logo_label'] ); ?>">
						<span class="dpk-header__brand-icon"><?php echo $this->img( $s['logo'], '', '', true ); // phpcs:ignore ?></span>
						<span class="dpk-header__brand-text"><?php echo esc_html( $s['brand_text'] ); ?></span>
					</a>
					<button type="button" class="dpk-header__toggle" aria-label="<?php echo esc_attr( $s['menu_label'] ); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $overlay ); ?>" data-dpk-menu-toggle>
						<span><?php echo esc_html( $s['menu_label'] ); ?></span>
						<span class="dpk-header__burger" aria-hidden="true"><span></span><span></span></span>
					</button>
				</nav>
			</header>

			<div class="dpk-header__overlay" id="<?php echo esc_attr( $overlay ); ?>">
				<div class="dpk-header__overlay-top">
					<span class="dpk-header__brand dpk-header__brand--ivory">
						<span class="dpk-header__brand-icon"><?php echo $this->img( $s['overlay_logo'] ); // phpcs:ignore ?></span>
						<span class="dpk-header__brand-text"><?php echo esc_html( $s['brand_text'] ); ?></span>
					</span>
					<button type="button" class="dpk-header__close" data-dpk-menu-toggle><?php echo esc_html( $s['close_label'] ); ?></button>
				</div>
				<div class="dpk-header__overlay-links"><?php $this->links( $all_links ); ?></div>
				<?php if ( $langs ) : ?>
					<div class="dpk-header__overlay-lang"><?php $this->lang_switch( $langs, 'overlay', $hide_gt ); ?></div>
				<?php endif; ?>
				<?php if ( $s['overlay_tagline'] ) : ?>
					<div class="dpk-header__overlay-tagline"><?php echo esc_html( $s['overlay_tagline'] ); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
