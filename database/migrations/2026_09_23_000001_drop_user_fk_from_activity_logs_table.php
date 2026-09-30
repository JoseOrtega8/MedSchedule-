<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// La auditoria debe sobrevivir al usuario auditado. La FK original
// (activity_logs_user_id_foreign, ON DELETE SET NULL, 2026_02_24_045227)
// pone user_id a NULL cuando se borra el usuario, lo que cambia el
// contenido sellado de filas ya firmadas y hace que verificar() reporte
// un falso "rota". Se quita la FK; el indice activity_logs_user_id_index
// no se toca (sigue sirviendo a los filtros del visor).
return new class extends Migration
{
	public function up(): void
	{
		Schema::table('activity_logs', function (Blueprint $table) {
			$table->dropForeign('activity_logs_user_id_foreign');
		});
	}

	// Advertencia: restaurar la FK falla (MySQL 1452) si ya existen user_id
	// huerfanos de usuarios borrados despues de up(). En ese caso el rollback
	// exige decidir antes que hacer con esas filas selladas: ponerlas a NULL
	// rompe su sello, y borrarlas elimina evidencia de auditoria.
	public function down(): void
	{
		Schema::table('activity_logs', function (Blueprint $table) {
			$table->foreign('user_id', 'activity_logs_user_id_foreign')
				->references('id')->on('users')
				->onDelete('set null');
		});
	}
};
