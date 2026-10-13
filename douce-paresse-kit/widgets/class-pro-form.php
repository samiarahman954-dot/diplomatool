<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Professionnels — "Demander l'offre professionnelle" (intro + request form).
 */
class Pro_Form extends Base {

	use Form_Trait;

	public function get_name() {
		return 'dpk-pro-form';
	}

	public function get_title() {
		return __( 'DP Professional Request Form', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	public static function default_fields() {
		return array(
			array( 'label' => 'Nom et prénom', 'name' => 'nom', 'type' => 'text', 'auto' => 'name', 'required' => 'yes' ),
			array( 'label' => 'Établissement', 'name' => 'etablissement', 'type' => 'text', 'auto' => 'organization', 'required' => 'yes' ),
			array( 'label' => 'Fonction / poste', 'name' => 'fonction', 'type' => 'text', 'auto' => 'organization-title', 'required' => 'yes' ),
			array( 'label' => 'E-mail professionnel', 'name' => 'email', 'type' => 'email', 'auto' => 'email', 'required' => 'yes' ),
			array( 'label' => 'Téléphone', 'name' => 'telephone', 'type' => 'tel', 'auto' => 'tel', 'required' => '' ),
			array( 'label' => 'Nombre de chambres', 'name' => 'chambres', 'type' => 'number', 'auto' => 'off', 'required' => '' ),
			array(
				'label'       => 'Objet de la demande',
				'name'        => 'objet',
				'type'        => 'select',
				'required'    => 'yes',
				'full'        => 'yes',
				'placeholder' => 'Choisir un objet',
				'options'     => "Découvrir l’offre professionnelle\nRecevoir les tarifs\nDemander un devis\nRéassort / consommables\nAutre",
			),
			array( 'label' => 'Message', 'name' => 'message', 'type' => 'textarea', 'required' => '', 'full' => 'yes' ),
		);
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Intro', 'douce-paresse-kit' ) ) );
		$this->add_control( 'anchor', array( 'label' => __( 'Section ID (anchor)', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'demande' ) );
		$this->add_control( 'title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => 'Demander l’offre professionnelle' ) );
		$this->add_control( 'text', array( 'label' => __( 'Text', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'default' => 'Les tarifs, conditions de commande et informations techniques sont détaillés dans notre offre professionnelle.' ) );
		$this->end_controls_section();

		$this->register_form_controls(
			self::default_fields(),
			array(
				'optional_text' => 'facultatif',
				'privacy_text'  => 'Les informations recueillies sont utilisées uniquement pour répondre à votre demande. En savoir plus : <a href="#">Politique de confidentialité</a>.',
				'required_note' => 'Les champs non marqués « facultatif » sont requis.',
				'submit_text'   => 'Envoyer ma demande',
				'success_icon'  => 'logo-icon-goldlight.png',
				'success_title' => 'Votre demande a bien été prise en compte.',
				'success_text'  => 'Merci pour votre intérêt. Nous reviendrons vers vous dans les meilleurs délais.',
				'reset_text'    => 'Nouvelle demande',
				'email_subject' => 'Demande d’offre professionnelle — Douce Paresse',
			)
		);

		$this->style_box( '.dpk-proform' );
		$this->style_text( 'title', __( 'Title', 'douce-paresse-kit' ), '.dpk-proform__title' );
		$this->style_text( 'text', __( 'Intro text', 'douce-paresse-kit' ), '.dpk-proform__intro .dpk-p' );
		$this->style_form();
	}

	protected function render() {
		$s  = $this->get_settings_for_display();
		$id = $s['anchor'] ? ' id="' . esc_attr( sanitize_title( $s['anchor'] ) ) . '"' : '';
		?>
		<section class="dpk dpk-proform"<?php echo $id; // phpcs:ignore ?>>
			<div class="dpk-proform__row">
				<div class="dpk-proform__intro">
					<span class="dpk-line" aria-hidden="true"></span>
					<h2 class="dpk-proform__title"><?php echo $this->text( $s['title'] ); // phpcs:ignore ?></h2>
					<?php if ( $s['text'] ) : ?>
						<p class="dpk-p dpk-p--body dpk-p--small"><?php echo $this->text( $s['text'] ); // phpcs:ignore ?></p>
					<?php endif; ?>
				</div>
				<div class="dpk-proform__form">
					<?php $this->render_form( $s, 'dark' ); ?>
				</div>
			</div>
		</section>
		<?php
	}
}
