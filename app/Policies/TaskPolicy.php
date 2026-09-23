<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Support\DataScope;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.view')
            && DataScope::canAccessTask($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tasks.create');
    }

    public function update(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.update')
            && DataScope::canAccessTask($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.delete')
            && DataScope::canAccessTask($user, $task);
    }
}
