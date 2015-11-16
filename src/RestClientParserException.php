<?php
namespace Antavo;

/**
 * Exception thrown when parsing response body fails either because of an
 * invalid (or incomplete) body or a mismatching <tt>Content-Type</tt> header.
 */
class RestClientParserException extends RestClientException {}
