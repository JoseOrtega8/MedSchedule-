<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;

class FullDataSeeder extends Seeder
{
	public function run(): void
	{
		// ─── ESPECIALIDADES ───────────────────────────────────────
		$cardio = DB::table('specialties')->insertGetId([
			'name'        => 'Cardiología',
			'description' => 'Enfermedades del corazón y sistema cardiovascular',
			'status'      => 1,
			'created_at'  => now(),
			'updated_at'  => now(),
		]);

		$oftalmo = DB::table('specialties')->insertGetId([
			'name'        => 'Oftalmología',
			'description' => 'Enfermedades de los ojos',
			'status'      => 1,
			'created_at'  => now(),
			'updated_at'  => now(),
		]);

		$neuro = DB::table('specialties')->insertGetId([
			'name'        => 'Neurología',
			'description' => 'Enfermedades del sistema nervioso',
			'status'      => 1,
			'created_at'  => now(),
			'updated_at'  => now(),
		]);

		// ─── USUARIOS ─────────────────────────────────────────────
		$doctor  = User::where('email', 'doctor@test.com')->first();
		$patient = User::where('email', 'patient@test.com')->first();

		// ─── DOCTOR PROFILE ───────────────────────────────────────
		DB::table('doctor_profiles')->updateOrInsert(
			['user_id' => $doctor->id],
			[
				'specialty_id'          => $cardio,
				'license_number'        => 'CED-123456',
				'bio'                   => 'Cardiólogo con 10 años de experiencia en el IMSS.',
				'consultation_duration' => 30,
				'created_at'            => now(),
				'updated_at'            => now(),
			]
		);

		// ─── PATIENT PROFILE ──────────────────────────────────────
		DB::table('patient_profiles')->updateOrInsert(
			['user_id' => $patient->id],
			[
				'birth_date'              => '1995-03-12',
				'blood_type'              => 'O+',
				'allergies'               => 'Penicilina, Ibuprofeno',
				'chronic_conditions'      => 'Hipertensión arterial',
				'emergency_contact_name'  => 'Juan López',
				'emergency_contact_phone' => '662-999-8877',
				'curp'                    => 'LOAA950312MSLPNN09',
				'created_at'              => now(),
				'updated_at'              => now(),
			]
		);

		// ─── SCHEDULES ────────────────────────────────────────────
		$schedules = [
			['start_time' => '09:00:00', 'end_time' => '09:30:00', 'status' => 'available'],
			['start_time' => '09:30:00', 'end_time' => '10:00:00', 'status' => 'blocked'],
			['start_time' => '10:00:00', 'end_time' => '10:30:00', 'status' => 'available'],
			['start_time' => '11:00:00', 'end_time' => '11:30:00', 'status' => 'available'],
			['start_time' => '14:00:00', 'end_time' => '14:30:00', 'status' => 'blocked'],
		];

		$scheduleIds = [];
		foreach ($schedules as $s) {
			$scheduleIds[] = DB::table('schedules')->insertGetId([
				'doctor_id'  => $doctor->id,
				'date'       => Carbon::today()->format('Y-m-d'),
				'start_time' => $s['start_time'],
				'end_time'   => $s['end_time'],
				'status'     => $s['status'],
				'created_at' => now(),
				'updated_at' => now(),
			]);
		}

		// ─── APPOINTMENTS ─────────────────────────────────────────
		$apt1 = DB::table('appointments')->insertGetId([
			'patient_id'       => $patient->id,
			'doctor_id'        => $doctor->id,
			'schedule_id'      => $scheduleIds[1], // blocked
			'specialty_id'     => $cardio,
			'appointment_date' => Carbon::today()->format('Y-m-d'),
			'start_time'       => '09:30:00',
			'end_time'         => '10:00:00',
			'status'           => 'confirmed',
			'reason'           => 'Dolor en el pecho al hacer ejercicio',
			'created_at'       => now(),
			'updated_at'       => now(),
		]);

		$apt2 = DB::table('appointments')->insertGetId([
			'patient_id'       => $patient->id,
			'doctor_id'        => $doctor->id,
			'schedule_id'      => $scheduleIds[4], // blocked
			'specialty_id'     => $cardio,
			'appointment_date' => Carbon::today()->format('Y-m-d'),
			'start_time'       => '14:00:00',
			'end_time'         => '14:30:00',
			'status'           => 'pending',
			'reason'           => 'Revisión general',
			'created_at'       => now(),
			'updated_at'       => now(),
		]);

		$apt3 = DB::table('appointments')->insertGetId([
			'patient_id'       => $patient->id,
			'doctor_id'        => $doctor->id,
			'schedule_id'      => null,
			'specialty_id'     => $cardio,
			'appointment_date' => Carbon::yesterday()->format('Y-m-d'),
			'start_time'       => '09:00:00',
			'end_time'         => '09:30:00',
			'status'           => 'completed',
			'reason'           => 'Control de presión arterial',
			'created_at'       => now(),
			'updated_at'       => now(),
		]);

		$apt4 = DB::table('appointments')->insertGetId([
			'patient_id'       => $patient->id,
			'doctor_id'        => $doctor->id,
			'schedule_id'      => null,
			'specialty_id'     => $cardio,
			'appointment_date' => Carbon::yesterday()->format('Y-m-d'),
			'start_time'       => '10:00:00',
			'end_time'         => '10:30:00',
			'status'           => 'cancelled',
			'reason'           => 'No pudo asistir',
			'created_at'       => now(),
			'updated_at'       => now(),
		]);

		// ─── APPOINTMENT HISTORY ──────────────────────────────────
		DB::table('appointment_history')->insert([
			'appointment_id'           => $apt3,
			'diagnosis'                => 'Hipertensión arterial leve',
			'treatment'                => 'Dieta baja en sodio, ejercicio moderado',
			'prescription'             => 'Losartán 50mg — 1 vez al día',
			'follow_up_notes'          => 'Regresar en 30 días para control',
			'next_appointment_suggested' => Carbon::today()->addDays(30)->format('Y-m-d'),
			'created_at'               => now(),
			'updated_at'               => now(),
		]);

		// ─── ACTIVITY LOGS ────────────────────────────────────────
		$admin = User::where('email', 'admin@test.com')->first();

		DB::table('activity_logs')->insert([
			[
				'user_id'     => $patient->id,
				'action'      => 'create',
				'model_type'  => 'Appointment',
				'model_id'    => $apt1,
				'description' => 'Cita agendada con Dr. Test',
				'ip_address'  => '127.0.0.1',
				'user_agent'  => 'Mozilla/5.0',
				'old_values'  => null,
				'new_values'  => json_encode(['status' => 'confirmed']),
				'created_at'  => now(),
				'updated_at'  => now(),
			],
			[
				'user_id'     => $doctor->id,
				'action'      => 'update',
				'model_type'  => 'Appointment',
				'model_id'    => $apt3,
				'description' => 'Cita completada',
				'ip_address'  => '127.0.0.1',
				'user_agent'  => 'Mozilla/5.0',
				'old_values'  => json_encode(['status' => 'confirmed']),
				'new_values'  => json_encode(['status' => 'completed']),
				'created_at'  => now(),
				'updated_at'  => now(),
			],
			[
				'user_id'     => $patient->id,
				'action'      => 'delete',
				'model_type'  => 'Appointment',
				'model_id'    => $apt4,
				'description' => 'Cita cancelada por paciente',
				'ip_address'  => '127.0.0.1',
				'user_agent'  => 'Mozilla/5.0',
				'old_values'  => json_encode(['status' => 'pending']),
				'new_values'  => json_encode(['status' => 'cancelled']),
				'created_at'  => now(),
				'updated_at'  => now(),
			],
		]);

		$this->command->info('✅ FullDataSeeder completado con todas las tablas.');
	}
}
