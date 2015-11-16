<?php
namespace Antavo;

/**
 * A rather straightforward and lightweight, yet flexible helper class to
 * perform operations on a RESTful API with cURL.
 *
 * @method mixed get(string $url, mixed $data = NULL, array $curl_options = array())
 * Performs a GET request to the API.
 * @method mixed post(string $url, mixed $data = NULL, array $curl_options = array())
 * Performs a POST request to the API.
 * @method mixed put(string $url, mixed $data = NULL, array $curl_options = array())
 * Performs a PUT request to the API.
 * @method mixed delete(string $url, mixed $data = NULL, array $curl_options = array())
 * Performs a DELETE request to the API.
 */
class RestClient {
    /**
     * @var string  Base URL to prepend when a request is made with relative
     * path given.
     */
    protected $base_url;

    /**
     * @var array
     */
    protected $curl_options = array();

    /**
     * @var mixed
     */
    protected $last_result;

    /**
     * @var string
     */
    protected $last_result_headers;

    /**
     * @var array
     */
    protected $last_result_info;

    /**
     * @var \Antavo\RestClientException  Stores the last processing error.
     */
    protected $last_error;

    /**
     * Catches all invocations to inaccessible methods and forwards them to
     * <tt>call()</tt>, with the original method name as request method.
     *
     * @param string $name  Name of the invoked method.
     * @param array $arguments  Arguments.
     * @return mixed
     * @internal
     */
    public function __call($name, $arguments) {
        array_unshift($arguments, $name);
        return call_user_func_array(array($this, 'call'), $arguments);
    }

    /**
     * Performs an API request.
     *
     * @param string $method  HTTP method to use.
     * @param string $url  URL to send request to. It is prefixed with
     * the <tt>base_url</tt> unless it starts with a URL scheme.
     * @param array|string $data  Data to send with request. It is sent as a
     * request entity where allowed, or appended as a query string otherwise.
     * @param array $curl_options  Additional cURL options for current request
     * only.
     * @return mixed
     * @throws \LogicException  If PHP cURL extension is not loaded.
     * @throws \Antavo\RestClientException If an error occurred during
     * processing response.
     */
    public function call($method, $url, $data = NULL, array $curl_options = array()) {
        // Checking for PHP cURL extension.
        if (!extension_loaded('curl')) {
            throw new \LogicException(sprintf(
                '%s needs the PHP cURL extension in order to work',
                get_class($this)
            ));
        }

        // Converting HTTP method to uppercase.
        $method = strtoupper($method);

        // Converting data to application/x-www-form-urlencoded.
        if (is_array($data)) {
            $data = http_build_query($data);
        }

        // Extending URL.
        if (!preg_match('#https{0,1}://#', $url)) {
            $url = $this->base_url . '/' . ltrim($url, '/');
        }

        // Appending data as query string for requests that does not allow
        // sending an entity.
        if (strlen($data) && 'POST' != $method && 'PUT' != $method) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . $data;
        }

        // Initiating cURL resource.
        $ch = curl_init();

        // Setting default cURL options.
        if (count($this->curl_options)) {
            curl_setopt_array($ch, $this->curl_options);
        }

        // Appending cURL options given for this request only.
        if (count($curl_options)) {
            curl_setopt_array($ch, $curl_options);
        }

        // Setting up cURL for a POST request.
        if ('POST' == $method || 'PUT' == $method) {
            if (strlen($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            curl_setopt($ch, CURLOPT_POST, true);
        }

        // These cURL options are not overrideable.
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'captureHeader'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'TLSv1');
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $this->setError();
        $this->last_result_headers = NULL;
        $this->last_result = curl_exec($ch);
        $this->last_result_info = curl_getinfo($ch);

        // Releasing resource.
        curl_close($ch);

        // Checking HTTP status code.
        if (200 != $this->last_result_info['http_code']) {
            $this->setError(new RestClientStatusCodeException(
                preg_match(
                    '/^HTTP\/1.[01] \d+ (.+)/i',
                    $this->last_result_headers,
                    $matches
                )
                    ? $matches[1]
                    : 'No headers',
                $this->last_result_info['http_code']
            ));
        }

        // Processing result.
        $this->last_result = $this->processResult(
            $this->last_result,
            strtok($this->last_result_info['content_type'], ';')
        );

        if ($error = $this->getLastError()) {
            throw $error;
        }

        return $this->last_result;
    }

