<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Fs;

venv_load(__DIR__ . '/.env', false);
venv_set('config.name', venv('CONFIG_NAME', 'user.json'));
venv_set('config.dir', venv('CONFIG_DIR', __DIR__));
venv_set('config.path', Fs::join(venv('config.dir'), venv('config.name')));
venv_set('config.backups_dir', venv('BACKUPS_DIR', '/app/backups'));
