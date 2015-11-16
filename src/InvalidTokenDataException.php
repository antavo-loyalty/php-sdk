<?php
namespace Antavo;

/**
 * Exception thrown when a token integrity check fails.
 *
 * @see Antavo\Token::setToken()
 */
class InvalidTokenDataException extends InvalidTokenException {}
