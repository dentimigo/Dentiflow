<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DentistSchedule extends Model
{
    use BelongsToClinic;

    protected $fillable = ['dentist_id', 'weekday', 'enabled', 'start_time', 'end_time'];

    protected $casts = ['enabled' => 'boolean'];

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class);
    }
}
