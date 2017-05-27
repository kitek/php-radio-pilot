<?php

use Silex\Application;

$app = new Application();

$app->get('/', function () use ($app) {
	$memcache = new Memcache();
	$news = $memcache->get('news') ?: [];
	return $app->json($news);
});

return $app;
