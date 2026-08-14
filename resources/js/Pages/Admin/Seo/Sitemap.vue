<script setup>
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Workspace from '@/Components/admin/Workspace.vue';
import Panel from '@/Components/admin/Panel.vue';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * What the site currently offers a search engine.
 *
 * A screen of reassurance, not of settings — there is nothing to configure
 * here, because what the sitemap lists follows from what is published. The two
 * questions it answers are "is my new page in there" and "what do I hand to
 * Google", and the answer to the second is one button.
 */
const props = defineProps({
    indexUrl: { type: String, required: true },
    robotsUrl: { type: String, required: true },
    lastModified: { type: String, default: null },
    locales: { type: Array, default: () => [] },
    indexingOpen: { type: Boolean, default: false },
});

const { t } = useTranslation();
const { number, date } = useFormat();

const open = ref(null);
const copied = ref(false);

/**
 * Copies the index URL, which is the one Search Console asks for.
 *
 * `navigator.clipboard` is unavailable over plain HTTP, which is exactly how
 * this panel is reached in development — so the failure path selects the text
 * instead of silently doing nothing.
 */
async function copyIndex(event) {
    try {
        await navigator.clipboard.writeText(props.indexUrl);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        const field = event.target.closest('.handover')?.querySelector('.handover__url');

        if (field) window.getSelection()?.selectAllChildren(field);
    }
}
</script>

<template>
    <AdminLayout :title="t('admin.sitemap')">
        <Workspace>
            <Panel :title="t('admin.sitemap')">
                <p class="intro">{{ t('admin.sitemap.intro') }}</p>

                <!-- Said before anything else: on a staging machine every
                     number below is real and none of it will be crawled. -->
                <p v-if="!indexingOpen" class="notice">{{ t('admin.sitemap.closed') }}</p>

                <div class="handover">
                    <span class="handover__label">{{ t('admin.sitemap.handover') }}</span>
                    <code class="handover__url latin">{{ indexUrl }}</code>

                    <div class="handover__actions">
                        <button class="btn btn--cta" type="button" @click="copyIndex">
                            {{ copied ? t('admin.sitemap.copied') : t('admin.sitemap.copy') }}
                        </button>

                        <a class="link-weave" :href="indexUrl" target="_blank" rel="noopener">
                            {{ t('admin.sitemap.open') }}
                        </a>

                        <a class="link-weave" :href="robotsUrl" target="_blank" rel="noopener">
                            {{ t('admin.sitemap.robots') }}
                        </a>
                    </div>

                    <p class="handover__hint">{{ t('admin.sitemap.handover_hint') }}</p>
                </div>

                <p v-if="lastModified" class="stamp">
                    {{ t('admin.sitemap.last_change') }}
                    <strong>{{ date(lastModified) }}</strong>
                </p>

                <ul class="files">
                    <li v-for="file in locales" :key="file.locale" class="file">
                        <div class="file__head">
                            <span class="file__name latin">{{ file.url }}</span>

                            <span class="file__count">
                                {{ t('admin.sitemap.count', { count: number(file.count) }) }}
                            </span>

                            <button
                                class="file__btn"
                                type="button"
                                @click="open = open === file.locale ? null : file.locale"
                            >
                                {{ open === file.locale ? t('admin.sitemap.hide') : t('admin.sitemap.show') }}
                            </button>

                            <a class="link-weave" :href="file.url" target="_blank" rel="noopener">
                                {{ t('admin.sitemap.open') }}
                            </a>
                        </div>

                        <ul v-if="open === file.locale" class="urls">
                            <li v-for="url in file.urls" :key="url.loc" class="url">
                                <a class="url__loc latin" :href="url.loc" target="_blank" rel="noopener">
                                    {{ url.loc }}
                                </a>
                                <span v-if="url.lastmod" class="url__date">{{ date(url.lastmod) }}</span>
                            </li>
                        </ul>
                    </li>
                </ul>

                <p class="footnote">{{ t('admin.sitemap.automatic') }}</p>
            </Panel>
        </Workspace>
    </AdminLayout>
</template>

<style scoped>
.intro {
    margin-block-end: var(--s-5);
    color: var(--ink-600);
    line-height: var(--lh-body);
}

.notice {
    margin-block-end: var(--s-5);
    padding: var(--s-4);
    background: #FBF0DA;
    color: #7A5B12;
    line-height: var(--lh-body);
}

.handover {
    display: flex;
    flex-direction: column;
    gap: var(--s-3);
    padding: var(--s-5);
    background: var(--paper);
    box-shadow: inset 0 0 0 1px var(--hairline);
    border-inline-start: 3px solid var(--navy-900);
}

.handover__label {
    font-weight: 700;
    color: var(--navy-900);
}

/* The URL is Latin inside an RTL panel: forcing the direction keeps the
   scheme at the start of the line where it belongs. */
.handover__url {
    direction: ltr;
    text-align: start;
    padding: var(--s-2) var(--s-3);
    background: var(--paper-alt, #fff);
    box-shadow: inset 0 0 0 1px var(--hairline);
    overflow-wrap: anywhere;
}

.handover__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--s-3);
}

.handover__hint,
.footnote {
    font-size: var(--fs-caption);
    color: var(--ink-600);
    line-height: var(--lh-body);
}

.stamp {
    margin-block: var(--s-5) var(--s-3);
    color: var(--ink-600);
}

.files {
    display: grid;
    gap: var(--s-3);
    list-style: none;
    margin: 0 0 var(--s-5);
    padding: 0;
}

.file {
    padding: var(--s-4);
    background: var(--paper);
    box-shadow: inset 0 0 0 1px var(--hairline);
}

.file__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--s-3);
}

.file__name {
    flex: 1 1 16rem;
    min-inline-size: 0;
    direction: ltr;
    text-align: start;
    font-weight: 600;
    color: var(--navy-900);
    overflow-wrap: anywhere;
}

.file__count {
    font-variant-numeric: tabular-nums;
    color: var(--ink-600);
}

.file__btn {
    min-block-size: 44px;
    padding-inline: var(--s-3);
    border: 0;
    background: none;
    color: var(--action-600);
    font: inherit;
    cursor: pointer;
    text-decoration: underline;
}

.urls {
    display: grid;
    gap: var(--s-2);
    margin-block-start: var(--s-4);
    padding-block-start: var(--s-4);
    border-block-start: 1px solid var(--hairline);
    list-style: none;
}

.url {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: var(--s-3);
}

.url__loc {
    flex: 1 1 18rem;
    min-inline-size: 0;
    direction: ltr;
    text-align: start;
    overflow-wrap: anywhere;
}

.url__date {
    font-size: var(--fs-caption);
    color: var(--ink-600);
}
</style>
