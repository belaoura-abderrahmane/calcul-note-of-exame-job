<?php

use App\Models\Competition;
use App\Support\ScoringPresets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * - تطبيق أحكام الجريدة الرسمية عدد 13 لسنة 2020 على السلالم المبنية على السلم العام:
     *   تقدير الماجستير يعوض المعدل، علاوة المدارس العليا = 2، نوعا خبرة جديدان، ترتيب الفصل بين المتساوين.
     * - إضافة سلمي منشور رقم 7 لسنة 2011 غير الملغيين (التعليم في القطاعات الأخرى، التكوين المتخصص).
     * القيم التي عدّلها المستخدم بنفسه لا تُمس.
     */
    public function up(): void
    {
        $base = ScoringPresets::baseRules();
        $oldPathHelp = 'يُحتسب على أساس المعدل العام لمسار الدراسة أو التكوين (المعدل العام لكل سنوات الدراسة، حسب كشوف النقاط) وفق السلم أدناه. '
            .'تضاف علاوة تقدير شهادة الماستر/المهندس، وعلاوة خريجي المدارس العليا أو الأول على الدفعة، دون أن يتجاوز المجموع الحد الأقصى للمعيار.';
        $oldMentions = [
            ['key' => 'passable', 'label' => 'مقبول', 'bonus' => 0],
            ['key' => 'assez_bien', 'label' => 'قريب من الحسن', 'bonus' => 0.5],
            ['key' => 'bien', 'label' => 'حسن', 'bonus' => 1],
            ['key' => 'tres_bien', 'label' => 'حسن جداً', 'bonus' => 1.5],
            ['key' => 'excellent', 'label' => 'ممتاز', 'bonus' => 2],
        ];
        $derived = ['fonction-publique', 'education-enseignants', 'sante-praticiens', 'docteurs-fonction-publique', 'personnalise'];

        foreach (DB::table('scoring_profiles')->get() as $profile) {
            $rules = json_decode($profile->rules, true) ?: [];

            if (in_array($profile->slug, $derived, true) && ! isset($rules['path']['mention_mode'])) {
                $mentions = array_map(fn (array $m): array => array_intersect_key($m, array_flip(['key', 'label', 'bonus'])), $rules['path']['mentions'] ?? $oldMentions);
                if ($mentions == $oldMentions) {
                    $rules['path']['mentions'] = $base['path']['mentions'];
                    $rules['path']['mention_mode'] = 'replace';
                    $rules['path']['mention_label'] = $base['path']['mention_label'];
                }
                if (($rules['path']['help'] ?? $oldPathHelp) === $oldPathHelp) {
                    $rules['path']['help'] = $base['path']['help'];
                }
                if ((float) ($rules['path']['grande_ecole_bonus'] ?? 1) === 1.0) {
                    $rules['path']['grande_ecole_bonus'] = 2;
                }

                $types = collect($rules['experience']['types'] ?? []);
                if ($types->contains('key', 'same_institution')) {
                    foreach ($base['experience']['types'] as $type) {
                        if (in_array($type['key'], ['lower_post', 'part_time'], true) && ! $types->contains('key', $type['key'])) {
                            $types->push($type);
                        }
                    }
                    $rules['experience']['types'] = $types->values()->all();
                }
            }

            if (! isset($rules['ranking'])) {
                $rules['ranking'] = $profile->slug === 'enseignement-superieur-ma'
                    ? ScoringPresets::higherEducation()['rules']['ranking']
                    : $base['ranking'];
            }

            DB::table('scoring_profiles')->where('id', $profile->id)
                ->update(['rules' => json_encode($rules, JSON_UNESCAPED_UNICODE)]);
        }

        $sort = (int) DB::table('scoring_profiles')->max('sort');
        foreach ([ScoringPresets::teachingOtherSectors(), ScoringPresets::specializedTraining()] as $preset) {
            if (DB::table('scoring_profiles')->where('slug', $preset['slug'])->exists()) {
                continue;
            }

            DB::table('scoring_profiles')->insert([
                ...collect($preset)->except('rules')->all(),
                'rules' => json_encode($preset['rules'], JSON_UNESCAPED_UNICODE),
                'is_default' => false,
                'is_builtin' => true,
                'sort' => ++$sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // التقدير "ممتاز" غير موجود في النص: يُلحق بـ "حسن جداً أو مشرف جداً"
        DB::table('candidates')->where('master_mention', 'excellent')->update(['master_mention' => 'tres_bien']);

        Competition::query()->each(fn (Competition $competition): int => $competition->recalculate());
    }

    public function down(): void
    {
        DB::table('scoring_profiles')->whereIn('slug', ['enseignement-autres-secteurs', 'formation-specialisee'])->delete();
    }
};
