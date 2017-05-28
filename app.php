<?php

use Silex\Application;

$app = new Application();

$app->get('/news', function () use ($app) {
	$memcache = new Memcache();
	$news = $memcache->get('news') ?: [];
	return $app->json($news);
});

$app->post('/users/register', function () use ($app) {
	
	// -> deviceToken
	// <- secretToken
	
});

$app->post('/users/unregister', function () use ($app) {

	// -> deviceToken, secretToken

});

return $app;
