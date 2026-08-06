<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientProfile extends Model
{
	protected $fillable = [
		'user_id',
		'birth_date',
		'blood_type',
		'allergies',
		'chronic_conditions',
		'emergency_contact_name',
		'emergency_contact_phone',
		'curp',
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
