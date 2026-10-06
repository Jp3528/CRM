<?php

namespace App\Policies;

use App\Models\Pipeline;
use App\Models\User;

class PipelinePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('pipelines.view')
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }

    public function view(User $actor, Pipeline $pipeline): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('pipelines.create')
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }

    public function update(User $actor, Pipeline $pipeline): bool
    {
        return $actor->hasPermission('pipelines.update')
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }

    public function delete(User $actor, Pipeline $pipeline): bool
    {
        return ($actor->hasPermission('pipelines.delete') || $actor->hasPermission('pipelines.archive'))
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }

    public function archive(User $actor, Pipeline $pipeline): bool
    {
        return $this->delete($actor, $pipeline);
    }
}
