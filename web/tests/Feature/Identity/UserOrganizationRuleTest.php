<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Http\Requests\Admin\StoreUserRequest;
use App\Modules\Identity\Http\Requests\Admin\UpdateUserRequest;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * An inactive organization keeps its students but takes no new ones.
 * web/docs/ACCOUNT-MANAGEMENT.md, web/docs/TRACK-A-ACCOUNTS.md step A2.
 */
class UserOrganizationRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refuses_a_new_student_in_an_inactive_organization(): void
    {
        $organization = Organization::factory()->inactive()->create();

        $this->assertFalse($this->passes(StoreUserRequest::class, $this->form($organization)));
    }

    public function test_it_accepts_a_new_student_in_an_active_organization(): void
    {
        $organization = Organization::factory()->create();

        $this->assertTrue($this->passes(StoreUserRequest::class, $this->form($organization)));
    }

    public function test_it_lets_a_student_stay_in_their_inactive_organization(): void
    {
        $organization = Organization::factory()->inactive()->create();
        $student = User::factory()->for($organization)->create();

        $this->assertTrue($this->passes(UpdateUserRequest::class, $this->form($organization), $student));
    }

    public function test_it_refuses_moving_a_student_into_an_inactive_organization(): void
    {
        $student = User::factory()->create();
        $organization = Organization::factory()->inactive()->create();

        $this->assertFalse($this->passes(UpdateUserRequest::class, $this->form($organization), $student));
    }

    /**
     * @param  class-string<FormRequest>  $requestClass
     */
    private function passes(string $requestClass, array $input, ?User $routeUser = null): bool
    {
        $request = $requestClass::create('/', 'POST', $input);
        $route = (new Route('POST', '/', []))->bind($request);

        if ($routeUser !== null) {
            $route->setParameter('user', $routeUser);
        }

        $request->setRouteResolver(fn (): Route => $route);

        return Validator::make($input, $request->rules())->passes();
    }

    private function form(Organization $organization): array
    {
        return [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana@example.com',
            'student_number' => '2026000001',
            'role' => User::ROLE_STUDENT,
            'organization_id' => $organization->id,
            'status' => User::STATUS_ACTIVE,
        ];
    }
}
