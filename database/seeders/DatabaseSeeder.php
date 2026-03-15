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
		$admin = User::firstOrCreate(['email' => 'admin@test.com'], [
			'name'      => 'Admin',
			'last_name' => 'Robel',
			'password'  => bcrypt('password'),
		]);
		$admin->assignRole($adminRole);

		// Crear doctor
		$doctor = User::firstOrCreate(['email' => 'doctor@test.com'], [
			'name'      => 'Doctor',
			'last_name' => 'Test',
			'password'  => bcrypt('password'),
		]);
		$doctor->assignRole($doctorRole);

		// Crear patient
		$patient = User::firstOrCreate(['email' => 'patient@test.com'], [
			'name'      => 'Patient',
			'last_name' => 'Test',
			'password'  => bcrypt('password'),
		]);
		$patient->assignRole($patientRole);

		// Seeders adicionales
		$this->call([
			DoctorProfileSeeder::class,
			PatientProfileSeeder::class,
			ScheduleSeeder::class,
			AppointmentSeeder::class,
		]);
	}
}
