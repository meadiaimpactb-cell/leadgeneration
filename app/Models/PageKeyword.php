<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One keyword a page is meant to rank for, with its last analysis.
 *
 * @property int $page_id
 * @property string $locale
 * @property string $keyword
 * @property string $keyword_normalized
 * @property int $score
 * @property bool $is_primary the one phrase this page is actually about
 * @property array<string, mixed>|null $checks
 * @property string|null $content_hash fingerprint of the content this score
 *                                     was calculated from; a mismatch with
 *                                     the page as it stands now means the
 *                                     queued re-analysis has not landed yet
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
            'is_primary' => 'boolean',
            'analyzed_at' => 'datetime',
        ];
    }

    /**
     * Make this the page's main keyword for its language, and demote whoever
     * held it.
     *
     * One statement then one save, inside a transaction: "there is exactly one"
     * has to be true at every moment a reader could look, and doing it the
     * other way round leaves a blink with two.
     */
    public function makePrimary(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('page_id', $this->page_id)
                ->where('locale', $this->locale)
                ->whereKeyNot($this->getKey())
                ->update(['is_primary' => false]);

            $this->forceFill(['is_primary' => true])->save();
        });
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
