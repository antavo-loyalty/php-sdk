<?php
namespace Antavo;

use Antavo\SignedToken\SignedToken;

/**
 * Manages customer authentication with a signed token.
 */
class CustomerToken extends SignedToken {
    /**
     * Name of the cookie under which customer token is stored.
     *
     * @var string
     */
    const COOKIE_NAME = '__alc';

    /**
     * Creates an authenticating token for customer and sets it in cookies.
     *
     * @param mixed $customer  Unique customer ID. Scalar values only.
     * @param string $secret  API secret.
     * @param int $expires_in  Time-to-live value for cookie (less than 30 days
     * in seconds) or Unix Timestamp of the expiration time. Default is 0 (it
     * expires with the session).
     * @return bool  Returns <tt>TRUE</tt> if cookie is set successfully,
     * <tt>FALSE</tt> otherwise.
     * @static
     */
    public static function auth($customer, $secret, $expires_in = 0) {
        $token = new static($secret, $expires_in);
        $token->setCustomer($customer);
        return $token->setCookie();
    }

    /**
     * Creates token from cookie set previously.
     *
     * Since {@see setToken()} is invoked during creation, exceptions may have
     * thrown from here.
     *
     * @param string $secret  API secret.
     * @return self  Returns token extracted from cookie or <tt>NULL</tt> if
     * cookie not found.
     */
    public static function createFromCookie($secret) {
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            $token = new static($secret);
            $token->setToken($_COOKIE[self::COOKIE_NAME]);
            return $token;
        }
        return NULL;
    }

    /**
     * Alias of {@see removeCookie()}.
     *
     * @static
     */
    public static function deauth() {
        return static::removeCookie();
    }

    /**
     * Returns base domain for site.
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

    /**
     * Returns unique customer ID from payload.
     *
     * @return mixed  Returns customer ID if set, <tt>NULL</tt> otherwise.
     */
    public function getCustomer() {
        if (isset($this->payload['customer'])) {
            return $this->payload['customer'];
        }
        return NULL;
    }

    /**
     * Returns expiration time for cookie based on token expiration setting.
     *
     * @return int
     */
    public function getCookieExpirationTime() {
        if ($this->expires_at > 0) {
            return $this->getCalculatedExpirationTime();
        }
        return 0;
    }

    /**
     * Deletes cookie that identifies customer.
     *
     * @return bool  Returns <tt>TRUE</tt> if cookie unset successfully,
     * <tt>FALSE</tt> otherwise.
     * @static
     */
    public static function removeCookie() {
        return setcookie(self::COOKIE_NAME, '', time() - 3600, '/', static::getCookieDomain());
    }

    /**
     * Sets token in cookies.
     *
     * @return bool  Returns <tt>TRUE</tt> if cookie is set successfully,
     * <tt>FALSE</tt> otherwise. **Please note the difference from all other
     * setter methods!**
     */
    public function setCookie() {
        return setcookie(
            self::COOKIE_NAME,
            (string) $this,
            $this->getCookieExpirationTime(),
            '/',
            $this->getCookieDomain()
        );
    }

    /**
     * Sets unique customer ID for payload.
     *
     * @param mixed $customer  Unique customer ID. Non-scalar values are
     * discarded.
     * @return self  Object instance for method chaining.
     */
    public function setCustomer($customer) {
        if (is_scalar($customer)) {
            $this->payload['customer'] = $customer;
        }
        return $this;
    }
}
