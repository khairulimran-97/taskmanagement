<?php

use App\Mcp\Servers\TaskflowServer;
use App\Mcp\Tools\GetNoteTool;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function staleNoteFor(User $user): Note
{
    $note = Note::create(['user_id' => $user->id, 'title' => 'J&T VIP', 'content' => '<p>Body</p>']);
    $note->timestamps = false;
    $note->forceFill(['updated_at' => now()->subMonth()])->save();

    return $note->fresh();
}

test('reading a note over mcp records the access without marking it edited', function () {
    $user = User::factory()->create();
    $note = staleNoteFor($user);

    TaskflowServer::actingAs($user)->tool(GetNoteTool::class, ['id' => $note->id])->assertOk();

    $fresh = $note->fresh();
    expect($fresh->last_accessed_at)->not->toBeNull()
        ->and($fresh->updated_at->equalTo($note->updated_at))->toBeTrue();
});

test('opening a note in the web ui records the access without marking it edited', function () {
    $user = User::factory()->create();
    $note = staleNoteFor($user);

    $this->actingAs($user)->get(route('notes.show', $note->id))->assertOk();

    $fresh = $note->fresh();
    expect($fresh->last_accessed_at)->not->toBeNull()
        ->and($fresh->updated_at->equalTo($note->updated_at))->toBeTrue();
});
