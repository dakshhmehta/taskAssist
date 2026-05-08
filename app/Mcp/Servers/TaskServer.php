<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddTask;
use App\Mcp\Tools\CompleteTask;
use App\Mcp\Tools\GetDailyBriefing;
use App\Mcp\Tools\ListTeamMembers;
use App\Mcp\Tools\RecordLeave;
use App\Mcp\Tools\SyncTasks;
use Laravel\Mcp\Server;

class TaskServer extends Server
{
    public string $serverName = 'Task Server';

    public string $serverVersion = '1.0.0';

    public string $instructions = 'Server for managing Daksh\'s tasks, schedules, and leaves.';

    public array $tools = [
        SyncTasks::class,
        AddTask::class,
        CompleteTask::class,
        // GetDailyBriefing::class,
        // RecordLeave::class,
        ListTeamMembers::class,
    ];

    public array $resources = [
        //
    ];

    public array $prompts = [
        //
    ];
}
