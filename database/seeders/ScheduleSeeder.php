<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScheduleSeeder extends Seeder
{
	public function run(): void
	{
		$doctor = User::role('doctor')->first();

		if ($doctor) {
			$schedules = [
				['start_time' => '09:00:00', 'end_time' => '09:30:00', 'status' => 'available'],
				['start_time' => '09:30:00', 'end_time' => '10:00:00', 'status' => 'blocked'],
				['start_time' => '10:00:00', 'end_time' => '10:30:00', 'status' => 'available'],
				['start_time' => '11:00:00', 'end_time' => '11:30:00', 'status' => 'available'],
				['start_time' => '14:00:00', 'end_time' => '14:30:00', 'status' => 'available'],
			];

			foreach ($schedules as $schedule) {
				DB::table('schedules')->insertOrIgnore([
					'doctor_id'  => $doctor->id,
					'date'       => Carbon::today()->format('Y-m-d'),
					'start_time' => $schedule['start_time'],
					'end_time'   => $schedule['end_time'],
					'status'     => $schedule['status'],
					'created_at' => now(),
					'updated_at' => now(),
				]);
			}
		}
	}
}
