<?php
/**
 * Class GatewaysTest
 *
 * @package Reepay\Checkout
 */

/**
 * CurrencyTest.
 */
class OrderTest extends WP_UnitTestCase {
	/**
	 * WC_Order instance
	 *
	 * @var WC_Order
	 */
	private WC_Order $order;

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up() {
		parent::set_up();

		$this->order = wc_create_order(
			array(
				'status'      => 'completed',
				'created_via' => 'tests',
				'cart_hash'   => 'cart_hash',
			)
		);
	}

	/**
	 * After a test method runs, resets any state in WordPress the test method might have changed.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->order->delete( true );
	}

	/**
	 * Test function rp_get_order_handle
	 */
	public function test_rp_get_order_handle() {
		$this->assertSame(
			'order-' . $this->order->get_id(),
			rp_get_order_handle( $this->order ),
			'Wrong order handle generated'
		);

		$this->assertSame(
			'order-' . $this->order->get_id(),
			$this->order->get_meta( '_reepay_order' ),
			'Wrong order handle saved in meta'
		);

		$this->assertSame(
			'order-' . $this->order->get_id(),
			rp_get_order_handle( $this->order ),
			'Wrong order handle returned'
		);

		$this->assertSame(
			'order-' . $this->order->get_id() . '-' . time(),
			rp_get_order_handle( $this->order, true ),
			'Wrong new unique order handle generated'
		);

		$this->assertSame(
			'order-' . $this->order->get_id() . '-' . time(),
			$this->order->get_meta( '_reepay_order' ),
			'Wrong unique order handle saved in meta'
		);

		$this->order->delete_meta_data( '_reepay_order' );
		$this->order->save();
	}

	/**
	 * Test function rp_get_order_by_handle_found
	 */
	public function test_rp_get_order_by_handle_found() {
		$handle = 'order-1234';
		$this->order->add_meta_data( '_reepay_order', $handle );
		$this->order->save();

		$order = rp_get_order_by_handle( $handle );

		$this->assertSame(
			$this->order->get_id(),
			$order ? $order->get_id() : false
		);

		$this->order->delete_meta_data( '_reepay_order' );
		$this->order->save();
	}

	/**
	 * Test function rp_get_order_by_handle_not_found
	 */
	public function test_rp_get_order_by_handle_not_found() {
		$handle = 'order-1234';

		$this->assertFalse( rp_get_order_by_handle( $handle ) );
	}

	/**
	 * Test function rp_get_order_by_handle_cache
	 */
	public function test_rp_get_order_by_handle_cache() {
		$handle = 'order-1234';
		$this->order->add_meta_data( '_reepay_order', $handle );
		$this->order->save();

		rp_get_order_by_handle( $handle );
		rp_get_order_by_handle( $handle );
		$order = rp_get_order_by_handle( $handle );

		$this->assertSame(
			$this->order->get_id(),
			$order ? $order->get_id() : false
		);

		$this->order->delete_meta_data( '_reepay_order' );
		$this->order->save();
	}

	/**
	 * Test function rp_get_order_by_session_found
	 */
	public function test_rp_get_order_by_session_found() {
		$session_id = 'sid_1234';
		$this->order->add_meta_data( 'reepay_session_id', $session_id );
		$this->order->save();

		$order = rp_get_order_by_session( $session_id );

		$this->assertSame(
			$this->order->get_id(),
			$order ? $order->get_id() : false
		);

		$this->order->delete_meta_data( 'reepay_session_id' );
		$this->order->save();
	}

	/**
	 * Test function rp_get_order_by_session_not_found
	 */
	public function test_rp_get_order_by_session_not_found() {
		$session_id = 'sid_1234';

		$this->assertFalse( rp_get_order_by_session( $session_id ) );
	}

	/**
	 * Test function rp_get_order_by_session_cache
	 */
	public function test_rp_get_order_by_session_cache() {
		$session_id = 'sid_1234';
		$this->order->add_meta_data( 'reepay_session_id', $session_id );
		$this->order->save();

		rp_get_order_by_session( $session_id );
		rp_get_order_by_session( $session_id );
		$order = rp_get_order_by_session( $session_id );

		$this->assertSame(
			$this->order->get_id(),
			$order ? $order->get_id() : false
		);

		$this->order->delete_meta_data( 'reepay_session_id' );
		$this->order->save();
	}

	/**
	 * Test function rp_is_order_paid_via_reepay
	 *
	 * @param string       $method_name payment method name.
	 * @param string|false $class payment method class name.
	 * @param bool         $is_reepay is reepay gateway.
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\HELPERS::get_payment_methods
	 */
	public function test_rp_is_order_paid_via_reepay( string $method_name, $class, bool $is_reepay ) {
		$this->order->set_payment_method( $method_name );

		$this->assertSame(
			$is_reepay,
			rp_is_order_paid_via_reepay( $this->order )
		);
	}
}
