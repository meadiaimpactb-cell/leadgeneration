<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The one image picker, used everywhere an image is chosen.
 *
 * Two tabs, because there are exactly two things an editor arrives wanting:
 * an image the site already has, or one on their desk. The library is the
 * default of the two deliberately — the whole point of a library is that the
 * second visit does not need an upload, and putting "upload" first teaches
 * people to re-upload what they already have.
 *
 * It emits ids and never touches the page itself. What a chosen image means —
 * a hero, a gallery, a partner's logo — belongs to whoever opened it, and the
 * one thing this component must not do is know.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    /** How many may be chosen: 1 for a single slot, 0 for no limit. */
    limit: { type: Number, default: 0 },
    /** Ids already in the slot, so re-opening shows them ticked. */
    selected: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'insert']);

const { t } = useTranslation();

/** The panel's own interface language, for dates and for which alt to show. */
const locale = computed(() => usePage().props.locale ?? 'ar');

const LOCALES = ['ar', 'en'];
const MAX_MB = 10;
const ACCEPT = 'image/jpeg,image/png,image/webp,image/avif,image/svg+xml';

const tab = ref('library');
const items = ref([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const search = ref('');
const chosen = ref([]);
const active = ref(null);
const savingAlt = ref(false);
const uploads = ref([]);
const dragOver = ref(false);
const fileInput = ref(null);
const searchTimer = ref(null);

const single = computed(() => props.limit === 1);
const activeItem = computed(() => items.value.find((i) => i.id === active.value) ?? null);

/**
 * Chosen ids in the order they were clicked, not in library order — an editor
 * clicking four images for a gallery is stating a sequence, and re-sorting it
 * to "newest first" behind their back is the kind of small betrayal that makes
 * a screen feel unreliable.
 */
const canInsert = computed(() => chosen.value.length > 0);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;

        tab.value = 'library';
        chosen.value = [...props.selected];
        active.value = props.selected[0] ?? null;
        uploads.value = [];
        load(1, true);
    }
);

