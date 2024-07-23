<?php

namespace App;

class ResellerClub {
    protected static $endpoints = [
        'domains' => 'https://httpapi.com/api/domains/search.json',
        'hosting' => 'https://httpapi.com/api/hosting/linux/search.json',
        'email' => 'https://httpapi.com/api/mail/search.json'
    ];

    protected static $userId = '433839';
    protected static $apiKey = 'iHw8T69SKedmkoeYjGBSMAHlAhCVH80Y';
    protected $resellerId = '433839';

    public static function fetch($domain){
        // API endpoint for listing domains
        $apiEndpoint = static::$endpoints['domains'];

        // Define the parameters for the API call
        $params = [
            'auth-userid' => static::$userId,
            'api-key' => static::$apiKey,
            'domain-name' => $domain,
            'no-of-records' => 11,  // Number of records to fetch
            'page-no' => 1,         // Page number to fetch
        ];

        // Initialize cURL session
        $ch = curl_init();

        // Set the URL with parameters
        curl_setopt($ch, CURLOPT_URL, $apiEndpoint . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Skip SSL verification for simplicity

        // Execute the cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            return 'Error:' . curl_error($ch);
        } else {
            // Decode the JSON response
            $responseData = json_decode($response, true);

            // Close the cURL session
            curl_close($ch);

            return $responseData;
        }

        return 'Error: Undefined!';
    }

    public static function getDomains()
    {
        // API endpoint for listing domains
        $apiEndpoint = static::$endpoints['domains'];

        // Define the parameters for the API call
        $params = [
            'auth-userid' => static::$userId,
            'api-key' => static::$apiKey,

            'no-of-records' => 10,  // Number of records to fetch
            'page-no' => 1,         // Page number to fetch
            'order-by' => 'endtime asc',
            'expiry-date-start' => strtotime(now()->format('Y-m-d H:i:s')),
        ];

        // Initialize cURL session
        $ch = curl_init();

        // Set the URL with parameters
        curl_setopt($ch, CURLOPT_URL, $apiEndpoint . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Skip SSL verification for simplicity

        // Execute the cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            return 'Error:' . curl_error($ch);
        } else {
            // Decode the JSON response
            $responseData = json_decode($response, true);

            // Close the cURL session
            curl_close($ch);

            return $responseData;
        }

        return 'Error: Undefined!';
    }
}