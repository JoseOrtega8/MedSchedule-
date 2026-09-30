<?php

use App\Models\ActivityLog;
use App\Services\Auditoria\SelladorAuditoria;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
			// Nullable porque hash se rellena para TODAS las filas (incluidas
			// las preexistentes) al final de este mismo up(); una fila con
			// hash nulo despues de esta migracion se considera rota.
			$table->char('hash', 64)->nullable()->after('hash_anterior');
			$table->index('trace_id', 'idx_trace_id');
		});

		$this->sellar_filas_existentes();
	}

	public function down(): void
	{
		Schema::table('activity_logs', function (Blueprint $table) {
			$table->dropIndex('idx_trace_id');
			$table->dropColumn(['trace_id', 'hash_anterior', 'hash']);
		});
	}

	// Unico lugar legitimo de escritura directa de hash/hash_anterior fuera
	// del sellador: no hay fila "historica sin sello" que verificar() deba
	// perdonar, asi que las filas creadas antes de esta migracion se sellan
	// aqui mismo, encadenadas en orden de id, con DB::table (nunca Eloquent,
	// para no disparar los guardas de inmutabilidad de ActivityLog).
	private function sellar_filas_existentes(): void
	{
		$sellador = app(SelladorAuditoria::class);
		$anterior = null;

		ActivityLog::query()->orderBy('id')->each(function (ActivityLog $log) use ($sellador, &$anterior) {
			$hash = $sellador->calcular($anterior, $log);

			DB::table('activity_logs')->where('id', $log->id)->update([
				'hash_anterior' => $anterior,
				'hash' => $hash,
			]);

			$anterior = $hash;
		});
	}
};
