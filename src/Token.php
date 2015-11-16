<?php
namespace Antavo;

/**
 * Packs/unpacks a signed token with custom data.
 *
 * @example <br><tt>
 *   $token = new Antavo\Token($token_secret);<br>
 *   $token->setData(array('user_id' => 1));<br>
 *   echo $token->getToken();<br>
 *   </tt>
 * @example <br><tt>
 *   $token = new Antavo\Token;<br>
 *   $token->setToken($input_token);<br>
 *   var_dump($token->getData());<br>
 *   </tt>
 */
class Token {
    /**
     * @var string  Algorithm used for creating the token digest.
     */
    protected $algorithm = 'sha256';

    /**
     * @var string  String to use for token digest calculation.
     * @link https://xkcd.com/936/  xkcd: Password Strength
     */
    protected $secret = 'correct horse battery staple';

    /**
     * @var int  Expiraton time as Unix timestamp.
     */
    protected $expires_at = 0;

    /**
     * @var string  Cached token string.
     */
    protected $token;

    /**
     * @var array  Data to embed into the token string.
     */
    protected $data = array();

    /**
     * Constructs token object: sets secret string and expiration time.
     *
     * @param string $secret  String to use for token digest calculation. If
     * omitted the hardcoded default is used (**not recommended**).
     * @param int $expires_at  Expiraton time as Unix timestamp. If omitted 0
     * is used. See {@see setExpirationTime()} for details.
     */
    public function __construct($secret = NULL, $expires_at = NULL) {
        $this->setSecret($secret);
        $this->setExpirationTime($expires_at);
    }

    /**
     * Shorthand method for getting token string.
     *
     * @return string
     * @see getToken()
     */
    public function __toString() {
        return $this->getToken();
    }

    /**
     * Base64 decodes an URL-safely encoded string.
     *
     * @param string $string
     * @return string
     * @static
     */
    private static function base64Decode($string) {
        return base64_decode(strtr($string, '-_', '+/'));
    }

    /**
     * Base64 encodes a string and makes it URL-safe.
     *
     * @param string $string
     * @return string
     * @static
     */
    private static function base64Encode($string) {
        return strtr(trim(base64_encode($string), '='), '+/', '-_');
    }

    /**
     * @param string $data
     * @return string
     */
    private function calculateDigest($data) {
        return hash_hmac($this->algorithm, $data, $this->secret);
    }

    /**
     * Returns algorithm used for creating the token digest.
     *
     * @return string
     */
    public function getAlgorithm() {
        return $this->algorithm;
    }

    /**
     * Returns calculated expiration time.
     *
     * @return int  If set value is a time-to-live value &mdash; meaning it's
     * smaller than or equals to 30 days (in seconds) &mdash; it is offsetted
     * with current time.
     */
    public function getCalculatedExpirationTime() {
        if ($this->expires_at > 0 && $this->expires_at <= 2592000) {
            return time() + $this->expires_at;
        }
        return $this->expires_at;
    }

    /**
     * Returns token expiration time as Unix Timestamp.
     *
     * @return int
     */
    public function getExpirationTime() {
        return $this->expires_at;
    }

    /**
     * Returns data extracted from token string.
     *
     * @return array
     */
    public function getData() {
        return $this->data;
    }

    /**
     * Returns secret string used to create the token digest.
     *
     * @return string
     */
    public function getSecret() {
        return $this->secret;
    }

    /**
     * @return string
     * @final
     */
    final public function getToken() {
        if (!isset($this->token)) {
            $data = json_encode($this->packData($this->data));
            $this->token = $this->base64Encode($this->calculateDigest($data)) .
                '.' . $this->base64Encode($data);
        }

        return $this->token;
    }

    /**
     * Tells if token expiration time had passed already.
     *
     * @return bool
     */
    public function isExpired() {
        return ($expires_at = $this->getCalculatedExpirationTime()) > 0
            && $expires_at < time();
    }

    /**
     * Returns data extended with expiration time.
     *
     * Override this method for customization.
     *
     * @param array $data
     * @return array
     */
    protected function packData(array $data) {
        if (
            !array_key_exists('expires_at', $data)
            && ($expires_at = $this->getCalculatedExpirationTime())
        ) {
            $data['expires_at'] = $expires_at;
        }
        return $data;
    }

    /**
     * Sets algorithm used for creating the token digest.
     *
     * @param string $algorithm
     * @return self  Object instance for method chaining.
     * @link http://php.net/function.hash_algos  How to get a list of
     * registered hashing algorithms?
     */
    public function setAlgorithm($algorithm) {
        if (in_array($algorithm, hash_algos())) {
            $this->algorithm = $algorithm;
            $this->token = NULL;
        }
        return $this;
    }

    /**
     * Sets expiration time for the token.
     *
     * @param int $expires_at  Unix Timestamp of expiration time. If given
     * value is smaller than or equals to 30 days (in seconds) then it is
     * handled as a relative time-to-live value. 0 means it will never expire.
     * Float values are casted to integer, while non-numeric values are
     * discarded.
     * @return self
     */
    public function setExpirationTime($expires_at) {
        if (is_numeric($expires_at)) {
            $this->expires_at = (int) $expires_at;
            $this->token = NULL;
        }
        return $this;
    }

    /**
     * Sets data to embed into the token string.
     *
     * @param array $data
     * @return self  Object instance for method chaining.
     */
    public function setData(array $data) {
        $this->data = $data;
        $this->token = NULL;
        return $this;
    }

    /**
     * Sets string to use for token digest calculation.
     *
     * @param string $secret  Non-scalar values are discarded.
     * @return self  Object instance for method chaining.
     */
    public function setSecret($secret) {
        if (is_scalar($secret)) {
            $this->secret = (string) $secret;
            $this->token = NULL;
        }
        return $this;
    }

    /**
     * Restores an object state from token string.
     *
     * @param string $token  Token string to process. Non-string values are
     * discarded.
     * @return self  Object instance for method chaining.
     * @throws \Antavo\ExpiredTokenException
     * @throws \Antavo\InvalidTokenDataException
     * @throws \Antavo\InvalidTokenException
     * @final
     */
    final public function setToken($token) {
        if (is_string($token)) {
            $this->token = $token;

            // Splitting token into parts and processing them.
            $digest = $this->base64Decode(strtok($token, '.'));
            $payload = $this->base64Decode(strtok('.'));

            // Checking token integrity.
            if ($this->calculateDigest($payload) != $digest) {
                throw new InvalidTokenException;
            }

            // Extracting token payload.
            $payload = json_decode($payload, true);
            if (!is_array($payload)) {
                throw new InvalidTokenDataException;
            }
            if (isset($payload['expires_at'])) {
                $this->expires_at = $payload['expires_at'];
            }
            $this->data = $payload;

            // Checking token expiration.
            if ($this->isExpired()) {
                throw new ExpiredTokenException;
            }
        }

        return $this;
    }
}
