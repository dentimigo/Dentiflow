<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class ClinicWorkspace extends Model
{
    public const TRIAL_PLANS = [
        'basico' => ['name' => 'Básico', 'price' => 199, 'seats' => 2],
        'consultorio' => ['name' => 'Consultorio', 'price' => 499, 'seats' => 8],
    ];

    protected $fillable = ['name', 'owner_user_id'];

    protected $casts = [
        'trial_required' => 'boolean',
        'trial_ends_at' => 'immutable_datetime',
        'stripe_paid_until' => 'immutable_datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'clinic_workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function hasWorkspaceAccess(): bool
    {
        if ($this->stripe_subscription_id || $this->stripe_customer_id) {
            return match ($this->stripe_status) {
                'trialing' => $this->trial_ends_at?->isFuture() === true,
                'active' => $this->stripe_paid_until?->isFuture() === true,
                default => false,
            };
        }

        return ! $this->trial_required || $this->trial_ends_at?->isFuture() === true;
    }

    public function seatLimit(): ?int
    {
        return $this->trial_required ? (self::TRIAL_PLANS[$this->trial_plan]['seats'] ?? null) : null;
    }

    public static function createForOwner(User $owner, string $name, bool $trialRequired = false): self
    {
        return DB::transaction(function () use ($owner, $name, $trialRequired) {
            $workspace = new self(['name' => $name, 'owner_user_id' => $owner->id]);
            $workspace->trial_required = $trialRequired;
            $workspace->save();
            $workspace->members()->attach($owner, ['role' => 'admin']);

            return $workspace;
        });
    }
}
