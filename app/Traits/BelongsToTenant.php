<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    /**
     * Boot the trait to register Eloquent lifecycle event listeners and query scopes.
     * Automates tenant assignment during record creation and enforces database level isolation.
     *
     * @return void
     */
    protected static function bootBelongsToTenant(): void
    {
        // Bind the tenant scope filter to the active model instances
        static::addGlobalScope(new TenantScope);

        // Bind creation hooks to capture active tenant references
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                if (App::bound('current_tenant_id')) {
                    $model->tenant_id = App::make('current_tenant_id');
                } elseif (Auth::check() && Auth::user()->tenant_id) {
                    $model->tenant_id = Auth::user()->tenant_id;
                }
            }
        });
    }

    /**
     * Establish the relationship association to the parent Tenant model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
