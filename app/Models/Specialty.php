<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
	use Auditable;

	protected $fillable = [
		'name',
		'description',
		'status',
	];

	public function doctors()
	{
		return $this->hasMany(DoctorProfile::class);
	}
}
