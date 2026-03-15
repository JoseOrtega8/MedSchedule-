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
		Schema::create('patient_profiles', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
			$table->date('birth_date')->nullable();
			$table->enum('blood_type', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable();
			$table->text('allergies')->nullable();
			$table->text('chronic_conditions')->nullable();
			$table->string('emergency_contact_name', 150)->nullable();
			$table->string('emergency_contact_phone', 20)->nullable();
			$table->string('curp', 18)->unique()->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('patient_profiles');
	}
};
