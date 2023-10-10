<?php

namespace App;

use Error;
use InvalidArgumentException;

class Cmd
{
    public static function noshell_exec(string $cmd): string|false
    {
        static $descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
        $options = ['bypass_shell' => true];

        if (!$proc = proc_open($cmd, $descriptors, $pipes, null, null, $options)) {
            throw new Error('Creating child process failed');
        }

        fclose($pipes[0]);
        $result = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($proc);
        return $result;
    }

    public static function parallel_exec(string $cmd): void
    {
        if (substr(php_uname(), 0, 7) == "Windows") {
            pclose(popen("start /B " . $cmd, "r"));
        } else {
            exec($cmd . " > /dev/null &");
        }
    }

    public static function escape_win32_argv(string $value): string
    {
        static $expr = '(
        [\x00-\x20\x7F"] # control chars, whitespace or double quote
      | \\\\++ (?=("|$)) # backslashes followed by a quote or at the end
    )ux';

        if ($value === '') {
            return '""';
        }
        $quote = false;
        $replacer = function ($match) use ($value, &$quote) {
            switch ($match[0][0]) { // only inspect the first byte of the match
                case '"': // double quotes are escaped and must be quoted
                    $match[0] = '\\"';
                case ' ':
                case "\t": // spaces and tabs are ok but must be quoted
                    $quote = true;
                    return $match[0];
                case '\\': // matching backslashes are escaped if quoted
                    return $match[0] . $match[0];
                default:
                    throw new InvalidArgumentException(
                        sprintf(
                            "Invalid byte at offset %d: 0x%02X",
                            strpos($value, $match[0]),
                            ord($match[0])
                        )
                    );
            }
        };

        $escaped = preg_replace_callback($expr, $replacer, (string) $value);
        if ($escaped === null) {
            throw preg_last_error() === PREG_BAD_UTF8_ERROR
                ? new InvalidArgumentException("Invalid UTF-8 string")
                : new Error("PCRE error: " . preg_last_error());
        }

        return $quote // only quote when needed
            ? '"' . $escaped . '"'
            : $value;
    }

    public static function escape_win32_cmd(string $value): string
    {
        return preg_replace('([()%!^"<>&|])', '^$0', $value);
    }

    public static function cmdp(string|array $cmd): string
    {
        return is_array($cmd) ? implode(' ', array_map(PHP_OS_FAMILY === 'Windows' ? [static::class, 'escape_win32_argv'] : 'trim', $cmd)) : $cmd;
    }

    public static function cmd(string|array $cmd, $parallel = false): string|false|null
    {
        $cmd = static::cmdp($cmd);
        $cmd = PHP_OS_FAMILY === 'Windows' ? static::escape_win32_cmd($cmd) : $cmd;
        return $parallel ? static::parallel_exec($cmd) : static::noshell_exec($cmd);
    }

    public static function xshell(string|array $cmd): string|false|null
    {
        return shell_exec(static::cmdp($cmd));
    }
}