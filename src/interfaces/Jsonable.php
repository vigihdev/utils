<?php

namespace vigihdev\utils\interfaces;

interface Jsonable
{
    public function toJson(int $options = 0): string;
}
