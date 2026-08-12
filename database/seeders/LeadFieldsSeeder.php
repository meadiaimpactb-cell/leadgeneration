<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LeadField;
use Database\Seeders\Concerns\SeedsRows;
use Illuminate\Database\Seeder;

/**
 * The lead form's shipped configuration.
 *
 * ON  — contact  (required, locked: without it there is no lead at all)
 * ON  — message  (optional, one line)
 * OFF — everything else
 *
 * That is exactly §6.1. The extra fields are created but disabled so Amad
 * Craft can switch one on from the admin panel if they later decide the
 * trade-off is worth it — the capability is theirs, the default is the
 * brief's.
 *
 * Structural data. Safe to run in production; it never re-enables or renames
 * a field an administrator has already changed.
 */
class LeadFieldsSeeder extends Seeder
{
    use SeedsRows;

    public function run(): void
    {
        $fields = [
            [
                'key' => LeadField::KEY_CONTACT,
                'type' => 'text',
                'enabled' => true,
                'required' => true,
                'locked' => true,
                'max' => 191,
                'ar' => ['وسيلة التواصل', 'بريدك الإلكتروني أو رقم جوالك', null],
                'en' => ['Contact', 'Your email or mobile number', null],
            ],
            [
                'key' => LeadField::KEY_MESSAGE,
                'type' => 'textarea',
                'enabled' => true,
                'required' => false,
                'locked' => false,
                'max' => 500,
                'ar' => ['رسالتك', 'سطر واحد…', null],
                'en' => ['Your message', 'One line…', null],
            ],

            // ---- Off by default (§6.1: "no name and no additional data") ---
            [
                'key' => 'name',
                'type' => 'text',
                'enabled' => false,
                'required' => false,
                'locked' => false,
                'max' => 120,
                'ar' => ['الاسم', 'الاسم الكامل', 'حقل إضافي — مُطفأ افتراضيًا حسب §6.1'],
                'en' => ['Name', 'Full name', 'Extra field — off by default per §6.1'],
            ],
            [
                'key' => 'organisation',
                'type' => 'text',
                'enabled' => false,
                'required' => false,
                'locked' => false,
                'max' => 160,
                'ar' => ['الجهة', 'اسم الجهة أو الشركة', 'حقل إضافي — مُطفأ افتراضيًا'],
                'en' => ['Organisation', 'Entity or company name', 'Extra field — off by default'],
            ],
            [
                // A phone number in its own right, separate from `contact`
                // (which accepts an email OR a mobile). Off by default like
                // every other extra; the client turns it on if they decide
                // they want both.
                'key' => 'phone',
                'type' => 'tel',
                'enabled' => false,
                'required' => false,
                'locked' => false,
                'max' => 32,
                'ar' => ['رقم الهاتف', '+966 5X XXX XXXX', 'حقل إضافي — مُطفأ افتراضيًا'],
                'en' => ['Phone number', '+966 5X XXX XXXX', 'Extra field — off by default'],
            ],
            [
                'key' => 'job_title',
                'type' => 'text',
                'enabled' => false,
                'required' => false,
                'locked' => false,
                'max' => 120,
                'ar' => ['المسمى الوظيفي', null, 'حقل إضافي — مُطفأ افتراضيًا'],
                'en' => ['Job title', null, 'Extra field — off by default'],
            ],
            [
                'key' => 'sector',
                'type' => 'select',
                'enabled' => false,
                'required' => false,
                'locked' => false,
                'max' => 64,
                'options' => [
                    ['value' => 'government', 'label' => ['ar' => 'جهة حكومية', 'en' => 'Government entity']],
                    ['value' => 'private', 'label' => ['ar' => 'قطاع خاص', 'en' => 'Private sector']],
                    ['value' => 'partners', 'label' => ['ar' => 'شريك', 'en' => 'Partner']],
                    ['value' => 'artisans', 'label' => ['ar' => 'حرفي', 'en' => 'Artisan']],
                ],
                'ar' => ['القطاع', null, 'حقل إضافي — مُطفأ افتراضيًا'],
                'en' => ['Sector', null, 'Extra field — off by default'],
            ],
        ];

        foreach ($fields as $order => $spec) {
            /*
             * `is_locked` is the one that has to be enforced. It is what
             * keeps `contact` un-switchable-off in the panel — without it
             * there is no lead at all (§6.1) — and LeadFieldController reads
             * it to decide whether to obey the toggle it was sent. A row
             * created down another path with is_locked = false would hand
             * an editor the power to disable the only field on the form.
             *
             * `type` and `options` join it: the panel does not expose either,
             * so this file is their only author.
             *
             * `is_enabled` and `is_required` follow the lock, because that is
             * the rule LeadFieldController already enforces — for a locked
             * field it writes `true` whatever the request said, so they are
             * not the client's to hold and this file must repair them too.
             * For every unlocked field they are the client's entirely: the
             * point of the extra fields is that Amad Craft can switch one on,
             * and a re-seed must not switch it back off.
             *
             * `sort_order` and `max_length` are the client's in all cases —
             * the panel writes both for every field, locked or not.
             */
            $enforcedByLock = $spec['locked']
                ? ['is_enabled' => $spec['enabled'], 'is_required' => $spec['required']]
                : [];

            $field = $this->seedRow(
                LeadField::query(),
                identity: ['key' => $spec['key']],
                structure: [
                    'type' => $spec['type'],
                    'is_locked' => $spec['locked'],
                    'options' => $spec['options'] ?? null,
                    ...$enforcedByLock,
                ],
                owned: [
                    'sort_order' => $order,
                    'max_length' => $spec['max'],
                    ...($spec['locked'] ? [] : [
                        'is_enabled' => $spec['enabled'],
                        'is_required' => $spec['required'],
                    ]),
                ],
            );

            foreach (['ar', 'en'] as $locale) {
                [$label, $placeholder, $help] = $spec[$locale];

                // Labels are edited in the panel and are never rewritten.
                $this->seedRow($field->translations(), identity: ['locale' => $locale], owned: [
                    'label' => $label,
                    'placeholder' => $placeholder,
                    'help' => $help,
                ]);
            }
        }
    }
}
