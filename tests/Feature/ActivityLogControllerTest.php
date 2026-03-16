<?php

namespace Tests\Feature;

use Tests\TestCase;

class ActivityLogControllerTest extends TestCase
{
    public function test_logs_endpoint_returns_filters_and_log_rows(): void
    {
        $response = $this->getJson(route('admin.logs.data'));

        $response->assertOk()
            ->assertJson([
                'filters' => [
                    'actions' => ['login', 'logout', 'create', 'update', 'delete'],
                    'models' => ['User', 'Appointment', 'Schedule', 'Specialty'],
                ],
            ])
            ->assertJsonStructure([
                'logs' => [
                    '*' => [
                        'id',
                        'userId',
                        'userName',
                        'action',
                        'modelType',
                        'description',
                        'ipAddress',
                        'userAgent',
                        'createdAt',
                        'oldValues',
                        'newValues',
                    ],
                ],
            ]);
    }
}
