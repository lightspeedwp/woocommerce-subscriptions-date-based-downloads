<?php

namespace woocommerce;

/**
 * Main Class
 *
 * @package   WooCommerce Subscription Date Based Downloads
 * @author    LightSpeed
 * @license   GPL-3.0+
 * @link
 * @copyright 2017 LightSpeedDevelopment
 */

class Product_Download_Dates {

	/**
	 * Holds the edit class
	 * @var array
	 */
	var $edit = false;

	/**
	 * Holds the edit class
	 * @var array
	 */
	var $frontend = false;

	/**
	 * Holds instance of the class
	 */
	private static $instance;

	/**
	 * Constructor.
	 */
	public function __construct() {
		require_once( WC_PDD_PATH . 'classes/class-product-downloads-edit.php' );
		require_once( WC_PDD_PATH . 'classes/class-product-downloads-frontend.php' );
		$this->setup();

	}

	/**
	 * Return an instance of this class.
	 *
	 * @return  object
	 */
	public static function init() {

		// If the single instance hasn't been set, set it now.
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Setup hooks and text load domain
	 */
	public function setup() {
		$this->edit = new Product_Downloads_Edit();
		$this->frontend = new Product_Downloads_Frontend();
	}

}
