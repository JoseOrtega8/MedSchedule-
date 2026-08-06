<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorProfile extends Model
{
	protected $fillable = [
		'user_id',
		'specialty_id',
		'license_number',
		'bio',
		'consultation_duration',
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function specialty()
	{
		return $this->belongsTo(Specialty::class);
	}
}
