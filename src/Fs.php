<?php

namespace App;

final class Fs
{
    final static function join(string ...$paths): bool|string
    {
        $path = array_shift($paths);
        for ($i = 0; $i < count($paths); $i++) {
            $path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . ltrim($paths[$i], '/\\');
        }

        return realpath($path);
    }

    final static function joinUnsafe(string ...$paths): bool|string
    {
        $path = array_shift($paths);
        for ($i = 0; $i < count($paths); $i++) {
            $path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . ltrim($paths[$i], '/\\');
        }

        return $path;
    }

    final static function move(string $source, string $destination): bool
    {
        return rename($source, $destination);
    }

    final static function copy(string $source, string $destination): bool
    {
        return copy($source, $destination);
    }

    final static function mkdir(string $path, int $mode = 0777): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, $mode, true);
        }

        return false;
    }
}