<?php
namespace CrestLibrary;

class Client
{
    
    private $useragent="Fuzzwork Crest agent 1.0";
    private $expiry;
    private $endpoints;
    private $secret;
    private $clientid;
    private $refresh_token;
    private $urlbase;
    private $dbh;
    private $database_prefix;
    private $guzzle_client;


// Temporaty, until I work up the database caching.
    private $regions=array();
    private $items=array();

    public function __construct($urlbase, $secret, $clientid, $refresh_token, $access_token = '', $expiry = 0)
    {
        $this->expiry=0;
        $this->access_token=$access_token;
        $this->urlbase=$urlbase;
        $this->endpoints=$this->getEndpoints();
        $this->clientid=$clientid;
        $this->secret=$secret;
        $this->refresh_token=$refresh_token;
        $this->expiry=$expiry;
        $this->guzzle_client=new GuzzleHTTP\Client([
            'base_url' => $urlbase,
            defaults => [
                'headers' => [
                    'User-Agent' => $this->useragent
                ]
            ]
        ]);
    }
    

    public function setDBHandle($dbh, $database_prefix)
    {
        $this->dbh=$dbh;
        $this->database_prefix=$database_prefix;
        
        $test_sql="select count(*) cnt from ".$this->database_prefix.".cache";
        $test_stmt=$this->dbh->prepare($test_sql);

        $result=$test_stmt->execute();
        if ($result->cnt < 0) {
            throw new \Exception("Bad Database handle. Cache table doesn't exist");
        }
    }

    private function getAccessToken()
    {
        $response = $this->guzzle_client->post(
            $this->endpoints->authEndpoint->href,
            [
                'query' => ['grant_type' => 'refresh_token','refresh_token' => $this->refresh_token],
                'headers' => [ 'Authorization' => 'Basic '.base64_encode($this->clientid.':'.$this->secret)]
            ]
        );
        $json=$response->json();
        $this->access_token=$json->access_token;
        $this->expiry=time()+$json->expires_in-20;
        echo $this->access_token;

    }

    private function getEndpoints()
    {
        $response = $this->guzzle_client->get('/');
        $json=$response->json();
        return $json;
    }

    private function getRegions()
    {

        if (count($this->regions)) {
            return;
        }
        if ($this->expiry<time()) {
            $this->getAccessToken();
        }

        $response = $this->guzzle_client->get(
            $this->endpoints->regions->href,
            [['headers'] => ['Authorization' => 'Bearer '.$this->access_token]]
        );
        $json=$response->json();
        var_dump($json->items);
        foreach ($json->items as $regiondata) {
            $this->regions[$regiondata->name]=$regiondata->href;
        }
    }


    private function getItems()
    {
        if (count($this->items)) {
            return;
        }
        if ($this->expiry<time()) {
            $this->getAccessToken();
        }
        $url=$this->endpoints->itemTypes->href;
        while (1) {
            $response = $this->guzzle_client->get(
                $this->endpoints->itemTypes->href,
                [
                    ['headers'] => ['Authorization' => 'Bearer '.$this->access_token],
                ]
            );
            $json=$response->json();
            foreach ($json->items as $item) {
                $this->items[$item->name]=$item->href;
            }
            if (isset($json->next)) {
                $url=$json->next;
            } else {
                break;
            }
        }
    }

    private function getRegionOrderUrls($region)
    {

        // Caching to be added here.

        $this->getRegions();
        
        if ($this->expiry<time()) {
            $this->getAccessToken();
        }
        
        $response = $this->guzzle_client->get(
            $this->regions[$region],
            [['headers'] => ['Authorization' => 'Bearer '.$this->access_token]]
        );
        $json=$response->json();
        return array($json->marketSellOrders->href,$json->marketBuyOrders->href);

    }



    public function getPriceData($region, $type)
    {
        $this->getItems();
        if ($this->expiry<time()) {
            $this->getAccessToken();
        }

        list($sellurl, $buyurl)=$this->getRegionOrderUrls($region);
        $buy=$this->processPrice($type, $buyurl);
        $sell=$this->processPrice($type, $sellurl);
    
        return json_encode(array("buy"=>$buy,"sell"=>$sell));



    }

    private function processPrice($type, $region)
    {
        if ($this->expiry<time()) {
            $this->getAccessToken();
        }
        
        $response = $this->guzzle_client->get(
            $this->regions[$region],
            [
                ['headers'] => ['Authorization' => 'Bearer '.$this->access_token],
                ['query'] => ['type' => $this->items[$type] ]
            ]
        );
        $json=$response->json();

        $details=array();
        foreach ($json->items as $item) {
            $details[]=array(
                "volume"=>$item->volume,
                "minVolume"=>$item->minVolume,
                "range"=>$item->range,
                "location"=>$item->location->href,
                "duration"=>$item->duration,
                "buy"=>$item->buy,
                "price"=>$item->price,
                "issued"=>$item->issued
            );
        }
        return $details;
        

    }
}
