<?php

use App\Controller\AlertsController;
use App\Controller\NewsController;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

$app['news.controller'] = function () use ($app) {
    return new NewsController($app['config']);
};
$app['alerts.controller'] = function () use ($app) {
    return new AlertsController($app['model.user']);
};

$app->get('/news', 'news.controller:indexAction');
$app->get('/alerts', 'alerts.controller:indexAction');
$app->post('/alerts', 'alerts.controller:updateAction');
$app->put('/alerts/phrases/{phrase}', 'alerts.controller:addPhraseAction');
$app->delete('/alerts/phrases/{phrase}', 'alerts.controller:delPhraseAction');


$app->post('/users/register', function (Request $request) use ($app) {
    $deviceToken = $request->get('deviceToken');
    if (!$deviceToken) {
        return $app->json([
            'error' => 'Parameter `deviceToken` is required.',
            'code' => 400
        ], 400);
    }
    $userStore = $app['model.user'];
    $user = $userStore->findByDeviceToken($deviceToken);
    if ($user) {
        return $app->json([
            'error' => 'Provided `deviceToken` is already registered.',
            'code' => 400
        ], 400);
    }
    return $app->json($userStore->create($deviceToken));
});

$app->post('/users/unregister', function (Request $request) use ($app) {
    $deviceToken = $request->get('deviceToken');
    $secretToken = $request->get('secretToken');
    if (!$deviceToken || !$secretToken) {
        return $app->json([
            'error' => 'Parameters `deviceToken` and `secretToken` are required.',
            'code' => 400
        ], 400);
    }
    $userStore = $app['model.user'];
    $user = $userStore->findByDeviceToken($deviceToken);
    if (!$user || $user->secretToken !== $secretToken) {
        return $app->json([
            'error' => 'Parameter `deviceToken` or `secretToken` is invalid.',
            'code' => 400
        ], 400);
    }
    $userStore->remove($user);
    return $app->json([], 200);
});

return $app;
