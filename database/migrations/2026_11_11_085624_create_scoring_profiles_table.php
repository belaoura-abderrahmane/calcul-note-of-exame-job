<?php

use App\Support\ScoringPresets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sector')->nullable();
            $table->text('legal_reference')->nullable();
            $table->text('description')->nullable();
            $table->json('rules');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_builtin')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach (ScoringPresets::all() as $i => $preset) {
            DB::table('scoring_profiles')->insert([
                'name' => $preset['name'],
                'slug' => $preset['slug'],
                'sector' => $preset['sector'],
                'legal_reference' => $preset['legal_reference'],
                'description' => $preset['description'],
                'rules' => json_encode($preset['rules'], JSON_UNESCAPED_UNICODE),
                'is_default' => $i === 0,
                'is_builtin' => true,
                'sort' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_profiles');
    }
};
