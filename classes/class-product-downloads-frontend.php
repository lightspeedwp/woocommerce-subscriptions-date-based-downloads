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

		//Run through each download
		//Check if the file date fits in between the "order" ranges you qualify for.

		// Run through each of the products
		if ( class_exists( 'WC_Subscriptions' ) && ! empty( $downloads ) ) {

			$this->index_valid_subscription_dates();

			$unset_array = false;

			foreach ( $downloads as $download_key => $download ) {

				//Check if the download has a completed order or not for the date of the current file.
				if ( ! $this->has_valid_date( $download ) ) {
					$unset_array[] = $download_key;
				}
			}

			//Remove the files that you dont have access to.
			if ( false !== $unset_array && is_array( $unset_array ) && ! empty( $unset_array ) ) {
				foreach ( $unset_array as $unset ) {
					unset( $downloads[ $unset ] );
				}
			}
		}

		$downloads = $this->remove_duplicate_downloads( $downloads );

		return $downloads;
	}

	/**
	 * Filters the Downloadable Files by the dates you have orders
	 *
	 * @param   $downloads array
	 * @param   $item \WC_Order_Item_Product()
	 * @param   $order \WC_Order()
	 *
	 * @return array
	 */
	public function get_item_downloadable_products( $downloads, $item, $order ) {

		// Run through each of the products
		if ( class_exists( 'WC_Subscriptions' ) && ! empty( $downloads ) ) {

			$this->index_valid_subscription_dates( $order->get_id() );

			$unset_array = false;

			foreach ( $downloads as $download_key => $download ) {

				if ( ! isset( $download['product_id'] ) ) {
					$download['product_id'] = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
				}

				//Check if the download has a completed order or not for the date of the current file.
				if ( ! $this->has_valid_date( $download ) ) {
					$unset_array[] = $download_key;
				}
			}

			//Remove the files that you dont have access to.
			if ( false !== $unset_array && is_array( $unset_array ) && ! empty( $unset_array ) ) {
				foreach ( $unset_array as $unset ) {
					unset( $downloads[ $unset ] );
				}
			}
		}

		$downloads = $this->remove_duplicate_downloads( $downloads );
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



		if ( false !== $item_id ) {
			$subscription = wcs_get_subscription( $item_id );
			if ( false !== $subscription && '' !== $subscription ) {
				$my_subscriptions[ $item_id ] = $subscription;
			}
		} else {
			$subscription_args = array(
				'subscriptions_per_page' => 100,
				'paged'                  => 1,
				'customer_id'            => WC()->customer->get_id(),
			);
			$my_subscriptions = wcs_get_subscriptions( $subscription_args );
		}

		if ( ! empty ( $my_subscriptions ) ) {

			/**
			 * Run through each subscription and gather the orders
			 * @var $subscription \WC_Subscription
			 */
			foreach ( $my_subscriptions as $sub_id => $subscription ) {

				$period = $subscription->get_billing_period();
				$interval = $subscription->get_billing_interval();

				$items = $subscription->get_items();
				if ( ! empty( $items ) ) {
					$product_ids = array();

					/**
					 * Run through each item looking for a downloadable subscription
					 * @var $item \WC_Order_Item_Product
					 */
					foreach ( $items as $item ) {

						if ( $item instanceof \WC_Order_Item_Product ) {

							$product = $item->get_product();
							$enable_filter = get_post_meta( $item->get_product_id(), '_enable_subscription_download_filtering', true );

							// Only check the download if the filter is enabled, and the product is a Downloadable Subscription.
							if ( 'yes' === $enable_filter && $product->is_downloadable() && $product->is_type( array(
									'subscription_variation',
									'subscription'
								) ) ) {
								$product_ids[] = $item->get_product_id();
								$this->index_downloads( $product );
								$this->index_dates( $product );
							}
						}
					}

					if ( ! empty( $product_ids ) ) {

						/**
						 * This is where we store the completed order dates against the product ID.
						 */
						$orders = $subscription->get_related_orders('all' );
						if ( ! empty( $orders ) ) {

							/**
							 * Run through each order and test to see if its completed or processing, if it is then generate a date range to test the file against.
							 * @var $order \WC_Order
							 */
							foreach ( $orders as $order_id => $order ) {
								if ( '' !== ( $date_paid = $order->get_date_paid() ) && null !== $date_paid ) {
									foreach ( $product_ids as $pid ) {
										$this->subscription_intervals[ $pid ][] = $this->generate_range_from_date( $date_paid, $interval, $period );
									}
								}
							}
						}
					}
				}
			}
		}
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
	 * Filters the Downloadable Files by the dates you have orders
	 *
	 * @param $download boolean | object
	 * @param $filename boolean | string
	 * @return boolean
	 */

	private function has_valid_date( $download = false, $filename = false ) {
		$return = false;

		if ( false === $filename ) {
			if ( is_array( $download['file'] ) ) {
				$filename = $download['file']['name'];
			} else {
				$filename = $download['name'];
			}
		}
		$file_date = $this->get_file_date_by_name( $download['product_id'], $filename );
		$file_datestamp = strtotime( $file_date );

		if ( false !== $file_date &&
			is_array( $this->subscription_intervals ) &&
			isset( $this->subscription_intervals[ $download['product_id'] ] ) &&
			! empty( $this->subscription_intervals[ $download['product_id'] ] ) ) {

			foreach ( $this->subscription_intervals[ $download['product_id'] ] as $dates ) {
				$dates = apply_filters( 'wc_pdd_has_valid_date', $dates, $file_date, $download );

				if ( $dates['start']->getTimestamp() <= $file_datestamp && $file_datestamp <= $dates['end']->getTimestamp() ) {
					$return = true;
				}
			}
		}
		return $return;
	}

	/**
	 * Remove Duplicate Downloads
	 * @param $downloads
	 */
	private function remove_duplicate_downloads( $downloads ) {
		$new_downloads = $downloads;
		if ( ! empty( $downloads ) ) {
			$download_sorter = array();
			foreach ( $downloads as $download_index => $download ) {
				if ( isset( $download['download_id'] ) ) {
					$did = $download['download_id'];
				} else {
					$did = $download['id'];
				}
				$download_sorter[ $did ] = $download_index;
			}

			$new_downloads = array();
			foreach ( $download_sorter as $download_id => $download_index ) {
				$new_downloads[] = $downloads[ $download_index ];
			}
		}

		return $new_downloads;
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
