<?php

namespace rock\helpers;


use rock\exception\BaseException;

class ObjectException extends BaseException
{
    public function __construct($level = self::ERROR, $msg = null, array $placeholders = [], \Exception $handler = null)
    {
        return parent::__construct($level, $msg, $placeholders, $handler);
    }
}