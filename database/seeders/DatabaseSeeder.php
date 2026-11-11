<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'المدير', 'password' => 'password'],
        );

        // سلالم التنقيط تُنشأ تلقائياً في الترحيل (migration) من App\Support\ScoringPresets

        // في نسخة سطح المكتب نبدأ بقاعدة فارغة (بدون بيانات تجريبية)
        if (config('app.desktop') || Competition::exists()) {
            return;
        }

        // بيانات تجريبية
        $competition = Competition::create([
            'title' => 'مسابقة على أساس الشهادة للالتحاق برتبة متصرف',
            'institution' => 'مديرية الإدارة المحلية',
            'grade' => 'متصرف',
            'required_diploma' => 'ليسانس',
            'required_specialty' => 'علوم قانونية وإدارية، علوم اقتصادية',
            'positions' => 2,
            'reference_date' => '2026-10-15',
            'exam_date' => '2026-11-01',
            'status' => 'open',
        ]);

        $rows = [
            ['بن علي', 'أحمد', '1994-03-12', 'علوم قانونية', '2017-06-30', 6, 14.20, 'bien', 2, 1, [['type' => 'same_institution', 'years' => 4, 'months' => 6]], 2.5],
            ['قاسمي', 'فاطمة', '1997-08-21', 'علوم اقتصادية', '2020-07-15', 6, 15.60, 'tres_bien', 1, 0, [['type' => 'daip', 'years' => 3, 'months' => 0], ['type' => 'private', 'years' => 2, 'months' => 0]], 2.75],
            ['بوزيد', 'محمد', '1990-01-05', 'علوم إدارية', '2013-09-10', 5, 12.10, 'assez_bien', 4, 2, [['type' => 'other_public', 'years' => 8, 'months' => 0]], 2],
            ['مرابط', 'خديجة', '1999-11-30', 'علوم قانونية', '2022-06-20', 6, 16.40, 'excellent', 0, 1, [], 3],
            ['حمدي', 'يوسف', '1995-05-17', 'علوم اقتصادية', '2018-07-01', 4, 11.30, null, 1, 0, [['type' => 'private', 'years' => 5, 'months' => 3]], 1.5],
        ];

        foreach ($rows as [$ln, $fn, $bd, $sp, $dd, $sm, $avg, $mention, $tr, $rs, $exp, $int]) {
            Candidate::create([
                'competition_id' => $competition->id,
                'last_name' => $ln,
                'first_name' => $fn,
                'birth_date' => $bd,
                'diploma' => 'ليسانس',
                'specialty' => $sp,
                'diploma_date' => $dd,
                'specialty_match' => $sm,
                'average' => $avg,
                'master_mention' => $mention,
                'trainings_count' => $tr,
                'research_count' => $rs,
                'experiences' => $exp,
                'interview' => $int,
            ]);
        }
    }
}
