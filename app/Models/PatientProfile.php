<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PatientProfile extends Model
{
	use Auditable;

	protected $fillable = [
		'user_id',
		'birth_date',
		'blood_type',
		'allergies',
		'chronic_conditions',
		'emergency_contact_name',
		'emergency_contact_phone',
		'curp',
		'photo_path',
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	// PII medica: la auditoria registra que cambio, nunca el valor
	public function campos_protegidos_auditoria(): array
	{
		return [
			'birth_date', 'blood_type', 'allergies', 'chronic_conditions',
			'emergency_contact_name', 'emergency_contact_phone', 'curp',
		];
	}
}
