<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * "Who changed what, and when" for a content model (§9.1, §15.3).
 *
 * WHY A SHARED TRAIT AND NOT LogsActivity ON EACH MODEL
 *
 * The audit trail's value is that it is complete. A model that was simply
 * forgotten leaves a hole nobody notices until the day someone needs to know
 * who unpublished a page — and the answer to "was this deliberate?" is then
 * unavailable precisely when it matters. One trait with one set of defaults
 * makes adding a model a single line, and makes the omissions visible: the
 * models that do NOT use this are the ones that had a reason.
 *
 * WHAT IS DELIBERATELY NOT LOGGED
 *
 * Translations are not audited separately. They are edited through their
 * parent in one save, so logging both turns a single edit into two rows and
 * makes the screen read as twice the activity that occurred.
 *
 * A model holding secrets must NOT use this trait as-is — the default logs
 * changed attribute values, and that would make the audit trail a second
 * place API keys are stored. `Setting` overrides it for exactly that reason;
 * see its own docblock.
 */
trait RecordsActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            /*
             * Every column the model lets you write.
             *
             * `logUnguarded`, not `logFillable`: these models declare
             * `$guarded = ['id']` rather than a fillable list, so
             * `getFillable()` is empty and `logFillable()` would silently
             * audit nothing at all — a trail that exists and records zero is
             * worse than none, because it looks like nothing happened.
             *
             * Generated rather than hand-listed for the same reason: a column
             * added next month is audited without anyone remembering to come
             * back here.
             */
            ->logUnguarded()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
