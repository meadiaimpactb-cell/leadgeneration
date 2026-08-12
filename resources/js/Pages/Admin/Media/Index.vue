<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import { useFormat } from '@/Composables/useFormat';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The media screen: everything the site has a picture of, in one place.
 *
 * Reads the same endpoints as the picker rather than its own Inertia props,
 * so there is one library with one behaviour. A screen and a modal that each
 * fetch their own way is how the two end up disagreeing about what exists.
 *
 * The one thing it can do that the picker cannot is delete, and that is why
 * "used in" is on the details rail and not hidden behind a menu: an image
 * removed from the library disappears from every page using it at once, and
 * the only defence against that being a surprise is saying so before the
 * click.
 */
defineProps({
    locales: { type: Array, default: () => ['ar', 'en'] },
});

const { t } = useTranslation();
const { date } = useFormat();

const MAX_MB = 64;
const ACCEPT =
    'image/jpeg,image/png,image/webp,image/avif,image/svg+xml,image/gif,video/mp4,video/webm';

const locale = computed(() => usePage().props.locale ?? 'ar');

const items = ref([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const search = ref('');
const active = ref(null);
const usage = ref([]);
const savingAlt = ref(false);
const uploads = ref([]);
const dragOver = ref(false);
const fileInput = ref(null);
const searchTimer = ref(null);

const activeItem = computed(() => items.value.find((i) => i.id === active.value) ?? null);

onMounted(() => load(1, true));

watch(search, () => {
    clearTimeout(searchTimer.value);
    searchTimer.value = setTimeout(() => load(1, true), 250);
});

watch(active, (id) => {
    usage.value = [];
    if (id) loadUsage(id);
});

async function load(target = 1, replace = false) {
    loading.value = true;

    try {
        const url = new URL('/admin/media/library', window.location.origin);
        url.searchParams.set('page', target);
        if (search.value.trim() !== '') url.searchParams.set('search', search.value.trim());

        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) return;

        const data = await response.json();

        items.value = replace ? data.items : [...items.value, ...data.items];
        page.value = data.page;
        lastPage.value = data.lastPage;
        total.value = data.total;
    } finally {
        loading.value = false;
    }
}

/**
 * Fetched per image rather than counted into the grid: the count is enough to
 * decide, and the list of places is only worth a query when someone is looking
 * at that image.
 */
async function loadUsage(id) {
    const response = await fetch(`/admin/media/${id}/usage`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });

    if (!response.ok) return;

    usage.value = (await response.json()).usage ?? [];
}

/**
 * A place an image is used.
 *
 * Section types and collection names are shown as they are, which is what the
 * builder and the content editor already do with them. They are identifiers
 * rather than copy, and inventing a translation key here that exists nowhere
 * else would print the key itself on the screen.
 */
