<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DoctorProfileSeeder extends Seeder
{
	public function run(): void
	{
		$doctor = User::role('doctor')->first();

		if ($doctor) {
			DB::table('doctor_profiles')->insertOrIgnore([
				'user_id'              => $doctor->id,
				'specialty_id'         => 1,
				'license_number'       => 'CED-123456',
				'bio'                  => 'Cardiólogo con 10 años de experiencia.',
				'consultation_duration' => 30,
				'created_at'           => now(),
				'updated_at'           => now(),
			]);
		}
	}
}
