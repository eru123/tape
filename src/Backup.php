<?php

namespace App;

use DateTime;
use Exception;
use ZipArchive;
use eru123\venv\VirtualEnv as A;

final class Backup
{
    final static function createCallback($id): callable
    {
        return function () use ($id) {
            $config = venv('config.data.backups.' . $id, []);

            $cron = A::get($config, 'cron', false);
            $crontab = A::get($config, 'crontab', false);
            $cronjob = A::get($config, 'cronjob', false);

            $date = date('Y-m-d H:i:s');
            $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $date);

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
        $date = date('Y-m-d H:i:s');
        echo "[$date] INFO: $id: " . trim($message) . PHP_EOL;
    }

    final static function error($id, $message)
    {
        $date = date('Y-m-d H:i:s');
        echo "[$date] ERROR: $id: " . trim($message) . PHP_EOL;
    }

    final static function runner($id)
    {
        $backup = venv('config.data.backups.' . $id);
        $name = A::get($backup, 'name', $id);
        $provider_name = A::get($backup, 'provider');
        $provider = venv('config.data.providers.' . $provider_name);

        if (!$backup) {
            throw new Exception("Backup $id not found");
        }

        if (!$provider_name || !$provider) {
            throw new Exception("Provider {$provider_name} not found");
        }

        $datetime = DateTime::createFromFormat('U', date('U'));
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

        $type = A::get($backup, 'type', false);
        if (!in_array($type, ['file', 'directory', 'database'])) {
            static::error($title, "Invalid backup type");
        }

        $backup_obj = null;

        if (in_array(strtolower($type), ['file', 'directory', 'folder', 'dir'])) {
            $inc = [];
            $exc = [];

            if (isset($backup['path']) && is_array($backup['path'])) {
                $inc = array_merge($inc, $backup['path']);
            } else if (isset($backup['path'])) {
                $inc[] = $backup['path'];
            }

            if (isset($backup['paths']) && is_array($backup['paths'])) {
                $inc = array_merge($inc, $backup['paths']);
            } else if (isset($backup['paths'])) {
                $inc[] = $backup['paths'];
            }

            if (isset($backup['include']) && is_array($backup['include'])) {
                $inc = array_merge($inc, $backup['include']);
            } else if (isset($backup['include'])) {
                $inc[] = $backup['include'];
            }

            if (isset($backup['exclude']) && is_array($backup['exclude'])) {
                $exc = array_merge($exc, $backup['exclude']);
            } else if (isset($backup['exclude'])) {
                $exc[] = $backup['exclude'];
            }

            $dir = venv('config.backups_dir', '/app/backups');
            Fs::mkdir($dir);

            $file = Fs::joinUnsafe($dir, $name . ".zip");
            if (file_exists($file)) {
                $file2 = Fs::joinUnsafe($dir, $name . '.' . Daemon::$time . '.bak.zip');
                Fs::move($file, $file2);
            }

            $zip = new ZipArchive();
            if (!$zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
                static::error($title, "Failed to create backup file. Exiting...");
                return;
            }

            $zip->addEmptyDir($name);
            $password = A::get($backup, 'password');
            $added_file_count = 0;
            foreach ($inc as $value) {
                $realpath = realpath($value);
                if (!$realpath) {
                    static::error($title, "Invalid path: $value");
                    continue;
                }

                foreach ($exc as $rgx) {
                    $rgx = str_replace('/', '\/', $rgx);
                    $rgx = str_replace('.', '\.', $rgx);
                    $rgx = str_replace('*', '.*', $rgx);
                    $rgx = str_replace('?', '.', $rgx);
                    $rgx = '/^' . $rgx . '$/';
                    if (preg_match($rgx, $realpath)) {
                        continue 2;
                    }
                }

                $zip->addFile($realpath, $name . '/' . basename($realpath));
                if ($zip->status !== ZipArchive::ER_OK) {
                    static::error($title, "Failed to add file: $value");
                    continue;
                }

                if ($password) {
                    $zip->setEncryptionName($name . '/' . basename($realpath), ZipArchive::EM_AES_256, $password);
                }
                
                $added_file_count++;
            }

            $zip->close();

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

            static::info($title, "Backup file created");
        }

        if (!$backup_obj) {
            static::error($title, "Failed to create backup. Exiting...");
            return;
        }

        if (class_exists($provider['class'])) {
            $result = call_user_func_array([$provider['class'], 'upload'], [$provider, $backup_obj]);
            if ($result === true) {
                static::info($title, "Backup file uploaded");
            } else {
                static::error($title, "Failed to upload backup file: $result");
            }
        } else {
            static::error($title, "Provider class not found");
        }

        if (!A::get($backup, 'persistent', false) && !A::get($backup, 'persist', false)) {
            unlink($backup_obj['path']);
            static::info($title, "Backup file deleted");
        }
    }
}