<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who the site tells when something happens, and which things they are told.
 *
 * WHY THIS IS A TABLE AND NOT AN ENV VARIABLE
 *
 * Until now the answer lived in `LEADS_NOTIFY_TO`, read through
 * `config('site.leads.notify_to')`. That made the single most operational
 * question on the site — who finds out a government buyer just made contact —
 * answerable only by someone with shell access to the production server.
 * §20 decision 4 names the person who responds to enquiries, and §9.1 puts
 * the panel's whole purpose as administration "with no technical help
 * needed". A staff change should not be a deployment.
 *
 * WHY PER-RECIPIENT EVENT FLAGS AND NOT ONE LIST
 *
 * The three things worth telling someone about have different audiences. A
 * salesperson wants every new enquiry and would be actively harmed by an
 * alert every time a CRM token expires; whoever maintains the integration
 * wants exactly the opposite. A manager wants neither, and wants one summary
 * a day. Modelling that as one list forces everyone onto the loudest setting,
 * and the predictable result is a filter rule in somebody's inbox — at which
 * point the alert is gone and nobody knows it.
 *
 * The failure that matters here is a missed lead, not a duplicate email, so
 * nothing in this table may quietly reduce who is told. Deactivating a
 * recipient is explicit, per row, and visible on the screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_recipients', function (Blueprint $table): void {
            $table->id();

            // The address is the identity. Two rows for one address would let
            // an event be switched both on and off for the same inbox.
            $table->string('email')->unique();

            // Whose inbox it is, for the screen. Optional: an alias such as
            // sales@ has no person behind it and must not demand a name.
            $table->string('name')->nullable();

            /*
             * Off is a real state, distinct from deleted.
             *
             * Someone on leave should stop receiving alerts without their
             * preferences being destroyed and rebuilt by hand on return —
             * and without the operator being tempted to leave them on
             * because re-adding looks like work.
             */
            $table->boolean('is_active')->default(true);

            // The three events of the approved scope. A new row defaults to
            // the enquiry alert only: that is the one every recipient is
            // added for, and the other two are opted into deliberately.
            $table->boolean('on_new_lead')->default(true);
            $table->boolean('on_crm_failure')->default(false);
            $table->boolean('on_daily_summary')->default(false);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
    }
};
