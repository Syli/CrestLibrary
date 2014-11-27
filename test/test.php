<?php

require 'vendor/autoload.php';
require_once("setup.php");


use CrestLibrary\Client;

$client = new Client($url, $secret, $clientid, $refresh_token);


$pricedata=$client->getPriceData('The Forge', 'Tritanium');

var_dump($pricedata);
