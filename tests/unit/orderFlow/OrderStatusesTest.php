<?php
/**
 * Class OrderStatusesTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\OrderFlow\InstantSettle;

use Reepay\Checkout\OrderFlow\OrderCapture;
use Reepay\Checkout\OrderFlow\OrderStatuses;
use Reepay\Checkout\Tests\Helpers\OptionsController;
use Reepay\Checkout\Tests\Helpers\OrderGenerator;
use Reepay\Checkout\Tests\Helpers\PLUGINS_STATE;
use Reepay\Checkout\Tests\Helpers\ProductGenerator;

/**
 * OrderStatusesTest.
 *
 * @covers \Reepay\Checkout\OrderFlow\OrderStatuses
 */
class OrderStatusesTest extends WP_UnitTestCase {
	/**
	 * OptionsController instance
	 *
	 * @var OptionsController
	 */
	private static OptionsController $options;

	/**
	 * ProductGenerator instance
	 *
	 * @var ProductGenerator
	 */
	private static ProductGenerator $product_generator;

	/**
	 * InstantSettle instance
	 *
	 * @var InstantSettle
	 */
	private static InstantSettle $instant_settle_instance;

	/**
	 * OrderCapture instance
	 *
	 * @var OrderStatuses
	 */
	private OrderStatuses $order_statuses;

	/**
	 * OrderCapture instance
	 *
	 * @var OrderCapture
	 */
	private OrderCapture $order_capture;

	/**
	 * OrderGenerator instance
	 *
	 * @var OrderGenerator
	 */
	private OrderGenerator $order_generator;

	/**
	 * Runs the routine before setting up all tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$options                 = new OptionsController();
		self::$product_generator       = new ProductGenerator();
		self::$instant_settle_instance = new InstantSettle();
	}

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up() {
		parent::set_up();

		$this->order_generator = new OrderGenerator();
		$this->order_capture   = new OrderCapture();
		$this->order_statuses  = new OrderStatuses();

		self::$options->set_options(
			array(
				'enable_sync' => 'no',
			)
		);
	}

	/**
	 * Test function added to filter woocommerce_settings_api_form_fields_reepay_checkout @see OrderStatuses::form_fields()
	 */
	public function test_form_fields_filter() {
		$filter_name = 'reepay_checkout_form_fields';

		remove_all_actions( $filter_name );

		new OrderStatuses();

		$this->assertNotEmpty( apply_filters( $filter_name, array() ) );
	}

