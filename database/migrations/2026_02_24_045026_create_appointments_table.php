<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('appointments', function (Blueprint $table) {
			$table->id();
			$table->foreignId('patient_id')->constrained('users')->onDelete('cascade');
			$table->foreignId('doctor_id')->constrained('users')->onDelete('cascade');
			$table->foreignId('schedule_id')->nullable()->constrained('schedules')->onDelete('set null');
			$table->foreignId('specialty_id')->constrained('specialties')->onDelete('cascade');
			$table->date('appointment_date');
			$table->time('start_time');
			$table->time('end_time');
			$table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
			$table->text('reason')->nullable();
			$table->string('google_event_id', 255)->nullable();
			$table->timestamps();
			$table->index('patient_id');
			$table->index(['doctor_id', 'appointment_date']);
			$table->index('status');
			$table->index('specialty_id');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('appointments');
	}
};
