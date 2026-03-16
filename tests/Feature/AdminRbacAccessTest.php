<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminRbacAccessTest extends TestCase
{
    public function test_admin_rbac_screen_returns_success_for_admin_session(): void
    {
        $response = $this->withSession([
            'mock_current_user' => [
                'id' => 1,
                'name' => 'Admin Mock',
                'role' => 'admin',
            ],
        ])->get(route('admin.rbac'));

        $response->assertOk();
    }

    public function test_admin_rbac_screen_returns_forbidden_for_non_admin_session(): void
    {
        $response = $this->withSession([
            'mock_current_user' => [
                'id' => 2,
                'name' => 'Doctor Mock',
                'role' => 'doctor',
            ],
        ])->get(route('admin.rbac'));

        $response->assertForbidden();
    }
}
