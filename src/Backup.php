<?php

namespace App;

use DateTime;
use Exception;
use ZipArchive;
use eru123\venv\VirtualEnv;

final class Backup
{
    final static function createCallback($id): callable
    {
        return function () use ($id) {
            $datetime = DateTime::createFromFormat('U', time());
            $config = venv('config.data.backups.' . $id, []);

            $cron = VirtualEnv::get($config, 'cron', false);
            $crontab = VirtualEnv::get($config, 'crontab', false);
            $cronjob = VirtualEnv::get($config, 'cronjob', false);

            $date = $datetime->format('Y-m-d H:i:s');

            if (!$cron && !$crontab && !$cronjob) {
                echo "[$date] $id: no crontab found" . PHP_EOL;
                return;
            }

            if ($cron && !Crontab::match($cron, $datetime)) {
                return;
            } else if ($crontab && !Crontab::match($crontab, $datetime)) {
                return;
            } else if ($cronjob && !Crontab::match($cronjob, $datetime)) {
                return;
            }

            $logfile = venv('config.log_path', '/app/app.log');
            $runner = venv('config.runner', '/app/runner');
            $cmdp = Cmd::cmdp("/bin/env php $runner $id >> $logfile 2>&1 &");
            echo "[$date] $id: matched" . PHP_EOL;
            shell_exec($cmdp);
        };
    }

    final static function info($id, $message)
    {
        $datetime = DateTime::createFromFormat('U', time());
        $date = $datetime->format('Y-m-d H:i:s');
        echo "[$date] INFO: $id: " . trim($message) . PHP_EOL;
    }

    final static function error($id, $message)
    {
        $datetime = DateTime::createFromFormat('U', time());
        $date = $datetime->format('Y-m-d H:i:s');
        echo "[$date] ERROR: $id: " . trim($message) . PHP_EOL;
    }

    final static function runner($id)
    {
        $backup = venv('config.data.backups.' . $id);
        $name = VirtualEnv::get($backup, 'name', $id);
        $provider_name = VirtualEnv::get($backup, 'provider');
        $provider = venv('config.data.providers.' . $provider_name);

        if (!$backup) {
            throw new Exception("Backup $id not found");
        }

        if (!$provider_name || !$provider) {
            throw new Exception("Provider {$provider_name} not found");
        }

        $datetime = DateTime::createFromFormat('U', time());
        $name = preg_replace_callback('/\%([a-zA-Z0-9]+|provider)\%/', function ($matches) use ($datetime, $provider_name) {
            if ($matches[1] === 'provider') {
                return $provider_name;
            }
            $format = $matches[1];
            return $datetime->format($format);
        }, $name);

        if (!preg_match('/^[a-zA-Z0-9\ \.\-\_]+$/', $name)) {
            static::error($name, "Invalid name provided.");
            return;
        }

        $title = trim("$name ($provider_name)");
        static::info($title, "Started");

        $type = VirtualEnv::get($backup, 'type', false);
        if (!in_array($type, ['file', 'directory', 'database'])) {
            static::error($title, "Invalid backup type");
        }

        $backup_obj = null;

        if ($type === 'file') {
            $inc = [];
            $exc = [];
            if (isset($backup['path'])) {
                $inc[] = $backup['path'];
            }
            if (isset($backup['include'])) {
                $inc = array_merge($inc, $backup['include']);
            }
            if (isset($backup['exclude'])) {
                $exc = $backup['exclude'];
            }

            $dir = venv('config.backups_dir', '/app/backups');
            Fs::mkdir($dir);

            $file = Fs::joinUnsafe($dir, $name . ".zip");
            if (file_exists($file)) {
                $file2 = Fs::joinUnsafe($dir, $name . '.' . Daemon::$time . '.bak.zip');
                Fs::move($file, $file2);
            }

            // create zip
            $zip = new ZipArchive();
            $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            // add folder to zip with $name
            $zip->addEmptyDir($name);

            // iterate files/dir, all files and dir will be added to zip as child of $name
            $added_file_count = 0;
            foreach ($inc as $value) {
                $realpath = realpath($value);
                if (!$realpath) {
                    static::error($title, "Invalid path: $value");
                    continue;
                }
                $zip->addFile($realpath, $name . '/' . basename($realpath));
                $added_file_count++;
            }

            // close zip
            $zip->close();

            // if no file added, delete the zip
            if ($added_file_count === 0) {
                unlink($file);
                static::error($title, "No file added in the backup. Exiting...");
                return;
            }

            $backup_obj = [
                'name' => $name,
                'path' => $file,
                'mime' => 'application/zip',
                'size' => filesize($file),
            ];
        }

        if (!$backup_obj) {
            static::error($title, "Failed to create backup. Exiting...");
            return;
        }
    }

    final static function do_backup(int $id): false|string|array
    {
        return false;
        // $type = VirtualEnv::get($config, 'type', false);

        // if (!$type) {
        //     return 'Invalid backup type';
        // }

        // return match ($type) {
        //     'file' => static::do_backup_file($config, $options),
        //     default => 'Invalid backup type',
        // };
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