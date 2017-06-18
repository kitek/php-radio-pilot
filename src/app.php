<?php

use App\DataModel\User;
use Silex\Application;
use Symfony\Component\Yaml\Yaml;

$app = new Application();
$config = __DIR__ . '/../config/settings.yml';
$app['config'] = Yaml::parse(file_get_contents($config));
$app['model.user'] = new User();
$app['debug'] = strpos(getenv('SERVER_SOFTWARE'), 'Development') === 0;
$app->register(new Silex\Provider\ServiceControllerServiceProvider());

return $app;
