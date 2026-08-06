<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Specialty;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class GoogleCalendarControllerTest extends TestCase
{
	use RefreshDatabase;

	private function makeAdmin(): User
	{
		Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
		Role::firstOrCreate(['name' => 'patient', 'guard_name' => 'web']);
		$role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
		$admin = User::factory()->create();
		$admin->assignRole($role);
		return $admin;
	}

	private function makeSpecialty(): Specialty
	{
		return Specialty::create(['name' => 'Cardiología', 'status' => 1]);
	}

	private function makeAppointment(User $admin, Specialty $specialty, string $status = 'pending'): Appointment
	{
		return Appointment::create([
			'patient_id'       => $admin->id,
			'doctor_id'        => $admin->id,
			'specialty_id'     => $specialty->id,
			'appointment_date' => '2026-03-22',
			'start_time'       => '10:00:00',
			'end_time'         => '10:30:00',
			'status'           => $status,
			'reason'           => 'Test',
		]);
	}

	public function test_sync_requires_authentication(): void
	{
		$response = $this->postJson(route('appointments.calendar.sync', 1));
		$response->assertUnauthorized();
	}

	public function test_unsync_requires_authentication(): void
	{
		$response = $this->deleteJson(route('appointments.calendar.unsync', 1));
		$response->assertUnauthorized();
	}

	public function test_sync_returns_404_for_nonexistent_appointment(): void
	{
		$admin = $this->makeAdmin();
		$response = $this->actingAs($admin)->postJson(route('appointments.calendar.sync', 999));
		$response->assertNotFound();
	}

	public function test_sync_rejects_non_confirmed_appointment(): void
	{
		$admin = $this->makeAdmin();
		$specialty = $this->makeSpecialty();
		$appointment = $this->makeAppointment($admin, $specialty, 'pending');

		$response = $this->actingAs($admin)->postJson(route('appointments.calendar.sync', $appointment->id));
		$response->assertStatus(422)
			->assertJsonPath('message', 'Solo se pueden sincronizar citas confirmadas.');
	}

	public function test_sync_confirmed_appointment_creates_event(): void
	{
		$admin = $this->makeAdmin();
		$specialty = $this->makeSpecialty();
		$appointment = $this->makeAppointment($admin, $specialty, 'confirmed');

		// Mock del servicio para no llamar a la API real
		$this->mock(GoogleCalendarService::class, function ($mock) {
			$mock->shouldReceive('createEvent')->once()->andReturn('fake-event-id-123');
		});

		$response = $this->actingAs($admin)->postJson(route('appointments.calendar.sync', $appointment->id));
		$response->assertOk()
			->assertJsonPath('google_event_id', 'fake-event-id-123');
	}

	public function test_sync_handles_calendar_failure(): void
	{
		$admin = $this->makeAdmin();
		$specialty = $this->makeSpecialty();
		$appointment = $this->makeAppointment($admin, $specialty, 'confirmed');

		$this->mock(GoogleCalendarService::class, function ($mock) {
			$mock->shouldReceive('createEvent')->once()->andReturn(null);
		});

		$response = $this->actingAs($admin)->postJson(route('appointments.calendar.sync', $appointment->id));
		$response->assertStatus(500);
	}

	public function test_unsync_returns_404_for_nonexistent_appointment(): void
	{
		$admin = $this->makeAdmin();
		$response = $this->actingAs($admin)->deleteJson(route('appointments.calendar.unsync', 999));
		$response->assertNotFound();
	}

	public function test_unsync_clears_google_event_id(): void
	{
		$admin = $this->makeAdmin();
		$specialty = $this->makeSpecialty();
		$appointment = $this->makeAppointment($admin, $specialty, 'confirmed');
		$appointment->update(['google_event_id' => 'existing-event-id']);

		$this->mock(GoogleCalendarService::class, function ($mock) {
			$mock->shouldReceive('deleteEvent')->once()->with('existing-event-id');
		});

		$response = $this->actingAs($admin)->deleteJson(route('appointments.calendar.unsync', $appointment->id));
		$response->assertOk();
		$this->assertNull($appointment->fresh()->google_event_id);
	}
}
