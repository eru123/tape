<?php

namespace App\Provider;

class Invalid
{
    public static function upload(array $config, array $data): bool|string|array
    {
        return "Invalid provider";
    }
}