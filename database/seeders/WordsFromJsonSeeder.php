<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Facades\File;

class WordsFromJsonSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('data/words_meaning_rivardo.json');

        if (!File::exists($jsonPath)) {
            $this->command->error("JSON file not found at: {$jsonPath}");
            return;
        }

        $admin = User::where('email', 'admin@kazinduzi.org')->first();

        if (!$admin) {
            $this->command->error("Admin user not found. Please seed the admin user first.");
            return;
        }

        $data = json_decode(File::get($jsonPath), true);
        $addedCount = 0;

        foreach ($data as $entry) {
            if (!isset($entry['word'], $entry['type'], $entry['definitions'])) {
                continue; // Skip invalid entries
            }
        
            $formattedWord = trim($entry['word']) . ' (' . trim($entry['type']) . ')';
            $duplicateWord = Word::where('word', $formattedWord)->first();
        
            // Check for duplicates
            $existingWord = Word::whereRaw('LOWER(word) = ?', [strtolower($duplicateWord)])->first();

            if ($existingWord) continue;

            $baseWord = trim($entry['word']);
            $slug = \Str::slug($baseWord);

            // Skip duplicates by slug
            if (Word::where('slug', $slug)->exists()) {
                continue;
            }

            if (Word::where('word', $formattedWord)->exists()) {
                continue;
            }
        
            // Create the word
            $word = $admin->words()->firstOrCreate([
                'word' => $formattedWord,
                'slug' => $slug,
                'user_id' => $admin->id,
            ]);
        
            // Add definition as meaning
            $word->meanings()->create([
                'meaning' => is_array($entry['definitions']) 
                ? trim($entry['definitions'][0] ?? '')  // Use the first meaning if it's an array
                : trim($entry['definitions']),
                'user_id' => $admin->id,
            ]);
        
            // Apply reputation logic
            // Define your point system
        $admin->updateReputation(10, 'Submitted a new word', $word);
        
        $addedCount++;
        }                

        $this->command->info("Seeding completed. {$addedCount} new words added.");
    }
}
