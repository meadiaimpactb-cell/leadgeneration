<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Workspace from '@/Components/admin/Workspace.vue';
import Panel from '@/Components/admin/Panel.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
import Field from '@/Components/admin/Field.vue';
import MediaSlot from '@/Components/admin/MediaSlot.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * One settings screen (§9.1, §14.1).
 *
 * The previous screen listed the whole settings table under its raw database
 * keys. This one shows a single concern at a time, and every field carries the
 * name and the sentence of explanation defined in resources/lang — so the
 * person maintaining the site can tell what a field does and where the change
 * will show up, without asking anyone.
 */
const props = defineProps({
    screen: { type: String, required: true },
    fields: { type: Array, default: () => [] },
    robotsPreview: { type: String, default: null },
    sitemapUrl: { type: String, default: null },
    isProduction: { type: Boolean, default: false },
});

const { t } = useTranslation();

// Seeded from the server once. Editing a field mutates this, never the prop.
const values = reactive(
    Object.fromEntries(props.fields.map((f) => [f.id, serialise(f)]))
);

const processing = ref(false);

/** A list setting is edited as JSON; everything else as its own value. */
function serialise(field) {
    if (field.type === 'list') {
        return JSON.stringify(field.value ?? [], null, 2);
    }

    if (field.type === 'boolean') {
        return Boolean(field.value);
    }

    return field.value ?? '';
}

/**
 * An image setting is stored as the URL of the chosen file, and edited as the
 * one-item list MediaSlot speaks.
 *
 * Storing the URL rather than a media id keeps every reader unchanged —
 * MetaBuilder already expects a path here, and a setting that meant one thing
 * to the panel and another to the renderer is how these values go stale.
 */
function asSlot(value) {
    return value ? [{ id: null, url: value, thumb: value, name: value }] : [];
}

function fromSlot(items) {
    return items.length ? (items[0].url ?? null) : null;
}

function deserialise(field, raw) {
    if (field.type === 'list') {
        try {
            return JSON.parse(raw || '[]');
        } catch {
            // Keep the text rather than discarding the editor's work; the
            // server stores it and the field shows it back for correction.
            return raw;
        }
    }

    return raw === '' ? null : raw;
}

/** Which HTML control each declared type deserves. */
const controls = {
    boolean: 'checkbox',
    textarea: 'textarea',
    list: 'textarea',
    code: 'textarea',
    url: 'url',
    email: 'email',
    tel: 'tel',
    text: 'text',
};

function control(field) {
    return controls[field.type] ?? 'text';
}

/**
 * Latin-script values read wrong in an RTL page: a URL or a GTM id gets its
 * punctuation reordered. Force LTR for the field types that are never Arabic.
 */
function direction(field) {
    if (['url', 'email', 'tel', 'code', 'list'].includes(field.type)) return 'ltr';
    if (field.key.endsWith('.en')) return 'ltr';
    if (field.key.startsWith('tracking.')) return 'ltr';

    return null;
}

/**
 * Advisory format check.
 *
 * An empty field is fine — most settings are optional. A filled one that does
 * not match the shape its provider uses gets a warning, not a block: these
 * formats belong to Google and Meta and could change, so refusing to save a
 * value we merely fail to recognise would be worse than flagging it.
 */
function status(field) {
    if (!field.pattern) return null;

    const value = String(values[field.id] ?? '').trim();

    if (value === '') return null;

    return new RegExp(field.pattern).test(value) ? 'ok' : 'warn';
}

const title = computed(() => t(`settings.screen.${props.screen}`));
const intro = computed(() => t(`settings.screen.${props.screen}_hint`));

function save() {
    processing.value = true;

    router.put(
        '/admin/settings',
        {
            settings: props.fields.map((f) => ({
                id: f.id,
                value: deserialise(f, values[f.id]),
            })),
        },
        {
            preserveScroll: true,
            onFinish: () => (processing.value = false),
        }
    );
}
</script>

