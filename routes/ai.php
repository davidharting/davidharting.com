<?php

use App\Mcp\Servers\PublicServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', PublicServer::class)
    ->middleware('throttle:60,1');
