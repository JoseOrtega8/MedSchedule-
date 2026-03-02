<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
	];

	protected $casts = [
		'old_values' => 'array',
		'new_values' => 'array',
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
