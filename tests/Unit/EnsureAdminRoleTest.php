<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureAdminRole;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureAdminRoleTest extends TestCase
{
    public function test_handle_allows_admin_user(): void
    {
        $middleware = new EnsureAdminRole();
        $request = Request::create('/admin/rbac', 'GET');
        $session = new Store('test', new ArraySessionHandler(1));
        $session->start();
        $session->put('mock_current_user', [
            'id' => 1,
            'name' => 'Admin Mock',
            'role' => 'admin',
        ]);
        $request->setLaravelSession($session);

        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_handle_throws_for_non_admin_user(): void
    {
        $middleware = new EnsureAdminRole();
        $request = Request::create('/admin/rbac', 'GET');
        $session = new Store('test', new ArraySessionHandler(1));
        $session->start();
        $session->put('mock_current_user', [
            'id' => 2,
            'name' => 'Doctor Mock',
            'role' => 'doctor',
        ]);
        $request->setLaravelSession($session);

        try {
            $middleware->handle($request, function () {
                return new Response('ok');
            });

            $this->fail('Se esperaba una excepcion 403 para un usuario sin rol admin.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
