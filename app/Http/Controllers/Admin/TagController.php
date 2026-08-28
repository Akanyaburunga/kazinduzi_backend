<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Riddle;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    public function index()
    {
        $tags = Tag::withCount('riddles')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $tags]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'slug' => ['nullable', 'string', 'max:60', 'unique:tags,slug'],
        ]);

        $tag = Tag::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
        ]);

        return response()->json(['success' => true, 'data' => $tag], 201);
    }

    public function update(Request $request, Tag $tag)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'slug' => ['nullable', 'string', 'max:60', 'unique:tags,slug,' . $tag->id],
        ]);

        $update = ['name' => $data['name'] ?? $tag->name];
        $update['slug'] = $request->filled('slug')
            ? Str::slug($request->input('slug'))
            : Str::slug($update['name']);

        $tag->update($update);

        return response()->json(['success' => true, 'data' => $tag]);
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();

        return response()->json(['success' => true, 'message' => 'Tag deleted.']);
    }

    /**
     * Riddle type catalogue for filter dropdowns / forms.
     */
    public function types()
    {
        return response()->json(['success' => true, 'data' => Riddle::RIDDLE_TYPES]);
    }
}
