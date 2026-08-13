<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One keyword a page is meant to rank for, with its last analysis.
 *
 * @property int $page_id
 * @property string $locale
 * @property string $keyword
 * @property string $keyword_normalized
 * @property int $score
 * @property array<string, mixed>|null $checks
 */
class PageKeyword extends Model
{
    /** Below this a keyword is red: the page barely mentions it. */
    public const WEAK_BELOW = 40;

    /** At or above this it is green. Between the two it is amber. */
    public const STRONG_FROM = 70;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'checks' => 'array',
            'analyzed_at' => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    /**
     * weak | medium | strong — the three colours, decided in one place.
     *
     * The interface needs the band, not the number, and computing it in the
     * component would put the thresholds in two languages at once.
     */
    public function band(): string
    {
        return match (true) {
            $this->score >= self::STRONG_FROM => 'strong',
            $this->score >= self::WEAK_BELOW => 'medium',
            default => 'weak',
        };
    }
}
