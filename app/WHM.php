<?php

namespace App;

use GuzzleHttp\Client;

class WHM {
    protected $server = [];
    protected $client;

    public function server($name)
    {
        $this->server = config('services.whm.'.$name);
        
        $this->client = new Client([
            'base_uri' => 'https://' . $this->server['host'] . ':2087/',
            'headers'  => [
                'Authorization' => 'whm ' . $this->server['username'] . ':' . $this->server['token']
            ],
            'verify' => false // Disable SSL verification for testing purposes
        ]);
    }

    public function listAccounts()
    {
        try {
            $response = $this->client->request('GET', 'json-api/listaccts', [
                'query' => ['api.version' => 1]
            ]);

            $responseBody = $response->getBody();
            $responseData = json_decode($responseBody, true);

            if (isset($responseData['status']) && $responseData['status'] == 0) {
                throw new \Exception('API Error: ' . $responseData['statusmsg']);
            }

            return $responseData['data']['acct'] ?? [];
        } catch (\Exception $e) {
            echo 'Request Error: ' . $e->getMessage();
            return [];
        }
    }
}