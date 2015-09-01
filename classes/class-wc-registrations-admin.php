<?php
/**
 * Registrations Admin Class
 *
 * Adds Registration product type with dates tab and saves dates as attributes used as variations of your product.
 *
 * @package		WooCommerce Registrations
 * @subpackage	WC_Registrations_Admin
 * @category	Class
 * @author		Allyson Souza
 * @since		1.0
 */
class WC_Registrations_Admin {

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 1.0
	 */
	public static function init() {
        /*
	     * Admin Panel
	     */
	 	// Enqueue scripts in product edit page
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::enqueue_styles_scripts' );

	    // Add registrations to the product select box
	    add_filter( 'product_type_selector', __CLASS__ . '::add_registrations_to_select' );

		// Add variations custom fields (time and days)
	    add_action( 'woocommerce_product_after_variable_attributes', __CLASS__ . '::variable_registration_pricing_fields', 10, 3 );

		// Saves registrations meta fields
	    add_action( 'woocommerce_process_product_meta_registrations', __CLASS__ . '::save_variable_fields', 11 );
		add_action( 'woocommerce_ajax_save_product_variations', __CLASS__ . '::save_variable_fields' );

		// Add registrations dates tab
		add_filter( 'woocommerce_product_data_tabs', __CLASS__ . '::registration_dates_tab' );

		// Load the view to display dates tab content
        add_action( 'woocommerce_product_data_panels', __CLASS__. '::show_dates_tab_content' );

		// Filter dates variations options name and display correctly for each date type (single, multiple, and range)
		add_filter( 'woocommerce_variation_option_name', __CLASS__ . '::registration_variation_option_name' );
	}

    /**
	 * Adds all necessary admin styles.
	 *
	 * @param array Array of Product types & their labels, excluding the Subscription product type.
	 * @return array Array of Product types & their labels, including the Subscription product type.
	 * @since 1.0
	 */
	public static function enqueue_styles_scripts() {
		global $woocommerce, $post;

		// Get admin screen id
		$screen = get_current_screen();

		$is_woocommerce_screen = ( in_array( $screen->id, array( 'product', 'edit-shop_order', 'shop_order', 'users', 'woocommerce_page_wc-settings' ) ) ) ? true : false;
		$is_activation_screen  = ( get_transient( WC_Registrations::$activation_transient ) == true ) ? true : false;

		if ( $is_woocommerce_screen ) {

			$dependencies = array( 'jquery' );

			// Version juggling
			if ( WC_Registrations::is_woocommerce_pre_2_1() ) { // WC 2.0
				$woocommerce_admin_script_handle = 'woocommerce_writepanel';
			} elseif ( WC_Registrations::is_woocommerce_pre_2_2() ) { // WC 2.1
				$woocommerce_admin_script_handle = 'woocommerce_admin_meta_boxes';
			} else {
				$woocommerce_admin_script_handle = 'wc-admin-meta-boxes';
			}

			if( $screen->id == 'product' ) {
				$dependencies[] = $woocommerce_admin_script_handle;

				if ( ! WC_Registrations::is_woocommerce_pre_2_2() ) {
					$dependencies[] = 'wc-admin-product-meta-boxes';
					$dependencies[] = 'wc-admin-variation-meta-boxes';
				}

				$script_params = array(
					'productType'              => WC_Registrations::$name,
				);
			}

			$script_params['ajaxLoaderImage'] = $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif';
			$script_params['ajaxUrl']         = admin_url('admin-ajax.php');
			$script_params['isWCPre21']       = var_export( WC_Registrations::is_woocommerce_pre_2_1(), true );
			$script_params['isWCPre22']       = var_export( WC_Registrations::is_woocommerce_pre_2_2(), true );
			$script_params['isWCPre23']       = var_export( WC_Registrations::is_woocommerce_pre_2_3(), true );

			// WooCommerce Registrations Admin - admin.js
			wp_enqueue_script( 'woocommerce_registrations_admin', plugin_dir_url( WC_Registrations::$plugin_file ) . '/js/admin.js', $dependencies, filemtime( plugin_dir_path( WC_Registrations::$plugin_file ) . 'js/admin.js' ) );
			wp_localize_script( 'woocommerce_registrations_admin', 'WCRegistrations', apply_filters( 'woocommerce_registrations_admin_script_parameters', $script_params ) );

			// WooCommerce Registrations Ajax - wc-registrations-ajax.js
			wp_enqueue_script( 'woocommerce_registrations_ajax', plugin_dir_url( WC_Registrations::$plugin_file ) . '/js/wc-registrations-ajax.js', $dependencies, filemtime( plugin_dir_path( WC_Registrations::$plugin_file ) . 'js/wc-registrations-ajax.js' ) );
			wp_localize_script( 'woocommerce_registrations_ajax', 'WCRegistrations', apply_filters( 'woocommerce_registrations_admin_script_parameters', $script_params ) );
		}

		// Maybe add the admin notice
		if ( $is_activation_screen ) {

			$woocommerce_plugin_dir_file = self::get_woocommerce_plugin_dir_file();

			if ( ! empty( $woocommerce_plugin_dir_file ) ) {

				wp_enqueue_style( 'woocommerce-activation', plugins_url(  '/assets/css/activation.css', self::get_woocommerce_plugin_dir_file() ), array(), WC_Registrations::$version );

				if ( ! isset( $_GET['page'] ) || 'wcs-about' != $_GET['page'] ) {
					add_action( 'admin_notices', __CLASS__ . '::admin_installed_notice' );
				}

			}
			delete_transient( WC_Registrations::$activation_transient );
		}

		if ( $is_woocommerce_screen || $is_activation_screen ) {
			wp_enqueue_style( 'woocommerce_admin_styles', $woocommerce->plugin_url() . '/assets/css/admin.css', array(), WC_Registrations::$version );
		}
	}

