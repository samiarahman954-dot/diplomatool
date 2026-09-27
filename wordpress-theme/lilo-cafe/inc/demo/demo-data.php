<?php
/**
 * Demo content: the single source for widget defaults and the one-click import.
 *
 * Image values carry import hints:
 *  - 'lilo_asset'  => file inside assets/images (bundled with the theme)
 *  - 'lilo_remote' => photo downloaded during import (falls back to 'lilo_asset')
 * URL values may use {home} and {menu}, which resolve to the site and Menu page.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bundled image.
 */
function lilo_demo_img( $file ) {
	return array(
		'url'        => lilo_asset( $file ),
		'id'         => '',
		'lilo_asset' => $file,
	);
}

/**
 * Stock photo (Unsplash) with a bundled fallback.
 */
function lilo_demo_photo( $photo_id, $fallback, $width = 1200 ) {
	$url = 'https://images.unsplash.com/' . $photo_id . '?auto=format&fit=crop&w=' . (int) $width . '&q=75';
	return array(
		'url'         => $url,
		'id'          => '',
		'lilo_remote' => $url,
		'lilo_asset'  => $fallback,
	);
}

/**
 * URL control value.
 */
function lilo_demo_link( $url, $external = false ) {
	return array(
		'url'         => $url,
		'is_external' => $external ? 'on' : '',
		'nofollow'    => '',
	);
}

/**
 * Commonly used photos.
 */
function lilo_demo_photos() {
	return array(
		'hero'     => lilo_demo_photo( 'photo-1708461646041-a606ea4b07c4', 'demo/photo-hero.jpg', 1400 ),
		'latte'    => lilo_demo_photo( 'photo-1461023058943-07fcbe16d735', 'demo/photo-latte.jpg' ),
		'founders' => lilo_demo_photo( 'photo-1752756992329-961db6366376', 'demo/photo-founders.jpg' ),
		'coffee'   => lilo_demo_photo( 'photo-1564327367919-cb377ea6a88f', 'demo/photo-coffee.jpg', 900 ),
		'crepes'   => lilo_demo_photo( 'photo-1565087170449-fa23854a6100', 'demo/photo-crepes.jpg', 900 ),
		'aguas'    => lilo_demo_photo( 'photo-1589985902809-39d25db22101', 'demo/photo-aguas.jpg', 900 ),
	);
}

/**
 * Opening hours used by the hero status and the Visit section.
 */
function lilo_demo_hours() {
	return array(
		'hours_sun'          => '9am-3pm',
		'hours_mon'          => '',
		'hours_tue'          => '8:30am-6:30pm',
		'hours_wed'          => '8:30am-6:30pm',
		'hours_thu'          => '8:30am-6:30pm',
		'hours_fri'          => '8:30am-6:30pm',
		'hours_sat'          => '8:30am-6:30pm',
		'status_open_text'   => 'Open now · Today {hours}',
		'status_later_text'  => 'Today {hours}',
		'status_closed_text' => 'Closed {day}s · Open {next_day} {next_open}',
	);
}

/**
 * The full cafe menu, one entry per menu section.
 */
