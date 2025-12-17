<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::web('mcp/task', \App\Mcp\Servers\TaskServer::class);
