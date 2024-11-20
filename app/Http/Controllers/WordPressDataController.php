<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WordPressDataController extends Controller
{
    public function getData(Request $request)
    {
        $domain = $request->query('domain');

        if (!$domain) {
            return response()->json(['error' => 'Domain name is required'], 400);
        }

        // Construct the WordPress API endpoint
        $url = "https://{$domain}/wp-json/taskassist/v1/site-info";

        // Send request to the WordPress API
        $response = Http::get($url);

        // Check if the response was successful
        if ($response->successful()) {
            // Output the data using dd
            dd($response->json());
        }

        return response()->json(['error' => 'Failed to retrieve data from the WordPress site'], 500);
    }
}
