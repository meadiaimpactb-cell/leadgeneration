<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §8.3 — campaigns and leads.
 *
 * `leads` is the only table that measures the site's success (§1). It holds
 * exactly two pieces of visitor-supplied data — one contact value and an
 * optional one-line message (§6.1) — plus the marketing attribution needed to
 * tell which channel produced it. No name. No IP. Nothing else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->boolean('is_active')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('default_utm_source', 128)->nullable();
            $table->string('default_utm_medium', 128)->nullable();
            $table->string('default_utm_campaign', 128)->nullable();
            $table->string('template', 64)->default('default');
            $table->json('settings')->nullable();
            $table->uuid('preview_token')->nullable()->unique();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('campaign_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 512)->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'locale']);
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            // ---- What the visitor actually typed (§6.1) ----------------
            // One field: an email OR a mobile number, normalised on the way in.
            $table->string('contact_value', 191);
            $table->enum('contact_type', ['email', 'phone']);
            $table->string('message', 500)->nullable();

            // ---- Where they were --------------------------------------
            $table->string('locale', 5);
            $table->string('page_url', 512)->nullable();
            $table->string('referrer', 512)->nullable();

            // ---- Marketing attribution (§6.2.5) ------------------------
            $table->string('utm_source', 128)->nullable();
            $table->string('utm_medium', 128)->nullable();
            $table->string('utm_campaign', 128)->nullable();
            $table->string('utm_term', 128)->nullable();
            $table->string('utm_content', 128)->nullable();
            $table->string('gclid', 255)->nullable();
            $table->string('fbclid', 255)->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            // Which sector page they came from — a hint for sales, not a claim.
            $table->string('sector_hint', 64)->nullable();

            // ---- Privacy (§15.3) ---------------------------------------
            $table->string('user_agent', 512)->nullable();
            // Salted hash. The raw IP is never written to disk.
            $table->char('ip_hash', 64)->nullable();

            // ---- Pipeline ----------------------------------------------
            $table->enum('status', ['new', 'contacted', 'qualified', 'won', 'lost'])
                ->default('new');
            $table->enum('crm_status', ['pending', 'synced', 'failed'])
                ->default('pending');
            $table->string('crm_provider', 32)->nullable();
            $table->string('crm_external_id', 128)->nullable();
            $table->timestamp('crm_synced_at')->nullable();

            $table->timestamps();

            $table->index('created_at');
            $table->index('crm_status');
            $table->index('status');
            $table->index('contact_value');
            $table->index(['utm_source', 'utm_campaign']);
        });

        Schema::create('crm_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['lead_id', 'attempt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sync_logs');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('campaign_translations');
        Schema::dropIfExists('campaigns');
    }
};
