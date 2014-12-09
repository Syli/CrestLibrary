<?php
namespace CrestLibrary;


class Client
{
    
    private $useragent="Fuzzwork Crest agent 1.0";
    private $expiry;
    private $cache;
    private $secret;
    private $clientid;
    private $refresh_token;
    private $urlbase;
    private $guzzle_client;


    public function __construct($urlbase, $secret, $clientid, $refresh_token, $access_token = '', $expiry = 0, $pool = '')
    {
        $this->expiry=0;
        if ($pool==='') {
            $pool = new \Stash\Pool();
        }
        $this->cache=$pool;
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
    }
  

    public function walkEndpoint($resource, $key, $collection, $index = '', $parameters = array(), $accept = '', $maxpages = INF, $ttl = 300)
    {
        $key=trim("walked/".str_replace($this->urlbase, '', $resource).sha1(json_encode($parameters).$collection.$index.$accept), '/');
        $item=$this->cache->getItem($key);
        $data=$item->get();
        if ($item->isMiss()) {
            $data=array();
            $url=$resource;
            $pagecount=0;
            while (1) {
                $pagecount++;
                $walker=$this->getEndpoint($url, $parameters, $accept);
                foreach ($walker->$collection as $collectionItem) {
                    if ($index=='') {
                        $data[]=$collectionItem;
                    } else {
                        $data[$collectionItem->$index]=$collectionItem;
                    }
                }
                if (isset($walker->next) and $pagecount<$maxpages) {
                    $url=$walker->next->href;
                } else {
                    break;
                }
            }
            $item->set($data, $ttl);
        }
        return $data;
    }


    public function getEndpoint($resource, $parameters = array(), $accept = '')
    {
        $key=trim(str_replace($this->urlbase, '', $resource), '/');
        $item=$this->cache->getItem($key.'/'.sha1(json_encode($parameters).$accept));
        $data=$item->get();
        if ($item->isMiss()) {
            $item->lock();
            if ($this->expiry<time()+5) {
                $this->getAccessToken();
            }
            $config['headers']=array(
                'Authorization' => 'Bearer '.$this->access_token,
                'Accept' => $accept
            );
            if (count($parameters)) {
                $config['query']=$parameters;
            }
            $response = $this->guzzle_client->get($resource, $config);
            $ttl=200;
            if ($cachecontrol=$response->getHeader('Cache-Control')) {
                $parts=explode(",", $cachecontrol);
                foreach ($parts as $line) {
                    if (substr(trim($line), 0, 7)=='max-age') {
                        $ttl=substr(trim($line), 8);
                    }
                }
            }
            if ($response->getHeader('X-Deprecated')) {
                error_log("Resource: ".$resource." has been marked deprecated. ".$accept);
            }
            $data=json_decode($response->getBody());
            $item->set($data, $ttl);
        }
        return $data;
    }


    private function getAccessToken()
    {
        $endpoints=$this->getEndpoints();
        $config['headers']=array( 'Authorization' => 'Basic '.base64_encode($this->clientid.':'.$this->secret));
        $config['query']=array('grant_type' => 'refresh_token','refresh_token' => $this->refresh_token);

        $response = $this->guzzle_client->post($endpoints->authEndpoint->href, $config);
        $json=json_decode($response->getBody());
        $this->access_token=$json->access_token;
        $this->expiry=time()+$json->expires_in-20;
    }

    public function getEndpoints()
    {
        $item=$this->cache->getitem('Endpoints');
        $data=$item->get();
        if ($item->isMiss()) {
            $item->lock();
            $config['headers']=array('Accept'=>'application/vnd.ccp.eve.Api-v3+json; charset=utf-8');
            $response = $this->guzzle_client->get('/', $config);
            $data=json_decode($response->getBody());
            $ttl=300;
            if ($cachecontrol=$response->getHeader('Cache-Control')) {
                $parts=explode(",", $cachecontrol);
                foreach ($parts as $line) {
                    if (substr(trim($line), 0, 7)=='max-age') {
                        $ttl=substr(trim($line), 8);
                    }
                }
            }
            if ($response->getHeader('X-Deprecated')) {
                error_log("The root endpoint has been marked deprecated.");
            }
            $item->set($data, $ttl);
        }
        return $data;
    }

    private function getRegions()
    {
        $endpoints=$this->getEndpoints();
        $regions=$this->walkEndpoint(
            $endpoints->regions->href,
            'regions',
            'items',
            'name',
            array(),
            'application/vnd.ccp.eve.RegionCollection-v1+json; charset=utf-8'
        );
        return $regions;
    }


    private function getItems()
    {
        $endpoints=$this->getEndpoints();
        $items=$this->walkEndpoint(
            $endpoints->itemTypes->href,
            'items',
            'items',
            'name',
            array(),
            'application/vnd.ccp.eve.ItemTypeCollection-v1+json; charset=utf-8'
        );
        return $items;
        
    }

    private function getRegionOrderUrls($region)
    {

        $regions=$this->getRegions();
        
        $json=$this->getEndpoint(
            $regions[$region]->href,
            array(),
            'application/vnd.ccp.eve.Region-v1+json; charset=utf-8'
        );
        return array($json->marketSellOrders->href,$json->marketBuyOrders->href);

    }



    public function getPriceData($region, $type)
    {
        list($sellurl, $buyurl)=$this->getRegionOrderUrls($region);
        $buy=$this->processPrice($type, $buyurl, "buy");
        $sell=$this->processPrice($type, $sellurl, "sell");
        return array("buy"=>$buy,"sell"=>$sell);



    }

    private function processPrice($type, $url, $orderType)
    {
        $items=$this->getItems();
        $config['query']=array('type' =>  $items[$type]->href);
        $details=$this->walkEndpoint(
            $url,
            "orders/".$type."/".$orderType,
            "items",
            "",
            $config['query'],
            'application/vnd.ccp.eve.MarketOrderCollection-v1+json; charset=utf-8'
        );
        return $details;
    }
}
