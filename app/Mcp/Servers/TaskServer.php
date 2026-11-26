<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddTask;
use App\Mcp\Tools\AddTaskComment;
use App\Mcp\Tools\CompleteTask;
use App\Mcp\Tools\GetDailyBriefing;
use App\Mcp\Tools\GetTask;
use App\Mcp\Tools\GetTimesheet;
use App\Mcp\Tools\ListLeaves;
use App\Mcp\Tools\ListNotifications;
use App\Mcp\Tools\ListTags;
use App\Mcp\Tools\ListTasks;
use App\Mcp\Tools\ListTeamMembers;
use App\Mcp\Tools\RecordLeave;
use App\Mcp\Tools\SetTimer;
use App\Mcp\Tools\SetTimesheet;
use App\Mcp\Tools\SyncTasks;
use App\Mcp\Tools\UpdateTask;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\ServerContext;

class TaskServer extends Server
{
    /**
     * Tools that team members (non-admins) must not see or call at all.
     *
     * @var array<int, class-string>
     */
    private const ADMIN_ONLY_TOOLS = [
        GetTimesheet::class,
        SetTimesheet::class,
    ];

    protected string $name = 'Task Server';

    protected string $version = '1.15.0';

    protected string $instructions = 'Server for managing Daksh\'s tasks, schedules, and leaves.';

    protected array $tools = [
        SyncTasks::class,
        // AddTask::class,
        CompleteTask::class,
        // GetDailyBriefing::class,
        // RecordLeave::class,
        ListTeamMembers::class,
        ListTags::class,
        ListLeaves::class,
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

    /**
     * Hide the admin-only tools from team members so they are neither listed
     * by tools/list nor callable through tools/call.
     */
    public function createContext(): ServerContext
    {
        if (Auth::user()?->is_admin) {
            return parent::createContext();
        }

        $tools = $this->tools;

        try {
            $this->tools = array_values(array_diff($tools, self::ADMIN_ONLY_TOOLS));

            return parent::createContext();
        } finally {
            $this->tools = $tools;
        }
    }
}
