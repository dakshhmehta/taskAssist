<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Ai\GptService;

class GptController extends Controller
{
    public function chat(Request $request, GptService $gpt)
    {
        $response = $gpt->handle($request->input('message'));

        return response()->json([
            'reply' => $response
        ]);
    }
}