  /**
	 * Add the 'registration' product type to the WooCommerce product type select box.
	 *
	 * @param array Array of Product types & their labels, excluding the Course product type.
	 * @return array Array of Product types & their labels, including the Course product type.
	 * @since 1.0
	 */
	public static function add_registrations_to_select( $product_types ){
		$product_types[ WC_Registrations::$name ] = __( 'Registration', 'woocommerce-registrations' );

		return $product_types;
	}

	/**
	 * Output the registration specific fields on the "Edit Product" admin page.
	 *
	 * @since 1.0
	 */
	public static function variable_registration_pricing_fields( $loop, $variation_data, $variation ) {
		include( 'views/html-dates-variation-fields-view.php' );
		do_action( 'woocommerce_registrations_after_variation', $loop, $variation_data, $variation );
	}

  /**
	 * Save meta data for registration product type when the "Edit Product" form is submitted.
	 *
	 * @param array Array of Product types & their labels, excluding the Course product type.
	 * @return array Array of Product types & their labels, including the Course product type.
	 * @since 1.0
	 */
	public static function save_variable_fields( $post_id ) {
		// Run WooCommerce core saving routine
		if ( ! class_exists( 'WC_Meta_Box_Product_Data' ) ) { // WC < 2.1
			process_product_meta_variable( $post_id );
		} elseif ( ! is_ajax() ) { // WC < 2.4
			WC_Meta_Box_Product_Data::save_variations( $post_id, get_post( $post_id ) );
		}

		if ( ! isset( $_REQUEST['variable_post_id'] ) ) {
			return;
		}

		$variable_post_ids = $_POST['variable_post_id'];

		$fields = array(
			'_event_start_time',
			'_event_end_time',
			'week_days'
		);

		$max_loop = max( array_keys( $variable_post_ids ) );

		// Save each variations details
		for ( $i = 0; $i <= $max_loop; $i ++ ) {

			if ( ! isset( $variable_post_ids[ $i ] ) ) {
				continue;
			}

			$variation_id = absint( $variable_post_ids[ $i ] );

			if( isset( $_POST[ '_event_start_time' . $i ] ) ) {
				update_post_meta( $variation_id, '_event_start_time', sanitize_text_field( $_POST[ '_event_start_time' . $i ] ) );
			}

			if( isset( $_POST[ '_event_end_time' . $i ] ) ) {
				update_post_meta( $variation_id, '_event_end_time', sanitize_text_field( $_POST[ '_event_end_time' . $i ] ) );
			}

			if( isset( $_POST[ '_week_days' . $i ] ) ) {
				update_post_meta( $variation_id, '_week_days', $_POST[ '_week_days' . $i ] );
			}
		}
	}

