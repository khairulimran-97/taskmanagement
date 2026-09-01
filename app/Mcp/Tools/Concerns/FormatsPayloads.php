<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\CalendarEvent;
use App\Models\Note;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;

trait FormatsPayloads
{
    /**
     * Shape a task for MCP responses; mirrors what the dashboard shows.
     *
     * @return array<string, mixed>
     */
    protected function taskPayload(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'project_id' => $task->project_id,
            'project' => $task->relationLoaded('project') ? $task->project?->name : null,
            'parent_task_id' => $task->parent_task_id,
            'start_date' => $task->start_date?->toIso8601String(),
            'due_date' => $task->due_date?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'sort_order' => $task->sort_order,
            'tags' => $task->relationLoaded('tags') ? $task->tags->pluck('name')->all() : null,
            'subtask_count' => $task->subtasks_count ?? ($task->relationLoaded('subtasks') ? $task->subtasks->count() : null),
        ];
    }

    /**
     * Shape a project including its task progress numbers.
     *
     * @return array<string, mixed>
     */
    protected function projectPayload(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'color' => $project->color,
            'status' => $project->status,
            'priority' => $project->priority,
            'start_date' => $project->start_date?->toDateString(),
            'due_date' => $project->due_date?->toDateString(),
            'task_count' => $project->tasks_count ?? null,
            'completed_task_count' => $project->completed_tasks_count ?? null,
        ];
    }

    /**
     * Shape a note; content included only when requested (list vs detail).
     *
     * @return array<string, mixed>
     */
    protected function notePayload(Note $note, bool $withContent = false): array
    {
        $payload = [
            'id' => $note->id,
            'title' => $note->title,
            'tags' => $note->tags_array,
            'is_pinned' => $note->is_pinned,
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];

        if ($withContent) {
            $payload['content'] = $note->content;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    protected function eventPayload(CalendarEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'start_date' => $event->start_date?->toIso8601String(),
            'end_date' => $event->end_date?->toIso8601String(),
            'all_day' => $event->all_day,
            'color' => $event->color,
        ];
    }

    /** @return array<string, mixed> */
    protected function tagPayload(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'color' => $tag->color,
            'description' => $tag->description,
            'task_count' => $tag->tasks_count ?? null,
        ];
    }
}
