<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Error pages. web/docs/DESIGN.md §8. Device routes keep answering JSON
 * (AGENTS.md §2 rule 8; API_Design.md §5).
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_explains_a_missing_page_in_plain_words(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('Page not found');
    }

    public function test_it_explains_an_admin_page_to_a_student(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('You don')
            ->assertDontSee(route('admin.users.index'));
    }

    public function test_it_never_answers_a_device_route_with_an_error_page(): void
    {
        $response = $this->get('/api/v1/device/no-such-route');

        $response->assertHeader('Content-Type', 'application/json');
        $this->assertStringNotContainsString('<html', $response->getContent());
    }
}
