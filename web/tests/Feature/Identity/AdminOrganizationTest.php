<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The admin organization pages. API_Design.md §7.4, §7.6, P33,
 * web/docs/ACCOUNT-MANAGEMENT.md ("Inactive organizations"),
 * web/docs/TRACK-A-ACCOUNTS.md step A6.
 */
class AdminOrganizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_it_lets_only_admins_reach_the_organization_pages(): void
    {
        $student = User::factory()->create();
        $organization = Organization::findOrFail($student->organization_id);
        $requests = [
            ['get', route('admin.organizations.index')],
            ['get', route('admin.organizations.create')],
            ['post', route('admin.organizations.store')],
            ['get', route('admin.organizations.edit', $organization)],
            ['put', route('admin.organizations.update', $organization)],
        ];

        foreach ($requests as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }

        $this->actingAs($student);

        foreach ($requests as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    public function test_it_lists_organizations_with_their_status(): void
    {
        Organization::factory()->create(['name' => 'Open College', 'code' => 'OPEN']);
        Organization::factory()->inactive()->create(['name' => 'Closed College', 'description' => 'Merged in 2025.']);

        $this->actingAs($this->admin)
            ->get(route('admin.organizations.index'))
            ->assertOk()
            ->assertSeeInOrder(['Closed College', 'Merged in 2025.', 'Inactive', 'Open College', 'OPEN', 'Active']);
    }

    public function test_it_sorts_organizations_by_name(): void
    {
        foreach (['Bravo College', 'Alpha College', 'Charlie College'] as $name) {
            Organization::factory()->create(['name' => $name]);
        }

        $this->actingAs($this->admin)
            ->get(route('admin.organizations.index'))
            ->assertSeeInOrder(['Alpha College', 'Bravo College', 'Charlie College']);
    }

    public function test_it_paginates_the_organization_list(): void
    {
        Organization::factory()->count(16)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.organizations.index'))->assertOk();

        $this->assertCount(15, $response->viewData('organizations'));
        $this->assertSame(16, $response->viewData('organizations')->total());
    }

    public function test_it_starts_a_new_organization_as_active(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.organizations.create'))
            ->assertOk()
            ->assertSee('value="1" checked', false);
    }

    public function test_it_creates_an_organization(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.organizations.store'), $this->form())
            ->assertRedirect(route('admin.organizations.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('organizations', [
            'name' => 'College of Engineering',
            'code' => 'COE',
            'description' => null,
            'is_active' => true,
        ]);
    }

    public function test_it_requires_a_unique_code(): void
    {
        Organization::factory()->create(['code' => 'COE']);

        $this->actingAs($this->admin)
            ->post(route('admin.organizations.store'), $this->form())
            ->assertSessionHasErrors('code');

        $this->assertDatabaseCount('organizations', 1);
    }

    public function test_it_keeps_what_was_typed_when_the_code_is_taken(): void
    {
        Organization::factory()->create(['code' => 'COE']);

        $this->actingAs($this->admin)
            ->from(route('admin.organizations.create'))
            ->followingRedirects()
            ->post(route('admin.organizations.store'), $this->form(['name' => 'Typed Name']))
            ->assertSee('value="Typed Name"', false)
            ->assertSee('The code has already been taken.');
    }

    public function test_it_keeps_the_field_limits_from_the_docs(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.organizations.store'), $this->form([
            'name' => str_repeat('a', 151),
            'code' => str_repeat('a', 51),
            'description' => str_repeat('a', 256),
        ]))->assertSessionHasErrors(['name', 'code', 'description']);

        $this->post(route('admin.organizations.store'), $this->form([
            'name' => str_repeat('a', 150),
            'code' => str_repeat('a', 50),
            'description' => str_repeat('a', 255),
        ]))->assertSessionHasNoErrors();
    }

    public function test_it_requires_a_name_and_a_code(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.organizations.store'), $this->form(['name' => '', 'code' => '']))
            ->assertSessionHasErrors(['name', 'code']);
    }

    public function test_it_shows_an_organization_on_its_edit_form(): void
    {
        $organization = Organization::factory()->create(['name' => 'College of Arts', 'code' => 'CAS']);

        $this->actingAs($this->admin)
            ->get(route('admin.organizations.edit', $organization))
            ->assertOk()
            ->assertSee('value="College of Arts"', false)
            ->assertSee('value="CAS"', false);
    }

    public function test_it_updates_an_organization_that_keeps_its_code(): void
    {
        $organization = Organization::factory()->create(['code' => 'COE']);

        $this->actingAs($this->admin)
            ->put(route('admin.organizations.update', $organization), $this->form(['name' => 'Renamed']))
            ->assertRedirect(route('admin.organizations.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $organization->fresh()->name);
    }

    public function test_it_refuses_a_code_another_organization_has(): void
    {
        Organization::factory()->create(['code' => 'COE']);
        $organization = Organization::factory()->create(['code' => 'CCS']);

        $this->actingAs($this->admin)
            ->put(route('admin.organizations.update', $organization), $this->form())
            ->assertSessionHasErrors('code');

        $this->assertSame('CCS', $organization->fresh()->code);
    }

    public function test_it_keeps_students_of_a_disabled_organization(): void
    {
        $student = User::factory()->create(['password' => 'correct-horse']);
        $organization = Organization::findOrFail($student->organization_id);

        $this->actingAs($this->admin)->put(
            route('admin.organizations.update', $organization),
            $this->form(['code' => $organization->code, 'is_active' => '0']),
        )->assertSessionHasNoErrors();

        $this->assertFalse($organization->fresh()->is_active);
        $this->assertSame($organization->id, $student->fresh()->organization_id);
        $this->assertTrue($student->fresh()->isActive());

        auth()->logout();
        $this->post(route('login'), ['email' => $student->email, 'password' => 'correct-horse']);
        $this->assertAuthenticatedAs($student);
    }

    public function test_it_takes_no_new_students_into_a_disabled_organization(): void
    {
        $organization = Organization::factory()->create(['name' => 'Closed College', 'code' => 'OLD']);

        $this->actingAs($this->admin)->put(
            route('admin.organizations.update', $organization),
            $this->form(['name' => 'Closed College', 'code' => 'OLD', 'is_active' => '0']),
        );

        $this->get(route('admin.users.create'))->assertDontSee('Closed College');
        $this->post(route('admin.users.import'), ['file' => UploadedFile::fake()->createWithContent(
            'students.csv',
            "student_number,first_name,last_name,email,organization_code\n2024-1,Ana,Lim,ana@example.com,OLD\n",
        )])->assertSessionHas('import_errors');

        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);

        auth()->logout();
        $this->get(route('register'))->assertDontSee('Closed College');
    }

    public function test_it_takes_students_again_once_switched_back_on(): void
    {
        $organization = Organization::factory()->inactive()->create(['name' => 'Reopened College', 'code' => 'OLD']);

        $this->actingAs($this->admin)
            ->put(route('admin.organizations.update', $organization), $this->form(['name' => 'Reopened College', 'code' => 'OLD']))
            ->assertSessionHasNoErrors();

        $this->get(route('admin.users.create'))->assertSee('Reopened College');
    }

    public function test_it_has_no_delete_route(): void
    {
        $organization = Organization::factory()->create();

        $this->actingAs($this->admin)
            ->delete('/admin/organizations/'.$organization->id)
            ->assertMethodNotAllowed();

        $this->assertModelExists($organization);
    }

    private function form(array $overrides = []): array
    {
        return [
            'name' => 'College of Engineering',
            'code' => 'COE',
            'description' => '',
            'is_active' => '1',
            ...$overrides,
        ];
    }
}
