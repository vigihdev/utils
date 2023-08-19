<?php

namespace vigihdev\utils;


class ReplaceArrayValue
{
    /**
     * @var mixed value used as replacement.
     */
    public $value;


    /**
     * Constructor.
     * @param mixed $value value used as replacement.
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    public static function __set_state($state)
    {
        if (!isset($state['value'])) {
            throw new \Exception('Failed to instantiate class "ReplaceArrayValue". Required parameter "value" is missing', 1);
        }

        return new self($state['value']);
    }
}
