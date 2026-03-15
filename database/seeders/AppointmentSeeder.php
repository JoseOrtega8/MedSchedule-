<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AppointmentSeeder extends Seeder
{
	public function run(): void
	{
		$doctor  = User::role('doctor')->first();
		$patient = User::role('patient')->first();

		if ($doctor && $patient) {
			$appointments = [
				[
					'appointment_date' => Carbon::today()->format('Y-m-d'),
					'start_time'       => '09:30:00',
					'end_time'         => '10:00:00',
					'status'           => 'confirmed',
					'reason'           => 'Dolor en el pecho al hacer ejercicio',
				],
				[
					'appointment_date' => Carbon::today()->format('Y-m-d'),
					'start_time'       => '11:00:00',
					'end_time'         => '11:30:00',
					'status'           => 'pending',
					'reason'           => 'Revisión general',
				],
				[
					'appointment_date' => Carbon::yesterday()->format('Y-m-d'),
					'start_time'       => '09:00:00',
					'end_time'         => '09:30:00',
					'status'           => 'completed',
					'reason'           => 'Control de presión arterial',
				],
				[
					'appointment_date' => Carbon::yesterday()->format('Y-m-d'),
					'start_time'       => '10:00:00',
					'end_time'         => '10:30:00',
					'status'           => 'cancelled',
					'reason'           => 'No pudo asistir',
				],
			];

			foreach ($appointments as $appointment) {
				DB::table('appointments')->insertOrIgnore([
					'patient_id'       => $patient->id,
					'doctor_id'        => $doctor->id,
					'schedule_id'      => null,
					'specialty_id'     => 1,
					'appointment_date' => $appointment['appointment_date'],
					'start_time'       => $appointment['start_time'],
					'end_time'         => $appointment['end_time'],
					'status'           => $appointment['status'],
					'reason'           => $appointment['reason'],
					'created_at'       => now(),
					'updated_at'       => now(),
				]);
			}
		}
	}
}
