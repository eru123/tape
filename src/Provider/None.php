<?php

namespace App\Provider;

class None
{
    public static function upload(array $config, array $data): bool|string|array
    {
        return true;
    }
}