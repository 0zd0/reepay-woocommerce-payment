<?php

use Automattic\WooCommerce\Blocks\Assets\Api as AssetApi;
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Automattic\WooCommerce\Blocks\Registry\Container;

defined( 'ABSPATH' ) || exit;

class Reepay_Woo_Blocks_Integration {
	public function __construct() {
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'register_payment_method_integrations' ) );
	}

	/**
	 * Register payment method integrations bundled with blocks.
	 *
	 * @param PaymentMethodRegistry $payment_method_registry Payment method registry instance.
	 */
	public function register_payment_method_integrations( PaymentMethodRegistry $payment_method_registry ) {
		include_once 'class-reepay-woo-blocks-payment-method.php';

		foreach (WC_ReepayCheckout::PAYMENT_METHODS as $payment_method) {
			Package::container()->register(
				$payment_method,
				function( Container $container ) use ( $payment_method ){
					$asset_api = $container->get( AssetApi::class );
					return new Reepay_Woo_Blocks_Payment_Method( $payment_method, $asset_api );
				}
			);

			$payment_method_registry->register(
				Package::container()->get( $payment_method )
			);
		}
	}
}