function placeLabel(place) {
    if (place.label?.model === 'section') {
        const parent = place.label.parent?.title;
        const section = t('admin.media_section_of', { type: place.label.type });

        return parent ? `${parent} — ${section}` : section;
    }

    return place.label?.title ?? place.collection;
}

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function saveAlt() {
    const item = activeItem.value;
    if (!item) return;

    savingAlt.value = true;

    try {
        const body = new FormData();
        body.append('_method', 'PATCH');

        item.translations &&
            Object.keys(item.translations).forEach((code) => {
                body.append(`translations[${code}][alt_text]`, item.translations[code]?.alt_text ?? '');
                body.append(`translations[${code}][caption]`, item.translations[code]?.caption ?? '');
            });

        await fetch(`/admin/media/${item.id}`, {
            method: 'POST',
            body,
            headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
    } finally {
        savingAlt.value = false;
    }
}

/**
 * Deleting names the damage first.
 *
 * The count comes from the server, and the confirm text lists how many places
 * lose the image — "are you sure?" on its own asks a question the person
 * cannot answer.
 */
function destroy(item) {
    const inUse = usage.value.length;

    const message = inUse
        ? t('admin.media_delete_warning', { count: inUse })
        : t('admin.confirm_delete');

    if (!confirm(message)) return;

    router.delete(`/admin/media/${item.id}`, {
        data: { force: true },
        preserveScroll: true,
        onSuccess: () => {
            items.value = items.value.filter((i) => i.id !== item.id);
            total.value -= 1;
            active.value = null;
        },
    });
}

// ---- uploading -------------------------------------------------------

function pick() {
    fileInput.value?.click();
}

function onDrop(event) {
    dragOver.value = false;
    queue(event.dataTransfer?.files);
}

function onPick(event) {
    queue(event.target.files);
    event.target.value = '';
}

/**
 * One file at a time, awaited.
 *
 * Sending them all at once put one PHP worker per file to work generating
 * thumbnails, and the ones that lost the race came back with session and
 * connection errors that had nothing to do with the file. See MediaPicker.
 */
async function queue(fileList) {
    const files = Array.from(fileList ?? []);
    if (files.length === 0) return;

    for (const file of files) {
        const entry = { name: file.name, progress: 0, error: null, done: false };
        uploads.value.push(entry);

        const tooBig = file.size > MAX_MB * 1024 * 1024;
        const wrongType = !ACCEPT.split(',').includes(file.type);

        if (tooBig || wrongType) {
            entry.error = tooBig ? t('admin.media_too_large', { max: MAX_MB }) : t('admin.media_wrong_type');

            continue;
        }

        // eslint-disable-next-line no-await-in-loop
        await send(file, entry);
    }
}

/** Resolves when this file is finished, successfully or not. */
function send(file, entry) {
    return new Promise((resolve) => {
        const body = new FormData();
        body.append('file', file);

        // XHR, not fetch: fetch still cannot report upload progress.
        const request = new XMLHttpRequest();

        request.open('POST', '/admin/media/library');
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('X-CSRF-TOKEN', csrf());

        request.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                entry.progress = Math.round((event.loaded / event.total) * 100);
            }
        });

        request.addEventListener('load', () => {
            if (request.status === 201) {
                entry.done = true;
                entry.progress = 100;

                const item = JSON.parse(request.responseText).item;
                items.value = [item, ...items.value];
                total.value += 1;
                active.value = item.id;
            } else {
                entry.error = failureMessage(request);
            }

            resolve();
        });

        request.addEventListener('error', () => {
            entry.error = t('admin.media_upload_failed');
            resolve();
        });

        request.send(body);
    });
}

/**
 * What to tell the editor when an upload comes back wrong.
 *
 * Only a 422 carries something they can act on. Anything else is the server
 * having a bad day, and pasting its exception into the panel puts
 * `SQLSTATE[HY000] [1049] Unknown database` next to a photograph, which reads
 * as "your picture is broken" when the picture is fine.
 */
function failureMessage(request) {
    if (request.status === 422) {
        try {
            const parsed = JSON.parse(request.responseText);

            return parsed.errors?.file?.[0] ?? parsed.message ?? t('admin.media_upload_failed');
        } catch {
            // Fall through to the generic message.
        }
    }

    if (request.status === 413) return t('admin.media_too_large', { max: MAX_MB });

    return t('admin.media_upload_failed');
}

/** A clip cannot be drawn with <img>; the browser paints its first frame. */
function isVideo(item) {
    return String(item?.mime ?? '').startsWith('video/');
}

