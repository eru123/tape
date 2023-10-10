<?php

namespace App;

use DateTime;
use Throwable;
use eru123\venv\VirtualEnv;

final class Backup
{
    // final static function create(array $config): callable
    // {
    //     $provider = VirtualEnv::get($config, 'provider', false);
    //     $providers['invalid'] = [
    //         'class' => Provider\Invalid::class,
    //     ];

    //     $providers['none'] = [
    //         'class' => Provider\None::class,
    //     ];

    //     if (!$provider || !isset($providers[$provider])) {
    //         $provider = 'invalid';
    //     }

    //     return function () use ($provider, $providers, $config, $options) {
    //         $datetime = DateTime::createFromFormat('U', Daemon::$time);
    //         $cron = VirtualEnv::get($config, 'cron', false);
    //         $crontab = VirtualEnv::get($config, 'crontab', false);
    //         $cronjob = VirtualEnv::get($config, 'cronjob', false);

    //         if (!$cron && !$crontab && !$cronjob) {
    //             return;
    //         }

    //         if ($cron && !Crontab::match($cron, $datetime)) {
    //             return;
    //         } else if ($crontab && !Crontab::match($crontab, $datetime)) {
    //             return;
    //         } else if ($cronjob && !Crontab::match($cronjob, $datetime)) {
    //             return;
    //         }

    //         try {
    //             ob_start();
    //             echo 'matched';
    //             // $backup = static::do_backup($config, $options);
    //             $output = ob_get_clean();
    //         } catch (Throwable $e) {
    //             ob_end_clean();
    //             $output = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
    //         }
    //         $lines = explode(PHP_EOL, trim($output));
    //         $date = DateTime::createFromFormat('U', Daemon::$time)->format('Y-m-d H:i:s');
    //         $provider = VirtualEnv::get($config, 'provider', 'unknown');
    //         $provider = addslashes($provider);
    //         $name = VirtualEnv::get($config, 'name', '');
    //         $name = addslashes($name);
    //         $title = trim("$name ($provider)");
    //         foreach ($lines as $line) {
    //             echo "[$date] $title: $line" . PHP_EOL;
    //         }
    //     };
    // }
    final static function createCallback($id): callable
    {
        $logfile = venv('config.log_path', '/app/app.log');
        $runner = venv('config.runner', '/app/runner');
        return fn() => Cmd::cmd("/bin/env php $runner $id >> $logfile 2>&1 &");
    }

    final static function runner($id)
    {

    }

    final static function do_backup($config, $options): false|string|array
    {
        $type = VirtualEnv::get($config, 'type', false);
        if (!$type) {
            return 'Invalid backup type';
        }

        return match ($type) {
            'file' => static::do_backup_file($config, $options),
            default => 'Invalid backup type',
        };
    }

    final static function do_backup_file($config, $options): false|string|array
    {
        echo 'backup file' . PHP_EOL;
        $path = VirtualEnv::get($config, 'path', false);
        $include = VirtualEnv::get($config, 'include', []);
        $exclude = VirtualEnv::get($config, 'exclude', []);
        $name = VirtualEnv::get($config, 'name', false);

        $backups_dir = venv('config.backups_dir', '/app/backups');
        $backup_file_unsafe = Fs::joinUnsafe($backups_dir, $name);

        Fs::mkdir($backups_dir);
        if (file_exists($backup_file_unsafe)) {
            $backup_file_unsafe2 = Fs::joinUnsafe($backups_dir, $name . '.' . Daemon::$time . '.bak');
            Fs::move($backup_file_unsafe, $backup_file_unsafe2);
        }

        $backup_file = Fs::join($backups_dir, $name);

        if ($path) {
            array_unshift($include, $path);
        }

        foreach ($include as $value) {
            $realpath = realpath($value);
        }

        return false;
    }
}