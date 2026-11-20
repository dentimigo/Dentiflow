<?php

namespace App\Support;

use App\Models\ClinicWorkspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class ClinicContext
{
    private ?int $legacyId = null;

    public ?int $workspaceId = null;

    public ?string $workspaceName = null;

    public ?string $role = null;

    public function legacyId(): int
    {
        return $this->legacyId ??= (int) ClinicWorkspace::query()->oldest('id')->firstOrFail()->id;
    }

    public function id(): int
    {
        return $this->workspaceId ?? $this->legacyId();
    }

    public function exists(string $table, string $column): Exists
    {
        return Rule::exists($table, $column)->where('clinic_workspace_id', $this->id());
    }

    public function unique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)->where('clinic_workspace_id', $this->id());
    }

    public function assistants(): Builder
    {
        if ($this->workspaceId === null) {
            return User::where(function (Builder $users) {
                $users->where(fn (Builder $legacyUsers) => $legacyUsers
                    ->where('users.role', 'asistente')
                    ->whereDoesntHave('clinicWorkspaces'))
                    ->orWhereHas('clinicWorkspaces', fn (Builder $workspaces) => $workspaces
                        ->whereKey($this->legacyId())->where('clinic_workspace_members.role', 'asistente'));
            });
        }

        return User::whereHas('clinicWorkspaces', fn (Builder $workspaces) => $workspaces
            ->whereKey($this->workspaceId)
            ->where('clinic_workspace_members.role', 'asistente'));
    }
}
