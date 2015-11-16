<?php
namespace Antavo;

/**
 * Exception thrown when a token is expired.
 *
 * @see Antavo\Helpers\Tokens\Token::set()
 */
class ExpiredTokenException extends InvalidTokenException {}
