<?php

use App\Mcp\Servers\ResellerServer;
use Laravel\Mcp\Server\Facades\Mcp;

// Mcp::web('demo', \App\Mcp\Servers\PublicServer::class); // Available at /mcp/demo
// Mcp::local('demo', \App\Mcp\Servers\LocalServer::class); // Start with ./artisan mcp:start demo

Mcp::web('reseller', ResellerServer::class)->middleware('auth:sanctum');
// Mcp::local('reseller', ResellerServer::class);