	/**
	 * Test @see OrderStatuses::plugins_loaded()
	 *
	 * @param string $status
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::order_statuses()
	 */
	public function test_payment_complete_action_setted( string $status ) {
		remove_all_actions( 'plugins_loaded' );

		$order_statuses = new OrderStatuses();

		do_action( 'plugins_loaded' );

		$this->assertTrue(
			has_action( 'woocommerce_payment_complete_order_status_' . $status, array( $order_statuses, 'payment_complete' ) ) > 0
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses_for_payment_complete with non reepay payment method
	 */
	public function test_add_valid_order_statuses_for_payment_complete_with_non_reepay_gateway() {
		$statuses = array( '1', '2' );

		$this->assertSame(
			$statuses,
			$this->order_statuses->add_valid_order_statuses_for_payment_complete( $statuses, $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses_for_payment_complete with disabled sync
	 */
	public function test_add_valid_order_statuses_for_payment_complete_with_disabled_sync() {
		$statuses = array( '1', '2' );

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		self::$options->set_options(
			array(
				'enable_sync' => 'no',
			)
		);

		$this->assertSame(
			$statuses,
			$this->order_statuses->add_valid_order_statuses_for_payment_complete( $statuses, $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses_for_payment_complete with disabled sync
	 */
	public function test_add_valid_order_statuses_for_payment_complete() {
		$statuses = array( '1', '2' );

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		self::$options->set_options(
			array(
				'enable_sync' => 'yes',
			)
		);

		$this->assertSame(
			array_merge( $statuses, array( OrderStatuses::$status_authorized, OrderStatuses::$status_settled ) ),
			$this->order_statuses->add_valid_order_statuses_for_payment_complete( $statuses, $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses_for_payment_complete with non reepay payment method
	 */
	public function test_payment_complete_order_status_with_non_reepay_gateway() {
		$status = 'default_status';

		$this->assertSame(
			$status,
			$this->order_statuses->payment_complete_order_status( $status, $this->order_generator->order()->get_id(), $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses_for_payment_complete with disabled status sync
	 *
	 * @param bool   $needs_processing order needs processing.
	 * @param string $status expected status.
	 *
	 * @testWith
	 * [true, "processing"]
	 * [false, "completed"]
	 */
	public function test_payment_complete_order_status_with_disabled_status_sync( bool $needs_processing, string $status ) {
		if ( PLUGINS_STATE::rp_subs_activated() ) {
			$this->markTestSkipped( 'Reepay subscriptions activated. It\'s changing default function behavior via filter' );
		}

		set_transient( 'wc_order_' . $this->order_generator->order()->get_id() . '_needs_processing', $needs_processing ? '1' : '0' );

		$this->order_generator->set_props(
			array(
				'payment_method' => reepay()->gateways()->checkout(),
			)
		);

		self::$options->set_options(
			array(
				'enable_sync' => 'no',
			)
		);

		$this->assertSame(
			$status,
			$this->order_statuses->payment_complete_order_status( $status, $this->order_generator->order()->get_id(), $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses_for_payment_complete with status sync
	 */
	public function test_payment_complete_order_status_with_status_syncs() {
		if ( PLUGINS_STATE::rp_subs_activated() ) {
			$this->markTestSkipped( 'Reepay subscriptions activated. It\'s changing default function behavior via filter' );
		}

		$default_status  = 'pending';
		$expected_status = 'completed';

		$this->order_generator->set_props(
			array(
				'payment_method' => reepay()->gateways()->checkout(),
			)
		);

		self::$options->set_options(
			array(
				'enable_sync'    => 'yes',
				'status_settled' => $expected_status,
			)
		);

		$this->assertSame(
			$expected_status,
			$this->order_statuses->payment_complete_order_status( $default_status, $this->order_generator->order()->get_id(), $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::payment_complete
	 *
	 * @param string $expected_status
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::order_statuses()
	 */
	public function test_payment_complete( string $expected_status ) {
		if ( PLUGINS_STATE::rp_subs_activated() ) {
			$this->markTestSkipped( 'Reepay subscriptions activated. It\'s changing default function behavior via filter' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );

		self::$options->set_options(
			array(
				'enable_sync'    => 'yes',
				'status_settled' => $expected_status,
			)
		);

		$this->order_statuses->payment_complete( $this->order_generator->order()->get_id() );

		$this->order_generator->reset_order();

		$this->assertSame(
			$expected_status,
			$this->order_generator->order()->get_status()
		);
	}

	/**
	 * Test @see OrderStatuses::get_authorized_order_status with non reepay payment method
	 */
	public function test_get_authorized_order_status_with_non_reepay_gateway() {
		$status = 'default_status';

		$this->assertSame(
			$status,
			$this->order_statuses->get_authorized_order_status( $this->order_generator->order(), $status )
		);
	}

	/**
	 * Test @see OrderStatuses::get_authorized_order_status with woo subscription
	 */
	public function test_get_authorized_order_status_with_woo_subscription() {
		if ( ! PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'Woocommerce subscriptions not activated' );
		}

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		$this->order_generator->add_product( 'woo_sub' );

		$this->assertSame(
			'on-hold',
			$this->order_statuses->get_authorized_order_status( $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::get_authorized_order_status without reepay order status sync
	 */
	public function test_get_authorized_order_status_without_sync() {
		$status = 'default_status';

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		$this->order_generator->add_product( 'simple' );

		self::$options->set_options(
			array(
				'enable_sync' => 'no',
			)
		);

		$this->assertSame(
			$status,
			$this->order_statuses->get_authorized_order_status( $this->order_generator->order(), $status )
		);
	}

	/**
	 * Test @see OrderStatuses::get_authorized_order_status with reepay order status sync
	 *
	 * @param string $sync_status
	 *
	 * @dataProvider \Reepay\Checkout\Tests\Helpers\DataProvider::order_statuses()
	 */
	public function testget_authorized_order_status_with_sync( string $sync_status ) {
		$status = 'default_status';

		$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		$this->order_generator->add_product( 'simple' );

		self::$options->set_options(
			array(
				'enable_sync'       => 'yes',
				'status_authorized' => $sync_status,
			)
		);

		$this->assertSame(
			$sync_status,
			$this->order_statuses->get_authorized_order_status( $this->order_generator->order(), $status )
		);
	}

	/**
	 * Test @see OrderStatuses::set_authorized_status with _reepay_state_authorized meta
	 */
	public function test_set_authorized_status_already_authorized() {
		$this->order_generator->set_meta('_reepay_state_authorized', 1);


	}

	/**
	 * Test @see OrderStatuses::set_authorized_status with _reepay_state_authorized meta
	 */
	public function test_set_authorized_status_already_authorized2() {
		self::$options->set_options(
			array(
				'enable_sync'       => 'yes',
				'status_authorized' => 'completed',
			)
		);
	}

	/**
	 * Test @see OrderStatuses::set_settled_status
	 */
	public function test_set_settled_status() {

	}

	/**
	 * Test @see OrderStatuses::update_order_status
	 */
	public function test_update_order_status() {
		$order_status   = 'completed';
		$transaction_id = 'transaction_id_123';

		OrderStatuses::update_order_status(
			$this->order_generator->order(),
			$order_status,
			'',
			$transaction_id,
			true
		);

		$this->order_generator->reset_order();

		$this->assertSame( $order_status, $this->order_generator->order()->get_status() );
		$this->assertSame( $transaction_id, $this->order_generator->order()->get_transaction_id() );
	}

	/**
	 * Test @see OrderStatuses::is_editable
	 *
	 * @param bool $default_value
	 * @param bool $status_sync_enabled
	 * @param bool $paid_via_reepay
	 * @param bool $order_has_settled_status
	 * @param bool $order_has_authorized_status
	 * @param bool $expected_value
	 *
	 * @testWith
	 * [false, false, false, false, false, false]
	 * [false, false, false, false, true, false]
	 * [false, false, false, true, false, false]
	 * [false, false, true, false, false, false]
	 * [false, false, true, false, true, false]
	 * [false, false, true, true, false, false]
	 * [false, false, false, false, false, false]
	 * [false, false, false, false, true, false]
	 * [false, true, false, true, false, false]
	 * [false, true, true, false, false, false]
	 * [false, true, true, false, true, true]
	 * [false, true, true, true, false, true]
	 * [true, true, false, false, false, true]
	 * [true, true, false, false, true, true]
	 * [true, false, false, true, false, true]
	 * [true, false, true, false, false, true]
	 * [true, false, true, false, true, true]
	 * [true, false, true, true, false, true]
	 * [true, true, false, false, false, true]
	 * [true, true, false, false, true, true]
	 * [true, true, false, true, false, true]
	 * [true, true, true, false, false, true]
	 * [true, true, true, false, true, true]
	 * [true, true, true, true, false, true]
	 */
	public function test_is_editable( bool $default_value, bool $status_sync_enabled, bool $paid_via_reepay, bool $order_has_settled_status, bool $order_has_authorized_status, bool $expected_value ) {
		if ( $order_has_settled_status && $order_has_authorized_status ) {
			$this->markTestSkipped( '$order_has_settled_status and $order_has_authorized_status are the same true' );
		}

		$status_settled    = 'pending';
		$status_authorized = 'processing';
		$order_status      = 'completed';

		if ( $order_has_settled_status ) {
			$order_status = $status_settled;
		} elseif ( $order_has_authorized_status ) {
			$order_status = $status_authorized;
		}

		$this->order_generator->set_prop( 'status', $order_status );

		if ( $paid_via_reepay ) {
			$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		}

		self::$options->set_options(
			array(
				'enable_sync'       => $status_sync_enabled ? 'yes' : 'no',
				'status_settled'    => $status_settled,
				'status_authorized' => $status_authorized,
			)
		);

		$this->assertSame(
			$expected_value,
			$this->order_statuses->is_editable( $default_value, $this->order_generator->order() ),
			var_export( func_get_args(), true )
		);
	}

	/**
	 * Test @see OrderStatuses::is_paid
	 *
	 * @param bool $default_value
	 * @param bool $status_sync_enabled
	 * @param bool $paid_via_reepay
	 * @param bool $order_has_settled_status
	 * @param bool $expected_value
	 *
	 * @testWith
	 * [false, false, false, false, false]
	 * [false, false, false, true, false]
	 * [false, false, true, false, false]
	 * [false, false, true, true, false]
	 * [false, true, false, false, false]
	 * [false, true, false, true, false]
	 * [false, true, true, false, false]
	 * [false, true, true, true, true]
	 * [true, false, false, false, true]
	 * [true, false, false, true, true]
	 * [true, false, true, false, true]
	 * [true, false, true, true, true]
	 * [true, true, false, false, true]
	 * [true, true, false, true, true]
	 * [true, true, true, false, true]
	 * [true, true, true, true, true]
	 */
	public function test_is_paid( bool $default_value, bool $status_sync_enabled, bool $paid_via_reepay, bool $order_has_settled_status, bool $expected_value ) {
		$status_settled     = 'pending';
		$status_not_settled = 'completed';

		self::$options->set_options(
			array(
				'enable_sync'    => $status_sync_enabled ? 'yes' : 'no',
				'status_settled' => $status_settled,
			)
		);

		if ( $paid_via_reepay ) {
			$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		}

		$this->order_generator->set_prop( 'status', $order_has_settled_status ? $status_settled : $status_not_settled );

		$this->assertSame(
			$expected_value,
			$this->order_statuses->is_paid( $default_value, $this->order_generator->order() ),
			var_export( func_get_args(), true )
		);
	}

	/**
	 * Test @see OrderStatuses::cancel_unpaid_order
	 *
	 * @param bool $default_value
	 * @param bool $paid_via_reepay
	 * @param bool $enabled_order_autocancel
	 * @param bool $expected_value
	 *
	 * @testWith
	 * [false, false, false, false]
	 * [false, false, true, false]
	 * [false, true, false, false]
	 * [false, true, true, false]
	 * [true, false, false, true]
	 * [true, false, true, true]
	 * [true, true, false, false]
	 * [true, true, true, true]
	 */
	public function test_cancel_unpaid_order( bool $default_value, bool $paid_via_reepay, bool $enabled_order_autocancel, bool $expected_value ) {
		if ( $paid_via_reepay ) {
			$this->order_generator->set_prop( 'payment_method', reepay()->gateways()->checkout() );
		}

		self::$options->set_options(
			array(
				'enable_order_autocancel' => $enabled_order_autocancel ? 'yes' : 'no',
			)
		);

		$this->assertSame(
			$expected_value,
			$this->order_statuses->cancel_unpaid_order( $default_value, $this->order_generator->order() ),
			var_export( func_get_args(), true )
		);
	}
}
