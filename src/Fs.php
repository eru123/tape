<?php

namespace App;

final class Fs
{
    static $tail = [];
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

    final static function touch(string $path): false|string
    {
        if (!file_exists($path)) {
            $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            $segm = explode(DIRECTORY_SEPARATOR, $path);
            array_pop($segm);
            $dir = implode(DIRECTORY_SEPARATOR, $segm);
            static::mkdir($dir);
            if (touch($path)) {
                return realpath($path);
            }
        }

        return realpath($path);
    }

    final static function tail(string $path, int $seek = -1): int
    {
        $modified = null;

        if (isset(static::$tail[$path])) {
            [$rseek, $modified] = static::$tail[$path];
            if ($seek < 0) {
                $seek = $rseek;
            }
        } else {
            $seek = 0;
        }

        clearstatcache(true, $path);
        $last_modified = filemtime($path);

        if (!file_exists($path)) {
            echo "File not found: $path" . PHP_EOL;
        }

        if ($modified !== $last_modified) {
            $h = fopen($path, 'r');
            fseek($h, $seek);
            $buffer = '';
            while (!feof($h)) {
                $buffer .= fread($h, 8192);
            }
            $seek = intval(ftell($h));
            fclose($h);
            static::$tail[$path] = [$seek, $last_modified];
            print $buffer;
        }

        return $seek;
    }
}