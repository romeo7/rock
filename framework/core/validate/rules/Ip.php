<?php

namespace rock\validate\rules;


use rock\validate\Exception;

class Ip extends Rule
{
    public function __construct($ipOptions = null, $config = [])
    {
        $this->parentConstruct($config);
        $this->params['ipOptions'] = null;
        if (is_int($ipOptions)) {
            $this->params['ipOptions'] = $ipOptions;
            return;
        }
        $this->params['networkRange'] = $this->parseRange($ipOptions);
    }

    /**
     * @inheritdoc
     */
    public function validate($input)
    {
        return $this->verifyAddress($input) && $this->verifyNetwork($input);
    }

    protected function parseRange($input)
    {
        if ($input === null || $input == '*' || $input == '*.*.*.*'
            || $input == '0.0.0.0-255.255.255.255') {
            return null;
        }
        $range = array('min' => null, 'max' => null, 'mask' => null);

        if (strpos($input, '-') !== false) {
            list($range['min'], $range['max']) = explode('-', $input);
        } elseif (strpos($input, '*') !== false) {
            $this->parseRangeUsingWildcards($input, $range);
        } elseif (strpos($input, '/') !== false) {
            $this->parseRangeUsingCidr($input, $range);
        } else {
            throw new Exception(Exception::ERROR, 'Invalid network range');
        }

        if (!$this->verifyAddress($range['min'])) {
            throw new Exception(Exception::ERROR, 'Invalid network range');
        }

        if (isset($range['max']) && !$this->verifyAddress($range['max'])) {
            throw new Exception(Exception::ERROR, 'Invalid network range');
        }
        return $range;
    }

    protected function fillAddress(&$input, $char = '*')
    {
        while (substr_count($input, '.') < 3) {
            $input .= '.' . $char;
        }
    }

    protected function parseRangeUsingWildcards($input, &$range)
    {
        $this->fillAddress($input);

        $range['min'] = strtr($input, '*', '0');
        $range['max'] = str_replace('*', '255', $input);
    }

    protected function parseRangeUsingCidr($input, &$range)
    {
        $input = explode('/', $input);
        $this->fillAddress($input[0], '0');

        $range['min'] = $input[0];
        $isAddressMask = strpos($input[1], '.') !== false;

        if ($isAddressMask && $this->verifyAddress($input[1])) {
            $range['mask'] = sprintf('%032b', ip2long($input[1]));

            return ;
        }

        if ($isAddressMask || $input[1] < 8 || $input[1] > 30) {
            throw new Exception(Exception::ERROR, 'Invalid network mask');
        }

        $range['mask'] = sprintf('%032b', ip2long(long2ip(~(pow(2, (32 - $input[1])) - 1))));
    }

    protected function verifyAddress($address)
    {
        return (boolean) filter_var(
            $address,
            FILTER_VALIDATE_IP,
            array(
                'flags' => $this->params['ipOptions']
            )
        );
    }

    protected function verifyNetwork($input)
    {
        if ($this->params['networkRange'] === null) {
            return true;
        }

        if (isset($this->params['networkRange']['mask'])) {
            return $this->belongsToSubnet($input);
        }

        $input = sprintf('%u', ip2long($input));

        return bccomp($input, sprintf('%u', ip2long($this->params['networkRange']['min']))) >= 0
               && bccomp($input, sprintf('%u', ip2long($this->params['networkRange']['max']))) <= 0;
    }

    protected function belongsToSubnet($input)
    {
        $range = $this->params['networkRange'];
        $min = sprintf('%032b', ip2long($range['min']));
        $input = sprintf('%032b', ip2long($input));

        return ($input & $range['mask']) === ($min & $range['mask']);
    }
} 