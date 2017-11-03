<?php
/**
 * Frontend Filters
 *
 * @package   WooCommerce Product Download Dates
 * @author    LightSpeed
 * @license   GPL-3.0+
 * @link
 * @copyright 2017 LightSpeedDevelopment
 */
namespace woocommerce;

defined( 'ABSPATH' ) || exit;


/*
 * TODO
 *
 * Run through the available downloads
 * find out if the product is a subscription
 * if it is check what the renewal interval is
 * look for a completed order in that interval with the product_id attached to the order
 */

/**
 * Product Retailers Admin Edit Screen
 *
 * @since 1.0.0
 */
class Product_Downloads_Frontend {

	/*
	 * Holds the downloadable files from the actual product.
	 */
	protected $downloadable_files = false;

	/*
	 * Holds the downloadable files dates.
	 */
	protected $file_dates = false;

	/*
	 * Holds the array of the customers orders
	 */
	protected $orders = false;

	/*
	 * Holds the array of the customers ordersby product ID
	 */
	protected $orders_by_product = false;

	/*
	 * Holds the array of subscription intervals by product ID
	 */
	protected $subscription_intervals = false;

	/*
	 * Holds the keys for the downloads we need to unset
	 */
	protected $unset_array = false;

	/**
	 * Initialize and setup the retailer add/edit screen.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		add_action( 'woocommerce_customer_get_downloadable_products', array(
			$this,
			'get_downloadable_products',
		), 20, 1 );

		add_action( 'woocommerce_get_item_downloads', array(
			$this,
			'get_item_downloadable_products',
		), 20, 3 );

	}

	/**
	 * Filters the Downloadable Files by the dates you have orders
	 *
	 * @param $downloads array
	 * @return array
	 */

	public function get_downloadable_products( $downloads ) {

		// Run through each of the products
		if ( class_exists( 'WC_Subscriptions' ) && ! empty( $downloads ) ) {
			$unset_array = false;

			foreach ( $downloads as $download_key => $download ) {

				$product = wc_get_product( $download['product_id'] );
				$enable_filter = get_post_meta( $download['product_id'], '_enable_subscription_download_filtering', true );

				if ( 'yes' === $enable_filter && $product->is_downloadable() && $product->is_type( array( 'subscription_variation', 'subscription' ) ) ) {

					//Get the array of downloadable files, so we can match the date
					$this->index_downloads( $product );
					$this->index_dates( $product );
					$this->index_orders( $download['order_id'], $download['product_id'] );

					//Check if the download has a completed order or not for the date of the current file.
					if ( ! $this->has_completed_order( $download ) ) {
						$unset_array[] = $download_key;
					}
				}
			}

			if ( false !== $unset_array && is_array( $unset_array ) && ! empty( $unset_array ) ) {

				foreach ( $unset_array as $unset ) {
					unset( $downloads[ $unset ] );
				}
			}
		}

		return $downloads;
	}

	/**
	 * Filters the Downloadable Files by the dates you have orders
	 *
	 * @param $files array
	 * @parm $download_obj object
	 * @param  $order object WC_Order()
	 *
	 * @return array
	 */

	public function get_item_downloadable_products( $files, $download_obj, $order ) {

		if ( class_exists( 'WC_Subscriptions' ) && ! empty( $files ) ) {

			$product    = $download_obj->get_product();
			$order      = $download_obj->get_order();
			$product_id = $download_obj->get_variation_id() ? $download_obj->get_variation_id() : $download_obj->get_product_id();

			$enable_filter = get_post_meta( $product_id, '_enable_subscription_download_filtering', true );

			if ( 'yes' === $enable_filter && $product->is_downloadable() && $product->is_type( array( 'subscription_variation', 'subscription' ) ) ) {

				$unset_array = array();

				//Get the array of downloadable files, so we can match the date
				$this->index_downloads( $product );
				$this->index_dates( $product );
				$this->index_orders( $order->get_id(), $product_id );

				foreach ( $files as $file_key => $file_array ) {
					//Check if the download has a completed order or not for the date of the current file.
					$file_array['product_id'] = $product_id;

					if ( ! $this->has_completed_order( $file_array, $file_array['name'] ) ) {
						$unset_array[] = $file_key;
					}
				}

				if ( false !== $unset_array && is_array( $unset_array ) ) {
					foreach ( $unset_array as $unset ) {
						unset( $files[ $unset ] );
					}
				}
			}
		}

		return $files;
	}

	/**
	 * Filters the Downloadable Files by the dates you have orders
	 *
	 * @param $product bool | object
	 */

	private function index_downloads( $product = false ) {

		if ( ! isset( $this->downloadable_files[ $product->get_id() ] ) ) {
			$downloads = $product->get_downloads();
			$counter = 0;
			foreach ( $downloads as $download ) {
				$this->downloadable_files[ $product->get_id() ][ $counter ] = $download->get_name();
				$counter++;
			}
		}
	}

