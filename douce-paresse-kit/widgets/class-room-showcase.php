<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * "Pensé pour rester dans la chambre": story + three cards that open pop-ups.
 */
class Room_Showcase extends Editorial_Split {

	public function get_name() {
		return 'dpk-room-showcase';
	}

	public function get_title() {
		return __( 'DP Room Showcase & Pop-ups', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	protected function split_defaults() {
		return array(
			'image'      => 'chambre-v2.webp',
			'image_pos'  => '',
			'image_side' => 'right',
			'media_grow'  => 1,
			'media_basis' => 380,
			'text_basis'  => 380,
			'ratio'      => '4/5',
			'image_max'  => 560,
			'align'      => 'center',
			'pt'         => 'mp',
			'pb'         => 'm',
			'marker'     => 'line',
			'marker_txt' => '',
			'title'      => 'Pensé pour rester dans la chambre',
			'title_size' => 'sm',
			'text_max'   => 540,
			'paragraphs' => array(
				array( 'text' => 'À la fois fonctionnel et esthétique, le Coffret Luxury a été conçu pour s’intégrer durablement au sein des chambres et des suites.', 'style' => 'body' ),
				array( 'text' => 'Présent au fil des séjours, il trouve naturellement sa place dans le décor et y apporte une touche d’élégance discrète, tout en conservant sa vocation première d’accueil.', 'style' => 'body' ),
				array( 'text' => 'Le coffret, les tasses et les cuillères restent en chambre, tandis que les consommables sont renouvelés selon les besoins de l’établissement.', 'style' => 'body' ),
				array( 'text' => 'Un objet d’hospitalité pensé pour être aussi agréable à admirer qu’à utiliser.', 'style' => 'closing' ),
			),
		);
	}

	protected function default_cards() {
		return array(
			array(
				'num'          => '01',
				'title'        => 'Composition',
				'cover'        => $this->media_default( 'composition-cover.webp' ),
				'cover_pos'    => '50% 72%',
				'media_layout' => 'cover',
				'modal_image'  => $this->media_default( 'composition-inside-v2.webp' ),
				'lead'         => 'Le coffret Luxury réunit :',
				'lead_accent'  => '',
				'body'         => '',
				'list_style'   => 'bullets',
				'list'         => "Deux tasses en porcelaine avec couvercle\nDeux cuillères dorées gravées\nUne sélection de deux thés et d’une infusion biologique Douce Paresse\nUn sucre blanc cristallisé\nUne carte poétique Douce Paresse",
				'meta_label'   => 'Dimensions du coffret',
				'meta_value'   => '230 × 150 × 80 mm',
				'meta_note'    => 'Hauteur 80 mm · Largeur 150 mm · Profondeur 230 mm',
			),
			array(
				'num'           => '02',
				'title'         => 'Détails & Finitions',
				'cover'         => $this->media_default( 'details-cover.webp' ),
				'cover_pos'     => '48% 50%',
				'media_layout'  => 'stack',
				'modal_image'   => $this->media_default( 'details-inside-1.webp' ),
				'modal_image_2' => $this->media_default( 'details-inside-2.webp' ),
				'lead'          => '',
				'body'          => 'Le Coffret Luxury réunit des matériaux soigneusement choisis : porcelaine blanche, acier inoxydable 304, détails en acrylique et en aluminium, et finitions dorées.',
				'list_style'    => 'specs',
				'list'          => "Les tasses | ornées de l’emblème Douce Paresse.\nLes cuillères | gravées de notre signature <em>« L’art de ralentir »</em>.",
			),
			array(
				'num'          => '03',
				'title'        => 'La Sélection de Thés',
				'cover'        => $this->media_default( 'thes-cover-v2.webp' ),
				'cover_pos'    => '50% 50%',
				'media_layout' => 'contain',
				'modal_image'  => $this->media_default( 'thes-inside-v2.webp' ),
				'lead'         => 'Trois saveurs, trois moments',
				'lead_accent'  => 'yes',
				'body'         => '',
				'list_style'   => 'entries',
				'list'         => "ÉCLAT DE CITRON | Thé vert citron | Une pause citronnée, légère et agréable.\nNUIT IMPÉRIALE | Thé noir à la bergamote | Une note de bergamote, élégante et raffinée.\nINFUSION RUBIS | Rooibos & baies sauvages | Une douceur rouge, ronde et réconfortante.",
			),
		);
	}

	protected function register_controls() {
		$this->register_split_controls();

		$this->start_controls_section( 'cards_section', array( 'label' => __( 'Cards & pop-ups', 'douce-paresse-kit' ) ) );
		$rep = new Repeater();
		$rep->add_control( 'num', array( 'label' => __( 'Number', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT ) );
		$rep->add_control( 'title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT ) );
		$rep->add_control( 'cover', array( 'label' => __( 'Card image', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA ) );
		$rep->add_control( 'cover_pos', array( 'label' => __( 'Card image focus', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => '50% 50%' ) );
		$rep->add_control(
			'media_layout',
			array(
				'label'     => __( 'Pop-up image layout', 'douce-paresse-kit' ),
				'type'      => Controls_Manager::SELECT,
				'separator' => 'before',
				'options'   => array(
					'cover'   => __( 'One image (fill)', 'douce-paresse-kit' ),
					'stack'   => __( 'Two stacked images', 'douce-paresse-kit' ),
					'contain' => __( 'One image (framed)', 'douce-paresse-kit' ),
				),
				'default'   => 'cover',
			)
		);
		$rep->add_control( 'modal_image', array( 'label' => __( 'Pop-up image', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA ) );
		$rep->add_control(
			'modal_image_2',
			array(
				'label'     => __( 'Pop-up second image', 'douce-paresse-kit' ),
				'type'      => Controls_Manager::MEDIA,
				'condition' => array( 'media_layout' => 'stack' ),
			)
		);
		$rep->add_control( 'lead', array( 'label' => __( 'Italic lead', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$rep->add_control(
			'lead_accent',
			array(
				'label'        => __( 'Lead in gold-brown', 'douce-paresse-kit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
			)
		);
		$rep->add_control( 'body', array( 'label' => __( 'Text', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA ) );
		$rep->add_control(
			'list_style',
			array(
				'label'   => __( 'List style', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'none'    => __( 'No list', 'douce-paresse-kit' ),
					'bullets' => __( 'Bullets (one item per line)', 'douce-paresse-kit' ),
					'specs'   => __( 'Label | Value', 'douce-paresse-kit' ),
					'entries' => __( 'Name | Kind | Description', 'douce-paresse-kit' ),
				),
				'default' => 'none',
			)
		);
		$rep->add_control(
			'list',
			array(
				'label'       => __( 'List (one per line, columns separated by |)', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 6,
				'condition'   => array( 'list_style!' => 'none' ),
			)
		);
		$rep->add_control( 'meta_label', array( 'label' => __( 'Footnote label', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'separator' => 'before' ) );
		$rep->add_control( 'meta_value', array( 'label' => __( 'Footnote value', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT ) );
		$rep->add_control( 'meta_note', array( 'label' => __( 'Footnote detail', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$this->add_control(
			'cards',
			array(
				'label'       => __( 'Cards', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'default'     => $this->default_cards(),
				'title_field' => '{{{ num }}} — {{{ title }}}',
			)
		);
		$this->add_control( 'close_label', array( 'label' => __( 'Close label (accessibility)', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Fermer' ) );
		$this->end_controls_section();

		$this->register_split_styles();
		$this->style_text( 'card_title', __( 'Card titles', 'douce-paresse-kit' ), '.dpk-card__title' );
		$this->start_controls_section( 'modal_style', array( 'label' => __( 'Pop-up', 'douce-paresse-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'modal_bg', array( 'label' => __( 'Background', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .dpk-modal__dialog' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'modal_backdrop', array( 'label' => __( 'Backdrop', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .dpk-modal' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'modal_title_color', array( 'label' => __( 'Title color', 'douce-paresse-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .dpk-modal__title' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	private function render_list( $card ) {
		$style = $card['list_style'];
		if ( 'none' === $style || empty( $card['list'] ) ) {
			return;
		}
		echo '<div class="dpk-mlist dpk-mlist--' . esc_attr( $style ) . '">';
		if ( 'bullets' === $style ) {
			foreach ( $this->lines( $card['list'] ) as $l ) {
				echo '<div class="dpk-mlist__item"><span class="dpk-mlist__diamond" aria-hidden="true"></span><span>' . $this->inline( $l[0] ) . '</span></div>'; // phpcs:ignore
			}
		} elseif ( 'specs' === $style ) {
			foreach ( $this->lines( $card['list'], 2 ) as $l ) {
				echo '<div class="dpk-mlist__item"><span class="dpk-mlist__label">' . esc_html( $l[0] ) . '</span><span class="dpk-mlist__value">' . $this->inline( $l[1] ) . '</span></div>'; // phpcs:ignore
			}
		} else {
			foreach ( $this->lines( $card['list'], 3 ) as $l ) {
				echo '<div class="dpk-mlist__item"><div class="dpk-mlist__head"><span class="dpk-mlist__name">' . esc_html( $l[0] ) . '</span><span class="dpk-mlist__kind">' . esc_html( $l[1] ) . '</span></div><span class="dpk-mlist__desc">' . $this->inline( $l[2] ) . '</span></div>'; // phpcs:ignore
			}
		}
		echo '</div>';
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$cards = array_values( array_filter( (array) $s['cards'], function ( $c ) {
			return ! empty( $c['title'] );
		} ) );
		$uid   = 'dpk-modal-' . $this->get_id();

		echo '<section class="' . esc_attr( $this->section_classes( $s, 'dpk-split dpk-room' ) ) . '" data-dpk-room>';
		$this->render_split_row( $s );

		if ( $cards ) :
			?>
			<div class="dpk-room__cards">
				<?php foreach ( $cards as $i => $c ) : ?>
					<button type="button" class="dpk-card" data-dpk-modal-open="<?php echo esc_attr( $i ); ?>" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $uid ); ?>">
						<span class="dpk-card__media"><?php echo $this->img( $c['cover'], 'dpk-card__img', $c['title'], false, $this->pos( $c['cover_pos'] ) ); // phpcs:ignore ?></span>
						<span class="dpk-card__bar">
							<span class="dpk-card__meta"><span class="dpk-card__num"><?php echo esc_html( $c['num'] ); ?></span><span class="dpk-card__title"><?php echo esc_html( $c['title'] ); ?></span></span>
							<span class="dpk-card__plus" aria-hidden="true">+</span>
						</span>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="dpk-modal" id="<?php echo esc_attr( $uid ); ?>" data-dpk-modal hidden>
				<div class="dpk-modal__dialog" role="dialog" aria-modal="true" tabindex="-1">
					<button type="button" class="dpk-modal__close" data-dpk-modal-close aria-label="<?php echo esc_attr( $s['close_label'] ); ?>">✕</button>
					<?php
					$n = count( $cards );
					foreach ( $cards as $i => $c ) :
						$layout = in_array( $c['media_layout'], array( 'cover', 'stack', 'contain' ), true ) ? $c['media_layout'] : 'cover';
						$prev   = $cards[ ( $i - 1 + $n ) % $n ]['title'];
						$next   = $cards[ ( $i + 1 ) % $n ]['title'];
						?>
						<div class="dpk-modal__panel" data-dpk-panel="<?php echo esc_attr( $i ); ?>" hidden>
							<div class="dpk-modal__media dpk-modal__media--<?php echo esc_attr( $layout ); ?>">
								<?php if ( 'stack' === $layout ) : ?>
									<div class="dpk-modal__cell"><?php echo $this->img( $c['modal_image'], 'dpk-modal__img' ); // phpcs:ignore ?></div>
									<div class="dpk-modal__cell"><?php echo $this->img( isset( $c['modal_image_2'] ) ? $c['modal_image_2'] : array(), 'dpk-modal__img' ); // phpcs:ignore ?></div>
								<?php else : ?>
									<?php echo $this->img( $c['modal_image'], 'dpk-modal__img' ); // phpcs:ignore ?>
								<?php endif; ?>
							</div>
							<div class="dpk-modal__body<?php echo 'stack' === $layout ? ' dpk-modal__body--center' : ''; ?>">
								<span class="dpk-modal__num"><?php echo esc_html( $c['num'] ); ?></span>
								<h3 class="dpk-modal__title"><?php echo esc_html( $c['title'] ); ?></h3>
								<?php if ( $c['lead'] ) : ?>
									<p class="dpk-modal__lead<?php echo 'yes' === $c['lead_accent'] ? ' dpk-modal__lead--accent' : ''; ?>"><?php echo esc_html( $c['lead'] ); ?></p>
								<?php endif; ?>
								<?php if ( $c['body'] ) : ?>
									<p class="dpk-modal__text"><?php echo $this->text( $c['body'] ); // phpcs:ignore ?></p>
								<?php endif; ?>
								<?php $this->render_list( $c ); ?>
								<?php if ( ! empty( $c['meta_label'] ) || ! empty( $c['meta_value'] ) ) : ?>
									<div class="dpk-modal__meta">
										<span class="dpk-modal__meta-label"><?php echo esc_html( $c['meta_label'] ); ?></span>
										<span class="dpk-modal__meta-value"><?php echo esc_html( $c['meta_value'] ); ?></span>
										<?php if ( ! empty( $c['meta_note'] ) ) : ?>
											<span class="dpk-modal__meta-note"><?php echo esc_html( $c['meta_note'] ); ?></span>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</div>
							<?php if ( $n > 1 ) : ?>
								<div class="dpk-modal__nav">
									<button type="button" data-dpk-modal-step="-1">← <?php echo esc_html( $prev ); ?></button>
									<button type="button" data-dpk-modal-step="1"><?php echo esc_html( $next ); ?> →</button>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
		endif;
		echo '</section>';
	}
}
