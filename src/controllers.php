<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

$app->get('/news', function () use ($app) {
    $memcache = new Memcache();
    $news = $memcache->get('news') ?: [];
    return $app->json($news);
});

$app->post('/users/register', function (Request $request) use ($app) {
    $deviceToken = $request->get('deviceToken');
    if (!$deviceToken) {
        return $app->json([
            'error' => 'Parameter `deviceToken` is required.',
            'code' => 400
        ], 400);
    }
    $userStore = $app['model.user'];
    $user = $userStore->find($deviceToken);
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
    $user = $userStore->find($deviceToken);
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
