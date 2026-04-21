<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ResellerClub;

class PersonalAssistantController extends Controller
{
    public function getResellerBalance(Request $request)
    {
        return response()->json([
            'balance' => ResellerClub::getBalance(),
        ]);
    }
}