function lilo_demo_menu() {
	$p = lilo_demo_photos();
	$i = function ( $name, $desc, $price = '' ) {
		return array(
			'name'  => $name,
			'desc'  => $desc,
			'price' => $price,
		);
	};
	$ph = function ( $name, $desc, $file ) {
		return array(
			'name'  => $name,
			'desc'  => $desc,
			'image' => lilo_demo_img( 'demo/' . $file . '.png' ),
		);
	};
	$g = function ( $name, $price, $options ) {
		return array(
			'name'    => $name,
			'price'   => $price,
			'options' => implode( "\n", $options ),
		);
	};

	return array(
		array(
			'widget' => 'lilo-menu-list',
			'anchor' => 'lattes',
			'tab'    => 'Signature Lattes',
			'script' => 'signature',
			'title'  => 'Lattes',
			'note'   => 'Hot 12 / 16 oz · Iced 16 / 20 oz',
			'image'  => $p['latte'],
			'foot'   => 'Cold foam only on iced drinks.',
			'items'  => array(
				$i( 'Golden Eagle', 'banana latte with banana cold foam', '6.00 / 6.50' ),
				$i( 'Maple Spanish Latte', 'maple, sweetened condensed milk, and salted maple cold foam', '6.00 / 6.50' ),
				$i( 'Bogey Bliss', 'blueberry latte with vanilla cold foam', '6.00 / 6.50' ),
				$i( 'Two Putt', 'vanilla and caramel latte', '5.50 / 6.50' ),
				$i( 'Horchata Latte', 'homemade horchata with your choice of espresso or matcha', '5.50 / 6.50' ),
			),
		),
		array(
			'widget' => 'lilo-menu-list',
			'anchor' => 'coffee',
			'tab'    => 'Coffee & Tea',
			'script' => '',
			'title'  => 'Coffee & Tea',
			'note'   => 'Hot 12 / 16 oz · Iced 16 / 20 oz',
			'image'  => $p['coffee'],
			'foot'   => '',
			'items'  => array(
				$i( 'Espresso Latte', 'espresso with your choice of milk' ),
				$i( 'Cortado', 'espresso with a little steamed milk' ),
				$i( 'Americano', 'espresso and water' ),
				$i( 'Macchiato', 'espresso with a touch of milk' ),
				$i( 'Chai Tea Latte', 'spiced chai with your choice of milk', '5.00 / 5.50' ),
				$i( 'Matcha Latte', 'matcha with your choice of milk' ),
				$i( 'Herbal Tea', 'hot or iced · ask about our current selections', '3.00 / 3.50' ),
			),
		),
		array(
			'widget' => 'lilo-menu-list',
			'anchor' => 'aguas',
			'tab'    => 'Aguas Frescas',
			'script' => 'refreshers',
			'title'  => 'Aguas Frescas',
			'note'   => 'Iced 16 / 20 oz',
			'image'  => null,
			'foot'   => '',
			'items'  => array(
				$i( 'Horchata', 'homemade rice, cinnamon, vanilla, and milk drink', '6.00 / 6.50' ),
				$i( 'Jamaica', 'homemade hibiscus refresher', '5.50 / 6.00' ),
			),
		),
		array(
			'widget' => 'lilo-menu-options',
			'anchor' => 'custom',
			'tab'    => 'Make it yours',
			'script' => '',
			'title'  => 'Make it yours',
			'note'   => '',
			'foot'   => '',
			'items'  => array(
				$g( 'Milk', '', array( 'Whole', 'Oat', 'Almond', 'Half-and-half' ) ),
				$g( 'Cold foams', '+$1', array( 'Salted Maple', 'Vanilla', 'Banana' ) ),
				$g( 'Homemade syrups', '+$1', array( 'Banana', 'Blueberry', 'Vanilla', 'Caramel', 'White chocolate', 'Chocolate Abuelita' ) ),
				$g( 'Simple syrups', '+$0.50', array( 'Honey', 'Maple' ) ),
				$g( 'Sugar', '', array( 'Regular', 'Splenda', 'Stevia' ) ),
			),
		),
		array(
			'widget' => 'lilo-menu-list',
			'anchor' => 'sweet',
			'tab'    => 'Sweet Crepes',
			'script' => 'crepes',
			'title'  => 'Sweet',
			'note'   => 'Regular · Mini',
			'image'  => $p['crepes'],
			'foot'   => '',
			'items'  => array(
				$i( 'Strawberry Banana with Chocolate', 'strawberries, banana, and chocolate', '9.50 · 7.50' ),
				$i( 'Strawberry and Chocolate', 'strawberries and chocolate', '9 · 7' ),
				$i( 'Banana and Chocolate', 'banana and chocolate', '9 · 7' ),
				$i( 'Peanut Butter Banana Crunch', 'peanut butter, banana, and crunch', '9.25 · 7.25' ),
				$i( 'Chocolate Crepe', 'warm crepe with chocolate', '7 · 5' ),
			),
		),
		array(
			'widget' => 'lilo-menu-list',
			'anchor' => 'savory',
			'tab'    => 'Savory Crepes',
			'script' => 'crepes',
			'title'  => 'Savory',
			'note'   => '',
			'image'  => null,
			'foot'   => 'Sourdough bread substitute: make any crepe mini. Chocolate crepe mini 4.50.',
			'items'  => array(
				$i( 'Turkey Avocado', 'turkey, avocado, cheddar, spinach, and spicy mayo', '10' ),
				$i( 'Tomato Caprese', 'cherry tomatoes, mozzarella, basil, olive oil, and balsamic', '9.25' ),
				$i( 'Bacon, Egg and Cheese', 'bacon, scrambled egg, and cheddar', '9.25' ),
			),
		),
		array(
			'widget' => 'lilo-menu-options',
			'anchor' => 'addons',
			'tab'    => 'Crepe Add-ons',
			'script' => '',
			'title'  => 'Crepe add-ons',
			'note'   => '',
			'foot'   => '',
			'items'  => array(
				$g( 'Toppings', '+$0.50', array( 'Pecans', 'Almonds', 'Sliced Strawberry', 'Sliced Banana', 'Granola', 'Spinach', 'Cheese' ) ),
				$g( 'Toppings', '+$1.25', array( 'Turkey', 'Bacon', 'Avocado', 'Egg' ) ),
				$g( 'Spreads', '+$1', array( 'Peanut butter', 'Hazelnut Chocolate', 'Honey', 'Chipotle Mayo' ) ),
			),
		),
		array(
			'widget' => 'lilo-menu-list',
			'anchor' => 'extra',
			'tab'    => 'Something Extra',
			'script' => '',
			'title'  => 'Something extra',
			'note'   => '',
			'image'  => null,
			'foot'   => '',
			'items'  => array(
				$i( 'Corn in a Cup', 'fresh corn, mayo, and cheese', 'S 4.50 · M 5' ),
				$i( 'Strawberries with Chocolate', '16 oz', '7' ),
				$i( 'Fruit Cup', 'pineapple, mango, and strawberries · 12 oz', '4' ),
				$i( 'Mini Pancakes', 'ten mini pancakes with your choice of sauces and toppings', '8' ),
			),
		),
		array(
			'widget' => 'lilo-menu-photos',
			'anchor' => 'ice',
			'tab'    => 'Shaved Ice',
			'script' => 'cheesecakes, coladas & spicy',
			'title'  => 'Shaved Ice',
			'note'   => '',
			'foot'   => '',
			'items'  => array(
				$ph( 'Cheesecake Shaved Ice', 'your choice of berry, mango, strawberry, guava, Oreo, or pistachio with sweet cream', 'p-berrycheese' ),
				$ph( 'Fruit Coladas', 'your choice of berry, mango, strawberry, guava, pineapple, or walnut with sweet cream', 'p-mangocolado' ),
				$ph( 'Mangonada', 'mango, fresh mango, and spicy chamoy', 'p-mangonada' ),
				$ph( 'Piñolada', 'pineapple, fresh pineapple, and spicy chamoy', 'p-pinolada' ),
				$ph( 'Diablito', 'tamarind, spicy chamoy, and tamarind candy', 'p-diablito' ),
				$ph( 'Volcano', 'mango, pineapple, tamarind, and spicy chamoy', 'p-volcano' ),
			),
		),
		array(
			'widget' => 'lilo-menu-photos',
			'anchor' => 'raspados',
			'tab'    => 'Raspados',
			'script' => 'gourmet shaved ice',
			'title'  => 'Raspados',
			'note'   => '',
			'foot'   => '',
			'items'  => array(
				$ph( 'Oreo Deluxe', 'Oreo-inspired shaved ice', 'p-oreo' ),
				$ph( 'Coffee Celeste', 'coffee-flavored shaved ice', 'p-coffee' ),
				$ph( 'Berry Berry Good', 'berry-flavored shaved ice', 'p-berry' ),
				$ph( 'Strawberries on Ice with Cream', 'strawberries, shaved ice, and cream', 'p-strawice' ),
				$ph( 'Strawberries and Cream', 'a creamy strawberry treat', 'p-strawcream' ),
				$ph( 'Creamy Walnut', 'walnut-flavored shaved ice', 'p-walnut' ),
				$ph( 'Pistachio', 'pistachio-flavored shaved ice', 'p-pistachio' ),
				$ph( 'Guava', 'guava-flavored shaved ice', 'p-guava' ),
				$ph( 'Peaches and Cream', 'peach flavor and cream', 'p-peach' ),
				$ph( 'Banderita', 'kiwi, coconut cream, and strawberry purée', 'p-banderita' ),
			),
		),
		array(
			'widget' => 'lilo-menu-photos',
			'anchor' => 'specialty',
			'tab'    => 'Specialty Raspados',
			'script' => 'specialty',
			'title'  => 'Raspados',
			'note'   => '',
			'foot'   => '',
			'items'  => array(
				$ph( 'Creamy Mango', 'mango purée, fresh mango, sweet cream, and eggnog', 'p-cmango' ),
				$ph( 'Sunrise', 'strawberry, mango, sweet cream, and eggnog', 'p-sunrise' ),
				$ph( 'Gloria', 'banana, strawberry, sweet cream, cinnamon, vanilla, and eggnog', 'p-gloria' ),
				$ph( 'Georgia Peach', 'strawberry, sweet cream, and peach flavor', 'p-gpeach' ),
			),
		),
	);
}