	public static function registration_dates_tab( $tabs ) {
		// Adds the new dates tab
		$tabs['dates'] = array(
			'label' 	=> __( 'Dates', 'woocommerce-registrations' ),
			'target' 	=> 'registration_dates',
			'class' 	=> array('show_if_registration')
		);

		return $tabs;
	}

    public static function show_dates_tab_content() {
		include_once( 'views/html-dates-view.php' );
    }

	public static function registration_variation_option_name( $option ) {
			$opt = json_decode( stripslashes( $option ) );
			if( $opt ) {
				return self::format_variations_dates( $opt );
			} else {
				return $option;
			}
	}

	public static function format_variations_dates( $opt ) {
		if ( $opt ) {
			if ( $opt->type == 'single' ) {

				return date_i18n( get_option( 'date_format' ), strtotime( $opt->date ) );

			} elseif ( $opt->type == 'multiple' ) {

				$date_option = '';
				$size = count( $opt->dates );

				for( $i = 0; $i < $size ; $i++ ) {
					if( $date_option == '' ) {
						$date_option .= date_i18n( get_option( 'date_format' ), strtotime( $opt->dates[ $i ] ) );
					} else {
						$date_option .= ', ' . date_i18n( get_option( 'date_format' ), strtotime( $opt->dates[ $i ] ) );
					}
				}

				return $date_option;

			} elseif ( $opt->type == 'range' ) {
				return date_i18n( get_option( 'date_format' ), strtotime( $opt->dates[0] ) ) . __(' to ', 'woocommerce-registrations') . date_i18n( get_option( 'date_format' ), strtotime( $opt->dates[1] ) );
			} else {
				return $opt;
			}
		}
	}

	/**
	 * Searches through the list of active plugins to find WooCommerce. Just in case
	 * WooCommerce resides in a folder other than /woocommerce/
	 *
	 * @since 1.0
	 */
	public static function get_woocommerce_plugin_dir_file() {

		$woocommerce_plugin_file = '';

		foreach ( get_option( 'active_plugins', array() ) as $plugin ) {
			if ( substr( $plugin, strlen( '/woocommerce.php' ) * -1 ) === '/woocommerce.php' ) {
				$woocommerce_plugin_file = $plugin;
				break;
			}
		}

		return $woocommerce_plugin_file;
	}

	/**
	 * Display a welcome message. Called when the Registrations extension is activated.
	 *
	 * @since 1.0
	 */
	public static function admin_installed_notice() {
		?>
		<div id="message" class="updated woocommerce-message wc-connect woocommerce-registrations-activated">
			<div class="squeezer">
				<h4><?php printf( __( '%sWooCommerce Registrations Installed%s &#8211; %sYou\'re ready to start selling registrations!%s', 'woocommerce-registrations' ), '<strong>', '</strong>', '<em>', '</em>' ); ?></h4>

				<p class="submit">
					<a href="https://twitter.com/share" class="twitter-share-button" data-url="http://www.hastedesign.com.br/" data-text="Sell course and events registrations with #WooCommerce" data-via="HasteDesign" data-size="large">Tweet</a>
					<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
				</p>
			</div>
		</div>
		<?php
	}
}

WC_Registrations_Admin::init();