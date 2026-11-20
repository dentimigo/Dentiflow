<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarBlock extends Model
{
    protected $fillable = [
        'dentist_id',
        'target_user_id',
        'created_by_user_id',
        'date',
        'start_time',
        'end_time',
        'type',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
