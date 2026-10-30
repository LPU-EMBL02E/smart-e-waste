<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The user factory every other test builds on.
 * Database_Schema.md §2–§3, web/docs/TRACK-A-ACCOUNTS.md step A0.
 */
class UserFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_each_factory_state(): void
    {
        $student = User::factory()->create();
        $this->assertTrue($student->isStudent());
        $this->assertTrue($student->isActive());
        $this->assertTrue($student->isActivated());
        $this->assertNotNull($student->organization_id);
        $this->assertNotNull($student->qrCredential);

        $admin = User::factory()->admin()->create();
        $this->assertTrue($admin->isAdmin());
        $this->assertNull($admin->organization_id);
        $this->assertNull($admin->student_number);

        $this->assertFalse(User::factory()->inactive()->create()->isActive());
        $this->assertFalse(User::factory()->unactivated()->create()->isActivated());
    }
}
