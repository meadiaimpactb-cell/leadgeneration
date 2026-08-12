<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The seeder write pattern, after `firstOrCreate` cost us a page element.
 *
 * `firstOrCreate` sets every column in its second argument ONLY when it
 * creates the row. That is right for content and wrong for structure, and it
 * hides which of the two you meant — the call looks identical either way.
 *
 * It has already failed once in production shape: a failed seeder run let
 * DemoContentSeeder create `contact.header_cta.*` first — its writer sets
 * `value` and nothing else — so `is_public` stayed at the column default,
 * StructureSeeder's later run found the row present and changed nothing, and
 * the «لنبدأ معًا» button vanished from every page. Nothing was broken, the
 * row was there, and the setting simply never reached the browser.
 *
 * So the two kinds of column are named at every call site instead:
 *
 *   $structure — this file is the authority. Enforced on every run, because
 *                a row born down another path must not keep a wrong flag.
 *   $owned     — the client's. Written once, at creation, and never again:
 *                once an administrator has edited a heading or switched a
 *                field off, a seeder that overwrites them is a bug (§9.1).
 *
 * A call passing no `$structure` is a statement, not an omission: it says
 * every non-identity column on that row belongs to the client. Say why in a
 * comment when that is the case.
 */
trait SeedsRows
{
    /**
     * @param  Builder<Model>|Relation<Model, Model, *>  $query
     * @param  array<string, mixed>  $identity  how the row is found
     * @param  array<string, mixed>  $structure  enforced on every run
     * @param  array<string, mixed>  $owned  set at creation only
     */
    protected function seedRow(Builder|Relation $query, array $identity, array $structure = [], array $owned = []): Model
    {
        $row = $query->where($identity)->first();

        if ($row === null) {
            return $query->create($identity + $structure + $owned);
        }

        if ($structure !== []) {
            // forceFill + isDirty rather than comparing by hand: Eloquent's
            // own comparison understands the casts, so a boolean column read
            // back as 1 does not look like a change and trigger a write on
            // every seeder run.
            $row->forceFill($structure);

            if ($row->isDirty()) {
                $row->save();
            }
        }

        return $row;
    }
}
