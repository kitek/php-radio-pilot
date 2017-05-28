<?php

namespace App\DataModel;

use GDS\Entity;
use GDS\Schema;
use GDS\Store;

class User
{
    private $store;

    function __construct()
    {
        $this->store = new Store($this->makeSchema());
    }

    private function makeSchema()
    {
        return (new Schema('User'))
            ->addString('secretToken', false);
    }

    public function create($deviceToken)
    {
        $user = new Entity();
        $user->secretToken = bin2hex(openssl_random_pseudo_bytes(16));
        $user->setKeyName($deviceToken);
        $this->store->upsert($user);

        return ['deviceToken' => $deviceToken, 'secretToken' => $user->secretToken];
    }

    public function find($deviceToken)
    {
        return $this->store->fetchByName($deviceToken);
    }

    public function remove($user)
    {
        $this->store->delete($user);
    }

}
