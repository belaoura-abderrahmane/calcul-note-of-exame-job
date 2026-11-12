<?php

namespace Tests\Feature;

use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Filament\Resources\ScoringProfiles\Pages\CreateScoringProfile;
use App\Filament\Resources\ScoringProfiles\Pages\EditScoringProfile;
use App\Models\Candidate;
use App\Models\Competition;
use App\Models\ScoringProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScoringProfilesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::first());
    }

    public function test_builtin_scales_are_installed_with_the_general_grid_as_default(): void
    {
        $this->assertSame(
            ['fonction-publique', 'education-enseignants', 'enseignement-superieur-ma', 'sante-praticiens', 'docteurs-fonction-publique', 'personnalise'],
            ScoringProfile::orderBy('sort')->pluck('slug')->all(),
        );
        $this->assertSame('fonction-publique', ScoringProfile::default()->slug);
    }

    public function test_candidates_are_scored_with_the_scale_of_their_competition(): void
    {
        $teachers = ScoringProfile::where('slug', 'education-enseignants')->first();
        $competition = Competition::create(['title' => 'أساتذة', 'grade' => 'أستاذ', 'positions' => 1, 'scoring_profile_id' => $teachers->id]);

        $candidate = Candidate::create([
            'competition_id' => $competition->id,
            'last_name' => 'أ', 'first_name' => 'ب',
            'specialty_match' => 6, 'average' => 16, 'is_grande_ecole' => true, 'is_major' => true,
            'interview' => 3,
        ]);

        $this->assertSame(33.0, $candidate->max_total);
        $this->assertSame(10.0, $candidate->score_path);
        $this->assertSame(19.0, $candidate->total);
    }

    public function test_interview_details_are_entered_per_item_and_summed(): void
    {
        $competition = Competition::create(['title' => 'عامة', 'grade' => 'متصرف', 'positions' => 1]);

        Livewire::test(CreateCandidate::class)
            ->fillForm([
                'competition_id' => $competition->id,
                'last_name' => 'أ', 'first_name' => 'ب',
                'interview_details' => ['analysis' => 1, 'communication' => 0.75, 'qualifications' => 0.5],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $candidate = $competition->candidates()->firstOrFail();
        $this->assertSame(2.25, $candidate->score_interview);
        $this->assertSame(2.25, $candidate->interview);
    }

    public function test_editing_a_scale_recalculates_the_candidates_that_use_it(): void
    {
        $candidate = Candidate::first();
        $before = $candidate->total;
        $profile = $candidate->competition->scoringProfile;

        Livewire::test(EditScoringProfile::class, ['record' => $profile->getRouteKey()])
            ->fillForm(['rules.interview.max' => 0])
            ->call('save')
            ->assertHasNoFormErrors();

        $candidate->refresh();
        $this->assertSame(27.0, $candidate->max_total);
        $this->assertSame(0.0, $candidate->score_interview);
        $this->assertLessThan($before, $candidate->total);
    }

    public function test_a_new_scale_can_be_created_from_the_general_grid(): void
    {
        Livewire::test(CreateScoringProfile::class)
            ->fillForm(['name' => 'الشؤون الدينية - مسابقة الأئمة', 'rules.interview.max' => 5])
            ->call('create')
            ->assertHasNoFormErrors();

        $profile = ScoringProfile::where('name', 'الشؤون الدينية - مسابقة الأئمة')->firstOrFail();
        $this->assertSame(32.0, $profile->maxTotal());
        $this->assertFalse($profile->is_builtin);
    }

    public function test_only_one_scale_can_be_the_default(): void
    {
        ScoringProfile::where('slug', 'sante-praticiens')->first()->update(['is_default' => true]);

        $this->assertSame(1, ScoringProfile::where('is_default', true)->count());
        $this->assertSame('sante-praticiens', ScoringProfile::default()->slug);
    }

    public function test_scale_pages_and_forms_for_each_scale_render(): void
    {
        $this->get('/admin/scoring-profiles')->assertOk()->assertSee('التربية الوطنية');
        $this->get('/admin/scoring-profiles/create')->assertOk();

        foreach (ScoringProfile::all() as $profile) {
            $this->get("/admin/scoring-profiles/{$profile->id}/edit")->assertOk();

            $competition = Competition::create(['title' => 'م '.$profile->slug, 'grade' => 'ر', 'positions' => 1, 'scoring_profile_id' => $profile->id]);
            $this->get("/admin/candidates/create?competition={$competition->id}")->assertOk()->assertSee($profile->name);
            $this->get("/competitions/{$competition->id}/print")->assertOk();
        }

        $this->get('/admin/candidates/create?competition='.Competition::where('title', 'م enseignement-superieur-ma')->value('id'))
            ->assertSee('المنشورات العلمية')
            ->assertSee('تقدير شهادة الدكتوراه');
    }
}
