<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Organization writes. API_Design.md §7.6, P33,
 * web/docs/TRACK-A-ACCOUNTS.md step A2.
 */
class OrganizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_organization(): void
    {
        $organization = app(OrganizationService::class)->create([
            'name' => 'College of Engineering',
            'code' => 'COE',
            'description' => null,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('organizations', ['id' => $organization->id, 'code' => 'COE']);
    }

    public function test_it_keeps_students_when_an_organization_is_switched_off(): void
    {
        $student = User::factory()->create();
        $organization = Organization::findOrFail($student->organization_id);

        app(OrganizationService::class)->update($organization, ['is_active' => false]);

        $this->assertFalse($organization->fresh()->is_active);
        $this->assertSame($organization->id, $student->fresh()->organization_id);
        $this->assertTrue($student->fresh()->isActive());
    }
}
