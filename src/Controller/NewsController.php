<?php
namespace App\Controller;

use App\DataModel\Cache;
use Memcache;
use Symfony\Component\HttpFoundation\JsonResponse;

class NewsController
{
    const KEY_NAME = 'news';
    protected $config;
    /** @var Cache */
    protected $cache;

    function __construct(array $config, Cache $cache)
    {
        $this->config = $config;
        $this->cache = $cache;
    }

    public function indexAction()
    {
        $memcache = new Memcache();
        $news = $memcache->get(self::KEY_NAME);
        if (empty($news)) {
            $news = $this->cache->get();
            if (!empty($news)) $memcache->set(self::KEY_NAME, $news);
        }
        if (empty($news)) $news = ['items' => [], 'expiredAt' => 0];
        $expiresIn = (strtotime($news['updatedAt']) + $this->config['cron_interval']) - time();
        return new JsonResponse([
            'items' => $news['items'],
            'expiresIn' => $expiresIn > 0 ? $expiresIn : 0
        ]);
    }
}
