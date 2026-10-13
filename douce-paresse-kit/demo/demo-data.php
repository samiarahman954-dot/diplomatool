<?php
/**
 * Demo content of the Douce Paresse kit.
 *
 * Tokens resolved at import time:
 *   {{img:file.webp}}  → image imported in the Media Library
 *   {{page:key}}       → URL of the imported page with that key
 *
 * Each page is a list of section widgets (one Elementor container per widget).
 * Changing this file and running “Smart sync” updates sites without losing their own edits.
 */

defined( 'ABSPATH' ) || exit;

$link = function ( $url, $external = false ) {
	return array(
		'url'         => $url,
		'is_external' => $external ? 'on' : '',
		'nofollow'    => '',
	);
};

$page_settings = array(
	'hide_title'            => 'yes',
	'background_background' => 'classic',
	'background_color'      => '#F5F0E6',
);

$privacy = 'Les informations recueillies sont utilisées uniquement pour répondre à votre demande. En savoir plus : <a href="{{page:politique}}">Politique de confidentialité</a>.';

return array(
	'version' => '1.0.0',

	'images'  => array(
		'logo-icon-navy.png'         => 'Douce Paresse',
		'logo-icon-gold.png'         => 'Douce Paresse',
		'logo-icon-goldlight.png'    => 'Douce Paresse',
		'logo-icon-ivory.png'        => 'Douce Paresse',
		'logo-icon.png'              => 'Douce Paresse',
		'logo-full.png'              => 'Douce Paresse',
		'logo-full-ivory.png'        => 'Douce Paresse',
		'hero-v2.webp'               => 'Le Coffret Luxury Douce Paresse, tasses en porcelaine, carte d’accueil et fleurs de freesia',
		'premier-geste.webp'         => 'Deux tasses de thé et la carte d’accueil Douce Paresse',
		'chambre-v2.webp'            => 'Le Coffret Luxury, ses deux tasses Douce Paresse, sachets de thé, sucre cristallisé et cartes d’accueil',
		'composition-cover.webp'     => 'Le Coffret Luxury Douce Paresse',
		'composition-inside-v2.webp' => 'Intérieur du tiroir : sachets de thé, cuillères dorées gravées et sucre cristallisé',
		'details-cover.webp'         => 'Détails & Finitions',
		'details-inside-1.webp'      => 'Finitions dorées du coffret',
		'details-inside-2.webp'      => 'Cuillères dorées gravées « L’art de ralentir »',
		'thes-cover-v2.webp'         => 'La Sélection de Thés',
		'thes-inside-v2.webp'        => 'Les trois thés Douce Paresse, leurs sachets et un bâtonnet de sucre cristallisé',
		'maison-principal.webp'      => 'Deux tasses de thé sur un plateau en porcelaine, cuillères dorées et roses',
		'maison-add.webp'            => 'Une tasse de thé sur un magazine, une rose et des sachets Douce Paresse sur un lit',
		'maison-ambiance.webp'       => 'Plateau de thé sur un lit, bouilloire, magazine et sachet Infusion Rubis',
		'coffret-hero.webp'          => 'Tasse en porcelaine Douce Paresse, sachet Nuit Impériale et bâtonnet de sucre cristallisé dans le Coffret Luxury',
		'composition-inside.webp'    => 'Intérieur du coffret : sachets de thé, cuillères dorées et sucre cristallisé',
		'coffret-add.webp'           => 'Trois thés en vrac dans une coupelle en porcelaine et deux cuillères dorées gravées « L’art de ralentir »',
		'chambre.webp'               => 'Le Coffret Luxury dans une chambre, près de tasses dorées et de livres anciens',
		'pro-hero.webp'              => 'Une main infuse un sachet de thé dans une tasse Douce Paresse posée sur le Coffret Luxury',
		'pro-experience.webp'        => 'Un hôte tient la carte d’accueil Douce Paresse dans une chambre d’hôtel',
		'pro-offre.webp'             => 'Un maître d’hôtel en gants blancs présente le Coffret Luxury',
	),

	'items'   => array(

		/* ------------------------------------------------------------ Header */
		'header'    => array(
			'kind'    => 'template',
			'type'    => 'header',
			'title'   => 'Douce Paresse — Header',
			'widgets' => array(
				array(
					'key'      => 'header',
					'label'    => 'Header',
					'type'     => 'dpk-header',
					'settings' => array(
						'logo'         => '{{img:logo-icon-navy.png}}',
						'logo_hover'   => '{{img:logo-icon-gold.png}}',
						'logo_link'    => $link( '{{page:home}}' ),
						'overlay_logo' => '{{img:logo-icon-ivory.png}}',
						'menu_left'    => array(
							array( 'text' => 'La Maison', 'link' => $link( '{{page:maison}}' ) ),
							array( 'text' => 'Le Coffret Luxury', 'link' => $link( '{{page:coffret}}' ) ),
						),
						'menu_right'   => array(
							array( 'text' => 'Professionnels', 'link' => $link( '{{page:pro}}' ) ),
							array( 'text' => 'Contact', 'link' => $link( '{{page:contact}}' ) ),
						),
					),
				),
			),
		),

		/* ------------------------------------------------------------ Footer */
		'footer'    => array(
			'kind'    => 'template',
			'type'    => 'footer',
			'title'   => 'Douce Paresse — Footer',
			'widgets' => array(
				array(
					'key'      => 'footer',
					'label'    => 'Footer',
					'type'     => 'dpk-footer',
					'settings' => array(
						'logo'    => '{{img:logo-full-ivory.png}}',
						'nav'     => array(
							array( 'text' => 'La Maison', 'link' => $link( '{{page:maison}}' ) ),
							array( 'text' => 'Le Coffret Luxury', 'link' => $link( '{{page:coffret}}' ) ),
							array( 'text' => 'Professionnels', 'link' => $link( '{{page:pro}}' ) ),
							array( 'text' => 'Contact', 'link' => $link( '{{page:contact}}' ) ),
						),
						'legal'   => array(
							array( 'text' => 'Mentions légales', 'link' => $link( '{{page:mentions}}' ) ),
							array( 'text' => 'Politique de confidentialité', 'link' => $link( '{{page:politique}}' ) ),
						),
						'contact' => array(
							array( 'prefix' => 'Email', 'text' => 'contact@douceparesse.com', 'link' => $link( 'mailto:contact@douceparesse.com' ) ),
							array( 'prefix' => '', 'text' => 'Instagram', 'link' => $link( 'https://www.instagram.com/douceparesse.officiel/', true ) ),
						),
					),
				),
			),
		),

		/* ------------------------------------------------------------ Accueil */
		'home'      => array(
			'kind'          => 'page',
			'title'         => 'Accueil',
			'slug'          => 'accueil',
			'front'         => true,
			'page_settings' => $page_settings,
			'widgets'       => array(
				array(
					'key'      => 'hero',
					'label'    => '01 Hero',
					'type'     => 'dpk-home-hero',
					'settings' => array(
						'layout'    => 'centered',
						'title'     => 'DOUCE PARESSE',
						'subtitle'  => 'l’art de la courtoisie française',
						'image'     => '{{img:hero-v2.webp}}',
						'image_pos' => '40% 62%',
						'link_text' => 'Chapitre I',
						'link'      => $link( '#chapitre-1' ),
					),
				),
				array(
					'key'      => 'chapter',
					'label'    => '02 Chapitre I',
					'type'     => 'dpk-chapter',
					'settings' => array(
						'layout'      => 'split',
						'anchor'      => 'chapitre-1',
						'image'       => '{{img:premier-geste.webp}}',
						'image_pos'   => '50% 60%',
						'label'       => 'Chapitre I',
						'title'       => 'Le Premier Geste',
						'subtitle'    => 'Et si un détail faisait toute la différence ?',
						'button_text' => 'Découvrir le Coffret',
						'button_link' => $link( '{{page:coffret}}' ),
					),
				),
				array(
					'key'      => 'room',
					'label'    => '03 Pensé pour rester dans la chambre',
					'type'     => 'dpk-room-showcase',
					'settings' => array(
						'image'      => '{{img:chambre-v2.webp}}',
						'title'      => 'Pensé pour rester dans la chambre',
						'paragraphs' => array(
							array( 'text' => 'À la fois fonctionnel et esthétique, le Coffret Luxury a été conçu pour s’intégrer durablement au sein des chambres et des suites.', 'style' => 'body' ),
							array( 'text' => 'Présent au fil des séjours, il trouve naturellement sa place dans le décor et y apporte une touche d’élégance discrète, tout en conservant sa vocation première d’accueil.', 'style' => 'body' ),
							array( 'text' => 'Le coffret, les tasses et les cuillères restent en chambre, tandis que les consommables sont renouvelés selon les besoins de l’établissement.', 'style' => 'body' ),
							array( 'text' => 'Un objet d’hospitalité pensé pour être aussi agréable à admirer qu’à utiliser.', 'style' => 'closing' ),
						),
						'cards'      => array(
							array(
								'num'          => '01',
								'title'        => 'Composition',
								'cover'        => '{{img:composition-cover.webp}}',
								'cover_pos'    => '50% 72%',
								'media_layout' => 'cover',
								'modal_image'  => '{{img:composition-inside-v2.webp}}',
								'lead'         => 'Le coffret Luxury réunit :',
								'list_style'   => 'bullets',
								'list'         => "Deux tasses en porcelaine avec couvercle\nDeux cuillères dorées gravées\nUne sélection de deux thés et d’une infusion biologique Douce Paresse\nUn sucre blanc cristallisé\nUne carte poétique Douce Paresse",
								'meta_label'   => 'Dimensions du coffret',
								'meta_value'   => '230 × 150 × 80 mm',
								'meta_note'    => 'Hauteur 80 mm · Largeur 150 mm · Profondeur 230 mm',
							),
							array(
								'num'           => '02',
								'title'         => 'Détails & Finitions',
								'cover'         => '{{img:details-cover.webp}}',
								'cover_pos'     => '48% 50%',
								'media_layout'  => 'stack',
								'modal_image'   => '{{img:details-inside-1.webp}}',
								'modal_image_2' => '{{img:details-inside-2.webp}}',
								'body'          => 'Le Coffret Luxury réunit des matériaux soigneusement choisis : porcelaine blanche, acier inoxydable 304, détails en acrylique et en aluminium, et finitions dorées.',
								'list_style'    => 'specs',
								'list'          => "Les tasses | ornées de l’emblème Douce Paresse.\nLes cuillères | gravées de notre signature <em>« L’art de ralentir »</em>.",
							),
							array(
								'num'          => '03',
								'title'        => 'La Sélection de Thés',
								'cover'        => '{{img:thes-cover-v2.webp}}',
								'cover_pos'    => '50% 50%',
								'media_layout' => 'contain',
								'modal_image'  => '{{img:thes-inside-v2.webp}}',
								'lead'         => 'Trois saveurs, trois moments',
								'lead_accent'  => 'yes',
								'list_style'   => 'entries',
								'list'         => "ÉCLAT DE CITRON | Thé vert citron | Une pause citronnée, légère et agréable.\nNUIT IMPÉRIALE | Thé noir à la bergamote | Une note de bergamote, élégante et raffinée.\nINFUSION RUBIS | Rooibos & baies sauvages | Une douceur rouge, ronde et réconfortante.",
							),
						),
					),
				),
			),
		),

		/* ------------------------------------------------------------ La Maison */
		'maison'    => array(
			'kind'          => 'page',
			'title'         => 'La Maison',
			'slug'          => 'la-maison',
			'page_settings' => $page_settings,
			'widgets'       => array(
				array(
					'key'      => 'intro',
					'label'    => '01 Intro',
					'type'     => 'dpk-page-intro',
					'settings' => array(
						'eyebrow'  => 'Temps · Courtoisie · Hospitalité',
						'title'    => 'La Maison',
						'size'     => 'xl',
						'subtitle' => 'Tout est parti d’une attention.',
						'pb'       => 'l',
					),
				),
				array(
					'key'      => 'story',
					'label'    => '02 Récit',
					'type'     => 'dpk-editorial-split',
					'settings' => array(
						'image'      => '{{img:maison-principal.webp}}',
						'image_side' => 'left',
						'image_size' => 'wide',
						'ratio'      => '5/4',
						'pt'         => 'none',
						'pb'         => 'm',
						'marker'     => 'none',
						'title'      => '',
						'text_max'   => 520,
						'paragraphs' => array(
							array( 'text' => 'Douce Paresse est née d’une envie que l’on peut tous ressentir à un moment : ralentir, prendre du temps pour soi et s’accorder simplement une pause.', 'style' => 'body' ),
							array( 'text' => 'Et si, parfois, il suffisait de s’autoriser ce moment ?', 'style' => 'quote' ),
							array( 'text' => 'J’ai voulu donner une forme à cette envie et, surtout, pouvoir la partager. J’ai toujours aimé ces petits gestes qui témoignent du soin que l’on porte aux autres.', 'style' => 'body' ),
							array( 'text' => 'Je voulais une création simple, mais raffinée. De belles matières, des finitions soignées et ce souci du détail qui peut rendre un instant ordinaire un peu plus précieux.', 'style' => 'body' ),
							array( 'text' => 'C’est ainsi qu’est née Douce Paresse.', 'style' => 'closing-plain' ),
						),
					),
				),
				array(
					'key'      => 'hospitality',
					'label'    => '03 Hospitalité',
					'type'     => 'dpk-hospitality',
					'settings' => array(
						'lead'        => 'L’hôtellerie est alors devenue une évidence.',
						'paragraphs'  => array(
							array( 'text' => 'Un lieu où l’accueil se construit aussi à travers les détails laissés à l’hôte et les instants qu’il peut s’accorder au cours de son séjour.', 'style' => 'body' ),
							array( 'text' => 'Douce Paresse imagine des objets d’hospitalité qui donnent forme à cette vision et permettent aux établissements de la partager avec leurs voyageurs.', 'style' => 'body' ),
						),
						'image_small' => '{{img:maison-add.webp}}',
						'image_large' => '{{img:maison-ambiance.webp}}',
					),
				),
				array(
					'key'      => 'closing',
					'label'    => '04 Premier geste',
					'type'     => 'dpk-statement',
					'settings' => array(
						'scheme'      => 'light',
						'ornament'    => 'icon',
						'icon'        => '{{img:logo-icon-gold.png}}',
						'title'       => 'Le Coffret Luxury en est le premier geste.',
						'subtitle'    => 'Le premier chapitre d’une Maison imaginée autour de l’attention, du partage et du temps que l’on s’accorde.',
						'button_text' => 'Découvrir le coffret',
						'button_link' => $link( '{{page:coffret}}' ),
						'max_width'   => 820,
					),
				),
			),
		),

		/* ------------------------------------------------------------ Le Coffret Luxury */
		'coffret'   => array(
			'kind'          => 'page',
			'title'         => 'Le Coffret Luxury',
			'slug'          => 'le-coffret-luxury',
			'page_settings' => $page_settings,
			'widgets'       => array(
				array(
					'key'      => 'hero',
					'label'    => '01 Hero',
					'type'     => 'dpk-split-hero',
					'settings' => array(
						'scheme'      => 'light',
						'marker'      => 'above',
						'eyebrow'     => 'Le premier geste de Douce Paresse',
						'title'       => "Le Coffret\nLuxury",
						'title_size'  => 'large',
						'subtitle'    => 'Une nouvelle interprétation du rituel de courtoisie en chambre.',
						'button_text' => '',
						'image'       => '{{img:composition-cover.webp}}',
						'image_size'  => 'wide',
					),
				),
				array(
					'key'      => 'geste',
					'label'    => '02 Le geste retrouvé',
					'type'     => 'dpk-editorial-split',
					'settings' => array(
						'image'       => '{{img:coffret-hero.webp}}',
						'image_side'  => 'right',
						'image_size'  => 'equal',
						'ratio'       => '4/5',
						'image_max'   => 580,
						'align'       => 'flex-end',
						'pt'          => 'l',
						'pb'          => 's',
						'marker'      => 'numeral',
						'marker_text' => 'I',
						'title'       => 'Le geste retrouvé',
						'text_max'    => 540,
						'paragraphs'  => array(
							array( 'text' => 'Pendant longtemps, le plateau de courtoisie s’est résumé à un objet fonctionnel, un assemblage standardisé auquel on ne prête presque plus attention.', 'style' => 'body' ),
							array( 'text' => 'Nous avons choisi de lui rendre toute sa place dans l’expérience d’accueil.', 'style' => 'body' ),
							array( 'text' => 'Recevoir, c’est susciter une émotion. C’est transformer une simple arrivée en un temps suspendu. C’est faire d’un détail une attention dont on se souvient.', 'style' => 'quote' ),
							array( 'text' => 'À travers le Coffret Luxury, le moment du thé retrouve cette intention : inviter à ralentir, à prendre le temps et à savourer l’instant.', 'style' => 'body' ),
						),
					),
				),
				array(
					'key'      => 'signature',
					'label'    => '03 La signature d’un accueil',
					'type'     => 'dpk-signature',
					'settings' => array(
						'numeral' => 'II',
						'title'   => 'La signature d’un accueil',
						'image'   => '{{img:composition-inside.webp}}',
						'lead'    => 'Douce Paresse est née d’une conviction : la courtoisie française reste un art vivant, qui s’exprime dans les détails de l’accueil. Le Coffret Luxury en est la première expression.',
						'body'    => 'Pensé comme un ensemble, chaque élément participe à une même intention : des tasses en porcelaine aux cuillères délicatement gravées de la signature « l’art de ralentir », en passant par la sélection de thés, le sucre et la carte poétique.',
						'closing' => 'Autant d’attentions pensées pour sublimer les gestes les plus simples.',
						'image_2' => '{{img:coffret-add.webp}}',
					),
				),
				array(
					'key'      => 'presence',
					'label'    => '04 Une présence qui se prolonge',
					'type'     => 'dpk-editorial-split',
					'settings' => array(
						'image'       => '{{img:chambre.webp}}',
						'image_side'  => 'left',
						'image_size'  => 'wide',
						'ratio'       => '5/4',
						'pt'          => 's',
						'pb'          => 'l',
						'marker'      => 'numeral',
						'marker_text' => 'III',
						'title'       => 'Une présence qui se prolonge',
						'text_max'    => 520,
						'paragraphs'  => array(
							array( 'text' => 'Pensé pour rester dans la chambre ou la suite, le Coffret Luxury accompagne les séjours au fil du temps.', 'style' => 'body' ),
							array( 'text' => 'Il trouve naturellement sa place dans le décor tout en conservant sa fonction première : être disponible lorsqu’un hôte choisit de s’accorder quelques instants.', 'style' => 'body' ),
							array( 'text' => 'Le coffret, les tasses et les cuillères demeurent en chambre. Les consommables sont renouvelés au fil des séjours.', 'style' => 'body' ),
							array( 'text' => 'Une présence discrète, pensée pour faire partie de l’expérience.', 'style' => 'closing' ),
						),
					),
				),
				array(
					'key'      => 'maison',
					'label'    => '05 Une maison dédiée à l’art de recevoir',
					'type'     => 'dpk-statement',
					'settings' => array(
						'scheme'      => 'dark',
						'ornament'    => 'icon',
						'icon'        => '{{img:logo-icon-goldlight.png}}',
						'eyebrow'     => 'Une maison dédiée à l’art de recevoir',
						'title'       => 'L’hospitalité ne s’arrête pas à une tasse de thé.',
						'subtitle'    => '',
						'paragraphs'  => array(
							array( 'text' => 'Elle réside dans une atmosphère, un geste, un objet choisi avec soin ; dans ces détails presque silencieux qui participent au souvenir d’un lieu.', 'style' => 'body' ),
							array( 'text' => 'Avec Douce Paresse, nous imaginons l’hospitalité comme une succession d’attentions pensées pour accompagner l’hôte sans jamais s’imposer.', 'style' => 'body' ),
						),
						'closing'     => 'Le Coffret Luxury en est le premier chapitre.',
						'button_text' => '',
						'max_width'   => 860,
						'spacing'     => 'airy',
					),
				),
				array(
					'key'      => 'reasons',
					'label'    => '06 Pourquoi choisir',
					'type'     => 'dpk-reasons',
					'settings' => array(
						'title'       => 'Pourquoi choisir le Coffret Luxury ?',
						'items'       => array(
							array( 'num' => 'I', 'title' => 'Valoriser l’expérience en chambre', 'desc' => 'Offrir à vos hôtes une attention singulière qui prolonge le geste d’accueil.' ),
							array( 'num' => 'II', 'title' => 'Différencier votre accueil', 'desc' => 'Remplacer le plateau de courtoisie standardisé par un ensemble cohérent, élégant et identifiable.' ),
							array( 'num' => 'III', 'title' => 'Créer un souvenir', 'desc' => 'Transformer un moment quotidien en une parenthèse plus personnelle, pensée pour rester en mémoire.' ),
						),
						'button_text' => 'Demander l’offre professionnelle',
						'button_link' => $link( '{{page:pro}}#demande' ),
					),
				),
			),
		),

		/* ------------------------------------------------------------ Professionnels */
		'pro'       => array(
			'kind'          => 'page',
			'title'         => 'Professionnels',
			'slug'          => 'professionnels',
			'page_settings' => $page_settings,
			'widgets'       => array(
				array(
					'key'      => 'hero',
					'label'    => '01 Hero',
					'type'     => 'dpk-split-hero',
					'settings' => array(
						'scheme'      => 'dark',
						'marker'      => 'after',
						'eyebrow'     => 'Professionnels',
						'title'       => 'L’art du thé pensé pour l’hôtellerie d’exception.',
						'title_size'  => 'medium',
						'subtitle'    => 'Une attention singulière conçue pour sublimer le séjour de vos voyageurs.',
						'button_text' => 'Demander l’offre professionnelle',
						'button_link' => $link( '#demande' ),
						'image'       => '{{img:pro-hero.webp}}',
						'image_pos'   => '50% 40%',
						'image_size'  => 'equal',
					),
				),
				array(
					'key'      => 'intro',
					'label'    => '02 Introduction',
					'type'     => 'dpk-statement',
					'settings' => array(
						'scheme'      => 'light',
						'ornament'    => 'line',
						'title'       => 'Douce Paresse s’adresse aux établissements soucieux d’apporter une attention singulière à l’accueil de leurs clients.',
						'title_mode'  => 'lead',
						'subtitle'    => '',
						'paragraphs'  => array(
							array( 'text' => 'À travers le Coffret Luxury, la Maison propose une nouvelle interprétation du plateau de courtoisie en chambre. Pensé comme un ensemble, il réunit les essentiels du thé dans une présentation cohérente, élégante et prête à intégrer votre univers.', 'style' => 'body' ),
						),
						'closing'     => 'Offrez à vos hôtes une parenthèse de raffinement et une invitation à ralentir.',
						'button_text' => '',
						'max_width'   => 860,
						'spacing'     => 'airy',
					),
				),
				array(
					'key'      => 'experience',
					'label'    => '03 L’expérience en chambre',
					'type'     => 'dpk-editorial-split',
					'settings' => array(
						'image'       => '{{img:pro-experience.webp}}',
						'image_side'  => 'left',
						'image_size'  => 'wide',
						'ratio'       => '4/3',
						'pt'          => 'none',
						'pb'          => 'l',
						'marker'      => 'eyebrow',
						'marker_text' => 'L’expérience en chambre',
						'title'       => 'Une attention qui se prolonge',
						'text_max'    => 480,
						'paragraphs'  => array(
							array( 'text' => 'Présent en chambre au fil des séjours, le Coffret Luxury prolonge le geste d’accueil et accompagne les moments que l’hôte choisit de s’accorder.', 'style' => 'body-bordered' ),
						),
					),
				),
				array(
					'key'      => 'services',
					'label'    => '04 Offre professionnelle',
					'type'     => 'dpk-services',
					'settings' => array(
						'eyebrow'     => 'Offre professionnelle & sur mesure',
						'title'       => 'Nous accompagnons votre établissement de la sélection de l’offre jusqu’à son intégration en chambre.',
						'items'       => array(
							array( 'num' => 'I', 'title' => 'Logistique simplifiée', 'desc' => 'Plusieurs volumes de commande adaptés aux besoins de votre établissement.' ),
							array( 'num' => 'II', 'title' => 'Réapprovisionnement fluide', 'desc' => 'Gestion facilitée des consommables selon vos besoins.' ),
							array( 'num' => 'III', 'title' => 'Accompagnement dédié', 'desc' => 'Un interlocuteur unique pour accompagner votre maison.' ),
						),
						'image'       => '{{img:pro-offre.webp}}',
						'scroll_link' => $link( '#demande' ),
					),
				),
				array(
					'key'      => 'form',
					'label'    => '05 Demander l’offre',
					'type'     => 'dpk-pro-form',
					'settings' => array(
						'anchor'       => 'demande',
						'title'        => 'Demander l’offre professionnelle',
						'text'         => 'Les tarifs, conditions de commande et informations techniques sont détaillés dans notre offre professionnelle.',
						'privacy_text' => $privacy,
						'success_icon' => '{{img:logo-icon-goldlight.png}}',
					),
				),
			),
		),

		/* ------------------------------------------------------------ Contact */
		'contact'   => array(
			'kind'          => 'page',
			'title'         => 'Contact',
			'slug'          => 'contact',
			'page_settings' => $page_settings,
			'widgets'       => array(
				array(
					'key'      => 'intro',
					'label'    => '01 Intro',
					'type'     => 'dpk-page-intro',
					'settings' => array(
						'eyebrow'  => 'Contact',
						'title'    => 'Échangeons autour de Douce Paresse',
						'size'     => 'l',
						'subtitle' => 'Vous avez une demande, une question ou souhaitez simplement échanger ?',
						'text'     => 'Écrivez-nous, nous serons ravis de vous répondre.',
						'pb'       => 'l',
					),
				),
				array(
					'key'      => 'form',
					'label'    => '02 Formulaire',
					'type'     => 'dpk-contact',
					'settings' => array(
						'icon'         => '{{img:logo-icon-goldlight.png}}',
						'card_title'   => 'Nous contacter',
						'pro_link'     => $link( '{{page:pro}}#demande' ),
						'privacy_text' => $privacy,
					),
				),
			),
		),

		/* ------------------------------------------------------------ Mentions légales */
		'mentions'  => array(
			'kind'          => 'page',
			'title'         => 'Mentions légales',
			'slug'          => 'mentions-legales',
			'page_settings' => $page_settings,
			'widgets'       => array(
				array(
					'key'      => 'intro',
					'label'    => '01 Mentions légales',
					'type'     => 'dpk-page-intro',
					'settings' => array(
						'eyebrow'  => 'Informations légales',
						'title'    => 'Mentions légales',
						'size'     => 'm',
						'subtitle' => 'Mise à jour : 10 octobre 2026',
						'pb'       => 'm',
					),
				),
				array(
					'key'      => 'content',
					'label'    => '02 Contenu',
					'type'     => 'dpk-legal',
					'settings' => array(
						'articles' => array(
									array(
										'title' => 'Éditeur du site',
										'content' => '<p>Le site douceparesse.com, consacré à la présentation de Douce Paresse et de son offre destinée aux professionnels de l’hôtellerie, est édité par :</p>
<p>Mariam SIBY, entrepreneur individuel (EI), régime microentrepreneur, exerçant sous le nom commercial Douce Paresse.</p>',
										'rows' => 'Adresse de domiciliation | 91 rue du Faubourg Saint-Honoré, 75008 Paris, France.
SIREN | 993 752 963.
SIRET de l’établissement actif | 993 752 963 00039.
Immatriculation | RCS Paris — 993 752 963.
TVA | franchise en base, article 293 B du Code général des impôts.
E-mail | <a href="mailto:contact@douceparesse.com">contact@douceparesse.com</a>.
Téléphone | <a href="tel:+33762758638">07 62 75 86 38</a>.
Capital social | sans objet (entreprise individuelle).',
										'content_after' => '',
									),
									array(
										'title' => 'Direction de la publication',
										'content' => '<p>Directrice de la publication : Mariam SIBY.</p>',
										'rows' => '',
										'content_after' => '',
									),
									array(
										'title' => 'Hébergement',
										'content' => '',
										'rows' => 'Hébergeur | OVH SAS (OVHcloud).
Adresse | 2 rue Kellermann, 59100 Roubaix, France.
Téléphone du service client en France | 1007.',
										'content_after' => '',
									),
									array(
										'title' => 'Propriété intellectuelle',
										'content' => '<p>Les textes, photographies, visuels, logos et autres contenus présents sur ce site sont protégés par le droit de la propriété intellectuelle. Ils appartiennent à Douce Paresse ou à leurs titulaires respectifs. Sauf exceptions prévues par la loi, leur reproduction, représentation ou utilisation nécessite l’autorisation préalable du titulaire des droits.</p>',
										'rows' => '',
										'content_after' => '',
									),
									array(
										'title' => 'Données personnelles et cookies',
										'content' => '<p>Pour connaître les modalités de traitement de vos données personnelles, notamment lors de l’utilisation des formulaires du site, ainsi que les informations relatives aux cookies, consultez la page « <a href="{{page:politique}}">Politique de confidentialité</a> » accessible depuis le pied de page du site.</p>',
										'rows' => '',
										'content_after' => '',
									),
									array(
										'title' => 'Contact',
										'content' => '<p>Pour toute question concernant le site ou son contenu, vous pouvez contacter l’éditeur aux coordonnées indiquées à la rubrique « Éditeur du site ».</p>',
										'rows' => '',
										'content_after' => '',
									),
						),
						'see_text' => 'Politique de confidentialité',
						'see_link' => $link( '{{page:politique}}' ),
					),
				),
			),
		),

		/* ------------------------------------------------------------ Politique de confidentialité */
		'politique' => array(
			'kind'          => 'page',
			'title'         => 'Politique de confidentialité',
			'slug'          => 'politique-de-confidentialite',
			'page_settings' => $page_settings,
			'widgets'       => array(
				array(
					'key'      => 'intro',
					'label'    => '01 Politique de confidentialité',
					'type'     => 'dpk-page-intro',
					'settings' => array(
						'eyebrow'  => 'Informations légales',
						'title'    => 'Politique de confidentialité',
						'size'     => 'm',
						'subtitle' => 'Mise à jour : 10 octobre 2026',
						'pb'       => 'm',
					),
				),
				array(
					'key'      => 'content',
					'label'    => '02 Contenu',
					'type'     => 'dpk-legal',
					'settings' => array(
						'articles' => array(
									array(
										'title' => 'Responsable du traitement',
										'content' => '<p>Mariam SIBY, entrepreneur individuel (EI), exerçant sous le nom commercial Douce Paresse.</p>',
										'rows' => 'Adresse | 91 rue du Faubourg Saint-Honoré, 75008 Paris, France.
Contact pour les données personnelles | <a href="mailto:contact@douceparesse.com">contact@douceparesse.com</a>.',
										'content_after' => '',
									),
									array(
										'title' => 'Données traitées',
										'content' => '<p>Lorsque vous utilisez les formulaires « Contact » ou « Demander l’offre », ou nous contactez par e-mail, nous traitons les renseignements que vous communiquez pour répondre à votre demande.</p>',
										'rows' => 'Formulaire « Contact » | nom, prénom, adresse e-mail, objet et message.
Formulaire « Demander l’offre » | nom et prénom, établissement, fonction, adresse e-mail professionnelle, objet et message ; numéro de téléphone et nombre de chambres (facultatifs).',
										'content_after' => '<p>Les champs obligatoires sont signalés dans les formulaires. Sans les informations nécessaires pour vous répondre, votre demande peut ne pas être traitée. Merci de ne pas communiquer de données sensibles dans votre message.</p>',
									),
									array(
										'title' => 'Finalités et bases légales',
										'content' => '',
										'rows' => 'Demandes d’offre ou de devis | préparer une proposition et échanger sur votre projet. Lorsque vous êtes vous-même partie au contrat envisagé, ce traitement repose sur les mesures précontractuelles prises à votre demande. Pour les interlocuteurs agissant au nom d’un établissement, il repose sur notre intérêt légitime à gérer nos relations professionnelles.
Autres demandes de contact | répondre à vos questions, sur la base de notre intérêt légitime à assurer les échanges avec les visiteurs et professionnels intéressés par Douce Paresse.',
										'content_after' => '',
									),
									array(
										'title' => 'Durées de conservation',
										'content' => '<p>Les demandes de prospects et les échanges associés sont conservés pendant une durée maximale de trois ans à compter du dernier contact émanant du prospect, sauf nécessité justifiée ou obligation légale différente. Les messages sont reçus par e-mail et les soumissions des formulaires sont également enregistrées dans la base de données du site. Leur suppression est effectuée périodiquement, notamment dans la messagerie et dans WordPress.</p>
<p>Si une relation commerciale est engagée, les données nécessaires à la gestion du contrat et au respect des obligations légales peuvent être conservées selon les durées applicables. Les pièces comptables sont conservées dix ans.</p>
<p>Les sauvegardes et journaux techniques sont gérés selon les durées applicables chez l’hébergeur et les besoins de sécurité.</p>',
										'rows' => '',
										'content_after' => '',
									),
									array(
										'title' => 'Destinataires et prestataires',
										'content' => '<p>Les données sont accessibles à la responsable de Douce Paresse et aux prestataires techniques habilités, dans la limite de leurs missions.</p>
<p>L’hébergement du site est assuré par OVH SAS (OVHcloud), sur des serveurs situés en France. Les demandes envoyées via les formulaires Elementor Pro sont transmises par e-mail à <a href="mailto:contact@douceparesse.com">contact@douceparesse.com</a> et enregistrées dans la base de données WordPress hébergée chez OVHcloud. Des sauvegardes automatiques sont réalisées par l’hébergeur.</p>
<p>La messagerie professionnelle est consultée via le webmail OVHcloud. Des extensions techniques assurent l’envoi des messages, la sécurité des connexions administrateur et la mise en cache du site. Les polices sont hébergées localement. Les journaux techniques de l’hébergeur peuvent contenir des adresses IP à des fins de sécurité.</p>',
										'rows' => '',
										'content_after' => '',
									),
									array(
										'title' => 'Transferts hors de l’Espace économique européen',
										'content' => '<p>Selon la configuration technique annoncée pour le site et la messagerie OVHcloud, aucun transfert de données personnelles des visiteurs hors de l’Espace économique européen n’est prévu. Si les outils ou les modalités de traitement évoluent, notamment en cas de transfert de messages vers un autre fournisseur de messagerie, cette rubrique sera mise à jour et les garanties applicables seront précisées.</p>',
										'rows' => '',
										'content_after' => '',
									),
									array(
										'title' => 'Vos droits',
										'content' => '<p>Selon le traitement concerné et les conditions prévues par le RGPD, vous pouvez demander l’accès à vos données, leur rectification, leur effacement ou la limitation de leur utilisation. Vous pouvez vous opposer aux traitements fondés sur l’intérêt légitime. La portabilité s’applique dans les cas prévus par le RGPD. Vous pouvez retirer votre consentement à tout moment lorsqu’un traitement repose sur celui-ci, sans remettre en cause les traitements antérieurs.</p>
<p>Pour exercer vos droits : <a href="mailto:contact@douceparesse.com">contact@douceparesse.com</a>. Nous répondons en principe sous un mois ; ce délai peut être prolongé de deux mois si nécessaire, en vous en informant. Vous pouvez introduire une réclamation auprès de la <a href="https://www.cnil.fr" target="_blank" rel="noopener">CNIL</a>.</p>',
										'rows' => '',
										'content_after' => '',
									),
									array(
										'title' => 'Cookies et autres traceurs',
										'content' => '<p>Selon la configuration prévue, le site n’utilise aucun cookie de mesure d’audience ni de publicité, et ne recourt pas à des services tiers de suivi des visiteurs. Seuls des cookies techniques strictement nécessaires peuvent être utilisés, notamment pour l’administration et l’authentification WordPress.</p>
<p>Les fonctionnalités de fenêtres contextuelles peuvent utiliser un stockage local strictement nécessaire à leur fonctionnement, sans finalité de suivi publicitaire. Aucun traceur nécessitant un consentement préalable n’est prévu dans cette configuration.</p>',
										'rows' => '',
										'content_after' => '',
									),
									array(
										'title' => 'Mise à jour de la politique',
										'content' => '<p>Cette politique peut être actualisée pour refléter l’évolution du site et de ses traitements de données.</p>',
										'rows' => '',
										'content_after' => '',
									),
						),
						'see_text' => 'Mentions légales',
						'see_link' => $link( '{{page:mentions}}' ),
					),
				),
			),
		),
	),
);
