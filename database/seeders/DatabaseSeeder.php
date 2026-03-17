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

		// Crear usuarios
		$admin = User::firstOrCreate(['email' => 'admin@test.com'], [
			'name'      => 'Admin',
			'last_name' => 'Robel',
			'password'  => bcrypt('password'),
			'phone'     => '662-000-0001',
			'status'    => 1,
		]);
		$admin->assignRole($adminRole);

		$doctor = User::firstOrCreate(['email' => 'doctor@test.com'], [
			'name'      => 'Doctor',
			'last_name' => 'Test',
			'password'  => bcrypt('password'),
			'phone'     => '662-000-0002',
			'status'    => 1,
		]);
		$doctor->assignRole($doctorRole);

		$patient = User::firstOrCreate(['email' => 'patient@test.com'], [
			'name'      => 'Patient',
			'last_name' => 'Test',
			'password'  => bcrypt('password'),
			'phone'     => '662-000-0003',
			'status'    => 1,
		]);
		$patient->assignRole($patientRole);

		// Correr FullDataSeeder solo si no hay datos
		if (\App\Models\Specialty::count() === 0) {
			$this->call(FullDataSeeder::class);
		}
	}
}
