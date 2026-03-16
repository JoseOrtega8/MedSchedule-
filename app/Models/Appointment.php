<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
	protected $fillable = [
		'patient_id',
		'doctor_id',
		'schedule_id',
		'specialty_id',
		'appointment_date',
		'start_time',
		'end_time',
		'status',
		'reason',
		'google_event_id',
	];

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
}
