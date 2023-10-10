<?php

namespace App\Provider;

abstract class AbstractProvider
{
    abstract public static function upload(array $config, array $data): bool|string|array;
}