<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import NavIcon from '@/Components/admin/NavIcon.vue';
import { confirmDialog } from '@/admin/confirm';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * The account workspace: a header, a standing column, and whatever screen the
 * column currently points at.
 *
 * Every screen the column links to renders inside this frame. That is the
 * whole point — the column used to disappear the moment you followed one of
 * its links, so reaching the next screen meant going back to the profile
 * first. Now the frame stays and only the pane changes, which is what makes
 * "settings" feel like one place instead of nine.
 *
 * Its data comes from the shared `workspace` prop rather than from each
 * controller, so a screen joins the frame by wrapping itself in this
 * component and nothing else.
 */
defineProps({
    /** Shown on the profile tabs; the settings screens keep the header but
     *  have no reason to offer photo controls. */
    editable: { type: Boolean, default: false },
});

const { t } = useTranslation();
const { date, dateTime } = useFormat();
const page = usePage();

const workspace = computed(() => page.props.workspace ?? {});
const profile = computed(() => workspace.value.profile ?? {});
const busy = ref(null);

const initials = computed(() =>
    (profile.value.name ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
);

const roleLabel = computed(() =>
    profile.value.role ? t(`admin.role_${profile.value.role.replace(/-/g, '_')}`) : ''
);

const TAB_ICONS = {
    details: 'profile',
    appearance: 'brand',
    password: 'advanced',
    security: 'robots',
};

/**
 * Highlights the entry whose href matches the page being shown.
 *
 * `/admin/profile` renders the details tab, so it has to light up
 * `/admin/profile/details` — otherwise the first thing an operator sees on
 * arriving is a column with nothing selected.
 */
function isCurrent(href) {
    const path = page.url.split('?')[0];

    if (path === '/admin/profile') {
        return href === '/admin/profile/details';
    }

    return path === href;
}

function upload(collection, event) {
    const file = event.target.files?.[0];

    if (!file) return;

    busy.value = collection;

    router.post(
        '/admin/profile/image',
        { collection, file },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                busy.value = null;
                event.target.value = '';
            },
        }
    );
}

async function removeImage(collection) {
    if (!(await confirmDialog({ message: t('admin.confirm_delete') }))) return;

    router.delete(`/admin/profile/image/${collection}`, { preserveScroll: true });
}
</script>

<template>
    <!-- ---- Header: cover, portrait, identity ------------------------- -->
    <section class="hero">
        <div class="hero__cover" :style="profile.cover ? { backgroundImage: `url(${profile.cover})` } : null">
            <template v-if="editable">
                <label class="hero__coverBtn">
                    <NavIcon name="brand" />
                    <span>{{ busy === 'cover' ? t('admin.saving') : t('admin.cover_add') }}</span>
                    <input type="file" accept="image/*" @change="(e) => upload('cover', e)" />
                </label>

                <button
                    v-if="profile.cover"
                    class="hero__coverBtn hero__coverBtn--del"
                    type="button"
                    @click="removeImage('cover')"
                                    :title="t('admin.delete')"
                                    :aria-label="t('admin.delete')"><NavIcon name="trash" :size="18" :muted="false" /></button>
            </template>
        </div>

        <div class="hero__bar">
            <!-- Only the portrait is lifted over the cover. Lifting the whole
                 identity block took the name and email with it, so the email
                 ended up behind the portrait. -->
            <div class="hero__portrait">
                <img v-if="profile.avatar" class="hero__img" :src="profile.avatar" alt="" />
                <span v-else class="hero__initials" aria-hidden="true">{{ initials }}</span>

                <label v-if="editable" class="hero__camera" :title="t('admin.avatar_add')">
                    <NavIcon name="stories" />
                    <span class="visually-hidden">{{ t('admin.avatar_add') }}</span>
                    <input type="file" accept="image/*" @change="(e) => upload('avatar', e)" />
                </label>
            </div>

            <div class="hero__who">
                <h2 class="hero__name">{{ profile.name }}</h2>
                <p class="hero__email latin">{{ profile.email }}</p>

                <div class="badges">
                    <span class="badge badge--role">{{ roleLabel }}</span>
                    <span class="badge" :class="profile.twoFactor ? 'badge--ok' : 'badge--warn'">
                        {{ profile.twoFactor ? t('admin.twofa_on') : t('admin.twofa_off') }}
                    </span>
                </div>
            </div>

            <dl class="stats">
                <div class="stat">
                    <dt>{{ t('admin.last_login') }}</dt>
                    <dd>{{ profile.lastLoginAt ? dateTime(profile.lastLoginAt) : '—' }}</dd>
                </div>
                <div class="stat">
                    <dt>{{ t('admin.member_since') }}</dt>
                    <dd>{{ profile.createdAt ? date(profile.createdAt) : '—' }}</dd>
                </div>
            </dl>
        </div>
    </section>

    <!-- ---- Column beside the pane ------------------------------------ -->
    <div class="split">
        <nav class="tabs" :aria-label="t('admin.profile')">
            <p class="tabs__head">{{ t('admin.profile') }}</p>

            <Link
                v-for="item in workspace.tabs ?? []"
                :key="item.href"
                class="tab"
                :class="{ 'is-current': isCurrent(item.href) }"
                :href="item.href"
                :aria-current="isCurrent(item.href) ? 'page' : undefined"
            >
                <NavIcon :name="TAB_ICONS[item.key] ?? 'dot'" />
                <span class="tab__text">
                    <span class="tab__title">{{ item.label }}</span>
                    <span class="tab__note">{{ item.hint }}</span>
                </span>
            </Link>

            <template v-if="(workspace.shortcuts ?? []).length">
                <p class="tabs__head tabs__head--spaced">{{ t('admin.settings') }}</p>

                <Link
                    v-for="item in workspace.shortcuts"
                    :key="item.href"
                    class="tab"
                    :class="{ 'is-current': isCurrent(item.href) }"
                    :href="item.href"
                    :aria-current="isCurrent(item.href) ? 'page' : undefined"
                >
                    <NavIcon :name="item.icon" />
                    <span class="tab__text">
                        <span class="tab__title">{{ item.label }}</span>
                        <span class="tab__note">{{ item.hint }}</span>
                    </span>
                </Link>
            </template>
        </nav>

        <div class="pane">
            <slot />
        </div>
    </div>