<template>
    <AdminLayout :title="title">
        <Workspace>
            <Panel :title="title">
                <p class="intro">{{ intro }}</p>

                <!-- robots.txt gets context the other screens do not need: what
                     the file currently says, and where the sitemap lives. -->
                <template v-if="screen === 'robots'">
                    <p v-if="!isProduction" class="notice">{{ t('settings.robots.staging_notice') }}</p>

                    <p class="sitemap">
                        <strong>{{ t('settings.robots.sitemap') }}:</strong>
                        <a class="link-weave latin" :href="sitemapUrl" target="_blank" rel="noopener">{{ sitemapUrl }}</a>
                        <span class="sitemap__hint">{{ t('settings.robots.sitemap_hint') }}</span>
                    </p>
                </template>

                <div class="grid">
                    <div
                        v-for="field in fields"
                        :key="field.id"
                        :class="{ 'grid__wide': ['textarea', 'code', 'list', 'image'].includes(field.type) }"
                    >
                        <!-- Picked from the library, with the choice shown
                             back as a picture. Typing a path was the reason
                             this setting sat empty. -->
                        <MediaSlot
                            v-if="field.type === 'image'"
                            :model-value="asSlot(values[field.id])"
                            :limit="1"
                            :label="t(`settings.${field.key}`)"
                            :hint="t(`settings.${field.key}_hint`)"
                            @update:model-value="(items) => (values[field.id] = fromSlot(items))"
                        />

                        <Field
                            v-else
                            v-model="values[field.id]"
                            :label="t(`settings.${field.key}`)"
                            :hint="t(`settings.${field.key}_hint`)"
                            :type="control(field)"
                            :dir="direction(field)"
                            :placeholder="field.placeholder ?? undefined"
                            :rows="field.type === 'code' || field.type === 'list' ? 10 : 3"
                        />

                        <p v-if="status(field) === 'ok'" class="check check--ok">
                            {{ t('settings.format_ok') }}
                        </p>
                        <p v-else-if="status(field) === 'warn'" class="check check--warn">
                            {{ t('settings.format_warn', { example: field.placeholder }) }}
                        </p>
                    </div>
                </div>

                <template v-if="screen === 'robots' && robotsPreview">
                    <h3 class="preview__title">{{ t('settings.robots.preview') }}</h3>
                    <pre class="preview latin">{{ robotsPreview }}</pre>
                    <a class="link-weave" href="/robots.txt" target="_blank" rel="noopener">
                        {{ t('settings.robots.open') }}
                    </a>
                </template>

                <div class="bar">
                    <button class="act btn btn--cta" type="button" :disabled="processing" @click="save">
                        <NavIcon name="check" :size="18" :muted="false" />
                        <span>{{ processing ? t('admin.saving') : t('admin.save') }}</span>
                    </button>
                </div>
            </Panel>
    
        </Workspace>
    </AdminLayout>
</template>

<style scoped>
.intro {
    color: var(--muted);
    font-size: var(--fs-sm);
    margin-block-end: var(--s-5);
    max-inline-size: 70ch;
}

.notice {
    padding: var(--s-3) var(--s-4);
    margin-block-end: var(--s-5);
    border-radius: var(--r-sm);
    background: var(--gold-100);
    color: var(--navy-900);
    font-size: var(--fs-sm);
}

.sitemap {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: var(--s-2);
    padding: var(--s-3) var(--s-4);
    margin-block-end: var(--s-5);
    border-radius: var(--r-sm);
    background: var(--paper-alt);
    font-size: var(--fs-sm);
}

.sitemap__hint {
    color: var(--muted);
    flex-basis: 100%;
}

.grid {
    display: grid;
    gap: var(--s-5);
    grid-template-columns: 1fr;
}

.check {
    margin-block-start: var(--s-2);
    font-size: var(--fs-xs);
    font-weight: 600;
}

.check--ok {
    color: #16643B;
}

.check--warn {
    color: var(--action-600);
}

.preview__title {
    margin-block-start: var(--s-6);
    font-size: var(--fs-h3);
}

.preview {
    margin-block: var(--s-3);
    padding: var(--s-4);
    border-radius: var(--r-sm);
    border: 1px solid var(--hairline);
    background: var(--paper-alt);
    font-size: var(--fs-sm);
    line-height: 1.7;
    white-space: pre-wrap;
    overflow-x: auto;
}

.bar {
    display: flex;
    gap: var(--s-3);
    margin-block-start: var(--s-6);
}

@media (min-width: 900px) {
    .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .grid__wide {
        grid-column: 1 / -1;
    }
}
</style>
