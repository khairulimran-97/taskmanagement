<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateCalendarEventTool;
use App\Mcp\Tools\CreateNoteTool;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\CreateSecretTool;
use App\Mcp\Tools\CreateTagTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\DeleteCalendarEventTool;
use App\Mcp\Tools\DeleteNoteTool;
use App\Mcp\Tools\DeleteProjectTool;
use App\Mcp\Tools\DeleteSecretTool;
use App\Mcp\Tools\DeleteTagTool;
use App\Mcp\Tools\DeleteTaskTool;
use App\Mcp\Tools\GetNoteTool;
use App\Mcp\Tools\GetSecretTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListCalendarEventsTool;
use App\Mcp\Tools\ListNotesTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListSecretsTool;
use App\Mcp\Tools\ListTagsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\UpdateCalendarEventTool;
use App\Mcp\Tools\UpdateNoteTool;
use App\Mcp\Tools\UpdateProjectTool;
use App\Mcp\Tools\UpdateSecretTool;
use App\Mcp\Tools\UpdateTagTool;
use App\Mcp\Tools\UpdateTaskStatusTool;
use App\Mcp\Tools\UpdateTaskTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Taskflow')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
Taskflow is a personal task-management workspace: projects hold tasks (with subtasks,
tags, priorities, and due dates), plus free-form notes and a calendar. Everything is
scoped to the signed-in user — you see and change only their data, exactly like the
web dashboard.

Typical flow: list-projects to orient, list-tasks (filter by project, status, priority,
or due=overdue/today/week) to find work, then create/update tasks or flip statuses with
update-task-status (accepts one id or many). Task statuses: todo, in_progress, completed,
cancelled. Priorities: low, medium, high, urgent. Tags are free-form names — pass names
and they are created on the fly.

Notes are rich-text (HTML content) with comma-style tags and pinning. Calendar events
have start/end datetimes and an all_day flag. Deletes are real deletes, same as the
dashboard: deleting a project removes its tasks, deleting a task removes its subtasks.

The encrypted secret vault is also available: list-secrets shows names only; get-secret
returns the decrypted value — fetch values only when the user explicitly needs them, and
never repeat a secret value anywhere it does not have to appear.
TEXT)]
class TaskflowServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListProjectsTool::class,
        CreateProjectTool::class,
        UpdateProjectTool::class,
        DeleteProjectTool::class,
        ListTasksTool::class,
        GetTaskTool::class,
        CreateTaskTool::class,
        UpdateTaskTool::class,
        UpdateTaskStatusTool::class,
        DeleteTaskTool::class,
        ListTagsTool::class,
        CreateTagTool::class,
        UpdateTagTool::class,
        DeleteTagTool::class,
        ListNotesTool::class,
        GetNoteTool::class,
        CreateNoteTool::class,
        UpdateNoteTool::class,
        DeleteNoteTool::class,
        ListCalendarEventsTool::class,
        CreateCalendarEventTool::class,
        UpdateCalendarEventTool::class,
        DeleteCalendarEventTool::class,
        ListSecretsTool::class,
        GetSecretTool::class,
        CreateSecretTool::class,
        UpdateSecretTool::class,
        DeleteSecretTool::class,
    ];
}
