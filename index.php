<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/app.php';

$app['debug'] = strpos(getenv('SERVER_SOFTWARE'), 'Development') === 0;
$app->run();