/**
 * Settings for a menu section widget from a lilo_demo_menu() entry.
 */
function lilo_demo_menu_section_settings( $sec ) {
	$s = array(
		'anchor_id' => $sec['anchor'],
		'tab_label' => $sec['tab'],
		'script'    => $sec['script'],
		'title'     => $sec['title'],
		'note'      => $sec['note'],
		'footnote'  => $sec['foot'],
		'items'     => $sec['items'],
	);
	if ( 'lilo-menu-list' === $sec['widget'] ) {
		$s['show_image'] = $sec['image'] ? 'yes' : '';
		$s['image']      = $sec['image'] ? $sec['image'] : array(
			'url' => '',
			'id'  => '',
		);
	}
	return $s;
}

/**
 * Default settings for every widget. These double as the demo content.
 *
 * @param string $widget Widget name.
 */
function lilo_demo_defaults( $widget ) {
	static $cache = array();
	if ( isset( $cache[ $widget ] ) ) {
		return $cache[ $widget ];
	}
	$p    = lilo_demo_photos();
	$menu = lilo_demo_menu();
	$pick = function ( $names ) use ( $menu ) {
		$out = array();
		foreach ( $names as $name ) {
			foreach ( $menu as $sec ) {
				if ( 'lilo-menu-photos' !== $sec['widget'] ) {
					continue;
				}
				foreach ( $sec['items'] as $item ) {
					if ( $item['name'] === $name ) {
						$out[] = $item;
						continue 3;
					}
				}
			}
		}
		return $out;
	};
	$hib = lilo_demo_img( 'hibiscus.png' );

	$all = array(
		'lilo-header'          => array(
			'logo'        => lilo_demo_img( 'lilo-logo.png' ),
			'logo_link'   => lilo_demo_link( '{home}/' ),
			'source'      => 'links',
			'menu'        => '',
			'links'       => array(
				array( 'text' => 'Home', 'link' => lilo_demo_link( '{home}/' ) ),
				array( 'text' => 'Menu', 'link' => lilo_demo_link( '{menu}' ) ),
				array( 'text' => 'About', 'link' => lilo_demo_link( '{home}/#about' ) ),
			),
			'cta_text'    => 'Order online',
			'cta_link'    => lilo_demo_link( '#' ),
			'hamburger'   => 'yes',
		),
		'lilo-footer'          => array(
			'logo'      => lilo_demo_img( 'lilo-logo.png' ),
			'columns'   => array(
				array( 'title' => 'Visit', 'text' => "684 West Church Street<br>Jasper, GA 30143", 'links' => '' ),
				array( 'title' => 'Hours', 'text' => 'Tue–Sat 8:30am–6:30pm<br>Sun 9am–3pm · Mon closed', 'links' => '' ),
				array(
					'title' => 'Follow',
					'text'  => "<a href=\"https://www.instagram.com/lilocafeco\" target=\"_blank\" rel=\"noopener\">Instagram</a>\n<a href=\"https://www.tiktok.com/@lilocafe_\" target=\"_blank\" rel=\"noopener\">TikTok</a>\n<a href=\"https://www.facebook.com/Lilocafeco\" target=\"_blank\" rel=\"noopener\">Facebook</a>",
					'links' => 'yes',
				),
			),
			'copyright' => '© {year} Lilo Cafe LLC · lilocafé.com',
		),
		'lilo-hero'            => array_merge(
			array(
				'layout'          => 'split',
				'eyebrow_icon'    => $hib,
				'eyebrow_text'    => 'Jasper, Georgia',
				'title_before'    => 'Life’s',
				'title_highlight' => 'sweetest',
				'title_after'     => 'moments, shared.',
				'description'     => 'Fresh crepes, signature lattes, matcha, tea and gourmet raspados on West Church Street. Pull up a chair and stay a while.',
				'btn1_text'       => 'See the menu',
				'btn1_link'       => lilo_demo_link( '{menu}' ),
				'btn2_text'       => 'Get directions',
				'btn2_link'       => lilo_demo_link( 'https://www.google.com/maps/search/?api=1&query=684+West+Church+Street+Jasper+GA+30143', true ),
				'show_status'     => 'yes',
				'image'           => $p['hero'],
				'show_badge'      => 'yes',
				'badge_image'     => lilo_demo_img( 'demo/p-mangonada.png' ),
				'show_flower'     => 'yes',
				'flower_image'    => $hib,
				'card_position'   => 'right',
			),
			lilo_demo_hours()
		),
		'lilo-menu-categories' => array(
			'script'     => 'what we make',
			'title'      => 'On the menu',
			'link_text'  => 'Full menu →',
			'link'       => lilo_demo_link( '{menu}' ),
			'categories' => array(
				array( 'image' => $p['coffee'], 'title' => 'Signature lattes & coffee', 'desc' => 'Golden Eagle, Maple Spanish, Bogey Bliss and more, plus matcha and chai.', 'link' => lilo_demo_link( '{menu}#lattes' ) ),
				array( 'image' => $p['crepes'], 'title' => 'Sweet & savory crepes', 'desc' => 'Strawberry banana with chocolate to turkey avocado, in regular or mini.', 'link' => lilo_demo_link( '{menu}#sweet' ) ),
				array( 'image' => lilo_demo_img( 'demo/p-sunrise.png' ), 'title' => 'Shaved ice & raspados', 'desc' => 'Cheesecakes, coladas, and spicy mangonadas with chamoy and Tajín.', 'link' => lilo_demo_link( '{menu}#ice' ) ),
				array( 'image' => $p['aguas'], 'title' => 'Aguas frescas', 'desc' => 'Homemade horchata and Jamaica, a hibiscus refresher.', 'link' => lilo_demo_link( '{menu}#aguas' ) ),
			),
		),
		'lilo-price-list'      => array(
			'anchor_id'      => '',
			'script'         => 'signature',
			'title'          => 'Lattes',
			'note'           => 'Hot 12 / 16 oz · Iced 16 / 20 oz',
			'items'          => $menu[0]['items'],
			'footnote'       => 'Add a cold foam for $1: salted maple, vanilla or banana. Cold foam on iced drinks only.',
			'image'          => $p['latte'],
			'image_position' => 'right',
		),
		'lilo-product-showcase' => array(
			'anchor_id'   => '',
			'script'      => 'gourmet shaved ice',
			'title'       => 'Raspados & coladas',
			'description' => 'Topped with condensed milk and homemade sweet cream, or go spicy with chamoy and Tajín.',
			'items'       => array_map(
				function ( $item ) {
					$short = array(
						'Cheesecake Shaved Ice' => 'your choice of flavor with sweet cream',
					);
					if ( isset( $short[ $item['name'] ] ) ) {
						$item['desc'] = $short[ $item['name'] ];
					}
					return $item;
				},
				$pick( array( 'Mangonada', 'Volcano', 'Cheesecake Shaved Ice', 'Oreo Deluxe', 'Coffee Celeste', 'Banderita', 'Creamy Mango', 'Gloria', 'Georgia Peach', 'Pistachio' ) )
			),
			'btn_text'    => 'All shaved ice',
			'btn_link'    => lilo_demo_link( '{menu}#ice' ),
		),
		'lilo-story'           => array(
			'anchor_id'      => 'about',
			'image'          => $p['founders'],
			'image_position' => 'left',
			'script'         => 'our story',
			'title'          => 'A dream, a love for coffee, and a place for community.',
			'content'        => '<p>Founder Yarumi Jimenez had long dreamed of owning her own business. After earning her Bachelor of Business Administration in Accounting from Kennesaw State University, she continued exploring entrepreneurship while gaining inspiration and guidance from experienced local business owners, including the owners of El Roble in Jasper, Georgia.</p>'
				. '<p>Coffee shops had always been a familiar part of Yarumi’s routine, and she became a regular at a local cafe in Jasper. When the owners eventually decided to close the location, Yarumi saw an opportunity. After speaking with them and learning more about the business, she decided it was time to turn a longtime dream into reality.</p>'
				. '<p>Along the way, Yarumi partnered with Xavier Mennefield, whom she met through mutual friends and organizations during their time at Kennesaw State University. Xavier also earned his degree in Accounting from Kennesaw State, giving the two partners a shared foundation in business and finance while bringing different strengths to the cafe.</p>'
				. '<p>Together, their goal is to build Lilo Cafe into more than just a place to grab coffee. They want it to become a community-centered space where people can meet, work, relax, and feel at home. And this is only the beginning: looking toward the future, Yarumi and Xavier hope to eventually open another location near the Kennesaw community where their own journey began.</p>',
			'people'         => array(
				array( 'name' => 'Yarumi Jimenez', 'role' => 'Founder · leads day-to-day, in-store operations' ),
				array( 'name' => 'Xavier Mennefield', 'role' => 'Partner · website, social, digital and finance' ),
			),
			'show_note'      => 'yes',
			'note_icon'      => $hib,
			'note_text'      => 'And the name? Lilo is Yarumi’s cat.',
		),
		'lilo-mission'         => array(
			'anchor_id'  => '',
			'icon'       => $hib,
			'quote'      => '“At Lilo Cafe, we believe some of life’s sweetest moments are the ones shared with others.”',
			'label'      => 'Our mission',
			'content'    => '<p>Whether you’re enjoying a freshly made crepe, sipping on coffee or matcha, relaxing with a cup of tea, or cooling down with shaved ice, our mission is to make every visit feel special.</p>'
				. '<p>Lilo Cafe is more than a place to eat and drink. We strive to create a warm, welcoming space filled with good energy, meaningful connections, and a strong sense of community, somewhere everyone can feel comfortable, spend time together, and create memories.</p>',
			'sub_script' => 'our why',
			'sub_text'   => 'To bring people together through good food, refreshing drinks, and the joy of enjoying life’s little moments.',
		),
		'lilo-visit'           => array(
			'anchor_id'     => 'visit',
			'script'        => 'come visit',
			'title'         => 'Find us in Jasper',
			'address_label' => 'Address',
			'address'       => "684 West Church Street\nJasper, GA 30143",
			'map_link_text' => 'Open in Maps →',
			'map_link'      => lilo_demo_link( 'https://www.google.com/maps/search/?api=1&query=684+West+Church+Street+Jasper+GA+30143', true ),
			'hours_label'   => 'Hours',
			'hours'         => array(
				array( 'days' => 'Tuesday – Saturday', 'time' => '8:30am – 6:30pm' ),
				array( 'days' => 'Sunday', 'time' => '9am – 3pm' ),
				array( 'days' => 'Monday', 'time' => 'Closed' ),
			),
			'map_type'      => 'embed',
			'map_query'     => '684 West Church Street, Jasper, GA 30143',
			'map_zoom'      => array( 'unit' => 'px', 'size' => 16 ),
			'map_image'     => array( 'url' => '', 'id' => '' ),
		),
		'lilo-tags'            => array(
			'anchor_id' => '',
			'script'    => 'coming soon',
			'title'     => 'Lilo merch',
			'tags'      => array(
				array( 'text' => 'Tote bags with golf print' ),
				array( 'text' => 'T-shirts' ),
				array( 'text' => 'Hibiscus flower visors' ),
				array( 'text' => 'Hibiscus flower caps' ),
				array( 'text' => 'Coffee mugs' ),
			),
		),
		'lilo-social'          => array(
			'anchor_id' => '',
			'script'    => 'follow along',
			'title'     => '@lilocafeco',
			'buttons'   => array(
				array( 'text' => 'Instagram', 'link' => lilo_demo_link( 'https://www.instagram.com/lilocafeco', true ) ),
				array( 'text' => 'TikTok', 'link' => lilo_demo_link( 'https://www.tiktok.com/@lilocafe_', true ) ),
				array( 'text' => 'Facebook', 'link' => lilo_demo_link( 'https://www.facebook.com/Lilocafeco', true ) ),
			),
			'images'    => array(
				array( 'image' => $p['crepes'], 'alt' => 'Crepe', 'link' => lilo_demo_link( 'https://www.instagram.com/lilocafeco', true ) ),
				array( 'image' => lilo_demo_img( 'demo/p-sunrise.png' ), 'alt' => 'Sunrise raspado', 'link' => lilo_demo_link( 'https://www.instagram.com/lilocafeco', true ) ),
				array( 'image' => $p['coffee'], 'alt' => 'Iced coffee', 'link' => lilo_demo_link( 'https://www.instagram.com/lilocafeco', true ) ),
				array( 'image' => lilo_demo_img( 'demo/p-oreo.png' ), 'alt' => 'Oreo Deluxe raspado', 'link' => lilo_demo_link( 'https://www.instagram.com/lilocafeco', true ) ),
			),
		),
		'lilo-page-header'     => array(
			'anchor_id'   => '',
			'icon'        => $hib,
			'script'      => 'our',
			'title'       => 'Menu',
			'description' => 'Coffee, tea, crepes and shaved ice, made with lots of love for you to enjoy.',
			'images'      => array(
				array( 'image' => $p['latte'], 'alt' => 'Iced latte' ),
				array( 'image' => $p['crepes'], 'alt' => 'Crepe' ),
				array( 'image' => lilo_demo_img( 'demo/p-sunrise.png' ), 'alt' => 'Sunrise raspado' ),
			),
		),
		'lilo-menu-tabs'       => array(
			'source' => 'manual',
			'tabs'   => array_map(
				function ( $sec ) {
					return array(
						'text'   => $sec['tab'],
						'anchor' => $sec['anchor'],
					);
				},
				$menu
			),
		),
		'lilo-menu-list'       => lilo_demo_menu_section_settings( $menu[0] ),
		'lilo-menu-options'    => lilo_demo_menu_section_settings( $menu[3] ),
		'lilo-menu-photos'     => lilo_demo_menu_section_settings( $menu[8] ),
		'lilo-note'            => array(
			'text' => 'Raspados may contain dairy and tree nuts.',
		),
	);

	foreach ( $all as $name => $settings ) {
		$cache[ $name ] = $settings;
	}
	return isset( $cache[ $widget ] ) ? $cache[ $widget ] : array();
}

