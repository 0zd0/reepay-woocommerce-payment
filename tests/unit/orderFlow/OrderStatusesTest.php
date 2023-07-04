<?php
/**
 * Class OrderStatusesTest
 *
 * @package Reepay\Checkout
 */

use Reepay\Checkout\Api;
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
	private static OrderStatuses $order_statuses;

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
		self::$order_statuses          = new OrderStatuses();
	}

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up() {
		parent::set_up();

		$this->order_generator = new OrderGenerator();
		$this->order_capture   = new OrderCapture();

		new OrderStatuses();

		reepay()->di()->set( Api::class, Api::class );
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses with non reepay payment method
	 */
	public function test_add_valid_order_statuses_with_non_reepay_gateway() {
		$statuses = array( '1', '2' );

		$this->assertSame(
			$statuses,
			self::$order_statuses->add_valid_order_statuses( $statuses, $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses with reepay payment method
	 */
	public function test_add_valid_order_statuses_with_reepay_gateway() {
		$statuses = array( '1', '2' );

		$this->order_generator->set_prop(
			'payment_method',
			reepay()->gateways()->checkout()
		);

		$this->assertSame(
			array_merge( $statuses, array( OrderStatuses::$status_authorized, OrderStatuses::$status_settled ) ),
			self::$order_statuses->add_valid_order_statuses( $statuses, $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses with non reepay payment method
	 */
	public function test_payment_complete_order_status_with_non_reepay_gateway() {
		$status = 'default_status';

		$this->assertSame(
			$status,
			self::$order_statuses->payment_complete_order_status( $status, $this->order_generator->order()->get_id(), $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses with disabled status sync
	 *
	 * @param bool $needs_processing order needs processing.
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

		self::$options->set_options( array(
			'enable_sync' => 'no',
		) );

		$this->assertSame(
			$status,
			self::$order_statuses->payment_complete_order_status( $status, $this->order_generator->order()->get_id(), $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::add_valid_order_statuses with status sync
	 */
	public function test_payment_complete_order_status_with_status_syncs() {
		if ( PLUGINS_STATE::rp_subs_activated() ) {
			$this->markTestSkipped( 'Reepay subscriptions activated. It\'s changing default function behavior via filter' );
		}

		$default_status = 'pending';
		$expected_status = 'completed';

		$this->order_generator->set_props(
			array(
				'payment_method' => reepay()->gateways()->checkout(),
			)
		);

		self::$options->set_options( array(
			'enable_sync' => 'yes',
			'status_settled' => $expected_status
		) );

		$this->assertSame(
			$expected_status,
			self::$order_statuses->payment_complete_order_status( $default_status, $this->order_generator->order()->get_id(), $this->order_generator->order() )
		);
	}

	/**
	 * Test @see OrderStatuses::payment_complete
	 */
	public function test_payment_complete() {
		if ( PLUGINS_STATE::rp_subs_activated() ) {
			$this->markTestSkipped( 'Reepay subscriptions activated. It\'s changing default function behavior via filter' );
		}

		$this->order_generator->set_props(
			array(
				'payment_method' => reepay()->gateways()->checkout(),
			)
		);

		$expected_status = 'pending';

		self::$options->set_options( array(
			'enable_sync' => 'yes',
			'status_settled' => $expected_status
		) );

		self::$order_statuses->payment_complete( $this->order_generator->order()->get_id() );

		$this->order_generator->reset_order();

		$this->assertSame(
			$expected_status,
			$this->order_generator->order()->get_status()
		);
	}
}
