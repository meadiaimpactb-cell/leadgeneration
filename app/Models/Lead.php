<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A prospective institutional client (§6).
 *
 * This is the site's only success metric (§1, §21). It holds exactly what the
 * visitor typed — one contact value, one optional line — plus the attribution
 * needed to tell which channel produced it. Never a name, never a raw IP.
 *
 * @property string $contact_value
 * @property string $contact_type
 * @property string|null $message
 * @property string $status
 * @property string $crm_status
 */
class Lead extends Model
{
    use HasFactory;

    public const TYPE_EMAIL = 'email';

    public const TYPE_PHONE = 'phone';

    public const STATUSES = ['new', 'contacted', 'qualified', 'won', 'lost'];

    public const CRM_PENDING = 'pending';

    public const CRM_SYNCED = 'synced';

    public const CRM_FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'crm_synced_at' => 'datetime',
            'archived_at' => 'datetime',
            // Answers to admin-enabled fields beyond §6.1's two.
            'extra' => 'array',
        ];
    }

    /**
     * Never expose the UA string or the IP hash through a JSON response —
     * they are for abuse investigation only, not for the admin table payload.
     */
    protected $hidden = ['user_agent', 'ip_hash'];

    protected static function booted(): void
    {
        static::creating(function (self $lead): void {
            $lead->uuid ??= (string) Str::uuid();
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function crmSyncLogs(): HasMany
    {
        return $this->hasMany(CrmSyncLog::class)->latest('id');
    }

    public function isEmail(): bool
    {
        return $this->contact_type === self::TYPE_EMAIL;
    }

    public function markSynced(string $provider, ?string $externalId): void
    {
        $this->forceFill([
            'crm_status' => self::CRM_SYNCED,
            'crm_provider' => $provider,
            'crm_external_id' => $externalId,
            'crm_synced_at' => now(),
        ])->save();
    }

    public function markCrmFailed(string $provider): void
    {
        $this->forceFill([
            'crm_status' => self::CRM_FAILED,
            'crm_provider' => $provider,
        ])->save();
    }

    /**
     * Leads that never reached the CRM — these need a human (§6.3).
     *
     * "Synced by the `null` provider" belongs here too. That driver reports
     * success without sending anything, which is right for local work and
     * wrong to forget about: without this clause the enquiries taken before a
     * real CRM was connected are stamped green forever and «resync all» steps
     * straight over them.
     */
    public function scopeNotSynced(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereIn('crm_status', [self::CRM_PENDING, self::CRM_FAILED])
            ->orWhere(fn (Builder $stub) => $stub
                ->where('crm_status', self::CRM_SYNCED)
                ->where('crm_provider', 'null')));
    }

    public function scopeFromCampaign(Builder $query, int $campaignId): Builder
    {
        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Archiving, which is what this panel has instead of deleting.
     *
     * Applied by the leads list and by nothing else. It is deliberately not a
     * global scope: the dashboard counts every enquiry that ever arrived, and
     * a number that quietly drops when someone tidies a list is a number §1
     * cannot be measured by.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