</template>

<style scoped>
/* ---- Header ---- */
.hero {
    border-radius: var(--r-md);
    background: var(--paper);
    box-shadow: var(--shadow-card);
    overflow: hidden;
    margin-block-end: var(--s-5);
}

.hero__cover {
    position: relative;
    block-size: 160px;
    background-color: var(--navy-900);
    background-size: cover;
    background-position: center;
}

.hero__coverBtn {
    position: absolute;
    inset-block-start: var(--s-3);
    inset-inline-start: var(--s-3);
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    min-block-size: 36px;
    padding-inline: var(--s-3);
    border-radius: var(--r-sm);
    background: rgb(255 255 255 / 0.92);
    color: var(--navy-900);
    font-size: var(--fs-xs);
    font-weight: 700;
    cursor: pointer;
}

.hero__coverBtn--del {
    inset-inline-start: auto;
    inset-inline-end: var(--s-3);
    color: var(--action-600);
}

.hero__coverBtn input {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    opacity: 0;
}

.hero__bar {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: var(--s-4) var(--s-6);
    padding: 0 var(--s-5) var(--s-5);
}

.hero__portrait {
    position: relative;
    inline-size: 96px;
    block-size: 96px;
    flex: 0 0 auto;
    margin-block-start: -44px;
    border-radius: var(--r-md);
    background: var(--navy-900);
    border: 3px solid var(--paper);
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero__img {
    inline-size: 100%;
    block-size: 100%;
    border-radius: calc(var(--r-md) - 2px);
    object-fit: cover;
}

.hero__initials {
    color: #fff;
    font-size: var(--fs-h3);
    font-weight: 700;
}

.hero__camera {
    position: absolute;
    inset-block-end: -6px;
    inset-inline-end: -6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    inline-size: 32px;
    block-size: 32px;
    border-radius: var(--r-sm);
    background: var(--action-600);
    color: #fff;
    cursor: pointer;
    box-shadow: 0 0 0 3px var(--paper);
}

.hero__camera input {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    opacity: 0;
}

.hero__who {
    flex: 1 1 240px;
    min-inline-size: 0;
    padding-block-end: var(--s-1);
}

.hero__name {
    font-size: var(--fs-h3);
    line-height: 1.2;
}


.badges {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-2);
    margin-block-start: var(--s-2);
}

.badge {
    padding: 2px var(--s-3);
    border-radius: var(--r-sm);
    font-size: var(--fs-xs);
    font-weight: 700;
}

.badge--role {
    background: var(--navy-100);
    color: var(--navy-900);
}

.badge--ok {
    background: #E6F4EA;
    color: #16643B;
}

.badge--warn {
    background: var(--gold-100);
    color: #7A4A00;
}

.stats {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-6);
    margin: 0;
    padding-block-end: var(--s-1);
}

.stat dt {
    font-size: var(--fs-xs);
    color: var(--muted);
}

.stat dd {
    margin: 0;
    font-size: var(--fs-sm);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

/* ---- Column ---- */
.split {
    display: grid;
    gap: var(--s-5);
    grid-template-columns: 1fr;
    align-items: start;
}

.tabs {
    display: grid;
    gap: var(--s-1);
    align-content: start;
    padding: var(--s-2);
    border-radius: var(--r-md);
    background: var(--paper);
    box-shadow: var(--shadow-card);
    /* Follows the reader down a long settings screen, so the next destination
       is never a scroll away. */
    position: sticky;
    inset-block-start: var(--s-4);
}

.tabs__head {
    padding: var(--s-2) var(--s-3) var(--s-1);
    font-size: var(--fs-xs);
    font-weight: 700;
    letter-spacing: 0.04em;
    color: var(--muted);
}

.tabs__head--spaced {
    margin-block-start: var(--s-3);
    padding-block-start: var(--s-3);
    border-block-start: 1px solid var(--hairline);
}

.tab {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    padding: var(--s-3);
    min-block-size: 56px;
    border-radius: var(--r-sm);
    color: var(--ink);
    border-inline-start: 3px solid transparent;
    transition: background-color var(--dur-micro) var(--ease);
}

.tab:hover {
    background: var(--paper-alt);
}

.tab.is-current {
    background: var(--navy-900);
    color: #fff;
    border-inline-start-color: var(--gold-400);
}

.tab.is-current :deep(.ico) {
    opacity: 1;
    color: var(--gold-400);
}

.tab__text {
    display: grid;
    line-height: 1.3;
    min-inline-size: 0;
}

.tab__title {
    font-size: var(--fs-sm);
    font-weight: 700;
}

.tab__note {
    font-size: var(--fs-xs);
    color: var(--muted);
}

.tab.is-current .tab__note {
    color: rgb(255 255 255 / 0.7);
}

.pane {
    min-inline-size: 0;
}

@media (min-width: 1024px) {
    .split {
        grid-template-columns: 288px minmax(0, 1fr);
    }
}
</style>
