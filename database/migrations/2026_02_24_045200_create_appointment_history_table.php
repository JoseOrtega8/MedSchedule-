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
		Schema::create('appointment_history', function (Blueprint $table) {
			$table->id();
			$table->foreignId('appointment_id')->unique()->constrained('appointments')->onDelete('cascade');
			$table->text('diagnosis')->nullable();
			$table->text('treatment')->nullable();
			$table->text('prescription')->nullable();
			$table->text('follow_up_notes')->nullable();
			$table->date('next_appointment_suggested')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('appointment_history');
	}
};