	/**
	 * Sorts the file dates out into an array by Product ID and download index
	 *
	 * @param $product bool | object
	 */

	private function index_dates( $product = false ) {
		$file_dates = false;

		//Check if we have set this info already
		if ( ! isset( $this->file_dates[ $product->get_id() ] ) ) {

			//Check what type of subscription to grab.
			$meta_key = '_wc_file_dates';
			if ( $product->is_type( 'subscription_variation' ) ) {
				$meta_key = '_wc_variation_file_dates';
			}
			$file_dates = get_post_meta( $product->get_id(), $meta_key , true );

			if ( false !== $file_dates ) {
				$file_dates = explode( ',', $file_dates );
				$this->file_dates[ $product->get_id() ] = $file_dates;
			}
		}
	}

	/**
	 * Sorts the file dates out into an array by Product ID and download index
	 *
	 * @param $order_id bool | string
	 */

	private function index_orders( $order_id = false, $product_id = false ) {
		$order = wc_get_order( $order_id );
		if ( 'shop_subscription' === $order->get_type() ) {

			$this->subscription_intervals[ $product_id ] = array (
				'period' => $order->get_billing_period(),
				'interval' => $order->get_billing_interval(),
			);

			$related_orders_ids_array = $order->get_related_orders();

			if ( ! empty( $related_orders_ids_array ) ) {

				foreach ( $related_orders_ids_array as $related_order ) {

					if ( ! isset( $this->orders[ $related_order ] ) ) {

						$order = wc_get_order( $related_order );
						if ( 'completed' === $order->get_status() || 'processing' === $order->get_status() ) {

							$dates = array(
								'day'   => get_the_date( 'd', $related_order ),
								'week'  => get_the_date( 'W', $related_order ),
								'month' => get_the_date( 'm', $related_order ),
								'year'  => get_the_date( 'Y', $related_order ),
							);
							$this->orders[ $related_order ] = $dates;

							$this->orders_by_product[ $product_id ][] = $this->orders[ $related_order ];
						}
					}
				}
			}
		}
	}

	/**
	 * Filters the Downloadable Files by the dates you have orders
	 *
	 * @param $download boolean | object
	 * @param $filename boolean | string
	 * @return boolean
	 */

	private function has_completed_order( $download = false, $filename = false ) {
		$return = false;

		if ( false === $filename ) {
			$filename = $download['file']['name'];
		}

		$file_date = $this->get_file_date_by_name( $download['product_id'], $filename );

		if ( false !== $file_date &&
			is_array( $this->orders_by_product ) &&
			isset( $this->orders_by_product[ $download['product_id'] ] ) &&
			! empty( $this->orders_by_product[ $download['product_id'] ] ) ) {

			//run through the current download products dates
			foreach ( $this->orders_by_product[ $download['product_id'] ] as $dates ) {

				//See what the subscription interval is and check if the item is in range.
				if ( isset( $this->subscription_intervals[ $download['product_id'] ] ) &&
					isset( $this->subscription_intervals[ $download['product_id'] ]['period'] ) ) {

					$test_date = $file_date;

					// See which interval we are testing against.
					switch( $this->subscription_intervals[ $download['product_id'] ]['period'] ){

						case 'day':
							$test_date = date( 'd', strtotime( $file_date ) );
							break;

						case 'week':
							$test_date = date( 'W', strtotime( $file_date ) );
							break;

						case 'month':
							$test_date = date( 'm', strtotime( $file_date ) );
							break;

						case 'year':
							$test_date = date( 'Y', strtotime( $file_date ) );
							break;

					}

					if ( $test_date === $dates[ $this->subscription_intervals[ $download['product_id'] ]['period'] ] ) {
						$return = true;
					}
				}
			}
		}
		return $return;
	}

	/**
	 * Runs through the file names array and select the date
	 *
	 * @param $key string | boolean
	 * @param $file_name string | boolean
	 * @return string | false
	 */
	private function get_file_date_by_name( $key = false, $file_name = false ) {
		$return = false;
		if ( false !== $key &&
		     false !== $file_name &&
		     false !== $this->file_dates &&
		     false !== $this->downloadable_files &&
		     isset( $this->downloadable_files[ $key ] ) &&
		     is_array( $this->downloadable_files[ $key ] ) ) {

			$index = array_search( $file_name, $this->downloadable_files[ $key ]  );

			if ( false !== $index &&
				isset( $this->file_dates[ $key ] ) &&
				is_array( $this->file_dates[ $key ] ) ) {

				$return = $this->file_dates[ $key ][ $index ];
			}
		}
		return $return;
	}

}
