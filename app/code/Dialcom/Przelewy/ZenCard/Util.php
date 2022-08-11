<?php
/**
 *
 * Zencard_Util
 *
 * @package Zencard
 * @author  Łukasz Lewandowski <lukasz.metys.lewandowski@gmail.com>
 *
 */
namespace Dialcom\Przelewy\ZenCard;

final class Util
{
	// name of Zencard code cookie
    const ZCODE_COOKIE = 'zenC';

    // name of Zencard discount cookie
    const DISCOUNT_COOKIE = 'zenD';

    // name of Zencard extra info cookie
	const EXTRA_INFO_COOKIE = 'zenE';

	/**
	 * Returns cookie value of default value.
	 *
	 * @static
	 *
	 * @param  string $cookie name
	 * @param  mixed  $default default value
	 * @return mixed
	 */
	static public function getCookie( $name, $default = null )
	{
		if ( isset( $_COOKIE[$name] ) ) {

			$value = (string) $_COOKIE[$name];

			if ( strlen( trim($value) ) )
				return $value;
		}

		return $default;
	}

	/**
	 * Returns Zencard code cookie value or null
	 *
	 * @static
	 *
	 * @return string|null
	 */
	static public function getZcode()
	{
		$value = self::getCookie( self::ZCODE_COOKIE, '' );

		if ( preg_match( '/^T\d+\..*/', $value ) )
			return $value;

		return null;
	}

	/**
	 * Returns Zencard extra info cookie value or null
	 *
	 * @static
	 *
	 * @return string|null
	 */
	static public function getExtraInfo()
	{
		return self::getCookie( self::EXTRA_INFO_COOKIE );
	}

	/**
	 * Returns amount with discount
	 *
	 * @static
	 *
	 * @param  integer $amount amount
	 * @return integer amount with discount
	 */
	static public function getAmountWithDiscount( $amount )
	{
		$cookieValue = (string) self::getCookie( self::DISCOUNT_COOKIE );

        if ( ! strlen($cookieValue) )
            return $amount;

        $pair = explode( ':', $cookieValue );

        if ( count($pair) != 2 )
            return $amount;

        $cookieAmount             = (int)$pair[0];
        $cookieAmountWithDiscount = (int)$pair[1];

        if ( $cookieAmount != $amount )
            return $amount;

        return $cookieAmountWithDiscount;
	}
	
	/**
	 * Returns user browser agent and ip address.
	 *
	 * @static
	 *
	 * @return array trace values
	 */
	static public function getTraceInfo()
	{
		return array(
			'userAgent' => self::getRemoteUserAgent(),
			'ip'        => self::getRemoteIp()
		);
	}

	/**
	 * Returns user ip address.
	 *
	 * @static
	 *
	 * @return string
	 */
	static public function getRemoteIp()
	{
		if ( !empty( $_SERVER['HTTP_CLIENT_IP'] ) )
			return $_SERVER['HTTP_CLIENT_IP'];

		if ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
			return $_SERVER['HTTP_X_FORWARDED_FOR'];

		if ( !empty( $_SERVER['REMOTE_ADDR'] ) )
			return $_SERVER['REMOTE_ADDR'];

		return '';
	}

	/**
	 * Returns user browser agent.
	 *
	 * @static
	 *
	 * @return string
	 */
	static public function getRemoteUserAgent()
	{
		if ( !empty( $_SERVER['HTTP_USER_AGENT'] ) )
			return $_SERVER['HTTP_USER_AGENT'];

		return '';
	}

	/**
	 * Constructor.
	 *
	 * @internal
	 * @access private
	 */
	private function __construct()
	{

	}
}