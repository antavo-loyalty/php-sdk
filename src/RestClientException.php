<?php
namespace Antavo;

/**
 * Abstract exception extended by all exceptions thrown from
 * {@see \Antavo\RestClient} when an error occurs during processing REST API
 * response.
 *
 * @abstract
 */
abstract class RestClientException extends \RuntimeException {}
