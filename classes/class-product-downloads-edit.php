<?php
/**
 * Edit Retailer Class
 *
 * @package   WooCommerce Product Download Dates
 * @author    LightSpeed
 * @license   GPL-3.0+
 * @link
 * @copyright 2017 LightSpeedDevelopment
 */
namespace woocommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Product Retailers Admin Edit Screen
 *
 * @since 1.0.0
 */
class Product_Downloads_Edit {

	/**
	 * Holds the array of fields
	 * @var array
	 */
	var $fields = array();

	/**
	 * Initialize and setup the retailer add/edit screen.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		//Add in the checkboxes
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'enable_checkbox' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variation_settings_fields' ), 10, 3 );

		//Save Custom Field Data
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_meta' ), 30, 2 );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ), 30, 2 );

		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_settings_fields' ), 10, 2 );

	}

	/**
	 * Enqueue the admin scripts for adding in the date field.
	 */
	function enqueue_admin_scripts() {

		if ( ( isset( $_GET['post'] ) && 'product' === get_post_type( $_GET['post'] ) ) || isset( $_GET['post_type'] ) && 'product' === $_GET['post_type'] ) {

			wp_register_script( 'wc_pdd_edit_admin_js', WC_PDD_URL . '/assets/js/wc-pdd-edit.min.js', array( 'jquery' ), WC_PDD_VER );
			wp_enqueue_script( 'wc_pdd_edit_admin_js' );

			//Set the columns for the archives
			$param_array['placeholder'] = esc_attr__( 'File date', 'wc-product-download-dates' );
			$param_array['file_dates'] = '';
			$param_array['variation_file_dates'] = array();

			// Get the Product
			if ( isset( $_GET['post'] ) ) {

				$param_array['file_dates']  = get_post_meta( get_the_ID(), '_wc_file_dates', true );

				$product = wc_get_product( $_GET['post'] );

				//Check if its a variation or not.
				if ( $product->is_type( 'variable' ) ) {

					$variations = $product->get_children();

					foreach ( $variations as $vid ) {
						$param_array['variation_file_dates'][ $vid ] = get_post_meta( $vid, '_wc_variation_file_dates', true );
					}
				}
			}

			wp_localize_script( 'wc_pdd_edit_admin_js', 'wc_pdd_edit_params', $param_array );

		}
	}

	/**
	 *
	 * Enqueue the admin scripts for adding in the date field.
	 *
	 * @param $post_id
	 * @param $post
	 *
	 * @return bool
	 */
	function save_product_meta( $post_id, $post ) {

		if ( ! ( isset( $_POST['woocommerce_meta_nonce'] ) || wp_verify_nonce( sanitize_key( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) ) {
			return false;
		}

		$file_dates = isset( $_POST['_wc_file_dates'] ) ? wc_clean( $_POST['_wc_file_dates'] ) : array();
		update_post_meta( $post_id, '_wc_file_dates', implode( ',', $file_dates ) );

		// Checkbox
		$woocommerce_checkbox = isset( $_POST['_enable_subscription_download_filtering'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_enable_subscription_download_filtering', $woocommerce_checkbox );
	}

	/**
	 * Enqueue the admin scripts for adding in the date field.
	 */
	function save_variation_meta( $variation_id, $i ) {

		if ( ! ( isset( $_POST['woocommerce_meta_nonce'] ) || wp_verify_nonce( sanitize_key( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) ) {
			return false;
		}

		$file_dates = isset( $_POST['_wc_variation_file_dates'][ $variation_id ] ) ? wc_clean( $_POST['_wc_variation_file_dates'][ $variation_id ] ) : array();
		update_post_meta( $variation_id, '_wc_variation_file_dates', implode( ',', $file_dates ) );
	}

	/**
	 * Enable checkbox
	 */
	public function enable_checkbox() {
		woocommerce_wp_checkbox(
			array(
				'id'            => '_enable_subscription_download_filtering',
				'wrapper_class' => 'show_if_downloadable show_if_subscription',
				'label'         => __( 'Enable', 'wc-product-download-dates' ),
				'description'   => __( 'Download Date Filtering', 'wc-product-download-dates' ),
			)
		);
	}

	/**
	 * Create the checkbox enable for variations
	 */
	function variation_settings_fields( $loop, $variation_data, $variation ) {
		woocommerce_wp_checkbox(
			array(
				'id'            => '_enable_subscription_download_filtering[' . $variation->ID . ']',
				'label'         => __( ' Enable Download Date Filtering', 'wc-product-download-dates' ),
				'description'   => '',
				'value'         => get_post_meta( $variation->ID, '_enable_subscription_download_filtering', true ),
			)
		);
	}

	/**
	 * Save new fields for variations
	 *
	 */
	function save_variation_settings_fields( $post_id ) {

		if ( ! ( isset( $_POST['woocommerce_meta_nonce'] ) || wp_verify_nonce( sanitize_key( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) ) {
			return false;
		}

		// Checkbox
		$checkbox = isset( $_POST['_enable_subscription_download_filtering'][ $post_id ] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_enable_subscription_download_filtering', $checkbox );
	}
}
