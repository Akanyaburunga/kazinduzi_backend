<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Support\Achievements;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function index()
    {
        $achievements = Achievement::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['success' => true, 'data' => $achievements]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:60', 'unique:achievements,slug'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:40'],
            'metric' => ['required', 'string', 'max:40'],
            'threshold' => ['required', 'integer', 'min:1'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $achievement = Achievement::create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'],
            'category' => $data['category'],
            'metric' => $data['metric'],
            'threshold' => $data['threshold'],
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['success' => true, 'data' => $achievement], 201);
    }

    public function update(Request $request, Achievement $achievement)
    {
        $data = $request->validate([
            'slug' => ['sometimes', 'required', 'string', 'max:60', 'unique:achievements,slug,' . $achievement->id],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', 'max:40'],
            'metric' => ['sometimes', 'required', 'string', 'max:40'],
            'threshold' => ['sometimes', 'required', 'integer', 'min:1'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $achievement->update($data);

        return response()->json(['success' => true, 'data' => $achievement]);
    }

    public function destroy(Achievement $achievement)
    {
        $achievement->delete();

        return response()->json(['success' => true, 'message' => 'Achievement deleted.']);
    }

    /**
     * Seed/resync the default badge catalogue.
     */
    public function sync()
    {
        $catalogue = Achievements::syncCatalogue();

        return response()->json(['success' => true, 'data' => $catalogue]);
    }
}
