<?php

use App\Support\ScoringPresets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تفصيل علامة المقابلة إلى عناصر (القدرة على التحليل والتلخيص، التواصل، المؤهلات الخاصة).
     * السلالم المبنية على السلم العام تأخذ العناصر الثلاثة، والبقية تبقى بعلامة واحدة.
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->json('interview_details')->nullable()->after('interview');
        });

        $base = ScoringPresets::baseRules()['interview'];
        $withItems = ['fonction-publique', 'sante-praticiens', 'docteurs-fonction-publique', 'personnalise'];
        $oldHelp = 'المقابلة مع لجنة الانتقاء (من 0 إلى 3 نقاط): تقييم معارف المترشح ومدى تحكمه في التخصص، قدرته على التواصل والتحليل، ودوافعه للالتحاق بالرتبة.';
        $oldHealthHelp = 'المقابلة (من 0 إلى 3 نقاط): القدرة على التحليل (1)، القدرة على التواصل (1)، القدرات أو المؤهلات الخاصة (1).';

        foreach (DB::table('scoring_profiles')->get() as $profile) {
            $rules = json_decode($profile->rules, true) ?: [];
            $interview = $rules['interview'] ?? [];

            if (! array_key_exists('items', $interview)) {
                $applies = in_array($profile->slug, $withItems, true) && (float) ($interview['max'] ?? 3) === 3.0;
                $interview['items'] = $applies ? $base['items'] : [];

                if ($applies && in_array($interview['help'] ?? $oldHelp, [$oldHelp, $oldHealthHelp], true)) {
                    $interview['help'] = $base['help'];
                }
            }

            $rules['interview'] = $interview;
            DB::table('scoring_profiles')->where('id', $profile->id)
                ->update(['rules' => json_encode($rules, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('interview_details');
        });
    }
};
