<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PatientProfileSeeder extends Seeder
{
	public function run(): void
	{
		$patient = User::role('patient')->first();

		if ($patient) {
			DB::table('patient_profiles')->insertOrIgnore([
				'user_id'                 => $patient->id,
				'birth_date'              => '1995-03-12',
				'blood_type'              => 'O+',
				'allergies'               => 'Penicilina',
				'chronic_conditions'      => 'Hipertensión arterial',
				'emergency_contact_name'  => 'Juan López',
				'emergency_contact_phone' => '662-999-8877',
				'curp'                    => 'LOAA950312MSLPNN09',
				'created_at'              => now(),
				'updated_at'              => now(),
			]);
		}
	}
}
