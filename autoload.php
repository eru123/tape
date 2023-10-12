<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Fs;

venv_load(__DIR__ . '/.env', false);
venv_set('config.name', venv('CONFIG_NAME', 'user.json'));
venv_set('config.dir', venv('CONFIG_DIR', __DIR__));
venv_set('config.path', Fs::join(venv('config.dir'), venv('config.name')));
venv_set('config.backups_dir', venv('BACKUPS_DIR', '/app/backups'));
venv_set('config.log_path', venv('LOG_PATH', __DIR__ . '/app.log'));
venv_set('config.runner', venv('RUNNER_PATH', __DIR__ . '/runner'));

$h = fopen(venv('config.path'), 'r');
if(!!$h) {
    $buffer = '';
    while (!feof($h))
        $buffer .= fread($h, 8192);
    fclose($h);
    venv_set('config.data', json_decode($buffer, true));
    unset($buffer);
}
unset($h);

if (!Fs::touch(venv('config.log_path'))) {
    throw new Exception('Log file is not writable');
}
