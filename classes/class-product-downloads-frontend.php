<?php
/**
 * Frontend Filters
 *
 * @package   WooCommerce Subscription Date Based Downloads
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
	 * Holds the downloadable files dates.
	 */
	protected $file_end_dates = false;

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

		add_filter( 'woocommerce_customer_get_downloadable_products', array(
			$this,
			'get_downloadable_products',
		), 20, 1 );

		add_filter( 'woocommerce_get_item_downloads', array(
			$this,
			'get_item_downloadable_products',
		), 20, 3 );

		add_filter( 'woocommerce_subscription_item_download_statuses', array(
			$this,
			'subscription_item_download_statuses',
		), 20, 1 );

		add_filter( 'woocommerce_order_is_download_permitted', array(
			$this,
			'order_is_download_permitted',
		), 20, 2 );
	}

	/**
	 * Allows on-hold, cancelled or expired subscriptions to view their items.
	 * @param $statuses
	 *
	 * @return array
	 */
	public function subscription_item_download_statuses( $statuses ) {
		$statuses[] = 'on-hold';
		$statuses[] = 'cancelled';
		$statuses[] = 'expired';
		return $statuses;
	}

	/**
	 * Aloow all subscriptions to download is they have a payment date.
	 * @param $allow string
	 * @param $order object \WC_Order()
	 *
	 * @return mixed
	 */
	public function order_is_download_permitted( $allow, $order ) {
		if ( 'shop_subscription' === $order->get_type() ) {
			$items = $order->get_items();
			if ( ! empty( $items ) ) {
				/**
				 * @var $item \WC_Order_Item_Product()
				 */
				foreach ( $items as $item ) {
					$product       = $item->get_product();
					$enable_filter = get_post_meta( $item->get_product_id(), '_enable_subscription_download_filtering', true );
					if ( 'yes' === $enable_filter ) {
						$allow = true;
					}
				}
			}
		}
		return $allow;
	}

	/**
	 * Filters the Downloadable Files by the dates you have orders
	 *
	 * @param $downloads array
	 * @return array
	 */

	public function get_downloadable_products( $downloads ) {

		// Run through each download
		// Check if the file date fits in between the "order" ranges you qualify for.
		// Run through each of the products.
		if ( class_exists( 'WC_Subscriptions' ) && ! empty( $downloads ) ) {

			$this->index_valid_subscription_dates();
			$unset_array = false;

			foreach ( $downloads as $download_key => $download ) {

				// Check if the download has a completed order or not for the date of the current file.
				if ( ! $this->has_valid_date( $download ) ) {
					$unset_array[] = $download_key;
				}
			}

			// Remove the files that you dont have access to.
			if ( false !== $unset_array && is_array( $unset_array ) && ! empty( $unset_array ) ) {
				foreach ( $unset_array as $unset ) {
					unset( $downloads[ $unset ] );
				}
			}
		}
		$downloads = $this->remove_duplicate_downloads( $downloads );
		$downloads = $this->sort_downloadable_products( $downloads );
		return $downloads;
	}

	function sort_downloads($a, $b)
	{

		$a_index = array_search ( $a , $this->downloadable_files[ $this->current_product_id ] );
		if ( '' === $a_index || false === $a_index ) {
			$a_index = 0;
		}
		$b_index = array_search ( $b , $this->downloadable_files[ $this->current_product_id ] );
		if ( '' === $b_index || false === $b_index ) {
			$b_index = 0;
		}

		/*print_r( '<pre>prod_ID' );
		print_r( $this->current_download_id );
		print_r( '</pre>' );

		print_r( '<pre>$a' );
		print_r( $a );
		print_r( ' - ' );
		print_r( $a_index );
		print_r( '</pre>' );

		print_r( '<pre>$b' );
		print_r( $b );
		print_r( ' - ' );
		print_r( $b_index );
		print_r( '</pre>' );*/

		if ( $a_index == $b_index ) {
			return 0;
		}
		return ( $a_index < $b_index ) ? -1 : 1;
	}

	/**
	 * Remove Duplicate Downloads
	 * @param $downloads
	 * @return array
	 */
	private function sort_downloadable_products( $downloads, $product_id = false ) {

		if ( ! empty( $downloads ) ) {
			//first, sort them into their products

			$sorting_hat = array();

			foreach( $downloads as $download_key => $download ) {

				if ( false !== $product_id ) {
					$sorting_hat[ $product_id ][ $download_key ] = $download['name'];
				} else {
					$sorting_hat[ $download['product_id'] ][ $download_key ] = $download['download_name'];
				}
			}

			$new_hat = array();

			/*if ( isset( $_GET['debug'] ) ) {
				print_r( '<pre>' );
				print_r( $sorting_hat );
				print_r( '</pre>' );
				print_r( '<pre>' );
				print_r( $downloads );
				print_r( '</pre>' );
			}*/

			//the sort each product by the original file index.
			foreach( $sorting_hat as $hat_product => $hat ) {
				$this->current_product_id = $hat_product;
				uasort( $hat, array( $this, 'sort_downloads' ) );
				$new_hat[ $hat_product ] = $hat;
				$this->current_product_id = false;
			}


			if ( ! empty( $new_hat ) ) {
				$new_downloads = array();

				foreach( $new_hat as $hat_key => $hat_values ) {
					if ( ! empty( $hat_values ) ) {
						foreach( $hat_values as $hat_value_key => $hat_value ) {
							$new_downloads[] = $downloads[ $hat_value_key ];
						}
					}
				}

				if ( ! empty( $new_downloads ) ) {
					$downloads = $new_downloads;
				}
			}

		}

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

		// Run through each of the products.
		//if ( class_exists( 'WC_Subscriptions' ) && ! empty( $downloads ) ) {

			$this->index_valid_subscription_dates( $order->get_id() );

			$unset_array = false;

			foreach ( $downloads as $download_key => $download ) {

				if ( ! isset( $download['product_id'] ) ) {
					$download['product_id'] = $item->get_product_id();
				}

				// Check if the download has a completed order or not for the date of the current file.
				if ( ! $this->has_valid_date( $download ) ) {
					$unset_array[] = $download_key;
				}
			}

			// Remove the files that you dont have access to.
			if ( false !== $unset_array && is_array( $unset_array ) && ! empty( $unset_array ) ) {
				foreach ( $unset_array as $unset ) {
					unset( $downloads[ $unset ] );
				}
			}
		//}
		$downloads = $this->remove_duplicate_downloads( $downloads );
		$downloads = $this->sort_downloadable_products( $downloads, $item->get_product_id() );
		return $downloads;
	}


	/**
	 * Sorts the file dates out into an array by Product ID and download index
	 *
	 * @param $item_id bool | string
	 */

	private function index_valid_subscription_dates( $item_id = false ) {

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

		if ( ! empty( $my_subscriptions ) ) {

			/**
			 * Run through each subscription and gather the orders
			 * @var $subscription \WC_Subscription
			 */
			foreach ( $my_subscriptions as $sub_id => $subscription ) {

				$period   = $subscription->get_billing_period();
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

							$product       = $item->get_product();
							$enable_filter = get_post_meta( $item->get_product_id(), '_enable_subscription_download_filtering', true );

							// Only check the download if the filter is enabled, and the product is a Downloadable Subscription.
							if ( 'yes' === $enable_filter && $product->is_downloadable() && $product->is_type( array(
									'subscription_variation',
									'subscription',
							) ) ) {
								$product_ids[] = $item->get_product_id();
								$this->index_downloads( $product );
								$this->index_dates( $product );
							}
						}
					}

					if ( ! empty( $product_ids ) ) {
						$date_start = $subscription->get_date( 'date_created' );
						$date_start_obj = new \WC_DateTime();
						$date_start_obj->modify( $date_start );

						$date_end = $subscription->get_date( 'end' );
						$date_end_obj = new \WC_DateTime();
						$date_end_obj->modify( $date_end );

						/*print_r( '<pre>Subscription Dates' );
						print_r( $date_start );
						print_r( ' - ' );
						print_r( $date_start );
						print_r( '</pre>' );*/

						foreach ( $product_ids as $pid ) {
							if ( '' === $date_end || false === $date_end ) {
								$this->subscription_intervals[ $pid ][] = $this->generate_range_from_date( $date_start, $interval, $period );
							} else {
								$this->subscription_intervals[ $pid ][] = array( 'start' => $date_start_obj, 'end' => $date_end_obj );
							}
						}
					}
				}
			}
		}
		$this->subscription_intervals = apply_filters( 'wc_pdd_subscription_intervals', $this->subscription_intervals );
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

			/*if ( isset( $_GET['debug'] ) && 1989 === $product->get_id() ) {
				print_r( '<pre>Indexed Dates' );
				print_r( $this->file_dates );
				print_r( '</pre>' );
			}*/
		}

		//Get the end dates
		if ( ! isset( $this->file_end_dates[ $product->get_id() ] ) ) {

			//Check what type of subscription to grab.
			$meta_key = '_wc_file_dates_end';
			if ( $product->is_type( 'subscription_variation' ) ) {
				$meta_key = '_wc_variation_file_dates_end';
			}
			$file_dates = get_post_meta( $product->get_id(), $meta_key , true );

			if ( false !== $file_dates ) {
				$file_dates = explode( ',', $file_dates );
				$this->file_end_dates[ $product->get_id() ] = $file_dates;
			}

			/*if ( isset( $_GET['debug'] ) && 1989 === $product->get_id() ) {
				print_r( '<pre>Indexed Dates' );
				print_r( $this->file_end_dates );
				print_r( '</pre>' );
			}*/
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

		$file_date_formatted = $this->get_file_date_by_name( $download['product_id'], $filename );
		//Allow files with no dates to be shown
		if ( '' === $file_date_formatted ) {
			return true;
		}
		$file_date = new \WC_DateTime();
		$file_date->setTimestamp( strtotime( $file_date_formatted ) );
		$file_date->modify( '10:00:00' );

		//Set the end dates
		$file_end_date_formatted = $this->get_file_end_date_by_name( $download['product_id'], $filename );
		$file_end_date = new \WC_DateTime();

		//Allow files with no dates to be shown
		if ( '' === $file_end_date_formatted ) {
			$file_end_date_formatted = $file_date_formatted;
			$file_end_date->setTimestamp( strtotime( $file_end_date_formatted ) );
			$file_end_date->modify( '+6 Months' );
		} else {
			$file_end_date->setTimestamp( strtotime( $file_end_date_formatted ) );
		}
		$file_date->modify( '10:00:00' );

		/*if ( isset( $_GET['debug'] ) && 1989 === $download['product_id'] ) {
			print_r( '<pre>' );
			print_r( $download['product_id'] . ' ' . $filename );
			print_r( ' (' );
			print_r( $file_date_formatted );
			print_r( ' ' );
			print_r( $file_date->format( 'Y-m-d h:i:s' ) );
			print_r( ') (' );
			print_r( $file_date_formatted );
			print_r( ' ' );
			print_r( $file_end_date->format( 'Y-m-d h:i:s' ) );
			print_r( ')</pre>' );
		}*/

		if ( false !== $file_date &&
			is_array( $this->subscription_intervals ) &&
			isset( $this->subscription_intervals[ $download['product_id'] ] ) &&
			! empty( $this->subscription_intervals[ $download['product_id'] ] ) ) {

			/*if ( isset( $_GET['debug'] ) && 1989 === $download['product_id'] ) {
				print_r( '<pre>' );
				print_r( $this->subscription_intervals[ $download['product_id'] ] );
				print_r( ')</pre>' );
			}*/

			foreach ( $this->subscription_intervals[ $download['product_id'] ] as $dates ) {

				/*if ( isset( $_GET['debug'] ) ) {
					print_r( '<pre>' );
					print_r( $download['product_id'] . ' ' . $filename );
					print_r( ' ' );
					print_r( $file_date->getTimestamp() );
					print_r( ' ' );
					print_r( $file_date->format( 'Y-m-d h:i:s' ) );
					print_r( ' ' );
					print_r( $dates['start']->getTimestamp() );
					print_r( ' ' );
					print_r( $dates['start']->format( 'Y-m-d h:i:s' ) );
					print_r( ' ' );
					print_r( $dates['end']->getTimestamp() );
					print_r( ' ' );
					print_r( $dates['end']->format( 'Y-m-d h:i:s' ) );
					print_r( '<br />' );
					print_r( '</pre>' );
				}*/

				if ( ( $dates['start']->getTimestamp() <= $file_date->getTimestamp() && $file_date->getTimestamp() <= $dates['end']->getTimestamp() ) ||
				     ( $dates['start']->getTimestamp() <= $file_end_date->getTimestamp() && $file_end_date->getTimestamp() <= $dates['end']->getTimestamp() )) {
					$return = true;
				}
			}
		}
		return $return;
	}

	/**
	 * Remove Duplicate Downloads
	 * @param $downloads
	 * @return array
	 */
	private function remove_duplicate_downloads( $downloads ) {
		$new_downloads = $downloads;
		if ( ! empty( $downloads ) ) {
			$download_sorter = array();
			foreach ( $downloads as $download_index => $download ) {
				if ( isset( $download['download_name'] ) ) {
					$did = $download['download_name'];
				} else {
					$did = $download['name'];
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

	/**
	 * Runs through the file names array and select the date
	 *
	 * @param $key string | boolean
	 * @param $file_name string | boolean
	 * @return string | false
	 */
	private function get_file_end_date_by_name( $key = false, $file_name = false ) {
		$return = false;
		if ( false !== $key &&
		     false !== $file_name &&
		     false !== $this->file_end_dates &&
		     false !== $this->downloadable_files &&
		     isset( $this->downloadable_files[ $key ] ) &&
		     is_array( $this->downloadable_files[ $key ] ) ) {

			$index = array_search( $file_name, $this->downloadable_files[ $key ] );

			if ( false !== $index &&
			     isset( $this->file_end_dates[ $key ] ) &&
			     is_array( $this->file_end_dates[ $key ] ) ) {

				$return = $this->file_end_dates[ $key ][ $index ];
			}
		}
		return $return;
	}

}
