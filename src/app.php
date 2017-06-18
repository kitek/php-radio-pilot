<?php

use App\DataModel\Cache;
use App\DataModel\User;
use Silex\Application;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Yaml\Yaml;

$app = new Application();
$config = __DIR__ . '/../config/settings.yml';
$app['config'] = Yaml::parse(file_get_contents($config));
$app['model.user'] = new User();
$app['store.cache'] = new Cache();
$app['debug'] = strpos(getenv('SERVER_SOFTWARE'), 'Development') === 0;
$app->register(new Silex\Provider\ServiceControllerServiceProvider());

ErrorHandler::register();

$app->error(function (\Exception $e) use ($app) {
    return $app->json([
        'error' => $e->getMessage(),
        'code' => 500
    ], 500);
});

return $app;
