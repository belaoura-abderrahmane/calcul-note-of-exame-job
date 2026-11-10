<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelPagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_all_admin_pages_render(): void
    {
        $this->actingAs(User::first());
        $competition = Competition::first();
        $candidate = Candidate::first();

        foreach ([
            '/admin', '/admin/competitions', '/admin/competitions/create', "/admin/competitions/{$competition->id}/edit",
            '/admin/candidates', '/admin/candidates/create', "/admin/candidates/{$candidate->id}/edit",
            '/admin/quick-calculator', '/admin/scoring-settings', "/competitions/{$competition->id}/print",
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_print_lists_candidates_in_rank_order(): void
    {
        $this->actingAs(User::first());
        $competition = Competition::first();

        $this->get("/competitions/{$competition->id}/print")
            ->assertSeeInOrder(['بن علي', 'بوزيد', 'قاسمي', 'مرابط', 'حمدي']);
    }

    public function test_desktop_mode_logs_in_automatically(): void
    {
        config(['app.desktop' => true]);

        $this->get('/admin')->assertOk()->assertSee('/__desktop/ping', false);
    }
}
