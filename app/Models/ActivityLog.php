<?php

namespace App\Models;

use App\Exceptions\RegistroAuditoriaInmutable;
use App\Services\Auditoria\SelladorAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ActivityLog extends Model
{
	protected $fillable = [
		'user_id',
		'action',
		'model_type',
		'model_id',
		'description',
		'ip_address',
		'user_agent',
		'old_values',
		'new_values',
		'trace_id',
	];

	protected $casts = [
		'old_values' => 'array',
		'new_values' => 'array',
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	protected static function booted(): void
	{
		static::creating(function (ActivityLog $log) {
			app(SelladorAuditoria::class)->sellar($log);
		});

		// Solo agregado: la aplicacion nunca edita ni borra auditoria
		static::updating(function () {
			throw new RegistroAuditoriaInmutable('Los registros de auditoria no se pueden modificar.');
		});
		static::deleting(function () {
			throw new RegistroAuditoriaInmutable('Los registros de auditoria no se pueden eliminar.');
		});
	}

	// La insercion va en transaccion para que el bloqueo de la ultima fila
	// serialice la cadena cuando dos cambios se auditan al mismo tiempo.
	// Reintenta hasta 3 veces si MySQL reporta un deadlock por la contencion
	// del lockForUpdate() en SelladorAuditoria::sellar().
	public function save(array $options = []): bool
	{
		if (!$this->exists) {
			return DB::transaction(fn () => parent::save($options), 3);
		}

		return parent::save($options);
	}
}
