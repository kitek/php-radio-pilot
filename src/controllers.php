<?php

use App\Controller\AlertsController;
use App\Controller\NewsController;
use App\Controller\UsersController;
use Silex\Application;

$app['news.controller'] = function () use ($app) {
    return new NewsController($app['config']);
};
$app['alerts.controller'] = function () use ($app) {
    return new AlertsController($app['model.user']);
};
$app['users.controller'] = function () use ($app) {
    return new UsersController($app['model.user']);
};

$app->get('/news', 'news.controller:indexAction');
$app->post('/users/register', 'users.controller:registerAction');
$app->post('/users/unregister', 'users.controller:unregisterAction');
$app->get('/alerts', 'alerts.controller:indexAction');
$app->post('/alerts', 'alerts.controller:updateAction');
$app->put('/alerts/phrases/{phrase}', 'alerts.controller:addPhraseAction');
$app->delete('/alerts/phrases/{phrase}', 'alerts.controller:delPhraseAction');

return $app;
