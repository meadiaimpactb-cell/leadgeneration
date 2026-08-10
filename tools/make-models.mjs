/**
 * Generates the formulaic Eloquent models (translation tables and the
 * content entities that are "translated content + media + ordering").
 *
 * Models with real behaviour — Page, Section, Sector, Lead, Campaign — are
 * hand-written and are NOT produced here.
 *
 * Run: node tools/make-models.mjs
 */
import { writeFile } from 'node:fs/promises';

const B = String.fromCharCode(92); // backslash, kept out of template literals

/* ------------------------------------------------------------------ */
/* Translation models                                                  */
/* ------------------------------------------------------------------ */

const translations = {
    PageTranslation: ['title', 'subtitle', 'excerpt', 'meta_title', 'meta_description', 'canonical_override'],
    SectionTranslation: ['heading', 'subheading', 'body', 'cta_label', 'cta_url'],
    SolutionTranslation: ['name', 'summary', 'body', 'meta_title', 'meta_description'],
    SectorTranslation: ['name', 'summary', 'body', 'meta_title', 'meta_description'],
    ProductCategoryTranslation: ['name'],
    ShowcaseProductTranslation: ['name', 'description', 'craft_technique', 'meta_title', 'meta_description'],
    ImpactMetricTranslation: ['label', 'note'],
    StoryTranslation: ['title', 'body', 'quote', 'attribution'],
    ReportTranslation: ['title', 'summary'],
    TrainingProgramTranslation: ['name', 'summary', 'body', 'outcomes'],
    PartnerTranslation: ['display_name', 'note'],
    CampaignTranslation: ['title', 'meta_title', 'meta_description'],
    NavigationItemTranslation: ['label'],
    CtaTranslation: ['label'],
    MediaTranslation: ['alt_text', 'caption'],
};

for (const [name, fields] of Object.entries(translations)) {
    const props = fields.map((f) => ` * @property string|null $${f}`).join('\n');

    const php = `<?php

declare(strict_types=1);

namespace App${B}Models;

use Illuminate${B}Database${B}Eloquent${B}Model;

/**
 * Per-locale content for ${name.replace('Translation', '')}.
 *
 * @property string $locale
${props}
 */
class ${name} extends Model
{
    protected $guarded = ['id'];
}
`;

    await writeFile(`app/Models/${name}.php`, php, 'utf8');
}

/* ------------------------------------------------------------------ */
/* Content entities                                                    */
/* ------------------------------------------------------------------ */

const entities = [
    {
        name: 'Solution',
        doc: 'A solution offered to companies (§5). Composes its own page from sections.',
        media: ['hero'],
        soft: true,
        flag: 'is_active',
        sections: true,
    },
    {
        name: 'ProductCategory',
        doc: 'Grouping for the product showcase.',
        media: [],
        soft: false,
        flag: 'is_active',
        sections: false,
    },
    {
        name: 'ShowcaseProduct',
        doc: 'A product shown for information only. No price, no cart, no purchase (§2.2, §4).',
        media: ['primary', 'gallery'],
        soft: true,
        flag: 'is_active',
        sections: false,
    },
    {
        name: 'TrainingProgram',
        doc: 'A training and empowerment track (§5).',
        media: ['hero'],
        soft: false,
        flag: 'is_active',
        sections: false,
    },
    {
        name: 'Report',
        doc: 'A publishable impact report (§5). The file itself is a private-disk media item.',
        media: ['file', 'cover'],
        soft: false,
        flag: 'is_public',
        sections: false,
    },
];

for (const m of entities) {
    const imports = [
        `use App${B}Models${B}Concerns${B}HasTranslations;`,
        m.sections ? `use App${B}Models${B}Concerns${B}HasSections;` : null,
        `use Illuminate${B}Database${B}Eloquent${B}Builder;`,
        `use Illuminate${B}Database${B}Eloquent${B}Factories${B}HasFactory;`,
        `use Illuminate${B}Database${B}Eloquent${B}Model;`,
        m.soft ? `use Illuminate${B}Database${B}Eloquent${B}SoftDeletes;` : null,
        m.media.length ? `use Spatie${B}MediaLibrary${B}HasMedia;` : null,
        m.media.length ? `use Spatie${B}MediaLibrary${B}InteractsWithMedia;` : null,
    ]
        .filter(Boolean)
        .sort()
        .join('\n');

    const traits = [
        'use HasFactory;',
        m.sections ? 'use HasSections;' : null,
        'use HasTranslations;',
        m.media.length ? 'use InteractsWithMedia;' : null,
        m.soft ? 'use SoftDeletes;' : null,
    ]
        .filter(Boolean)
        .join('\n    ');

    const collections = m.media.length
        ? '\n' +
          '    public function registerMediaCollections(): void\n' +
          '    {\n' +
          m.media
              .map((c) => `        $this->addMediaCollection('${c}')${c === 'gallery' ? '' : '->singleFile()'};`)
              .join('\n') +
          '\n    }\n'
        : '';

    const php = `<?php

declare(strict_types=1);

namespace App${B}Models;

${imports}

/**
 * ${m.doc}
 */
class ${m.name} extends Model${m.media.length ? ' implements HasMedia' : ''}
{
    ${traits}

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            '${m.flag}' => 'boolean',
        ];
    }
${collections}
    /** Records the public may see, in the order the admin panel set. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('${m.flag}', true)->orderBy('sort_order');
    }
}
`;

    await writeFile(`app/Models/${m.name}.php`, php, 'utf8');
}

console.log(
    `generated ${Object.keys(translations).length} translation models + ${entities.length} content entities`
);
