<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A managed menu: header, footer_main or footer_legal (§8.4, §9.1).
 */
class Navigation extends Model
{
    public const HEADER = 'header';

    public const FOOTER_MAIN = 'footer_main';

    public const FOOTER_LEGAL = 'footer_legal';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(NavigationItem::class)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }
}
