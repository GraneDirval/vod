<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 23.05.18
 * Time: 16:54
 */

namespace SubscriptionBundle\Affiliate\Service;


use GuzzleHttp\Client;

class GuzzleClientFactory
{
    public function getClient(): Client
    {
        return new Client(['connect_timeout' => 5, 'read_timeout' => 5,'timeout' => 5]);
    }
}