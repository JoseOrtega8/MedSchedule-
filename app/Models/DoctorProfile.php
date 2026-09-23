<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class DoctorProfile extends Model
{
	use Auditable;

	protected $fillable = [
		'user_id',
		'specialty_id',
		'license_number',
		'bio',
		'consultation_duration',
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'user_id');	}

	public function specialty()
	{
		return $this->belongsTo(Specialty::class, 'specialty_id');
	}

}


