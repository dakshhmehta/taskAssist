<?php

use App\Mcp\Servers\ResellerServer;
use App\Mcp\Servers\TaskServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// Mcp::web('demo', \App\Mcp\Servers\PublicServer::class); // Available at /mcp/demo
// Mcp::local('demo', \App\Mcp\Servers\LocalServer::class); // Start with ./artisan mcp:start demo

Route::middleware('auth:sanctum')->group(function () {
    Mcp::web('/mcp/reseller', ResellerServer::class);
    Mcp::web('/mcp/tasks', TaskServer::class);
});
// Mcp::local('reseller', ResellerServer::class);
