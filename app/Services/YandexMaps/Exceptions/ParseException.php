<?php

namespace App\Services\YandexMaps\Exceptions;

use Exception;

class ParseException extends Exception
{
    public const CODE_INVALID_URL = 'invalid_url';
    public const CODE_NOT_FOUND = 'not_found';
    public const CODE_LAYOUT_CHANGED = 'layout_changed';
    public const CODE_BLOCKED = 'blocked';
    public const CODE_EMPTY = 'empty';
    public const CODE_HTTP_ERROR = 'http_error';

    public function __construct(
        string $message,
        public readonly string $reason = self::CODE_HTTP_ERROR,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
