<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * Restricts the database operations to the active tenant domain context.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Prioritize explicit tenant ID bound to the application container
        if (App::bound('current_tenant_id')) {
            $builder->where($model->getTable() . '.tenant_id', App::make('current_tenant_id'));
            return;
        }

        // Fallback to authenticated user tenant reference context
        // We use hasUser() to prevent infinite recursion during user resolution
        if (Auth::guard()->hasUser() && Auth::user()->tenant_id && !Auth::user()->is_admin) {
            $builder->where($model->getTable() . '.tenant_id', Auth::user()->tenant_id);
        }
    }
}
