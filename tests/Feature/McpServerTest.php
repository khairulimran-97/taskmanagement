<?php

use App\Mcp\Servers\TaskflowServer;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\GetSecretTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\UpdateTaskStatusTool;
use App\Models\Project;
use App\Models\Secret;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function mcpProjectFor(User $user): Project
{
    return Project::create(['name' => 'Test project', 'user_id' => $user->id]);
}

test('unauthenticated mcp requests get a json 401, not a redirect', function () {
    $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

    $response->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
});

test('browsers get the info page on GET /mcp while clients get 405', function () {
    $this->get('/mcp', ['Accept' => 'text/html'])->assertOk();
    $this->get('/mcp', ['Accept' => 'application/json'])->assertStatus(405)->assertHeader('Allow', 'POST');
});

test('oauth discovery endpoints are served', function () {
    $this->getJson('/.well-known/oauth-authorization-server')->assertOk();
    $this->getJson('/.well-known/oauth-protected-resource')->assertOk();
});

test('create-task creates a user-scoped task with tags created on the fly', function () {
    $user = User::factory()->create();
    $project = mcpProjectFor($user);

    TaskflowServer::actingAs($user)->tool(CreateTaskTool::class, [
        'title' => 'Ship the MCP server',
        'project_id' => $project->id,
        'priority' => 'high',
        'tags' => ['mcp', 'launch'],
    ])->assertOk();

    $task = Task::where('title', 'Ship the MCP server')->firstOrFail();
    expect($task->user_id)->toBe($user->id)
        ->and($task->tags->pluck('name')->sort()->values()->all())->toBe(['launch', 'mcp']);
});

test('create-task refuses a project owned by someone else', function () {
    $user = User::factory()->create();
    $otherProject = mcpProjectFor(User::factory()->create());

    TaskflowServer::actingAs($user)->tool(CreateTaskTool::class, [
        'title' => 'Sneaky task',
        'project_id' => $otherProject->id,
    ])->assertHasErrors();

    expect(Task::where('title', 'Sneaky task')->exists())->toBeFalse();
});

test('list-tasks only returns the acting user\'s tasks', function () {
    $user = User::factory()->create();
    $project = mcpProjectFor($user);
    Task::create(['title' => 'Mine', 'user_id' => $user->id, 'project_id' => $project->id]);

    $stranger = User::factory()->create();
    Task::create(['title' => 'Not mine', 'user_id' => $stranger->id, 'project_id' => mcpProjectFor($stranger)->id]);

    TaskflowServer::actingAs($user)->tool(ListTasksTool::class, [])
        ->assertOk()
        ->assertSee('Mine')
        ->assertDontSee('Not mine');
});

test('update-task-status completes tasks in bulk and stamps completed_at', function () {
    $user = User::factory()->create();
    $project = mcpProjectFor($user);
    $tasks = collect([1, 2])->map(fn ($i) => Task::create([
        'title' => "Task {$i}",
        'user_id' => $user->id,
        'project_id' => $project->id,
        'status' => 'todo',
    ]));

    TaskflowServer::actingAs($user)->tool(UpdateTaskStatusTool::class, [
        'task_ids' => $tasks->pluck('id')->all(),
        'status' => 'completed',
    ])->assertOk();

    foreach ($tasks as $task) {
        $task->refresh();
        expect($task->status)->toBe('completed')->and($task->completed_at)->not->toBeNull();
    }
});

test('get-secret returns the decrypted value only for the owner', function () {
    $user = User::factory()->create();
    $secret = Secret::create([
        'user_id' => $user->id,
        'name' => 'Test key',
        'type' => 'api_token',
        'value' => 'super-secret-value',
    ]);

    TaskflowServer::actingAs($user)->tool(GetSecretTool::class, ['id' => $secret->id])
        ->assertOk()
        ->assertSee('super-secret-value');

    $stranger = User::factory()->create();
    TaskflowServer::actingAs($stranger)->tool(GetSecretTool::class, ['id' => $secret->id])
        ->assertHasErrors();
});