function formatSize(bytes) {
    if (!bytes) return '—';
    const mb = bytes / (1024 * 1024);

    return mb >= 1 ? `${mb.toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;
}

/*
 * Through useFormat, never through the browser's own date helper.
 *
 * One formatter for the whole platform: Latin digits in both languages and a
 * Gregorian calendar, because `ar-SA` otherwise renders Arabic-Indic numerals
 * and Hijri dates — and an upload date that does not match the same file's
 * date in an export is a date nobody trusts.
 */
const formatDate = date;
</script>

<template>
    <AdminLayout :title="t('admin.media_library')">
        <Panel :title="t('admin.media_tab_upload')">
            <div
                class="drop"
                :class="{ 'is-over': dragOver }"
                @dragover.prevent="dragOver = true"
                @dragleave.prevent="dragOver = false"
                @drop.prevent="onDrop"
            >
                <p class="drop__label">{{ t('admin.media_drop_here') }}</p>
                <button type="button" class="btn btn--ghost" @click="pick">
                    {{ t('admin.media_or_browse') }}
                </button>

                <input ref="fileInput" type="file" multiple :accept="ACCEPT" class="drop__input" @change="onPick" />
            </div>

            <ul v-if="uploads.length" class="queue">
                <li v-for="(entry, i) in uploads" :key="i" class="queue__row">
                    <span class="queue__name" dir="auto">{{ entry.name }}</span>

                    <span v-if="entry.error" class="queue__error">{{ entry.error }}</span>
                    <span v-else-if="entry.done" class="queue__done">{{ t('admin.media_upload_done') }}</span>

                    <span v-else class="queue__bar" role="progressbar" :aria-valuenow="entry.progress">
                        <span class="queue__fill" :style="{ inlineSize: `${entry.progress}%` }" />
                    </span>
                </li>
            </ul>
        </Panel>

        <Panel :title="t('admin.media_library')" :hint="t('admin.media_usage_count', { count: total })">
            <input v-model="search" type="search" class="search" :placeholder="t('admin.media_search')" />

            <div class="screen">
                <div class="screen__main">
                    <p v-if="!loading && items.length === 0" class="muted">
                        {{ search ? t('admin.media_no_results') : t('admin.media_empty') }}
                    </p>

                    <ul class="grid">
                        <li v-for="item in items" :key="item.id">
                            <button
                                type="button"
                                class="grid__cell"
                                :class="{ 'is-active': active === item.id }"
                                @click="active = item.id"
                            >
                                <video v-if="isVideo(item)" :src="item.url" muted playsinline preload="metadata" />
                                <img
                                    v-else
                                    :src="item.thumb"
                                    :alt="item.translations[locale]?.alt_text ?? ''"
                                    loading="lazy"
                                />
                            </button>
                        </li>
                    </ul>

                    <button
                        v-if="page < lastPage"
                        type="button"
                        class="btn btn--ghost more"
                        :disabled="loading"
                        @click="load(page + 1)"
                    >
                        {{ t('admin.media_load_more') }}
                    </button>
                </div>

                <aside v-if="activeItem" class="screen__side">
                    <video
                        v-if="isVideo(activeItem)"
                        :src="activeItem.url"
                        class="preview"
                        muted
                        playsinline
                        controls
                    />
                    <img v-else :src="activeItem.thumb" alt="" class="preview" />

                    <dl class="facts">
                        <dt>{{ t('admin.media_file_name') }}</dt>
                        <dd class="facts__file">{{ activeItem.fileName }}</dd>

                        <dt>{{ t('admin.media_dimensions') }}</dt>
                        <dd>
                            <span v-if="activeItem.width">{{ activeItem.width }} × {{ activeItem.height }}</span>
                            <span v-else>—</span>
                        </dd>

                        <dt>{{ t('admin.media_size') }}</dt>
                        <dd>{{ formatSize(activeItem.size) }}</dd>

                        <dt>{{ t('admin.media_uploaded_at') }}</dt>
                        <dd>{{ formatDate(activeItem.createdAt) }}</dd>
                    </dl>

                    <div v-for="code in locales" :key="code" class="alt">
                        <label :for="`alt-${code}`">{{ t('admin.alt_text') }} — {{ code.toUpperCase() }}</label>
                        <input
                            :id="`alt-${code}`"
                            v-model="activeItem.translations[code].alt_text"
                            type="text"
                            dir="auto"
                            @change="saveAlt"
                        />
                    </div>

                    <p v-if="savingAlt" class="muted">{{ t('admin.saving') }}</p>

                    <!-- Where it is used, named. The list is the warning. -->
                    <h3 class="side__title">{{ t('admin.media_used_in') }}</h3>

                    <p v-if="usage.length === 0" class="muted">{{ t('admin.media_unused') }}</p>

                    <ul v-else class="usage">
                        <li v-for="(place, i) in usage" :key="i">{{ placeLabel(place) }}</li>
                    </ul>

                    <button type="button" class="btn btn--ghost danger delete" @click="destroy(activeItem)">
                        {{ t('admin.media_delete_forever') }}
                    </button>
                </aside>
            </div>
        </Panel>
    </AdminLayout>
</template>

<style scoped>
.search {
    inline-size: 100%;
    max-inline-size: 420px;
    margin-block-end: var(--s-4);
    padding: var(--s-2) var(--s-3);
    border: 1px solid var(--hairline);
    border-radius: var(--r-sm);
    font: inherit;
}

.screen {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--s-5);
}

@media (min-width: 1024px) {
    .screen {
        grid-template-columns: 1fr 320px;
    }
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: var(--s-3);
    margin: 0;
    padding: 0;
    list-style: none;
}

.grid__cell {
    display: block;
    inline-size: 100%;
    aspect-ratio: 1;
    padding: 0;
    border: 2px solid transparent;
    border-radius: var(--r-sm);
    background: var(--paper-alt);
    cursor: pointer;
    overflow: hidden;
}

.grid__cell img,
.grid__cell video {
    inline-size: 100%;
    block-size: 100%;
    object-fit: contain;
}

.grid__cell.is-active {
    border-color: var(--action-600);
}

.screen__side {
    padding-inline-start: var(--s-4);
    border-inline-start: 1px solid var(--hairline);
}

.preview {
    inline-size: 100%;
    block-size: 180px;
    object-fit: contain;
    background: var(--paper-alt);
    border-radius: var(--r-sm);
}

.facts {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: var(--s-1) var(--s-2);
    margin-block: var(--s-3);
    font-size: var(--fs-sm);
}

.facts dt {
    color: var(--muted);
}

.facts dd {
    margin: 0;
}

.facts__file {
    overflow-wrap: anywhere;
}

.alt {
    display: grid;
    gap: var(--s-1);
    margin-block-end: var(--s-2);
}

.alt label {
    font-size: var(--fs-xs);
    color: var(--muted);
}

.alt input {
    inline-size: 100%;
    padding: var(--s-2);
    border: 1px solid var(--hairline);
    border-radius: var(--r-sm);
    font: inherit;
}

.side__title {
    margin-block: var(--s-4) var(--s-2);
    font-size: var(--fs-sm);
}

.usage {
    margin: 0 0 var(--s-3);
    padding-inline-start: var(--s-4);
    font-size: var(--fs-sm);
}

.delete {
    margin-block-start: var(--s-3);
}

.danger {
    color: var(--action-600);
}

.muted {
    color: var(--muted);
    font-size: var(--fs-sm);
}

.more {
    margin-block-start: var(--s-3);
}

.drop {
    display: grid;
    justify-items: center;
    gap: var(--s-3);
    padding: var(--s-6) var(--s-4);
    border: 2px dashed var(--hairline);
    border-radius: var(--r-md);
    text-align: center;
}

.drop.is-over {
    border-color: var(--action-600);
    background: var(--gold-100);
}

.drop__input {
    display: none;
}

.queue {
    margin-block-start: var(--s-4);
    padding: 0;
    list-style: none;
}

.queue__row {
    display: grid;
    grid-template-columns: 1fr 160px;
    align-items: center;
    gap: var(--s-3);
    padding-block: var(--s-2);
    border-block-end: 1px solid var(--hairline);
    font-size: var(--fs-sm);
}

.queue__name {
    overflow-wrap: anywhere;
}

.queue__bar {
    block-size: 6px;
    border-radius: var(--r-pill);
    background: var(--navy-100);
    overflow: hidden;
}

.queue__fill {
    display: block;
    block-size: 100%;
    background: var(--action-600);
    transition: inline-size 160ms cubic-bezier(0.2, 0.7, 0.3, 1);
}

.queue__error {
    color: var(--action-600);
}

.queue__done {
    color: var(--muted);
}
</style>
