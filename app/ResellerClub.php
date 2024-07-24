<?php

namespace App;

class ResellerClub
{
    protected static $endpoints = [
        'domains' => 'https://httpapi.com/api/domains/search.json',
        'hostings-in' => 'https://httpapi.com/api/singledomainhosting/linux/in/search.json',
        'hostings-us' => 'https://httpapi.com/api/singledomainhosting/linux/us/search.json',
        'emails' => 'https://httpapi.com/api/mail/search.json'
    ];

    protected static $userId = '433839';
    protected static $apiKey = 'iHw8T69SKedmkoeYjGBSMAHlAhCVH80Y';
    protected $resellerId = '433839';

    public static function fetch($domain)
    {
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

            'no-of-records' => 50,  // Number of records to fetch
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

    public static function getHostings($country)
    {
        // API endpoint for listing domains
        $apiEndpoint = static::$endpoints['hostings-' . $country];

        // Define the parameters for the API call
        $params = [
            'auth-userid' => static::$userId,
            'api-key' => static::$apiKey,

            'no-of-records' => 50,  // Number of records to fetch
            'page-no' => 1,         // Page number to fetch
            'order-by' => 'orders.endtime asc',
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

    public static function getBalance()
    {
        // API endpoint for listing domains
        $apiEndpoint = 'https://httpapi.com/api/billing/reseller-balance.json';

        // Define the parameters for the API call
        $params = [
            'auth-userid' => static::$userId,
            'api-key' => static::$apiKey,

            'reseller-id' => static::$userId,
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

            if (isset($responseData['sellingcurrencybalance'])) {
                return $responseData['sellingcurrencybalance'];
            }

            return -1;
        }

        return 'Error: Undefined!';
    }
}
