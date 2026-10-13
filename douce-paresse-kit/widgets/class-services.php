<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Professionnels — "Offre professionnelle & sur mesure" (numbered services + image + scroll cue).
 */
class Services extends Base {

	public function get_name() {
		return 'dpk-services';
	}

	public function get_title() {
		return __( 'DP Services', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-bullet-list';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Content', 'douce-paresse-kit' ) ) );
		$this->add_control( 'eyebrow', array( 'label' => __( 'Small label', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Offre professionnelle & sur mesure', 'label_block' => true ) );
		$this->add_control( 'title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'default' => 'Nous accompagnons votre établissement de la sélection de l’offre jusqu’à son intégration en chambre.' ) );
		$rep = new Repeater();
		$rep->add_control( 'num', array( 'label' => __( 'Number', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT ) );
		$rep->add_control( 'title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$rep->add_control( 'desc', array( 'label' => __( 'Text', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA ) );
		$this->add_control(
			'items',
			array(
				'label'       => __( 'Services', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ num }}} — {{{ title }}}',
				'default'     => array(
					array( 'num' => 'I', 'title' => 'Logistique simplifiée', 'desc' => 'Plusieurs volumes de commande adaptés aux besoins de votre établissement.' ),
					array( 'num' => 'II', 'title' => 'Réapprovisionnement fluide', 'desc' => 'Gestion facilitée des consommables selon vos besoins.' ),
					array( 'num' => 'III', 'title' => 'Accompagnement dédié', 'desc' => 'Un interlocuteur unique pour accompagner votre maison.' ),
				),
			)
		);
		$this->add_control( 'image', array( 'label' => __( 'Image', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA, 'default' => $this->media_default( 'pro-offre.webp' ) ) );
		$this->add_control( 'scroll_link', array( 'label' => __( 'Scroll cue link', 'douce-paresse-kit' ), 'type' => Controls_Manager::URL, 'default' => $this->link_default( '#demande' ), 'description' => __( 'Leave empty to hide the arrow.', 'douce-paresse-kit' ) ) );
		$this->add_control( 'scroll_label', array( 'label' => __( 'Scroll cue label (accessibility)', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Aller au formulaire de demande' ) );
		$this->end_controls_section();

		$this->style_box( '.dpk-services' );
		$this->style_text( 'title', __( 'Title', 'douce-paresse-kit' ), '.dpk-services__title' );
		$this->style_text( 'item_title', __( 'Service titles', 'douce-paresse-kit' ), '.dpk-services__item-title' );
		$this->style_text( 'item_text', __( 'Service text', 'douce-paresse-kit' ), '.dpk-services__item-desc' );
		$this->style_accents( '.dpk-services .dpk-marker' );
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<section class="dpk dpk-services">
			<div class="dpk-services__inner">
				<div class="dpk-services__text">
					<?php if ( $s['eyebrow'] ) : ?>
						<span class="dpk-marker"><?php echo esc_html( $s['eyebrow'] ); ?></span>
					<?php endif; ?>
					<h2 class="dpk-services__title"><?php echo $this->text( $s['title'] ); // phpcs:ignore ?></h2>
					<div class="dpk-services__list">
						<?php foreach ( (array) $s['items'] as $item ) : ?>
							<div class="dpk-services__item">
								<span class="dpk-services__num"><?php echo esc_html( $item['num'] ); ?></span>
								<div class="dpk-services__item-body">
									<span class="dpk-services__item-title"><?php echo esc_html( $item['title'] ); ?></span>
									<span class="dpk-services__item-desc"><?php echo $this->text( $item['desc'] ); // phpcs:ignore ?></span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="dpk-services__media"><?php echo $this->img( $s['image'], 'dpk-services__img' ); // phpcs:ignore ?></div>
			</div>
			<?php if ( ! empty( $s['scroll_link']['url'] ) ) : ?>
				<div class="dpk-services__cue">
					<a<?php echo $this->link_attrs( $s['scroll_link'] ); // phpcs:ignore ?> aria-label="<?php echo esc_attr( $s['scroll_label'] ); ?>"><span class="dpk-services__cue-line"></span><span class="dpk-services__cue-arrow">↓</span></a>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}
}
