<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WordsFromJsonSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('data/words_meaning_rivardo.json');

        if (!File::exists($jsonPath)) {
            $this->command->error("JSON file not found at: {$jsonPath}");
            return;
        }

        $admin = User::where('email', env('DEFAULT_ADMIN_EMAIL'))->first();
        if (!$admin) {
            $this->command->error("Admin user not found. Please seed the admin user first.");
            return;
        }

        $data = json_decode(File::get($jsonPath), true);

        $added = 0;
        $skipped = 0;
        $invalid = 0;

        $this->command->info("Seeding words, please wait...");

        // Optional: Wrap in transaction for rollback on error
        DB::beginTransaction();

        try {
            $bar = $this->command->getOutput()->createProgressBar(count($data));
            $bar->start();

            foreach ($data as $entry) {
                $bar->advance();

                // Check structure
                if (!isset($entry['word'], $entry['type'], $entry['definitions'])) {
                    Log::warning("Invalid entry: " . json_encode($entry));
                    $invalid++;
                    continue;
                }

                $baseWord = trim($entry['word']);
                $baseType = trim($entry['type']);
                $slug = Str::slug($baseWord);

                // Skip if already exists
                if (Word::where('slug', $slug)->exists()) {
                    $skipped++;
                    continue;
                }

                // Create word
                $word = $admin->words()->create([
                    'word' => $baseWord,
                    'type' => $baseType,
                    'slug' => $slug,
                ]);

                // Extract first meaning
                $definition = is_array($entry['definitions'])
                    ? trim($entry['definitions'][0] ?? '')
                    : trim($entry['definitions']);

                if (!empty($definition)) {
                    $word->meanings()->create([
                        'meaning' => $definition,
                        'user_id' => $admin->id,
                    ]);

                }

                $admin->updateReputation(10, 'Submitted a new word', $word);

                $added++;
            }

            $bar->finish();
            $this->command->newLine(2);

            DB::commit();

            $this->command->info("✅ Seeding completed.");
            $this->command->info("🟢 Added: $added");
            $this->command->info("🟡 Skipped (duplicates): $skipped");
            $this->command->info("🔴 Invalid entries: $invalid");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("❌ Seeding failed: " . $e->getMessage());
            Log::error('Seeder error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }
}
