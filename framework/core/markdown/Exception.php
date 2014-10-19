<?php

namespace rock\markdown;


use rock\exception\BaseException;

class Exception extends BaseException
{
    const UNKNOWN_HOSTING = 'Unknown hosting: {name}';

    public function __construct($level = self::ERROR, $msg = null, array $placeholders = [], \Exception $handler = null)
    {
        return parent::__construct($level, $msg, $placeholders, $handler);
    }
}