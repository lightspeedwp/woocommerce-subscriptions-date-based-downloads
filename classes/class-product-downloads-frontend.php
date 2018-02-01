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

		/*add_action( 'woocommerce_get_item_downloads', array(
			$this,
			'get_item_downloadable_products',
		), 20, 3 );*/

	}

	/**
	 * Filters the Downloadable Files by the dates you have orders
	 *
	 * @param $downloads array
	 * @return array
	 */

	public function get_downloadable_products( $downloads ) {

		//Run through each download
		//Check if the file date fits in between the "order" ranges you qualify for.


		// Run through each of the products
		if ( class_exists( 'WC_Subscriptions' ) && ! empty( $downloads ) ) {



			$this->index_valid_subscription_dates();



			$unset_array = false;

			print_r( '<pre> subscription_intervals' );
			print_r( $downloads );
			print_r( '</pre>' );

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

		print_r( '<pre> subscription_intervals' );
		print_r( $this->subscription_intervals );
		print_r( '</pre>' );
		print_r( '<pre> orders_by_product' );
		print_r( $this->orders_by_product );
		print_r( '</pre>' );
		print_r( '<pre> downloadable_files' );
		print_r( $this->downloadable_files );
		print_r( '</pre>' );
		die();

		return $downloads;
	}


	/**
	 * Sorts the file dates out into an array by Product ID and download index
	 *
	 * @param $item_id bool | string
	 */

	private function index_valid_subscription_dates( $item_id = false ) {

		//Get all of my subscriptions
		//Get the start date and end date of the subscription if it is active, or on-hold or expired
		//Get the orders for each of those subscripions and filter by the completed orders.
		//Get the date of each completed order, this will give us a range of dates to test against.
		//Format the array so we can find the valid dates by the product_id.

		$subscription_args = array(
			'subscriptions_per_page' => 100,
			'paged'                  => 1,
		);

		if ( false !== $item_id ) {
			$subscription_args['order_id'] = $item_id;
		} else {
			$subscription_args['customer_id'] = wp_get_current_user()->ID;
		}
		$my_subscriptions = wcs_get_subscriptions( $subscription_args );

		if ( ! empty ( $my_subscriptions ) ) {

			/**
			 * Run through each subscription and gather the orders
			 * @var $subscription \WC_Subscription
			 */

			foreach ( $my_subscriptions as $sub_id => $subscription ) {

				$period = $subscription->get_billing_period();
				$interval = $subscription->get_billing_interval();
				$start_date = $subscription->get_date( 'date_created' );
				$end_date = $subscription->get_date( 'end' );

				print_r( $sub_id );print_r('-');print_r( $subscription->get_id() );print_r('<br />');
				print_r( $period );print_r('-');print_r( $interval );print_r('<br />');
				print_r( $start_date );print_r('-');print_r( $end_date );print_r('<br />');

				$orders = $subscription->get_related_orders('all' );

				if ( ! empty( $orders ) ) {

					/**
					 * Run through each order and test to see if its completed or processing, if it is then generate a date range to test the file against.
					 * @var $order \WC_Order
					 */
					foreach ( $orders as $order_id => $order ) {
						print_r( $order_id );print_r('-');print_r( $order->get_status() );print_r('<br />');
						if ( 'completed' === $order->get_status() || 'processing' === $order->get_status() ) {
							$this->subscription_intervals[ $sub_id ][] = $this->generate_range_from_date( $order->get_date_paid(), $interval, $period );
						}
					}
				}
				print_r( '-----------------------------------------------' );print_r('<br />');
			}

			print_r( '<pre> Subscription Orders' );
			print_r( $this->subscription_intervals );
			print_r( '</pre>' );
		}
		die();

	}

	/**
	 * Returns a start and end date for the completed order.
	 * @param $start_date object \WC_DateTime
	 * @param $end_date object \WC_DateTime
	 * @param $interval string
	 * @param $period string
	 *
	 * @return array
	 */
	public function generate_range_from_date( $start_date, $interval, $period ) {
		$return = array();
		$return['start'] = $start_date;
		$end_date = clone $start_date;
		$end_date->modify( '+1 year' );
		$return['end'] = $end_date;
		return $return;
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
	 * @param $product bool | object \WC_Product()
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

			$this->subscription_intervals[ $product_id ] = array(
				'period' => $order->get_billing_period(),
				'interval' => $order->get_billing_interval(),
				'subscription' => $order->get_id(),
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

			//run through the orders testing the subscription dates found.
			foreach ( $this->orders_by_product[ $download['product_id'] ] as $dates ) {

				//See what the subscription interval is and check if the item is in range.
				if ( isset( $this->subscription_intervals[ $download['product_id'] ] ) &&
					isset( $this->subscription_intervals[ $download['product_id'] ]['period'] ) ) {

					$test_date = $file_date;

					// See which interval we are testing against.
					switch ( $this->subscription_intervals[ $download['product_id'] ]['period'] ) {

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

					//print_r( $filename );print_r( '-' );print_r( $file_date );print_r( '-' );print_r( $test_date );print_r( '-' );print_r( $dates[ $this->subscription_intervals[ $download['product_id'] ]['period'] ] );print_r('<br />');

					if ( $test_date === $dates[ $this->subscription_intervals[ $download['product_id'] ]['period'] ] ) {
						$return = true;
					}

					apply_filters( 'wc_pdd_has_completed_order', $download, $file_date );

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

			$index = array_search( $file_name, $this->downloadable_files[ $key ] );

			if ( false !== $index &&
				isset( $this->file_dates[ $key ] ) &&
				is_array( $this->file_dates[ $key ] ) ) {

				$return = $this->file_dates[ $key ][ $index ];
			}
		}
		return $return;
	}

}
