<?php
namespace App\Controller;

use Memcache;
use Symfony\Component\HttpFoundation\JsonResponse;

class NewsController
{
    protected $config;

    function __construct(array $config)
    {
        $this->config = $config;
    }

    public function indexAction()
    {
        $memcache = new Memcache();
        $news = $memcache->get('news') ?: ['items' => [], 'expiredAt' => 0];
        $expiresIn = (strtotime($news['updatedAt']) + $this->config['cron_interval']) - time();
        return new JsonResponse([
            'items' => $news['items'],
            'expiresIn' => $expiresIn > 0 ? $expiresIn : 0
        ]);
    }
}
