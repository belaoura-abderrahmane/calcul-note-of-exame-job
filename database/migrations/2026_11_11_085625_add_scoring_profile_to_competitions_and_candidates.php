<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->foreignId('scoring_profile_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->decimal('max_total', 5, 2)->default(30)->after('total');
            $table->decimal('training_points', 5, 2)->nullable()->after('trainings_details');
            $table->decimal('research_points', 5, 2)->nullable()->after('research_details');
        });

        // السلم الافتراضي يرث التعديلات التي أجراها المستخدم سابقاً في صفحة "إعدادات التنقيط"
        $default = DB::table('scoring_profiles')->where('is_default', true)->first();
        $legacy = Schema::hasTable('scoring_settings') ? DB::table('scoring_settings')->value('rules') : null;

        if ($default && $legacy) {
            $rules = array_replace(json_decode($default->rules, true), json_decode($legacy, true));
            foreach (['specialty', 'path', 'training', 'research', 'experience', 'seniority', 'interview'] as $key) {
                $rules[$key] = array_replace(json_decode($default->rules, true)[$key] ?? [], json_decode($legacy, true)[$key] ?? []);
            }

            // الإعدادات القديمة لم تكن تحدد سقفاً لكل نوع خبرة: نأخذه من السلم الجديد
            $defaultTypes = collect(json_decode($default->rules, true)['experience']['types'] ?? [])->keyBy('key');
            $rules['experience']['types'] = collect($rules['experience']['types'] ?? [])
                ->map(fn (array $type): array => $type + ['max' => $defaultTypes[$type['key'] ?? '']['max'] ?? null])
                ->all();
            DB::table('scoring_profiles')->where('id', $default->id)->update(['rules' => json_encode($rules, JSON_UNESCAPED_UNICODE)]);
        }

        if ($default) {
            DB::table('competitions')->whereNull('scoring_profile_id')->update(['scoring_profile_id' => $default->id]);
        }

        Schema::dropIfExists('scoring_settings');
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['max_total', 'training_points', 'research_points']);
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scoring_profile_id');
        });

        Schema::create('scoring_settings', function (Blueprint $table) {
            $table->id();
            $table->json('rules');
            $table->timestamps();
        });
    }
};
