<?php
/**
 * Reepay subscriptions token
 *
 * @package Reepay\Checkout\Tokens
 */

namespace Reepay\Checkout\Tokens;

use Exception;
use WC_HTTPS;
use WC_Payment_Gateway;
use WC_Payment_Token;
use WC_Payment_Token_CC;
use Reepay\Checkout\Tokens\TokenReepayTrait;

defined( 'ABSPATH' ) || exit();

/**
 * Class TokenReepay
 *
 * @package Reepay\Checkout\Tokens
 */
class TokenReepay extends WC_Payment_Token_CC {
	/**
	 * Token Type String.
	 *
	 * @var string
	 */
	protected $type = 'Reepay';

	/**
	 * Stores Credit Card payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = array(
		'last4'        => '',
		'expiry_year'  => '',
		'expiry_month' => '',
		'card_type'    => '',
		'masked_card'  => '',
	);

	/**
	 * Get type to display to user.
	 *
	 * @param string $deprecated Deprecated since WooCommerce 3.0.
	 *
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ): string {
		$img   = $this->get_card_image_url();
		$style = '';

		if ( $this->get_card_type() === 'visa_dk' ) {
			$style = 'style="width: 46px; height: 24px;"';
		}

		$type             = $this->get_card_type();
		$reepay_logo_url  = reepay()->get_setting( 'images_url' ) . $type . '.png';
		$reepay_logo_path = reepay()->get_setting( 'images_path' ) . $type . '.png';
		if ( file_exists( $reepay_logo_path ) ) {
			$img   = $reepay_logo_url;
			$style = 'style="width: 46px; height: 24px;"';
		}

		ob_start();
		?>
		<img <?php echo $style; ?> src="<?php echo $img; ?>"
								   alt="<?php echo wc_get_credit_card_type_label( $this->get_card_type() ); ?>"/>
		<?php echo esc_html( $this->get_masked_card() ); ?>
		<?php echo esc_html( $this->get_expiry_month() . '/' . substr( $this->get_expiry_year(), 2 ) ); ?>

		<?php
		$display = ob_get_contents();
		ob_end_clean();

		return $display;
	}

	/**
	 * Get card image url
	 *
	 * @return string
	 */
	public function get_card_image_url(): string {
		if ( $this->get_card_type() === 'visa_dk' ) {
			return reepay()->get_setting( 'images_url' ) . 'dankort.png';
		} else {
			return WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/' . $this->get_card_type() . '.png' );
		}
	}

