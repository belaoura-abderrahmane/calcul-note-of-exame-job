<?php

use App\Models\Competition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_HELP = 'تاريخ الحصول على الشهادة: 0.5 نقطة عن كل سنة كاملة من تاريخ الحصول على الشهادة إلى غاية تاريخ فتح المسابقة، في حدود 5 نقاط.';

    private const NEW_HELP = 'تاريخ الحصول على الشهادة: 0.5 نقطة عن كل سنة و0.04 نقطة عن كل شهر من تاريخ الحصول على الشهادة إلى غاية تاريخ فتح المسابقة، في حدود 5 نقاط. يُحسب تلقائياً من التاريخ.';

    /**
     * أقدمية الشهادة تُحسب بالسنة والشهر: السلالم التي تمنح 0.5 للسنة تأخذ 0.04 للشهر،
     * والبقية تبقى على السنوات الكاملة (0 للشهر). ثم يُعاد حساب نقاط المترشحين.
     */
    public function up(): void
    {
        foreach (DB::table('scoring_profiles')->get() as $profile) {
            $rules = json_decode($profile->rules, true) ?: [];
            $seniority = $rules['seniority'] ?? [];

            if (! array_key_exists('per_month', $seniority)) {
                $seniority['per_month'] = (float) ($seniority['per_year'] ?? 0.5) === 0.5 ? 0.04 : 0;
            }
            if (($seniority['help'] ?? self::OLD_HELP) === self::OLD_HELP && $seniority['per_month'] > 0) {
                $seniority['help'] = self::NEW_HELP;
            }

            $rules['seniority'] = $seniority;
            DB::table('scoring_profiles')->where('id', $profile->id)
                ->update(['rules' => json_encode($rules, JSON_UNESCAPED_UNICODE)]);
        }

        Competition::query()->each(fn (Competition $competition): int => $competition->recalculate());
    }

    public function down(): void
    {
        foreach (DB::table('scoring_profiles')->get() as $profile) {
            $rules = json_decode($profile->rules, true) ?: [];
            unset($rules['seniority']['per_month']);
            DB::table('scoring_profiles')->where('id', $profile->id)
                ->update(['rules' => json_encode($rules, JSON_UNESCAPED_UNICODE)]);
        }
    }
};
