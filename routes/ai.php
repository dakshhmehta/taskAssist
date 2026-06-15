<?php

use App\Mcp\Servers\ResellerServer;
use App\Mcp\Servers\TaskServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Facades\Mcp;

// Mcp::web('demo', \App\Mcp\Servers\PublicServer::class); // Available at /mcp/demo
// Mcp::local('demo', \App\Mcp\Servers\LocalServer::class); // Start with ./artisan mcp:start demo

Route::middleware('auth:sanctum')->group(function () {
    Mcp::web('reseller', ResellerServer::class);
    Mcp::web('tasks', TaskServer::class);
});
// Mcp::local('reseller', ResellerServer::class);
