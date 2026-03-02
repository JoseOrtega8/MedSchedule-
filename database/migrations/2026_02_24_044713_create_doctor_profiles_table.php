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
		Schema::create('doctor_profiles', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
			$table->foreignId('specialty_id')->constrained('specialties')->onDelete('cascade');
			$table->string('license_number', 50)->unique()->nullable();
			$table->text('bio')->nullable();
			$table->tinyInteger('consultation_duration')->default(30);
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('doctor_profiles');
	}
};
