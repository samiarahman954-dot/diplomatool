<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Contact — form + navy "Nous contacter" card.
 */
class Contact extends Base {

	use Form_Trait;

	public function get_name() {
		return 'dpk-contact';
	}

	public function get_title() {
		return __( 'DP Contact Form & Details', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-mail';
	}

	public static function default_fields() {
		return array(
			array( 'label' => 'Nom', 'name' => 'nom', 'type' => 'text', 'auto' => 'family-name', 'required' => 'yes' ),
			array( 'label' => 'Prénom', 'name' => 'prenom', 'type' => 'text', 'auto' => 'given-name', 'required' => 'yes' ),
			array( 'label' => 'E-mail', 'name' => 'email', 'type' => 'email', 'auto' => 'email', 'required' => 'yes' ),
			array( 'label' => 'Objet', 'name' => 'objet', 'type' => 'text', 'auto' => 'off', 'required' => 'yes', 'full' => 'yes' ),
			array( 'label' => 'Message', 'name' => 'message', 'type' => 'textarea', 'required' => 'yes', 'full' => 'yes' ),
		);
	}

	protected function register_controls() {
		$this->start_controls_section( 'card_section', array( 'label' => __( 'Contact card', 'douce-paresse-kit' ) ) );
		$this->add_control( 'icon', array( 'label' => __( 'Icon', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA, 'default' => $this->media_default( 'logo-icon-goldlight.png' ) ) );
		$this->add_control( 'card_title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Nous contacter' ) );
		$rep = new Repeater();
		$rep->add_control( 'label', array( 'label' => __( 'Label', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT ) );
		$rep->add_control( 'value', array( 'label' => __( 'Value', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$rep->add_control( 'link', array( 'label' => __( 'Link', 'douce-paresse-kit' ), 'type' => Controls_Manager::URL ) );
		$this->add_control(
			'links',
			array(
				'label'       => __( 'Contact links', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ label }}}',
				'default'     => array(
					array( 'label' => 'E-mail', 'value' => 'contact@douceparesse.com', 'link' => $this->link_default( 'mailto:contact@douceparesse.com' ) ),
					array( 'label' => 'Instagram', 'value' => 'douceparesse.officiel', 'link' => array( 'url' => 'https://www.instagram.com/douceparesse.officiel/', 'is_external' => 'on', 'nofollow' => '' ) ),
					array( 'label' => 'LinkedIn', 'value' => 'Douce Paresse', 'link' => array( 'url' => 'https://www.linkedin.com/in/mariam-siby-007689397', 'is_external' => 'on', 'nofollow' => '' ) ),
				),
			)
		);
		$this->add_control( 'pro_text', array( 'label' => __( 'Professional note', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Vous êtes un établissement ?', 'label_block' => true ) );
		$this->add_control( 'pro_link_text', array( 'label' => __( 'Professional link text', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Demander l’offre professionnelle', 'label_block' => true ) );
		$this->add_control( 'pro_link', array( 'label' => __( 'Professional link', 'douce-paresse-kit' ), 'type' => Controls_Manager::URL, 'default' => $this->link_default( '#' ) ) );
		$this->end_controls_section();

		$this->register_form_controls(
			self::default_fields(),
			array(
				'optional_text' => 'facultatif',
				'privacy_text'  => 'Les informations recueillies sont utilisées uniquement pour répondre à votre demande. En savoir plus : <a href="#">Politique de confidentialité</a>.',
				'required_note' => '',
				'submit_text'   => 'Envoyer',
				'success_icon'  => '',
				'success_title' => 'Votre demande a bien été prise en compte.',
				'success_text'  => 'Merci pour votre message. Nous vous répondrons dans les meilleurs délais.',
				'reset_text'    => 'Nouveau message',
				'email_subject' => 'Nouveau message — Douce Paresse',
			)
		);

		$this->style_box( '.dpk-contact' );
		$this->start_controls_section( 'card_style', array( 'label' => __( 'Contact card', 'douce-paresse-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'card_bg', array( 'label' => __( 'Background', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .dpk-contact__card' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'card_color', array( 'label' => __( 'Text color', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .dpk-contact__card, {{WRAPPER}} .dpk-contact__link' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'card_accent', array( 'label' => __( 'Accent', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .dpk-contact__card' => '--dpk-gold-l: {{VALUE}};' ) ) );
		$this->end_controls_section();
		$this->style_form();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<section class="dpk dpk-contact">
			<aside class="dpk-contact__card">
				<?php if ( $this->media_url( $s['icon'] ) ) : ?>
					<span class="dpk-contact__icon"><?php echo $this->img( $s['icon'], '', 'Douce Paresse' ); // phpcs:ignore ?></span>
				<?php endif; ?>
				<h2 class="dpk-contact__title"><?php echo esc_html( $s['card_title'] ); ?></h2>
				<div class="dpk-contact__links">
					<?php foreach ( (array) $s['links'] as $l ) : ?>
						<a class="dpk-contact__link"<?php echo $this->link_attrs( $l['link'] ); // phpcs:ignore ?>>
							<span class="dpk-contact__label"><?php echo esc_html( $l['label'] ); ?></span>
							<span class="dpk-contact__value"><?php echo esc_html( $l['value'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
				<?php if ( $s['pro_text'] || $s['pro_link_text'] ) : ?>
					<div class="dpk-contact__pro">
						<span><?php echo esc_html( $s['pro_text'] ); ?></span>
						<?php if ( $s['pro_link_text'] ) : ?>
							<a<?php echo $this->link_attrs( $s['pro_link'] ); // phpcs:ignore ?>><?php echo esc_html( $s['pro_link_text'] ); ?> <span aria-hidden="true">→</span></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</aside>
			<div class="dpk-contact__form">
				<?php $this->render_form( $s, 'light' ); ?>
			</div>
		</section>
		<?php
	}
}
