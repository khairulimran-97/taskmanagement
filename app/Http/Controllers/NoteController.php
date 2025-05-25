<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class NoteController extends Controller
{
    /**
     * Display the notes page
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $notesQuery = Note::forUser(Auth::id())
            ->ordered();

        if ($search) {
            $notesQuery->search($search);
        }

        $notes = $notesQuery->get()->map(function ($note) {
            return [
                'id' => $note->id,
                'title' => $note->generateAutoTitle(),
                'content' => $note->content,
                'content_preview' => $note->content_preview,
                'tags' => $note->tags_array,
                'is_pinned' => $note->is_pinned,
                'word_count' => $note->word_count,
                'created_at' => $note->created_at,
                'updated_at' => $note->updated_at,
                'last_accessed_at' => $note->last_accessed_at,
            ];
        });

        // Get the first note if available for initial display
        $selectedNote = $notes->first();

        return Inertia::render('Notes/Index', [
            'notes' => $notes,
            'selectedNote' => $selectedNote,
            'search' => $search,
        ]);
    }

    /**
     * Store a newly created note
     */
    public function store(StoreNoteRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();

        // Process tags
        if (isset($validated['tags']) && is_array($validated['tags'])) {
            $validated['tags'] = implode(', ', array_filter($validated['tags']));
        }

        $note = Note::create($validated);

        return redirect()->route('notes.show', $note->id)
            ->with('success', 'Note created successfully');
    }

    /**
     * Display the specified note
     */
    public function show(string $id): Response
    {
        $note = Note::forUser(Auth::id())->findOrFail($id);

        // Mark as accessed
        $note->markAsAccessed();

        // Get all notes for sidebar
        $notes = Note::forUser(Auth::id())
            ->ordered()
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'title' => $n->generateAutoTitle(),
                    'content_preview' => $n->content_preview,
                    'tags' => $n->tags_array,
                    'is_pinned' => $n->is_pinned,
                    'word_count' => $n->word_count,
                    'created_at' => $n->created_at,
                    'updated_at' => $n->updated_at,
                ];
            });

        $selectedNote = [
            'id' => $note->id,
            'title' => $note->title,
            'content' => $note->content,
            'tags' => $note->tags_array,
            'is_pinned' => $note->is_pinned,
            'word_count' => $note->word_count,
            'created_at' => $note->created_at,
            'updated_at' => $note->updated_at,
            'last_accessed_at' => $note->last_accessed_at,
        ];

        return Inertia::render('Notes/Index', [
            'notes' => $notes,
            'selectedNote' => $selectedNote,
            'search' => '',
        ]);
    }

    /**
     * Update the specified note
     */
    public function update(UpdateNoteRequest $request, string $id): RedirectResponse
    {
        $note = Note::forUser(Auth::id())->findOrFail($id);
        $validated = $request->validated();

        // Process tags
        if (isset($validated['tags']) && is_array($validated['tags'])) {
            $validated['tags'] = implode(', ', array_filter($validated['tags']));
        }

        $note->update($validated);

        // Always return Inertia response with flash data
        return redirect()->back()->with([
            'success' => 'Note updated successfully',
            'note' => [
                'id' => $note->id,
                'title' => $note->generateAutoTitle(),
                'content' => $note->content,
                'tags' => $note->tags_array,
                'is_pinned' => $note->is_pinned,
                'word_count' => $note->word_count,
                'created_at' => $note->created_at->toISOString(),
                'updated_at' => $note->updated_at->toISOString(),
            ]
        ]);
    }

    /**
     * Remove the specified note
     */
    public function destroy(string $id): RedirectResponse
    {
        $note = Note::forUser(Auth::id())->findOrFail($id);
        $note->delete();

        return redirect()->route('notes.index')
            ->with('success', 'Note deleted successfully');
    }

    /**
     * Toggle pin status
     */
    public function togglePin(string $id): RedirectResponse
    {
        $note = Note::forUser(Auth::id())->findOrFail($id);
        $note->update(['is_pinned' => !$note->is_pinned]);

        // Always return Inertia response with flash data
        return redirect()->back()->with([
            'success' => $note->is_pinned ? 'Note pinned' : 'Note unpinned',
            'is_pinned' => $note->is_pinned,
        ]);
    }

    /**
     * Auto-save note content (API endpoint - always returns JSON)
     */
    public function autoSaveApi(Request $request, string $id): JsonResponse
    {
        $note = Note::forUser(Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'content' => 'nullable|string',
            'title' => 'sometimes|string|max:255',
        ]);

        $note->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Note auto-saved',
            'updated_at' => $note->updated_at->toISOString(),
        ]);
    }

    /**
     * Auto-save note content (Inertia endpoint - returns redirect)
     */
    public function autoSave(Request $request, string $id): RedirectResponse
    {
        $note = Note::forUser(Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'content' => 'nullable|string',
            'title' => 'sometimes|string|max:255',
        ]);

        $note->update($validated);

        // Always return Inertia response with flash data
        return redirect()->back()->with([
            'success' => 'Note auto-saved',
            'updated_at' => $note->updated_at->toISOString(),
        ]);
    }

    /**
     * Search notes
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->input('q', '');

        $notes = Note::forUser(Auth::id())
            ->when($search, function ($query) use ($search) {
                $query->search($search);
            })
            ->ordered()
            ->get()
            ->map(function ($note) {
                return [
                    'id' => $note->id,
                    'title' => $note->generateAutoTitle(),
                    'content_preview' => $note->content_preview,
                    'tags' => $note->tags_array,
                    'is_pinned' => $note->is_pinned,
                    'word_count' => $note->word_count,
                    'created_at' => $note->created_at,
                    'updated_at' => $note->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'notes' => $notes,
        ]);
    }

    /**
     * Create a new empty note
     */
    public function createEmpty(): RedirectResponse
    {
        $note = Note::create([
            'title' => 'Untitled',
            'content' => '',
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('notes.show', $note->id)
            ->with('success', 'New note created');
    }
}
