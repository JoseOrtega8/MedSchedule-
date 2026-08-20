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
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['doctor_id', 'appointment_date'], 'idx_doctor_date');
            $table->index(['patient_id', 'status'], 'idx_patient_status');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['action', 'created_at'], 'idx_action_created');
            $table->index(['model_type', 'model_id'], 'idx_model');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->unique(['doctor_id', 'date', 'start_time'], 'uq_doctor_slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_doctor_date');
            $table->dropIndex('idx_patient_status');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_action_created');
            $table->dropIndex('idx_model');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropUnique('uq_doctor_slot');
        });
    }
};