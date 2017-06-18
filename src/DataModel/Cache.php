<?php

namespace App\DataModel;

use GDS\Entity;
use GDS\Schema;
use GDS\Store;

class Cache
{
    const KEY_NAME = 'version-1';

    /** @var Store */
    private $store;

    function __construct()
    {
        $this->store = new Store($this->makeSchema());
    }

    private function makeSchema()
    {
        return (new Schema('Cache'))
            ->addString('body', false)
            ->addDatetime('updatedAt', false);
    }

    public function save($data)
    {
        $cache = new Entity();
        $cache->body = json_encode($data);
        $cache->updatedAt = new \DateTime();
        $cache->setKeyName(self::KEY_NAME);
        $this->store->upsert($cache);
    }

    public function get()
    {
        $cached = $this->store->fetchByName(self::KEY_NAME);
        if (empty($cached->body)) return [];
        return json_decode($cached->body, true);
    }
}
