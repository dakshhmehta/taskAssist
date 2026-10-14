<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddTask;
use App\Mcp\Tools\AddTaskComment;
use App\Mcp\Tools\CompleteTask;
use App\Mcp\Tools\GetDailyBriefing;
use App\Mcp\Tools\GetTask;
use App\Mcp\Tools\GetTimesheet;
use App\Mcp\Tools\ListNotifications;
use App\Mcp\Tools\ListTags;
use App\Mcp\Tools\ListTasks;
use App\Mcp\Tools\ListTeamMembers;
use App\Mcp\Tools\RecordLeave;
use App\Mcp\Tools\SetTimer;
use App\Mcp\Tools\SetTimesheet;
use App\Mcp\Tools\SyncTasks;
use App\Mcp\Tools\UpdateTask;
use Laravel\Mcp\Server;

class TaskServer extends Server
{
    protected string $name = 'Task Server';

    protected string $version = '1.13.0';

    protected string $instructions = 'Server for managing Daksh\'s tasks, schedules, and leaves.';

    protected array $tools = [
        SyncTasks::class,
        // AddTask::class,
        CompleteTask::class,
        // GetDailyBriefing::class,
        // RecordLeave::class,
        ListTeamMembers::class,
        ListTags::class,
        // UpdateTask::class,
        ListTasks::class,
        GetTask::class,
        AddTaskComment::class,
        ListNotifications::class,
        GetTimesheet::class,
        SetTimesheet::class,
        SetTimer::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
