<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();
		// Crear roles necesarios
		Role::create(['name' => 'admin', 'guard_name' => 'web']);
		Role::create(['name' => 'doctor', 'guard_name' => 'web']);
		Role::create(['name' => 'patient', 'guard_name' => 'web']);
	}

	public function test_login_muestra_formulario(): void
	{
		$response = $this->get('/login');
		$response->assertStatus(200);
	}

	public function test_login_exitoso_admin_redirige_a_dashboard(): void
	{
		$user = User::factory()->create([
			'password' => bcrypt('Password1!'),
		]);
		$user->assignRole('admin');

		$response = $this->post('/login', [
			'email'    => $user->email,
			'password' => 'Password1!',
		]);

		$response->assertRedirect('/admin/dashboard');
	}

	public function test_login_fallido_con_credenciales_incorrectas(): void
	{
		$response = $this->post('/login', [
			'email'    => 'noexiste@test.com',
			'password' => 'wrongpassword',
		]);

		$response->assertSessionHasErrors();
	}

	public function test_logout_destruye_sesion(): void
	{
		$user = User::factory()->create();
		$user->assignRole('patient');

		$this->actingAs($user);
		$response = $this->post('/logout');

		$response->assertRedirect('/');
		$this->assertGuest();
	}

	public function test_ruta_protegida_redirige_a_login_sin_autenticar(): void
	{
		$response = $this->get('/admin/dashboard');
		$response->assertRedirect('/login');
	}
}
