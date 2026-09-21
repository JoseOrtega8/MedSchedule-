<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Siembra las cuentas desechables que usan las pruebas de carga con k6.
 *
 * Existe por un hallazgo concreto: LoginRequest de Laravel Breeze limita a
 * cinco intentos de autenticacion por minuto y por combinacion de correo e IP.
 * Si todos los usuarios virtuales comparten una sola cuenta, a partir del sexto
 * intento la aplicacion responde 429 y la prueba termina midiendo el limitador
 * en lugar de la aplicacion. Con una cuenta por usuario virtual, ningun correo
 * se acerca al limite y las metricas describen el comportamiento real.
 *
 * Las cuentas son de prueba y viven solo en entornos efimeros de liberacion.
 * La contrasena se toma de K6_PASSWORD cuando esta definida.
 */
class CargaSeeder extends Seeder
{
	/** Numero de cuentas a sembrar: una por usuario virtual de la prueba. */
	private const TOTAL_USUARIOS_CARGA = 10;

	public function run(): void
	{
		$rol_paciente = Role::firstOrCreate(['name' => 'patient']);
		$password_carga = env('K6_PASSWORD', 'password');

		$doctor = User::role('doctor')->first();
		$id_especialidad = DB::table('specialties')->value('id');

		if (!$doctor || !$id_especialidad) {
			$this->command->warn(
				'No hay doctor o especialidad sembrados. Ejecutar antes DatabaseSeeder; ' .
					'las cuentas de carga se crean sin citas asociadas.'
			);
		}

		for ($numero = 1; $numero <= self::TOTAL_USUARIOS_CARGA; $numero++) {
			$correo = "carga{$numero}@test.com";

			$usuario = User::firstOrCreate(['email' => $correo], [
				'name'      => 'Carga',
				'last_name' => "Usuario {$numero}",
				'password'  => bcrypt($password_carga),
				'phone'     => sprintf('662-900-%04d', $numero),
				'status'    => 1,
			]);

			if (!$usuario->hasRole($rol_paciente)) {
				$usuario->assignRole($rol_paciente);
			}

			DB::table('patient_profiles')->updateOrInsert(
				['user_id' => $usuario->id],
				[
					'birth_date'              => Carbon::today()->subYears(30)->format('Y-m-d'),
					'blood_type'              => 'O+',
					'emergency_contact_name'  => 'Contacto de prueba',
					'emergency_contact_phone' => '662-900-0000',
					'updated_at'              => now(),
					'created_at'              => now(),
				]
			);

			if (!$doctor || !$id_especialidad) {
				continue;
			}

			// Cada cuenta lleva citas futuras y pasadas para que el panel del
			// paciente devuelva datos y la consulta no se resuelva sobre un
			// conjunto vacio, que falsearia la latencia medida.
			$this->sembrar_citas($usuario->id, $doctor->id, $id_especialidad);
		}

		$this->command->info(
			self::TOTAL_USUARIOS_CARGA . ' cuentas de carga listas (carga1@test.com … carga' .
				self::TOTAL_USUARIOS_CARGA . '@test.com).'
		);
	}

	/**
	 * Inserta ocho citas por paciente repartidas entre pasado y futuro.
	 */
	private function sembrar_citas(int $id_paciente, int $id_doctor, int $id_especialidad): void
	{
		$estados = ['pending', 'confirmed', 'completed', 'cancelled'];

		for ($indice = 0; $indice < 8; $indice++) {
			$dias_desplazamiento = $indice < 4 ? $indice + 1 : -($indice - 3);
			$fecha = Carbon::today()->addDays($dias_desplazamiento)->format('Y-m-d');
			$hora_inicio = sprintf('%02d:00:00', 8 + $indice);
			$hora_fin = sprintf('%02d:30:00', 8 + $indice);

			DB::table('appointments')->insertOrIgnore([
				'patient_id'       => $id_paciente,
				'doctor_id'        => $id_doctor,
				'schedule_id'      => null,
				'specialty_id'     => $id_especialidad,
				'appointment_date' => $fecha,
				'start_time'       => $hora_inicio,
				'end_time'         => $hora_fin,
				'status'           => $estados[$indice % 4],
				'reason'           => 'Cita generada para las pruebas de carga',
				'created_at'       => now(),
				'updated_at'       => now(),
			]);
		}
	}
}
