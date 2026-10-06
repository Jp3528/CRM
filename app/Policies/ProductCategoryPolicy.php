<?php

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return ($actor->hasPermission('categories.view') || $actor->hasPermission('products.view'))
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }

    public function view(User $actor, ProductCategory $category): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return ($actor->hasPermission('categories.create') || $actor->hasPermission('products.create'))
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }

    public function update(User $actor, ProductCategory $category): bool
    {
        return ($actor->hasPermission('categories.update') || $actor->hasPermission('products.update'))
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }

    public function delete(User $actor, ProductCategory $category): bool
    {
        return ($actor->hasPermission('categories.delete') || $actor->hasPermission('products.delete'))
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }
}
