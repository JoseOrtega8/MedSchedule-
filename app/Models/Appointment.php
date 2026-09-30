<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
	use Auditable;

protected $fillable = [
    'patient_id',
    'doctor_id',
    'specialty_id',
    'appointment_date',
    'start_time',
    'end_time',
    'status',
    'reason'
];


	// Datos clinicos en texto libre: la auditoria registra que cambio, nunca el valor
	public function campos_protegidos_auditoria(): array
	{
		return ['reason', 'observaciones'];
	}

	public function patient()
	{
		return $this->belongsTo(User::class, 'patient_id');
	}
	public function doctor()
	{
		return $this->belongsTo(User::class, 'doctor_id');
	}

	public function schedule()
	{
		return $this->belongsTo(Schedule::class);
	}

	public function specialty()
	{
		return $this->belongsTo(Specialty::class);
	}

	public function history()
	{
		return $this->hasOne(AppointmentHistory::class);
	}

	public function patientProfile()
	{
		return $this->belongsTo(PatientProfile::class, 'patient_id');
	}

}
