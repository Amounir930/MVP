<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'logo_url',
        'domain',
        'status',
    ];

    /**
     * Establish relationship mapping to associated User entities.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Obtain the associated Salla configuration for this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function sallaConfig(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SallaConfig::class);
    }

    /**
     * Obtain the collection of products belonging to this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Obtain the collection of customers belonging to this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Obtain the collection of orders belonging to this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Obtain the associated WhatsApp configuration for this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function whatsappConfig(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WhatsappConfig::class);
    }

    /**
     * Obtain the collection of WhatsApp chat sessions belonging to this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function whatsappChatSessions(): HasMany
    {
        return $this->hasMany(WhatsappChatSession::class);
    }

    /**
     * Obtain the collection of reviews belonging to this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Obtain the subscription associated with the tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Check if the tenant has an active subscription.
     *
     * @return bool
     */
    public function hasActiveSubscription(): bool
    {
        $sub = $this->subscription;
        if (!$sub) {
            return true; // Default to free plan active
        }
        return $sub->status === 'active' && $sub->current_period_end->isFuture();
    }

    /**
     * Check if the message limit for the current subscription has been reached.
     *
     * @return bool
     */
    public function messageLimitReached(): bool
    {
        $sub = $this->subscription;
        if (!$sub) {
            $count = \App\Models\WhatsappMessageLog::where('tenant_id', $this->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();
            return $count >= 50; // Default limit for free
        }
        
        // If the subscription is expired or suspended, limit is reached
        if ($sub->status !== 'active' || !$sub->current_period_end->isFuture()) {
            return true;
        }

        return $sub->current_period_usage >= $sub->monthly_limit;
    }

    /**
     * Check if the branding watermark should be displayed on the storefront widget.
     *
     * @return bool
     */
    public function shouldShowWatermark(): bool
    {
        $sub = $this->subscription;
        if (!$sub) {
            return true; // Free plan displays watermark
        }
        return in_array(strtolower($sub->plan_name), ['free']);
    }
}
