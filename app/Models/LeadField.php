<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsActivity;
use App\Rules\InternationalPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One field on the lead form, managed from the admin panel.
 *
 * The site ships in the §6.1 configuration: `contact` on and required,
 * `message` on and optional, everything else off. Amad Craft can change that
 * from the panel; nothing here forces it either way.
 */
class LeadField extends Model
{
    use HasTranslations;
    use RecordsActivity;

    /** Maps onto leads.contact_value — the one field that must always exist. */
    public const KEY_CONTACT = 'contact';

    /** Maps onto leads.message. */
    public const KEY_MESSAGE = 'message';

    /** Keys backed by real columns; everything else lands in leads.extra. */
    public const RESERVED = [self::KEY_CONTACT, self::KEY_MESSAGE];

    public const TYPES = ['text', 'textarea', 'email', 'tel', 'select', 'checkbox'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_enabled' => 'boolean',
            'is_required' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true)->orderBy('sort_order');
    }

    public function isReserved(): bool
    {
        return in_array($this->key, self::RESERVED, true);
    }

    /**
     * Laravel validation rules for this field.
     *
     * @return list<string>
     */
    public function rules(): array
    {
        $rules = [$this->is_required ? 'required' : 'nullable'];

        $rules[] = match ($this->type) {
            'checkbox' => 'boolean',
            // `email:rfc,dns` would reject a real address whose DNS is slow or
            // briefly unreachable, and a refused lead costs more than a typo.
            // `rfc` alone still catches «a@b» and everything with a space in it.
            'email' => 'email:rfc',
            'select' => 'string',
            default => 'string',
        };

        // Google's metadata decides whether a number is dialable, per country.
        if ($this->type === 'tel') {
            $rules[] = new InternationalPhone;
        }

        if ($this->type === 'select' && filled($this->options)) {
            $rules[] = 'in:'.implode(',', array_column($this->options, 'value'));
        }

        if ($this->type !== 'checkbox') {
            $rules[] = 'max:'.($this->max_length ?? 255);
        }

        return $rules;
    }

    /**
     * The shape the Vue form renders from.
     *
     * @return array<string, mixed>
     */
    public function toFormArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'required' => $this->is_required,
            'label' => $this->t('label'),
            'placeholder' => $this->t('placeholder'),
            'help' => $this->t('help'),
            'maxLength' => $this->max_length,
            'options' => collect($this->options ?? [])
                ->map(fn (array $o): array => [
                    'value' => $o['value'] ?? '',
                    'label' => $o['label'][app()->getLocale()] ?? ($o['value'] ?? ''),
                ])
                ->values()
                ->all(),
        ];
    }
}