	/**
	 * Validate credit card payment tokens.
	 *
	 * @return boolean True if the passed data is valid
	 */
	public function validate(): bool {
		if ( false === parent::validate() ) {
			return false;
		}

		if ( ! $this->get_masked_card( 'edit' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Hook prefix
	 *
	 * @return string
	 */
	protected function get_hook_prefix(): string {
		return 'woocommerce_payment_token_reepay_get_';
	}

	/**
	 * Returns Masked Card
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string Masked Card
	 */
	public function get_masked_card( $context = 'view' ): string {
		return $this->get_prop( 'masked_card', $context );
	}

	/**
	 * Set the last four digits.
	 *
	 * @param string $masked_card Masked Card.
	 */
	public function set_masked_card( string $masked_card ) {
		$this->set_prop( 'masked_card', $masked_card );
	}

	/**
	 * Returns if the token is marked as default.
	 *
	 * @return boolean True if the token is default
	 */
	public function is_default(): bool {
		$change_payment_method = isset( $_GET['change_payment_method'] ) ? wc_clean( $_GET['change_payment_method'] ) : '';

		// Mark Method as Checked on "Payment Change" page.
		if ( $change_payment_method > 0 && wcs_is_payment_change() ) {
			$subscription = wcs_get_subscription( $change_payment_method );
			$tokens       = $subscription->get_payment_tokens();
			foreach ( $tokens as $token_id ) {
				if ( $this->get_id() === (int) $token_id ) {
					return true;
				}
			}

			return false;
		}

		return parent::is_default();
	}

	/**
	 * Register actions for displaying token
	 */
	public static function register_view_actions() {
		add_filter( 'woocommerce_payment_methods_list_item', array( __CLASS__, 'wc_get_account_saved_payment_methods_list_item' ), 10, 2 );
		add_action( 'woocommerce_account_payment_methods_column_method', __CLASS__ . '::wc_account_payment_methods_column_method', 10, 1 );
		add_filter( 'woocommerce_payment_gateway_get_saved_payment_method_option_html', __CLASS__ . '::wc_get_saved_payment_method_option_html', 10, 3 );
		add_filter( 'woocommerce_get_customer_payment_tokens', __CLASS__ . '::add_reepay_cards_to_list', 10, 3 );
	}

	/**
	 * Controls the output for credit cards on the my account page.
	 *
	 * @param array  $tokens      Tokens list.
	 * @param int    $customer_id Id of the customer.
	 * @param string $gateway_id  Gateway ID.
	 *
	 * @return array                           Filtered item.
	 * @throws Exception If unable to save token.
	 */
	public static function add_reepay_cards_to_list( array $tokens, int $customer_id, string $gateway_id ): array {
		if ( reepay()->gateways()->checkout()->id !== $gateway_id ) {
			return $tokens;
		}

		$reepay_cards = reepay()->api( $gateway_id )->get_reepay_cards( rp_get_customer_handle( $customer_id ) );

		if ( is_wp_error( $reepay_cards ) || empty( $reepay_cards ) ) {
			return $tokens;
		}

		$tokens = array();

		foreach ( $reepay_cards as $card_info ) {
			$token = TokenReepayTrait::get_payment_token( $card_info['id'] );

			if ( ! empty( $token ) ) {
				continue;
			}

			if ( 'ms_' === substr( $card_info['id'], 0, 3 ) ) {
				$token = new TokenReepayMS();
				$token->set_gateway_id( $gateway_id );
				$token->set_token( $card_info['id'] );
				$token->set_user_id( $customer_id );
			} else {
				$expiry_date = explode( '-', $card_info['exp_date'] );

				$token = new TokenReepay();
				$token->set_gateway_id( $gateway_id );
				$token->set_token( $card_info['id'] );
				$token->set_last4( substr( $card_info['masked_card'], - 4 ) );
				$token->set_expiry_year( 2000 + $expiry_date[1] );
				$token->set_expiry_month( $expiry_date[0] );
				$token->set_card_type( $card_info['card_type'] );
				$token->set_user_id( $customer_id );
				$token->set_masked_card( $card_info['masked_card'] );
			}

			// Save Credit Card.
			if ( ! $token->save() ) {
				throw new Exception( __( 'There was a problem adding the card.', 'reepay-checkout-gateway' ) );
			}

			$tokens[] = $token;
		}

		return $tokens;
	}

	/**
	 * Controls the output for credit cards on the my account page.
	 *
	 * @param array            $item          Individual list item from woocommerce_saved_payment_methods_list.
	 * @param WC_Payment_Token $payment_token The payment token associated with this method entry.
	 *
	 * @return array                           Filtered item.
	 */
	public static function wc_get_account_saved_payment_methods_list_item( array $item, WC_Payment_Token $payment_token ): array {
		if ( 'reepay_checkout' !== $payment_token->get_gateway_id() ) {
			return $item;
		}

		if ( ! method_exists( $payment_token, 'get_card_type' ) ) {
			return $item;
		}

		$card_type               = $payment_token->get_card_type();
		$item['method']['id']    = $payment_token->get_id();
		$item['method']['last4'] = $payment_token->get_last4();
		$item['method']['brand'] = ( ! empty( $card_type ) ? ucfirst( $card_type ) : esc_html__( 'Credit card', 'woocommerce' ) );

		$item['expires'] = $payment_token->get_expiry_month() . '/' . substr( $payment_token->get_expiry_year(), - 2 );

		return $item;
	}

	/**
	 * Controls the output for credit cards on the my account page.
	 *
	 * @param array $method payment method from wc_get_customer_saved_methods_list.
	 *
	 * @return void
	 * @see wc_get_customer_saved_methods_list
	 */
	public static function wc_account_payment_methods_column_method( array $method ) {
		if ( 'reepay_checkout' !== $method['method']['gateway'] ) {
			return;
		}

		if ( rp_is_reepay_payment_method( $method['method']['gateway'] ) ) {
			$token = new TokenReepay( $method['method']['id'] );
			echo $token->get_display_name();

			return;
		}

		/*
		 * Default output
		 * @see woocommerce/myaccount/payment-methods.php
		 */
		if ( ! empty( $method['method']['last4'] ) ) {
			/* translators: 1: credit card type 2: last 4 digits */
			echo sprintf( __( '%1$s ending in %2$s', 'woocommerce' ), esc_html( wc_get_credit_card_type_label( $method['method']['brand'] ) ), esc_html( $method['method']['last4'] ) );
		} else {
			if ( isset( $method['method']['brand'] ) ) {
				echo esc_html( wc_get_credit_card_type_label( $method['method']['brand'] ) );
			} else {
				echo esc_html( wc_get_credit_card_type_label( 'visa' ) );
			}
		}

	}

	/**
	 * Fix html on Payment methods list
	 *
	 * @param string             $html    option's html.
	 * @param WC_Payment_Token   $token   payment token.
	 * @param WC_Payment_Gateway $gateway token's gateway.
	 *
	 * @return string
	 */
	public static function wc_get_saved_payment_method_option_html( string $html, WC_Payment_Token $token, WC_Payment_Gateway $gateway ): string {
		if ( rp_is_reepay_payment_method( $token->get_gateway_id() ) ) {
			$html = html_entity_decode( $html, ENT_COMPAT | ENT_XHTML, 'UTF-8' );
		}

		return $html;
	}
}
