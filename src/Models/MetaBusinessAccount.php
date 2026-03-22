<?php

namespace ScriptDevelop\MetaCatalogManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ScriptDevelop\MetaCatalogManager\Enums\AccountStatus;
use ScriptDevelop\MetaCatalogManager\Traits\GeneratesUlid;

class MetaBusinessAccount extends Model
{
    use GeneratesUlid, SoftDeletes;

    protected $table = 'meta_business_accounts';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'meta_business_id',
        'name',
        'app_id',
        'app_secret',
        'access_token',
        'access_token_expires_at',
        'status',
        'disconnected_at',
        'fully_removed_at',
        'disconnection_reason',
    ];

    protected $casts = [
        'status'                  => AccountStatus::class,
        'access_token_expires_at' => 'datetime',
        'disconnected_at'         => 'datetime',
        'fully_removed_at'        => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Encrypted Mutators/Accessors
    // -------------------------------------------------------------------------

    public function setAppIdAttribute(?string $value): void
    {
        $this->attributes['app_id'] = $value !== null ? encrypt($value) : null;
    }

    public function getAppIdAttribute(?string $value): ?string
    {
        return $value !== null ? decrypt($value) : null;
    }

    public function setAppSecretAttribute(?string $value): void
    {
        $this->attributes['app_secret'] = $value !== null ? encrypt($value) : null;
    }

    public function getAppSecretAttribute(?string $value): ?string
    {
        return $value !== null ? decrypt($value) : null;
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value !== null ? encrypt($value) : null;
    }

    public function getAccessTokenAttribute(?string $value): ?string
    {
        return $value !== null ? decrypt($value) : null;
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function catalogs(): HasMany
    {
        return $this->hasMany(
            config('meta-catalog.models.meta_catalog', MetaCatalog::class),
            'meta_business_account_id'
        );
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', AccountStatus::ACTIVE->value);
    }

    public function scopeDisconnected($query)
    {
        return $query->where('status', AccountStatus::DISCONNECTED->value);
    }

    public function scopeRemoved($query)
    {
        return $query->where('status', AccountStatus::REMOVED->value);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === AccountStatus::ACTIVE;
    }

    public function isDisconnected(): bool
    {
        return $this->status === AccountStatus::DISCONNECTED;
    }

    public function isRemoved(): bool
    {
        return $this->status === AccountStatus::REMOVED;
    }
}
