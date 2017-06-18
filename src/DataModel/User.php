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
            ->addString('secretToken', true)
            ->addStringList('alertPhrases', false)
            ->addBoolean('alertsEnabled', true);
    }

    public function create($deviceToken)
    {
        $user = new Entity();
        $user->secretToken = bin2hex(openssl_random_pseudo_bytes(32));
        $user->notificationsEnabled = true;
        $user->alertPhrases = [];
        $user->setKeyName($deviceToken);
        $this->store->upsert($user);

        return ['deviceToken' => $deviceToken, 'secretToken' => $user->secretToken];
    }

    public function update($entity)
    {
        $this->store->upsert($entity);
    }

    public function findBySecretToken($secretToken)
    {
        return $this->store->fetchOne("SELECT * FROM User WHERE secretToken = @token", ['token' => $secretToken]);
    }

    public function findByDeviceToken($deviceToken)
    {
        return $this->store->fetchByName($deviceToken);
    }

    public function findAll()
    {
        return $this->store->fetchAll();
    }

    public function remove($user)
    {
        $this->store->delete($user);
    }

}
