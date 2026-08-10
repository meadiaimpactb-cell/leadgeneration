<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CrmSyncLog;
use App\Models\Lead;
use App\Models\Sector;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * ⚠️ DEMONSTRATION DATA — NOT FOR PRODUCTION.
 *
 * Sample leads so the dashboard, the charts, the filters, the export and the
 * CRM sync log can all be seen working before any real lead arrives.
 *
 * Spread across the last 60 days with a mix of statuses, sources, campaigns
 * and CRM states — including failures, because the failure path is the one
 * worth being able to see (§6.3).
 *
 * Run with:  php artisan db:seed --class=DemoLeadsSeeder
 */
class DemoLeadsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoLeadsSeeder must never run in production.');

            return;
        }

        $campaigns = Campaign::query()->pluck('id', 'slug');
        $sectors = Sector::query()->pluck('key')->all();

        $sources = [
            ['google', 'cpc', 'riyadh-season'],
            ['google', 'organic', null],
            ['linkedin', 'social', 'b2b-gifts'],
            ['instagram', 'social', null],
            ['direct', null, null],
            ['newsletter', 'email', 'q3-outreach'],
        ];

        $messages = [
            'عندي مؤتمر في الرياض، أحتاج هدايا للمتحدثين',
            'نبحث عن هدايا تحمل هوية الجهة لموسم الأعياد',
            null,
            'كم المدة اللازمة لتنفيذ 500 قطعة؟',
            null,
            'نرغب بزيارة المعرض والاطلاع على العينات',
            'هل يمكن تخصيص التغليف بشعارنا؟',
            null,
        ];

        $statuses = ['new', 'new', 'new', 'contacted', 'contacted', 'qualified', 'won', 'lost'];

        // Deterministic so re-running produces the same demo set rather than
        // piling up a new batch each time.
        mt_srand(20260806);

        $created = 0;

        for ($i = 0; $i < 64; $i++) {
            $daysAgo = mt_rand(0, 59);
            $at = now()->subDays($daysAgo)->setTime(mt_rand(8, 20), mt_rand(0, 59));

            [$source, $medium, $campaignSlug] = $sources[array_rand($sources)];

            $isEmail = mt_rand(0, 100) < 65;
            $seq = str_pad((string) $i, 2, '0', STR_PAD_LEFT);

            $status = $statuses[array_rand($statuses)];

            // Most leads sync; a few fail, so the alert path is visible.
            $crm = match (true) {
                $i % 17 === 0 => Lead::CRM_FAILED,
                $i % 11 === 0 => Lead::CRM_PENDING,
                default => Lead::CRM_SYNCED,
            };

            $lead = Lead::query()->create([
                'uuid' => (string) Str::uuid(),
                'contact_value' => $isEmail
                    ? "demo{$seq}@example.test"
                    : '+9665'.str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'contact_type' => $isEmail ? Lead::TYPE_EMAIL : Lead::TYPE_PHONE,
                'message' => $messages[array_rand($messages)],
                'locale' => mt_rand(0, 100) < 80 ? 'ar' : 'en',
                'page_url' => 'https://amadcraft.sa/ar/'.['', 'products', 'impact', 'contact'][array_rand(['', 'products', 'impact', 'contact'])],
                'referrer' => $source === 'google' ? 'https://www.google.com/' : null,
                'utm_source' => $source === 'direct' ? null : $source,
                'utm_medium' => $medium,
                'utm_campaign' => $campaignSlug,
                'campaign_id' => $campaignSlug ? ($campaigns[$campaignSlug] ?? null) : null,
                'sector_hint' => $sectors === [] ? null : $sectors[array_rand($sectors)],
                'user_agent' => 'Mozilla/5.0 (demo seeder)',
                'ip_hash' => hash('sha256', 'demo-'.$i),
                'status' => $status,
                'crm_status' => $crm,
                'crm_provider' => $crm === Lead::CRM_SYNCED ? 'null' : null,
                'crm_synced_at' => $crm === Lead::CRM_SYNCED ? $at->copy()->addSeconds(4) : null,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            $this->logSync($lead, $crm, $at);
            $created++;
        }

        mt_srand();

        $this->command?->info("Seeded {$created} demo leads across the last 60 days.");
    }

    private function logSync(Lead $lead, string $crm, Carbon $at): void
    {
        if ($crm === Lead::CRM_PENDING) {
            return;
        }

        CrmSyncLog::query()->create([
            'lead_id' => $lead->id,
            'provider' => 'null',
            'attempt' => 1,
            'http_status' => $crm === Lead::CRM_SYNCED ? 200 : 503,
            'request' => ['note' => 'demo'],
            'response' => $crm === Lead::CRM_SYNCED ? ['id' => 'local-'.$lead->uuid] : null,
            'error' => $crm === Lead::CRM_SYNCED ? null : 'Demo failure so the alert path is visible.',
            'created_at' => $at->copy()->addSeconds(4),
        ]);
    }
}