    /**
     * Callback method for cURL to capture header data.
     *
     * @param resource $resource
     * @param string $string
     * @return int  Returns the number of bytes written.
     */
    protected function captureHeader($resource, $string) {
        $this->last_result_headers .= $string;
        return strlen($string);
    }

    /**
     * Returns base URL prepended to relative URL's for each request.
     *
     * @return string
     */
    public function getBaseUrl() {
        return $this->base_url;
    }

    /**
     * Returns the last error occurred during processing the response.
     *
     * @return \Antavo\RestClientException  Returns <tt>NULL</tt> if there
     * was no error.
     */
    public function getLastError() {
        return $this->last_error;
    }

    /**
     * Returns body of last response, parsed according to
     * <tt>Content-Type</tt> header.
     *
     * @return mixed
     */
    public function getLastResult() {
        return $this->last_result;
    }

    /**
     * Returns headers of last response.
     *
     * @return string
     */
    public function getLastResultHeaders() {
        return $this->last_result_headers;
    }

    /**
     * Returns info on last request.
     *
     * @return array  For details see PHP <tt>curl_getinfo()</tt> manual.
     * @link http://php.net/curl_getinfo  PHP: curl_getinfo - Manual
     */
    public function getLastResultInfo() {
        return $this->last_result_info;
    }

    /**
     * Processes result according to its content type.
     *
     * @param string $result  Response body.
     * @param string $content_type  Content MIME-type without parameters (cut
     * off at first semicolon).
     * @return mixed
     */
    protected function processResult($result, $content_type) {
        switch ($content_type) {
            case 'application/json':
                return $this->processJsonResult($result);
            case 'application/xml':
            case 'text/xml':
                return $this->processXmlResult($result);
            default:
                return $result;
        }
    }

    /**
     * Processes and returns JSON result.
     *
     * On JSON parse error it sets an {@see \Antavo\RestParserException} with
     * error code & message then returns the original result string.
     *
     * @param string $result
     * @return mixed
     */
    protected function processJsonResult($result) {
        $parsed_result = json_decode($result);
        if (($error = json_last_error()) === JSON_ERROR_NONE) {
            return $parsed_result;
        }
        $this->setError(new RestClientParserException(
            json_last_error_msg(),
            $error
        ));
        return $result;
    }

    /**
     * Processes XML result.
     *
     * @param string $result
     * @return mixed  Returns a {@see \SimpleXMLElement} object on successful
     * operation, or the original result string otherwise.
     * @throws \LogicException  If PHP SimpleXML extension is not loaded.
     * @static
     */
    protected function processXmlResult($result) {
        // Checking for PHP SimpleXML extension.
        if (!extension_loaded('SimpleXML')) {
            throw new \LogicException(sprintf(
                '%s needs the PHP SimpleXML extension in order to work',
                get_called_class()
            ));
        }
        if (($parsed_result = simplexml_load_string($result)) !== false) {
            return $parsed_result;
        }
        return $result;
    }

    /**
     * Appends cURL option to defaults.
     *
     * @param string $option  Option name. See cURL options for option names.
     * @param mixed $value  Option value. Providing a <tt>NULL</tt> removes an
     * already set option.
     * @return void
     * @link http://php.net/curl_setopt List of cURL options.
     */
    public function setCurlOption($option, $value = NULL) {
        if (isset($value)) {
            $this->curl_options[$option] = $value;
        } else {
            unset($this->curl_options[$option]);
        }
    }

    /**
     * Appends multiple cURL options to defaults.
     *
     * @param array $options  cURL options as key-value pairs. For keys and
     * their values see cURL options. Providing a <tt>NULL</tt> value removes
     * an already set option.
     * @return void
     * @link http://php.net/curl_setopt List of cURL options.
     */
    public function setCurlOptions(array $options) {
        foreach ($options as $option => $value) {
            $this->setCurlOption($option, $value);
        }
    }

    /**
     * Sets base URL which is to prepend to relative URL's for each request.
     *
     * @param string $base_url
     */
    public function setBaseUrl($base_url) {
        $this->base_url = $base_url;
    }

    /**
     * Used to set an error without exiting the normal flow of an API call.
     *
     * Calling without argument or with a <tt>NULL</tt> value resets the stored
     * last error.
     *
     * @param \Antavo\RestClientException $exception
     * @return void
     * @see self::getLastError()
     */
    protected function setError(RestClientException $exception = NULL) {
        $this->last_error = $exception;
    }
}
