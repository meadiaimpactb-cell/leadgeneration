<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

/**
 * An admin-panel operator. There are no public user accounts — the site has
 * no login for visitors, only the four staff roles in §9.1.
 */
class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use InteractsWithMedia;
    use Notifiable;

    /**
     * Both are optional and both are the operator's own.
     *
     * `avatar` gives the panel a face instead of two letters — useful the
     * moment more than one person shares a screen. `cover` is decoration, and
     * is here because a profile page that cannot be made to feel like yours
     * is a profile page nobody visits twice.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
        $this->addMediaCollection('cover')->singleFile();
    }

    /**
     * Queried rather than read off the loaded relation.
     *
     * getFirstMedia() answers from `media` when that relation is already
     * loaded, and the signed-in user is one long-lived instance for the whole
     * request — so an upload or a delete earlier in that request leaves the
     * relation describing the state before it. The panel then showed the old
     * picture, and a delete found nothing to remove. One query is the price
     * of an answer that is always current.
     */
    public function avatarUrl(): ?string
    {
        return $this->mediaIn('avatar')?->getUrl();
    }

    public function coverUrl(): ?string
    {
        return $this->mediaIn('cover')?->getUrl();
    }

    public function mediaIn(string $collection): ?Media
    {
        return $this->media()->where('collection_name', $collection)->first();
    }

    /** Tiered permissions per §9.1. */
    public const ROLE_SUPER_ADMIN = 'super-admin';

    public const ROLE_EDITOR = 'editor';

    public const ROLE_CAMPAIGN_MANAGER = 'campaign-manager';

    public const ROLE_SALES = 'sales';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_EDITOR,
        self::ROLE_CAMPAIGN_MANAGER,
        self::ROLE_SALES,
    ];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'preferred_locale',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * A deactivated account keeps its audit history but cannot sign in.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }
}
