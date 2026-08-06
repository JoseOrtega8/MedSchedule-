<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentHistory extends Model
{
	protected $fillable = [
		'appointment_id',
		'diagnosis',
		'treatment',
		'prescription',
		'follow_up_notes',
		'next_appointment_suggested',
	];

	public function appointment()
	{
		return $this->belongsTo(Appointment::class);
	}
}
