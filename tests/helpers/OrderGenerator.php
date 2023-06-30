<?php
/**
 * Class OrderGenerator
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout\Tests\Helpers;

use WC_Order;
use WC_Order_Factory;
use WC_Order_Item;
use WC_Order_Item_Fee;
use WC_Order_Item_Shipping;
use WC_Order_Item_Tax;
use WC_Tax;

/**
 * Class OrderGenerator
 */
class OrderGenerator {
	/**
	 * Current order
	 *
	 * @var WC_Order|null
	 */
	private ?WC_Order $order;

	/**
	 * OrderGenerator constructor.
	 *
	 * @param array $args args to wc_create_order.
	 */
	public function __construct( array $args = array() ) {
		$this->generate( $args );
	}

	/**
	 * Create order
	 *
	 * @param array $args args to wc_create_order.
	 */
	public function generate( array $args = array() ) {
		$this->order = wc_create_order(
			wp_parse_args(
				$args,
				array(
					'status'      => 'completed',
					'created_via' => 'tests',
				)
			)
		);
	}

	/**
	 * Ger order
	 *
	 * @return WC_Order|null
	 */
	public function order(): ?WC_Order {
		return $this->order;
	}

	/**
	 * Update meta data by key
	 *
	 * @param string       $key   meta key.
	 * @param string|array $value meta value.
	 */
	public function set_meta( string $key, $value ) {
		$this->order->update_meta_data( $key, $value );
		$this->order->save();
	}

	/**
	 * Update property by key
	 *
	 * @param string $key   property key.
	 * @param mixed  $value property value.
	 */
	public function set_prop( string $key, $value ) {
		$this->set_props( array( $key => $value ) );
	}

	/**
	 * Set a collection of props in one go, collect any errors, and return the result.
	 * Only sets using public methods.
	 *
	 * @param array $props Key value pairs to set. Key is the prop and should map to a setter function name.
	 */
	public function set_props( array $props ) {
		$this->order->set_props( $props );
	}

	/**
	 * Get Meta Data by Key.
	 *
	 * @param string $key meta key.
	 *
	 * @return mixed
	 */
	public function get_meta( string $key ) {
		return $this->order->get_meta( $key );
	}

	/**
	 * Add product to order
	 *
	 * @param string $type            product type.
	 * @param array  $product_data    product meta data.
	 * @param array  $order_item_data order item meta data.
	 *
	 * @return int order item id
	 */
	public function add_product( string $type, array $product_data = array(), array $order_item_data = array() ): int {
		$order_item_id = $this->order->add_product(
			( new ProductGenerator( $type, $product_data ) )->product(),
			$order_item_data['quantity'] ?? 1
		);

		$this->add_data_to_order_item( $order_item_id, $order_item_data );

		return $order_item_id;
	}

	/**
	 * Add fee to order
	 *
	 * @param array $data optional. Fee data.
	 *
	 * @return int order item id
	 */
	public function add_fee( array $data = array() ): int {
		$item = new WC_Order_Item_Fee();
		$item->save();

		$this->add_data_to_order_item(
			$item,
			wp_parse_args(
				$data,
				array(
					'name'      => 'Test fee',
					'total'     => 0,
					'total_tax' => 0,
				)
			)
		);

		$this->order->add_item( $item );

		return $item->get_id();
	}

	/**
	 * Add shipping to order
	 *
	 * @param array $data optional. Shipping data.
	 *
	 * @return int order item id
	 */
	public function add_shipping( array $data = array() ): int {
		$item = new WC_Order_Item_Shipping();
		$item->save();

		$this->add_data_to_order_item(
			$item,
			wp_parse_args(
				$data,
				array(
					'method_title' => 'Test shipping method',
					'total'        => 0,
				)
			)
		);

		$this->order->add_item( $item );

		$this->order->calculate_shipping();

		return $item->get_id();
	}

	/**
	 * Add shipping to order
	 *
	 * @param array $data optional. Shipping data.
	 *
	 * @return int order item id
	 */
	public function add_tax( float $tax_rate, string $tax_rate_name = 'test'  ): int {
		$tax_rate_id = WC_Tax::_insert_tax_rate( array(
			'tax_rate'          => $tax_rate,
			'tax_rate_name'     => $tax_rate_name,
		) );

		$item = new WC_Order_Item_Tax();

		$item->set_props( array(
			'rate'               => $tax_rate_id,
			'order_id'           => $this->order->get_id()
		) );

		$item->save();

		$this->order->add_item( $item );

		$this->order->calculate_taxes();

		return $item->get_id();
	}

	/**
	 * @param WC_Order_Item|int $order_item
	 * @param array             $data
	 */
	protected function add_data_to_order_item( $order_item, array $data ) {
		if ( empty( $data ) ) {
			return;
		}

		if ( is_int( $order_item ) ) {
			$order_item = WC_Order_Factory::get_order_item( $order_item );
		}

		foreach ( $data as $key => $value ) {
			$function = 'set_' . ltrim( $key, '_' );

			if ( is_callable( array( $order_item, $function ) ) ) {
				$order_item->{$function}( $value );
			} else {
				$order_item->update_meta_data( $key, $value );
			}
		}

		$order_item->save();
	}
}