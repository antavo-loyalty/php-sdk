<?php
namespace Antavo;

use Antavo\RestClient\Exceptions\Exception;

/**
 * Exception thrown when Antavo Loyalty API JSON response contains an error.
 *
 * Exception message contains the error type and message, while exception code
 * holds the received HTTP status code.
 */
class ApiClientException extends Exception {}
