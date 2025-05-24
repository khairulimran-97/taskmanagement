<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    /**
     * Display a listing of the user's tags.
     * Used for API access if needed
     */
    public function index(): JsonResponse
    {
        $tags = Tag::where('user_id', Auth::id())
            ->orderBy('name')
            ->get();

        return response()->json($tags);
    }

    /**
     * Store a newly created tag in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tags')->where('user_id', Auth::id())
            ],
            'color' => 'nullable|string|size:7|regex:/^#[0-9A-F]{6}$/i',
            'description' => 'nullable|string',
        ]);

        $validated['user_id'] = Auth::id();

        if (empty($validated['color'])) {
            $validated['color'] = '#6B7280'; // Default gray color
        }

        $tag = Tag::create($validated);

        return redirect()->back()
            ->with('success', 'Tag created successfully.')
            ->with('newTag', $tag);
    }

    /**
     * Update the specified tag in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tag = Tag::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('tags')->where('user_id', Auth::id())->ignore($tag->id)
            ],
            'color' => 'nullable|string|size:7|regex:/^#[0-9A-F]{6}$/i',
            'description' => 'nullable|string',
        ]);

        $tag->update($validated);

        return response()->json($tag);
    }

    /**
     * Remove the specified tag from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $tag = Tag::where('user_id', Auth::id())->findOrFail($id);
        $tag->delete();

        return response()->json(['message' => 'Tag deleted successfully']);
    }
}
