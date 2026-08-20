<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
	use HasFactory, Notifiable, HasRoles;

	protected $guard_name = 'web';
	protected $fillable = [
		'name',
		'last_name',
		'email',
		'password',
		'phone',
		'status',
	];

	protected $hidden = [
		'password',
		'remember_token',
	];

	protected function casts(): array
	{
		return [
			'email_verified_at' => 'datetime',
			'password'          => 'hashed',
		];
	}

	public function doctorProfile()
	{
		return $this->hasOne(DoctorProfile::class, 'user_id');
	}

	public function patientProfile()
	{
		return $this->hasOne(PatientProfile::class, 'user_id');
	}

	public function activityLogs()
	{
		return $this->hasMany(ActivityLog::class);
	}
	public function specialty()
	{
		return $this->belongsTo(Specialty::class, 'specialty_id');
	}


}
