<?php
namespace Antavo;

use Antavo\SignedToken\SignedToken;

/**
 *
 */
class CustomerToken extends SignedToken {
    /**
     * @var string  Name of the cookie under which customer token is stored.
     */
    const COOKIE_NAME = '__alc';

    /**
     * Creates an authenticating token for customer and sets it in a cookie.
     *
     * @param string $customer  Unique customer ID.
     * @param string $secret  API secret.
     * @param int $expires_in  Time-to-live value for cookie (less than 30 days
     * in seconds) or Unix Timestamp of the expiration time. Default is 0 (it
     * expires with the session).
     * @return bool  Returns <tt>TRUE</tt> if cookie is set successfully,
     * <tt>FALSE</tt> otherwise.
     * @static
     */
    public static function auth($customer, $secret, $expires_in = 0) {
        // Creating new token for the customer.
        $token = new static($secret, $expires_in);
        $token->setPayload(compact('customer'));

        // Extending expire time.
        if ($expires_in > 0) {
            $expires_in = $token->getCalculatedExpirationTime();
        }

        // Setting cookie and returning if it was successsful.
        return setcookie(self::COOKIE_NAME, $token, $expires_in, '/', static::getCookieDomain());
    }

    /**
     * Deletes cookie that autenticates customer.
     *
     * @static
     */
    public static function deauth() {
        setcookie(self::COOKIE_NAME, '', time() - 3600, '/', static::getCookieDomain());
    }

    /**
     * Return base domain for site.
     *
     * @return string
     * @static
     */
    public static function getCookieDomain() {
        static $domain;
        if (!isset($domain)) {
            $domain = implode('.', array_slice(
                explode('.', $domain = getenv('HTTP_HOST')),
                preg_match('/\.co\.uk$/', $domain)
                    ? -3
                    : -2
            ));
        }
        return $domain;
    }
}
