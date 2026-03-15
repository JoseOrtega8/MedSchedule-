<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
	public function run(): void
	{
		// Crear roles
		$adminRole   = Role::firstOrCreate(['name' => 'admin']);
		$doctorRole  = Role::firstOrCreate(['name' => 'doctor']);
		$patientRole = Role::firstOrCreate(['name' => 'patient']);

		// Crear usuario admin
		$admin = User::factory()->create([
			'name' => 'Admin',
			'last_name' => 'Robel',
			'email' => 'admin@test.com',
			'password' => bcrypt('password'),
		]);

		$admin->assignRole($adminRole);

		// Crear doctor
		$doctor = User::factory()->create([
			'name' => 'Doctor',
			'last_name' => 'Test',
			'email' => 'doctor@test.com',
			'password' => bcrypt('password'),
		]);

		$doctor->assignRole($doctorRole);

		// Crear patient
		$patient = User::factory()->create([
			'name' => 'Patient',
			'last_name' => 'Test',
			'email' => 'patient@test.com',
			'password' => bcrypt('password'),
		]);

		$patient->assignRole($patientRole);
	}
}
