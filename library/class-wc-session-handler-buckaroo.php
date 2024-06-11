<?php
use Automattic\Jetpack\Constants;

/**
 * @package Buckaroo
 */
class WC_Session_Handler_Buckaroo extends WC_Session_Handler {

	public function set_customer_session_cookie( $set ) {
		if ( $set ) {
			$to_hash           = $this->_customer_id . '|' . $this->_session_expiration;
			$cookie_hash       = hash_hmac( 'md5', $to_hash, wp_hash( $to_hash ) );
			$cookie_value      = $this->_customer_id . '||' . $this->_session_expiration . '||' . $this->_session_expiring . '||' . $cookie_hash;
			$this->_has_cookie = true;

			if ( ! isset( $_COOKIE[ $this->_cookie ] ) || $_COOKIE[ $this->_cookie ] !== $cookie_value ) {
				$this->wc_setcookie( $this->_cookie, $cookie_value, $this->_session_expiration, $this->use_secure_cookie(), true );
			}
		}
	}

	function wc_setcookie( $name, $value, $expire = 0, $secure = false, $httponly = false ) {
		if ( ! headers_sent() ) {
			$arr_cookie_options = array(
				'expires'  => $expire,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => $secure,
				'httponly' => apply_filters( 'woocommerce_cookie_httponly', $httponly, $name, $value, $expire, $secure ),
				'samesite' => 'None, Secure',
			);
			setcookie( $name, $value, $arr_cookie_options );
		} elseif ( Constants::is_true( 'WP_DEBUG' ) ) {
			headers_sent( $file, $line );
            trigger_error( "{$name} cookie cannot be set - headers already sent by {$file} on line {$line}", E_USER_NOTICE ); // @codingStandardsIgnoreLine
		}
	}
}
