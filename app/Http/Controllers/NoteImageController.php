<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NoteImageController extends Controller
{
    /**
     * Display a listing of images for a note.
     */
    public function index(Note $note): Response
    {
        // Verify the note belongs to the current user
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $images = $note->images->map(function ($image) {
            return [
                'id' => $image->id,
                'url' => $image->url,
                'filename' => $image->original_filename,
                'mime_type' => $image->mime_type,
                'size' => $image->size,
                'created_at' => $image->created_at
            ];
        });

        return Inertia::render('Notes/Images/Index', [
            'note' => [
                'id' => $note->id,
                'title' => $note->title
            ],
            'images' => $images
        ]);
    }

    /**
     * Store a newly created image.
     */
    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240', // 10MB max
            'note_id' => 'required|integer|exists:notes,id'
        ]);

        // Verify the note belongs to the current user
        $note = Note::findOrFail($request->note_id);
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        // Process and store the image
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $originalFilename = $file->getClientOriginalName();
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('note-images/' . $note->id, $filename, 'public');

            // Create image record
            $image = NoteImage::create([
                'note_id' => $note->id,
                'path' => $path,
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize()
            ]);

            return back()->with([
                'success' => 'Image uploaded successfully',
                'image' => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'filename' => $image->original_filename,
                    'mime_type' => $image->mime_type
                ]
            ]);
        }

        return back()->withErrors(['image' => 'No image uploaded']);
    }

    /**
     * Store an image through Inertia (for editor direct uploads)
     * Fixed to return proper response with flash data accessible to Inertia
     */
    public function apiStore(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240', // 10MB max
            'note_id' => 'required|integer|exists:notes,id'
        ]);

        // Verify the note belongs to the current user
        $note = Note::findOrFail($request->note_id);
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        // Process and store the image
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $originalFilename = $file->getClientOriginalName();
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('note-images/' . $note->id, $filename, 'public');

            // Create image record
            $image = NoteImage::create([
                'note_id' => $note->id,
                'path' => $path,
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize()
            ]);

            // Store flash data in session for Inertia to access
            session()->flash('success', 'Image uploaded successfully');
            session()->flash('image', [
                'id' => $image->id,
                'url' => $image->url,
                'filename' => $image->original_filename,
                'mime_type' => $image->mime_type,
                'size' => $image->size
            ]);

            // Return success response (Inertia will handle the flash data)
            return response('', 200);
        }

        return response()->json(['errors' => ['image' => ['No image uploaded']]], 422);
    }

    /**
     * Get all images for a note (API endpoint for the editor)
     */
    public function apiIndex(Note $note)
    {
        // Verify the note belongs to the current user
        if ($note->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $images = $note->images->map(function ($image) {
            return [
                'id' => $image->id,
                'url' => $image->url,
                'filename' => $image->original_filename,
                'mime_type' => $image->mime_type,
                'size' => $image->size
            ];
        });

        return response()->json(['images' => $images]);
    }

    /**
     * Pure JSON API endpoint for fetch() requests (not Inertia)
     * This is for cases where you want to use regular fetch() instead of Inertia
     */
    public function pureApiStore(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240', // 10MB max
            'note_id' => 'required|integer|exists:notes,id'
        ]);

        // Verify the note belongs to the current user
        $note = Note::findOrFail($request->note_id);
        if ($note->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Process and store the image
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $originalFilename = $file->getClientOriginalName();
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('note-images/' . $note->id, $filename, 'public');

            // Create image record
            $image = NoteImage::create([
                'note_id' => $note->id,
                'path' => $path,
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize()
            ]);

            return response()->json([
                'success' => true,
                'image' => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'filename' => $image->original_filename,
                    'mime_type' => $image->mime_type,
                    'size' => $image->size
                ]
            ]);
        }

        return response()->json(['error' => 'No image uploaded'], 400);
    }

    /**
     * Remove the specified image.
     */
    public function destroy(NoteImage $image)
    {
        // Verify the image belongs to the current user's note
        if ($image->note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        // Delete the file
        if (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }

        // Delete the record
        $image->delete();

        return back()->with(['success' => 'Image deleted successfully']);
    }

    /**
     * Remove the specified image through API (for editor).
     */
    public function apiDestroy(NoteImage $image)
    {
        // Verify the image belongs to the current user's note
        if ($image->note->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Delete the file
        if (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }

        // Delete the record
        $image->delete();

        return response()->json(['success' => true]);
    }

    public function apiAllImages()
    {
        $userId = Auth::id();

        // Get all images for the current user's notes
        $images = NoteImage::whereHas('note', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->get()->map(function ($image) {
            return [
                'id' => $image->id,
                'url' => $image->url,
                'filename' => $image->original_filename,
                'mime_type' => $image->mime_type,
                'size' => $image->size,
                'created_at' => $image->created_at
            ];
        });

        return response()->json(['images' => $images]);
    }
}
