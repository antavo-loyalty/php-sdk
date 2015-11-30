<?php
namespace Antavo;

use Antavo\RestClient\RestClient;

/**
 * Antavo Loyalty API client class.
 */
class ApiClient extends RestClient {
    /**
     * {@inheritdoc}';
     */
    protected $base_url = 'https://api.antavo.com/v1';

    /**
     * @var string  Current client version
     */
    protected $version = '1.0';

    /**
     * @var string  Antavo Loyalty API key
     */
    protected $api_key;

    /**
     * @var string  API secret
     */
    protected $secret;

    /**
     * Constructs client object: sets API credentials.
     *
     * @param string $api_key  Antavo Loyalty API key.
     * @param string $secret  API secret.
     */
    public function __construct($api_key, $secret) {
        $this->api_key = $api_key;
        $this->secret = $secret;
    }

    /**
     * Automatically opts customer in if consent cookie is present.
     *
     * @param string $customer  Unique customer ID.
     * @param array $data  Custom data to store with the event. See
     * {@link https://docs.antavo.com/api/events/opt_in event documentation}
     * for required an optional keys.
     * @return mixed
     */
    public function autoOptInCustomer($customer, array $data = array()) {
        if (isset($_COOKIE['__alo']) && '1' == $_COOKIE['__alo']) {
            $result = $this->optInCustomer($customer, $data);
            setcookie('__alo', '2', 0, '/', implode('.', array_slice(explode('.', getenv('HTTP_HOST')), -2)));
            $_COOKIE['__alo'] = '2';
            return $result;
        }
    }

    /**
     * {@inheritdoc}
     *
     * It automatically appends <tt>api_key</tt> and calculated signature to
     * request data.
     */
    public function call($method, $url, $data = NULL, array $curl_options = array()) {
        $data['api_key'] = $this->api_key;
        $curl_options[CURLOPT_HTTPHEADER][] = 'User-Agent: Antavo PHP SDK API client v' . $this->version;
        return parent::call($method, $url, $data, $curl_options);
    }

    /**
     * Posts an {@link https://docs.antavo.com/api/events/opt_in opt-in event}
     * to the Events API.
     * <code>
     * $client->optInCustomer('8eb1b522', array(
     *     // Providing an email address is mandatory.
     *     'email' => 'john.doe@example.com'
     * ));
     * </code>
     *
     * @param string $customer  Unique customer ID.
     * @param array $data  Custom data to store with the event. See
     * {@link https://docs.antavo.com/api/events/opt_in event documentation}
     * for required an optional keys.
     * @return mixed
     */
    public function optInCustomer($customer, array $data = array()) {
        return $this->postEvent('opt_in', $customer, $data);
    }

    /**
     * Posts an {@link https://docs.antavo.com/api/events/opt_out opt-out event}
     * to the Events API.
     * <code>
     * $client->optOutCustomer('8eb1b522', array(
     *     'reason' => 'Not interested'
     * ));
     * </code>
     *
     * @param string $customer  Unique customer ID.
     * @param array $data  Custom data to store with the event. See
     * {@link https://docs.antavo.com/api/events/opt_out event documentation}
     * for required an optional keys.
     * @return mixed
     */
    public function optOutCustomer($customer, array $data = array()) {
        return $this->postEvent('opt_out', $customer, $data);
    }

    /**
     * Posts an event to the {@link https://docs.antavo.com/api/events Antavo
     * Loyalty Events API}.
     * <code>
     * $customer = array(
     *     'id' => '8eb1b522',
     *     'first_name' => 'John',
     *     'last_name' => 'Doe',
     *     'email' => 'john.doe@example.com'
     * );
     * $client = new Antavo\ApiClient('API_KEY', 'API_SECRET');
     * try {
     *     $result = $client->postEvent('opt_in', $customer['id'], array(
     *         'first_name' => $customer['first_name'],
     *         'last_name' => $customer['last_name'],
     *         'email' => $customer['email']
     *     ));
     * } catch (Antavo\RestClient\Exceptions\Exception $e) {
     *     echo $e->getMessage();
     * }
     * </code>
     *
     * @param string $action  Action name.
     * @param string $customer  Unique customer ID.
     * @param array $data  Custom data to store with the event.
     * @return mixed
     */
    public function postEvent($action, $customer, array $data = array()) {
        return $this->call('POST', '/events', compact('action', 'customer', 'data'));
    }

    /**
     * {@inheritdoc}
     */
    protected function processJsonResult($result) {
        $parsed_result = parent::processJsonResult($result);
        if (isset($parsed_result->error)) {
            $this->setError(new ApiClientException(
                sprintf(
                    '%s: %s',
                    $parsed_result->error->type,
                    $parsed_result->error->message
                ),
                $this->last_result_info['http_code']
            ));
        }
        return $parsed_result;
    }
}