/**
 * Page layouts for the demo import: containers of widgets.
 *
 * Each container: [ 'settings' => container settings, 'widgets' => [ [ name, settings ] ] ].
 */
function lilo_demo_pages() {
	$w = function ( $name, $overrides = array() ) {
		return array( $name, array_merge( lilo_demo_defaults( $name ), $overrides ) );
	};

	$home = array();
	foreach ( array( 'lilo-hero', 'lilo-menu-categories', 'lilo-price-list', 'lilo-product-showcase', 'lilo-story', 'lilo-mission', 'lilo-visit', 'lilo-tags', 'lilo-social' ) as $name ) {
		$home[] = array(
			'settings' => array(),
			'widgets'  => array( $w( $name ) ),
		);
	}

	$menu_widgets = array( $w( 'lilo-menu-tabs' ) );
	foreach ( lilo_demo_menu() as $sec ) {
		$menu_widgets[] = array( $sec['widget'], lilo_demo_menu_section_settings( $sec ) );
	}
	$menu_widgets[] = $w( 'lilo-note' );

	return array(
		'home' => array(
			'title'      => 'Home',
			'slug'       => 'home',
			'containers' => $home,
		),
		'menu' => array(
			'title'      => 'Menu',
			'slug'       => 'menu',
			'containers' => array(
				array(
					'settings' => array(),
					'widgets'  => array( $w( 'lilo-page-header' ) ),
				),
				array(
					'settings' => array(
						'padding' => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '96', 'left' => '0', 'isLinked' => false ),
					),
					'widgets'  => $menu_widgets,
				),
			),
		),
	);
}
