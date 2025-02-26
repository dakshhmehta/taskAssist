<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;

class WordPressDataController extends Controller
{
    public function receiveData(Request $request)
    {
        \Log::debug($request->all());
        $data = $request->all();



        $site = Site::where('domain', $data['url'])->first();

        if (! $site) {
            $site = Site::create([
                'domain' => $data['url'],
            ]);
        }

        foreach ($data as $key => $value) {
            $site->setMeta($key, $value);
        }
    }

    public function getPluginInfo(Request $request)
    {
        $data = config('wp.plugin_info');

        return $data;
    }
}
