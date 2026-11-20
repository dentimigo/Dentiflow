<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalHistory extends Model
{
    use BelongsToClinic;

    protected $fillable = ['patient_id', 'blood_type', 'allergies', 'conditions', 'medications', 'surgeries', 'notes'];
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
}
