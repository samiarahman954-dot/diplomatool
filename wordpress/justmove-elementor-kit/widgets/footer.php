<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Repeater;

class JMK_Widget_Footer extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-footer';
	}

	public function get_title() {
		return __( 'JM Footer', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-footer';
	}

	private static function links( array $pairs ) {
		$out = array();
		foreach ( $pairs as $label => $url ) {
			$out[] = array(
				'label' => $label,
				'link'  => array( 'url' => $url ),
			);
		}
		return $out;
	}

	protected function register_controls() {
		$this->start_controls_section( 'brand', array( 'label' => __( 'Logo & business info', 'jmk' ) ) );
		JMK_Widget_Header::add_logo_controls( $this );
		$info = array(
			'biz_name'  => array( __( 'Business name', 'jmk' ), 'Just Move DFW' ),
			'city'      => array( __( 'City', 'jmk' ), 'Grand Prairie' ),
			'region'    => array( __( 'State', 'jmk' ), 'TX' ),
			'zip'       => array( __( 'ZIP', 'jmk' ), '75054' ),
			'phone'     => array( __( 'Phone (display)', 'jmk' ), '(972) 638-7479' ),
			'phone_e164' => array( __( 'Phone (dialable, e.g. +19726387479)', 'jmk' ), '+19726387479' ),
			'email'     => array( __( 'Email', 'jmk' ), 'justmovedfw@gmail.com' ),
			'tagline'   => array( __( 'Extra line', 'jmk' ), 'Licensed & Insured' ),
		);
		foreach ( $info as $key => $f ) {
			$this->add_control(
				$key,
				array(
					'label'       => $f[0],
					'type'        => Controls_Manager::TEXT,
					'default'     => $f[1],
					'label_block' => true,
				)
			);
		}
		$this->end_controls_section();

		$columns = array(
			1 => array(
				'Services',
				array(
					'Local & Residential Moving' => '/local-moving/',
					'Long-Distance Moving'       => '/long-distance-moving/',
					'Commercial & Office Moving' => '/commercial-moving/',
					'Packing & Supplies'         => '/packing-services/',
				),
			),
			2 => array(
				'Service areas',
				array(
					'Grand Prairie movers' => '/movers-grand-prairie/',
					'Arlington movers'     => '/movers-arlington/',
					'Irving movers'        => '/movers-irving/',
					'Dallas movers'        => '/movers-dallas/',
					'Fort Worth movers'    => '/movers-fort-worth/',
					'Mansfield movers'     => '/movers-mansfield/',
					'All service areas ➤'  => '/areas/',
				),
			),
			3 => array(
				'Company',
				array(
					'About us'              => '/about/',
					'Get a quote'           => '/quote/',
					'All service areas'     => '/areas/',
					'Call (972) 638-7479'   => 'tel:+19726387479',
				),
			),
		);

		foreach ( $columns as $n => $col ) {
			/* translators: %d: column number */
			$this->start_controls_section( 'col' . $n, array( 'label' => sprintf( __( 'Link column %d', 'jmk' ), $n ) ) );
			$this->add_control(
				'col' . $n . '_title',
				array(
					'label'   => __( 'Heading', 'jmk' ),
					'type'    => Controls_Manager::TEXT,
					'default' => $col[0],
				)
			);
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
					'label' => __( 'Link', 'jmk' ),
					'type'  => Controls_Manager::URL,
				)
			);
			$this->add_control(
				'col' . $n . '_links',
				array(
					'label'       => __( 'Links', 'jmk' ),
					'type'        => Controls_Manager::REPEATER,
					'fields'      => $rep->get_controls(),
					'title_field' => '{{{ label }}}',
					'default'     => self::links( $col[1] ),
				)
			);
			$this->end_controls_section();
		}

		$this->start_controls_section( 'bottom', array( 'label' => __( 'Bottom bar & schema', 'jmk' ) ) );
		$this->add_control(
			'copyright',
			array(
				'label'       => __( 'Copyright ({year} = current year)', 'jmk' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '© {year} Just Move DFW. Licensed & insured DFW movers.',
				'label_block' => true,
			)
		);
		$this->add_control(
			'bottom_right',
			array(
				'label'   => __( 'Right text', 'jmk' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 'justmovedfw.com',
			)
		);
		$this->add_control(
			'schema',
			array(
				'label'       => __( 'Output MovingCompany schema (JSON-LD)', 'jmk' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'separator'   => 'before',
				'description' => __( 'Local-business structured data built from the info above.', 'jmk' ),
			)
		);
		$this->add_control(
			'rating_value',
			array(
				'label'     => __( 'Rating value', 'jmk' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => '4.7',
				'condition' => array( 'schema' => 'yes' ),
			)
		);
		$this->add_control(
			'rating_count',
			array(
				'label'     => __( 'Review count', 'jmk' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => '218',
				'condition' => array( 'schema' => 'yes' ),
			)
		);
		$this->add_control(
			'areas_served',
			array(
				'label'     => __( 'Areas served (comma separated)', 'jmk' ),
				'type'      => Controls_Manager::TEXTAREA,
				'default'   => implode( ', ', self::CITIES ),
				'condition' => array( 'schema' => 'yes' ),
			)
		);
		$this->add_control(
			'hours',
			array(
				'label'     => __( 'Opening hours (daily, HH:MM-HH:MM)', 'jmk' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => '07:00-19:00',
				'condition' => array( 'schema' => 'yes' ),
			)
		);
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<footer class="jmk jmk-footer"><div class="jmk-sec"><div class="jmk-wrap">
			<div class="jmk-foot-grid">
				<div>
					<?php $this->render_link( 'logo', $s['logo_link'], 'jmk-logo', JMK_Widget_Header::logo_html( $s ) ); ?>
					<p class="jmk-foot-nap"><b><?php echo esc_html( $s['biz_name'] ); ?></b><br>
						<?php echo esc_html( trim( $s['city'] . ', ' . $s['region'] . ' ' . $s['zip'], ', ' ) ); ?><br>
						<?php if ( '' !== $s['phone'] ) : ?>
							<a href="<?php echo esc_url( 'tel:' . $s['phone_e164'] ); ?>"><?php echo esc_html( $s['phone'] ); ?></a>
						<?php endif; ?>
						<?php if ( '' !== $s['phone'] && '' !== $s['email'] ) : ?> · <?php endif; ?>
						<?php if ( '' !== $s['email'] ) : ?>
							<a href="<?php echo esc_url( 'mailto:' . $s['email'] ); ?>"><?php echo esc_html( $s['email'] ); ?></a>
						<?php endif; ?>
						<?php if ( '' !== $s['tagline'] ) : ?>
							<br><?php echo esc_html( $s['tagline'] ); ?>
						<?php endif; ?></p>
				</div>
				<?php for ( $n = 1; $n <= 3; $n++ ) : ?>
					<div class="jmk-foot-col">
						<?php if ( '' !== $s[ 'col' . $n . '_title' ] ) : ?>
							<h4><?php echo esc_html( $s[ 'col' . $n . '_title' ] ); ?></h4>
						<?php endif; ?>
						<?php
						foreach ( (array) $s[ 'col' . $n . '_links' ] as $i => $item ) {
							$this->render_link( 'c' . $n . '_' . $i, $item['link'], '', esc_html( $item['label'] ) );
						}
						?>
					</div>
				<?php endfor; ?>
			</div>
			<div class="jmk-foot-bottom">
				<span><?php echo esc_html( str_replace( '{year}', gmdate( 'Y' ), $s['copyright'] ) ); ?></span>
				<span><?php echo esc_html( $s['bottom_right'] ); ?></span>
			</div>
		</div></div></footer>
		<?php
		if ( 'yes' === $s['schema'] ) {
			$this->render_schema( $s );
		}
	}

	private function render_schema( array $s ) {
		$home = home_url( '/' );
		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'MovingCompany',
			'@id'        => $home . '#business',
			'name'       => $s['biz_name'],
			'url'        => $home,
			'telephone'  => $s['phone'],
			'priceRange' => '$$',
			'address'    => array(
				'@type'           => 'PostalAddress',
				'addressLocality' => $s['city'],
				'addressRegion'   => $s['region'],
				'postalCode'      => $s['zip'],
				'addressCountry'  => 'US',
			),
		);
		if ( '' !== $s['email'] ) {
			$data['email'] = $s['email'];
		}
		if ( ! empty( $s['logo_image']['url'] ) ) {
			$data['logo']  = $s['logo_image']['url'];
			$data['image'] = $s['logo_image']['url'];
		}
		$areas = array_filter( array_map( 'trim', explode( ',', (string) $s['areas_served'] ) ) );
		if ( $areas ) {
			$data['areaServed'] = array_values(
				array_map(
					static function ( $city ) {
						return array(
							'@type' => 'City',
							'name'  => $city,
						);
					},
					$areas
				)
			);
		}
		if ( '' !== $s['rating_value'] && '' !== $s['rating_count'] ) {
			$data['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $s['rating_value'],
				'reviewCount' => $s['rating_count'],
				'bestRating'  => '5',
			);
		}
		if ( preg_match( '/^(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/', trim( (string) $s['hours'] ), $m ) ) {
			$data['openingHoursSpecification'] = array(
				array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ),
					'opens'     => $m[1],
					'closes'    => $m[2],
				),
			);
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>';
	}
}
