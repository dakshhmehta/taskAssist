<?php

namespace App\Services\Ai;

use App\ResellerClub;
use Illuminate\Support\Facades\Http;

class ToolExecutor
{
    public static function execute($toolName, $arguments = [])
    {
        switch ($toolName) {
            case 'getResellerBalance':
                return [
                    'balance' => ResellerClub::getBalance(),
                ];

            default:
                throw new \Exception("Unknown tool: {$toolName}");
        }
    }
}