watch(search, () => {
    // Debounced: every keystroke is a request otherwise, and the grid
    // flickering under the cursor makes searching feel broken.
    clearTimeout(searchTimer.value);
    searchTimer.value = setTimeout(() => load(1, true), 250);
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

function toggle(item) {
    active.value = item.id;

    const at = chosen.value.indexOf(item.id);

    if (at !== -1) {
        chosen.value.splice(at, 1);
        return;
    }

    if (single.value) {
        chosen.value = [item.id];
        return;
    }

    if (props.limit > 0 && chosen.value.length >= props.limit) return;

    chosen.value.push(item.id);
}

function isChosen(item) {
    return chosen.value.includes(item.id);
}

function insert() {
    // Emitted as full records, not ids: the caller needs a thumbnail to draw
    // immediately, and making it fetch what this component already has in
    // hand would put a spinner where an image should be.
    emit(
        'insert',
        chosen.value.map((id) => items.value.find((i) => i.id === id)).filter(Boolean)
    );
    emit('close');
}

// ---- alt text --------------------------------------------------------

/**
 * Saved against the image, not against the place it is used — §10.8 wants alt
 * text the client controls, and a description of a photograph does not change
 * because it moved to another page.
 */
async function saveAlt() {
    const item = activeItem.value;
    if (!item) return;

    savingAlt.value = true;

    try {
        const body = new FormData();
        body.append('_method', 'PATCH');

        LOCALES.forEach((code) => {
            body.append(`translations[${code}][alt_text]`, item.translations[code]?.alt_text ?? '');
            body.append(`translations[${code}][caption]`, item.translations[code]?.caption ?? '');
        });

        await fetch(`/admin/media/${item.id}`, {
            method: 'POST',
            body,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
    } finally {
        savingAlt.value = false;
    }
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
 * One request per file.
 *
 * A single request carrying five files can only report one progress bar for
 * all of them, and a bar that sits at 40% for a minute is what makes an editor
 * reload the page halfway through an upload.
 */
function queue(fileList) {
    const files = Array.from(fileList ?? []);
    if (files.length === 0) return;

    tab.value = 'upload';

    files.forEach((file) => {
        const entry = { name: file.name, progress: 0, error: null, done: false };
        uploads.value.push(entry);

        const tooBig = file.size > MAX_MB * 1024 * 1024;
        const wrongType = !ACCEPT.split(',').includes(file.type);

        // Checked here as well as on the server: the server is the authority,
        // but a 10MB upload that fails after 10MB of waiting is a worse way to
        // learn the limit than being told before it starts.
        if (tooBig || wrongType) {
            entry.error = tooBig ? t('admin.media_too_large', { max: MAX_MB }) : t('admin.media_wrong_type');
            return;
        }

        send(file, entry);
    });
}

function send(file, entry) {
    const body = new FormData();
    body.append('file', file);

    // XHR rather than fetch: fetch still cannot report upload progress.
    const request = new XMLHttpRequest();

    request.open('POST', '/admin/media/library');
    request.setRequestHeader('Accept', 'application/json');
    request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    request.setRequestHeader(
        'X-CSRF-TOKEN',
        document.querySelector('meta[name="csrf-token"]')?.content ?? ''
    );

    request.upload.addEventListener('progress', (event) => {
        if (event.lengthComputable) entry.progress = Math.round((event.loaded / event.total) * 100);
    });

    request.addEventListener('load', async () => {
        if (request.status === 201) {
            entry.done = true;
            entry.progress = 100;

            const item = JSON.parse(request.responseText).item;

            items.value = [item, ...items.value];
            total.value += 1;

            // Selected the moment it arrives, so the common case — upload,
            // insert — is two clicks and not a hunt through the grid.
            if (single.value) chosen.value = [item.id];
            else chosen.value.push(item.id);

            active.value = item.id;

            await nextTick();
        } else {
            let message = t('admin.media_upload_failed');

            try {
                const body = JSON.parse(request.responseText);
                message = body.errors?.file?.[0] ?? body.message ?? message;
            } catch {
                // A non-JSON body means the server fell over; the generic
                // message is the honest one.
            }

            entry.error = message;
        }
    });

    request.addEventListener('error', () => {
        entry.error = t('admin.media_upload_failed');
    });

    request.send(body);
}

function formatSize(bytes) {
    if (!bytes) return '—';
    const mb = bytes / (1024 * 1024);

    return mb >= 1 ? `${mb.toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;
}

function formatDate(iso) {
    if (!iso) return '—';

    return new Date(iso).toLocaleDateString(locale.value === 'ar' ? 'ar' : 'en', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <!--
        `dir` is inherited from the page, and every offset below is logical
        (§22.6), so the whole dialog mirrors with the interface language
        without a single rule of its own.
    -->
    <div v-if="open" class="picker" role="dialog" aria-modal="true" @keydown.esc="emit('close')">
        <div class="picker__scrim" @click="emit('close')" />

        <div class="picker__panel">
            <header class="picker__head">
                <div class="picker__tabs" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        class="picker__tab"
                        :class="{ 'is-active': tab === 'library' }"
                        :aria-selected="tab === 'library'"
                        @click="tab = 'library'"
                    >
                        {{ t('admin.media_tab_library') }}
                    </button>
                    <button
                        type="button"
                        role="tab"
                        class="picker__tab"
                        :class="{ 'is-active': tab === 'upload' }"
                        :aria-selected="tab === 'upload'"
                        @click="tab = 'upload'"
                    >
                        {{ t('admin.media_tab_upload') }}
                    </button>
                </div>

                <button type="button" class="btn btn--ghost" @click="emit('close')">
                    {{ t('admin.media_close') }}
                </button>
            </header>

            <!-- ---- Library ------------------------------------------- -->
            <div v-show="tab === 'library'" class="picker__body">
                <div class="picker__main">
                    <input
                        v-model="search"
                        type="search"
                        class="picker__search"
                        :placeholder="t('admin.media_search')"
                    />

                    <p v-if="!loading && items.length === 0" class="picker__empty">
                        {{ search ? t('admin.media_no_results') : t('admin.media_empty') }}
                    </p>

                    <ul class="grid">
                        <li v-for="item in items" :key="item.id">
                            <button
                                type="button"
                                class="grid__cell"
                                :class="{ 'is-chosen': isChosen(item), 'is-active': active === item.id }"
                                :aria-pressed="isChosen(item)"
                                @click="toggle(item)"
                            >
                                <img :src="item.thumb" :alt="item.translations[locale]?.alt_text ?? ''" loading="lazy" />
                                <span v-if="isChosen(item)" class="grid__tick" aria-hidden="true">✓</span>
                            </button>
                        </li>
                    </ul>

                    <button
                        v-if="page < lastPage"
                        type="button"
                        class="btn btn--ghost picker__more"
                        :disabled="loading"
                        @click="load(page + 1)"
                    >
                        {{ t('admin.media_load_more') }}
                    </button>
                </div>

                <!-- The details rail: what this image is, and its alt text. -->
                <aside v-if="activeItem" class="picker__side">
                    <img :src="activeItem.thumb" :alt="''" class="picker__preview" />

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

                        <dt>{{ t('admin.media_used_in') }}</dt>
                        <dd>
                            {{
                                activeItem.usageCount
                                    ? t('admin.media_usage_count', { count: activeItem.usageCount })
                                    : t('admin.media_unused')
                            }}
                        </dd>
                    </dl>

                    <div v-for="code in LOCALES" :key="code" class="picker__alt">
                        <label :for="`alt-${code}`">{{ t('admin.alt_text') }} — {{ code.toUpperCase() }}</label>
                        <input
                            :id="`alt-${code}`"
                            v-model="activeItem.translations[code].alt_text"
                            type="text"
                            dir="auto"
                            @change="saveAlt"
                        />
                    </div>

                    <p class="picker__hint">{{ t('admin.alt_text_hint') }}</p>
                    <p v-if="savingAlt" class="picker__hint">{{ t('admin.saving') }}</p>
                </aside>
            </div>

            <!-- ---- Upload -------------------------------------------- -->
            <div v-show="tab === 'upload'" class="picker__body">
                <div class="picker__main">
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

                        <input
                            ref="fileInput"
                            type="file"
                            multiple
                            :accept="ACCEPT"
                            class="drop__input"
                            @change="onPick"
                        />
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
                </div>
            </div>

            <footer class="picker__foot">
                <p class="picker__count">{{ t('admin.media_selected', { count: chosen.length }) }}</p>

                <button type="button" class="btn btn--primary" :disabled="!canInsert" @click="insert">
                    {{ t('admin.media_insert_count', { count: chosen.length }) }}
                </button>
            </footer>
        </div>
    </div>
</template>

<style scoped>
.picker {
    position: fixed;
    inset: 0;
    z-index: 60;
    display: grid;
    place-items: center;
    padding: var(--s-4);
}

.picker__scrim {
    position: absolute;
    inset: 0;
    background: rgba(0, 37, 70, 0.55);
}

.picker__panel {
    position: relative;
    display: flex;
    flex-direction: column;
    inline-size: min(1100px, 100%);
    block-size: min(760px, 90vh);
    background: var(--paper);
    border-radius: var(--r-md);
    overflow: hidden;
}

.picker__head,
.picker__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--s-3);
    padding: var(--s-3) var(--s-4);
    border-block-end: 1px solid var(--hairline);
}

.picker__foot {
    border-block-end: 0;
    border-block-start: 1px solid var(--hairline);
}

.picker__tabs {
    display: flex;
    gap: var(--s-2);
}

.picker__tab {
    padding: var(--s-2) var(--s-3);
    border: 0;
    border-block-end: 2px solid transparent;
    background: none;
    color: var(--muted);
    font: inherit;
    cursor: pointer;
}

.picker__tab.is-active {
    color: var(--ink);
    border-block-end-color: var(--action-600);
}

.picker__body {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--s-4);
    flex: 1;
    min-block-size: 0;
    padding: var(--s-4);
    overflow: hidden;
}

@media (min-width: 900px) {
    .picker__body {
        grid-template-columns: 1fr 300px;
    }
}

.picker__main {
    min-block-size: 0;
    overflow-y: auto;
}

.picker__side {
    min-block-size: 0;
    overflow-y: auto;
    padding-inline-start: var(--s-4);
    border-inline-start: 1px solid var(--hairline);
}

.picker__search {
    inline-size: 100%;
    margin-block-end: var(--s-3);
    padding: var(--s-2) var(--s-3);
    border: 1px solid var(--hairline);
    border-radius: var(--r-sm);
    font: inherit;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: var(--s-2);
    margin: 0;
    padding: 0;
    list-style: none;
}

.grid__cell {
    position: relative;
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

.grid__cell img {
    inline-size: 100%;
    block-size: 100%;
    /* `contain`, not `cover`: a logo cropped to a square in the picker is a
       logo an editor will not recognise as the one they are looking for. */
    object-fit: contain;
}

.grid__cell.is-active {
    border-color: var(--lavender-700);
}

.grid__cell.is-chosen {
    border-color: var(--action-600);
}

.grid__tick {
    position: absolute;
    inset-block-start: var(--s-1);
    inset-inline-end: var(--s-1);
    display: grid;
    place-items: center;
    inline-size: 22px;
    block-size: 22px;
    border-radius: var(--r-pill);
    background: var(--action-600);
    color: #fff;
    font-size: var(--fs-xs);
}

.picker__preview {
    inline-size: 100%;
    block-size: 160px;
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

.picker__alt {
    display: grid;
    gap: var(--s-1);
    margin-block-end: var(--s-2);
}

.picker__alt label {
    font-size: var(--fs-xs);
    color: var(--muted);
}

.picker__alt input {
    inline-size: 100%;
    padding: var(--s-2);
    border: 1px solid var(--hairline);
    border-radius: var(--r-sm);
    font: inherit;
}

.picker__hint,
.picker__empty,
.picker__count {
    color: var(--muted);
    font-size: var(--fs-sm);
}

.picker__more {
    margin-block-start: var(--s-3);
}

.drop {
    display: grid;
    justify-items: center;
    gap: var(--s-3);
    padding: var(--s-7) var(--s-4);
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
