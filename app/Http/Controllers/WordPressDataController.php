<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WordPressDataController extends Controller
{
    public function receiveData(Request $request)
    {
        \Log::debug($request->all());
    }

    public function getPluginInfo(Request $request){
        $data = config('wp_plugin');

        return $data;
    }
}
