<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which images are used where — a reference, not a second copy.
 *
 * Media Library ties every `media` row to exactly one owner through
 * `model_type`/`model_id`. That is right for an image that belongs to one
 * record — a partner's logo, an artisan's portrait — and wrong for a shared
 * library, because the only way to put the same photograph in two sections is
 * to upload it twice. Two rows, two files on disk, two alt texts to keep in
 * step, and an editor who fixes the alt text on one of them and cannot see why
 * the other page still reads wrong.
 *
 * So the library owns the file once and sections point at it. This is the
 * arrangement anyone who has used WordPress already understands: an image is
 * uploaded to the library, and a page references it. Removing it from a
 * section removes the reference; the image stays in the library, where the
 * other three sections using it still find it.
 *
 * `sort_order` lives here rather than on `media` for the same reason: the same
 * image can be third in one gallery and first in another, and neither position
 * is a property of the file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_attachments', function (Blueprint $table): void {
            $table->id();

            /*
             * Cascade: deleting the image deletes its references. The reverse
             * is deliberately NOT true — detaching is not deleting, and the
             * screen warns before deleting anything still referenced.
             */
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();

            // Polymorphic so this serves sections today and section items the
            // day their migration runs, with no second table.
            $table->morphs('attachable');

            /*
             * Which slot on the owner: `image` for the single illustration a
             * hero or media split uses, `gallery` for the repeatable set. The
             * same names Section::registerMediaCollections already uses, so
             * one vocabulary covers owned media and referenced media both.
             */
            $table->string('collection', 64)->default('gallery');

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id', 'collection', 'sort_order'], 'media_attachments_owner_index');

            /*
             * The same image twice in one gallery is never intent — it is a
             * double-click on "insert". The database refuses it so the code
             * does not have to remember to.
             */
            $table->unique(['media_id', 'attachable_type', 'attachable_id', 'collection'], 'media_attachments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_attachments');
    }
};
