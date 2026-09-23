<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sello encadenado e identificador de traza para el visor de auditoria.
// El indice (model_type, model_id) ya existe (idx_model, 2026_08_15_043158).
return new class extends Migration
{
	public function up(): void
	{
		Schema::table('activity_logs', function (Blueprint $table) {
			$table->string('trace_id', 32)->nullable()->after('new_values');
			$table->char('hash_anterior', 64)->nullable()->after('trace_id');
			// Nullable solo para los registros historicos anteriores a esta migracion
			$table->char('hash', 64)->nullable()->after('hash_anterior');
			$table->index('trace_id', 'idx_trace_id');
		});
	}

	public function down(): void
	{
		Schema::table('activity_logs', function (Blueprint $table) {
			$table->dropIndex('idx_trace_id');
			$table->dropColumn(['trace_id', 'hash_anterior', 'hash']);
		});
	}
};
