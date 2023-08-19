<?php

namespace vigihdev\utils;

class UnsetArrayValue
{

    public static function __set_state($state)
    {
        return new self();
    }
}
