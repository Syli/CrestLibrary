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
        $this->clientid=$clientid;
        $this->secret=$secret;
        $this->refresh_token=$refresh_token;
        $this->expiry=$expiry;
        $this->guzzle_client=new \GuzzleHttp\Client([
            'base_url' => $urlbase,
            'defaults' => [
                'headers' => [
                    'User-Agent' => $this->useragent
                ]
            ]
        ]);
        $this->endpoints=$this->getEndpoints();
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

        $config['headers']=array( 'Authorization' => 'Basic '.base64_encode($this->clientid.':'.$this->secret));
        $config['query']=array('grant_type' => 'refresh_token','refresh_token' => $this->refresh_token);

        $response = $this->guzzle_client->post($this->endpoints->authEndpoint->href, $config);
        $json=json_decode($response->getBody());
        $this->access_token=$json->access_token;
        $this->expiry=time()+$json->expires_in-20;
    }

    private function getEndpoints()
    {
        $config['headers']=array('Content-Type'=>'application/vnd.ccp.eve.Api-v3+json');
        $response = $this->guzzle_client->get('/', $config);
        return json_decode($response->getBody());
    }

    private function getRegions()
    {
        if (count($this->regions)) {
            return;
        }

        if ($this->expiry<time()) {
            $this->getAccessToken();
        }

        $config['headers']=array(
            'Authorization' => 'Bearer '.$this->access_token,
            'Content-Type' => 'application/vnd.ccp.eve.RegionCollection-v1+json'
            );
        $response = $this->guzzle_client->get($this->endpoints->regions->href, $config);
        $json=json_decode($response->getBody());
        foreach ($json->items as $regiondata) {
            $this->regions[$regiondata->name]=$regiondata->href;
        }
    }


    private function getItems()
    {
        if (count($this->items)) {
            return;
        }
        if ($this->expiry<time()+30) {
            $this->getAccessToken();
        }
        $url=$this->endpoints->itemTypes->href;
        
        $config['headers']=array(
            'Authorization' => 'Bearer '.$this->access_token,
            'Content-Type' => 'application/vnd.ccp.eve.ItemTypeCollection-v1+json'
        );
        while (1) {
            $response = $this->guzzle_client->get($url, $config);
            $json=json_decode($response->getBody());
            foreach ($json->items as $item) {
                $this->items[$item->name]=$item->href;
            }
            if (isset($json->next)) {
                $url=$json->next->href;
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
        $config['headers']=array(
            'Authorization' => 'Bearer '.$this->access_token,
            'Content-Type' => 'application/vnd.ccp.eve.Region-v1+json'
        );
        $response = $this->guzzle_client->get($this->regions[$region], $config);
        $json=json_decode($response->getBody());
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

    private function processPrice($type, $url)
    {
        if ($this->expiry<time()) {
            $this->getAccessToken();
        }
        $config['headers']=array(
            'Authorization' => 'Bearer '.$this->access_token,
            'Content-Type' => 'application/vnd.ccp.eve.MarketOrderCollection-v1+json'
        );
        $config['query']=array('type' =>  $this->items[$type]);
        $response = $this->guzzle_client->get($url, $config);
        $json=json_decode($response->getBody());
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
