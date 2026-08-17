<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Logo from '@/Components/ui/Logo.vue';
import Toast from '@/Components/ui/Toast.vue';
import ConfirmDialog from '@/Components/admin/ConfirmDialog.vue';
import { notice } from '@/admin/notify';
import NavIcon from '@/Components/admin/NavIcon.vue';
import { useTranslation } from '@/Composables/useTranslation';
import { NAV_GROUPS } from '@/admin/navigation';

/**
 * Layouts/AdminLayout (§9) — a custom panel, Arabic RTL by default.
 *
 * The navigation shows only what the signed-in user may reach, but that is a
 * convenience: every route is enforced by a policy on the server (§9.2), so
 * hiding a link is never the protection.
 */
defineProps({
    title: { type: String, default: null },
});

const { t } = useTranslation();
const page = usePage();
const menuOpen = ref(false);

const user = computed(() => page.props.auth?.user ?? null);
const can = computed(() => page.props.auth?.can ?? {});

/*
 * Flash messages, shown as the site's own toast.
 *
 * Watched by reference rather than by value: Laravel sends a fresh flash bag
 * with every response, so saving the same form twice produces two distinct
 * objects carrying the identical string. A value watcher would see no change
 * and stay silent on the second save — which is exactly the moment the editor
 * most needs telling that something happened.
 *
 * `toastKey` remounts the component so a second message interrupts the first
 * rather than queueing behind a countdown that is already half spent.
 */
const toastOpen = ref(false);
const toastType = ref('success');
const toastText = ref(null);
const toastKey = ref(0);

watch(
    () => page.props.flash,
    (flash) => {
        const text = flash?.success ?? flash?.error ?? null;

        if (! text) {
            return;
        }

        toastType.value = flash.success ? 'success' : 'error';
        toastText.value = text;
        toastKey.value += 1;
        toastOpen.value = true;
    },
    { immediate: true }
);

/*
 * The same toast, raised by a screen rather than by a redirect.
 *
 * A rejected save carries validation errors and no flash, so the flash watcher
 * above stays silent on the one outcome the editor most needs announced. See
 * admin/notify.js.
 */
watch(
    () => notice.bump,
    (bump) => {
        if (bump === 0) {
            return;
        }

        toastType.value = notice.type;
        toastText.value = notice.text;
        toastKey.value += 1;
        toastOpen.value = true;
    }
);

/*
 * The sidebar's contents come from one module — resources/js/admin/navigation.js.
 * This layout resolves the labels and filters by permission; it no longer
 * carries the map of the panel itself.
 */
const visibleGroups = computed(() =>
    NAV_GROUPS.map((group) => ({
        label: t(group.label),
        items: group.items
            .filter((item) => !item.can || can.value[item.can])
            .map((item) => ({ ...item, label: t(item.label) })),
    })).filter((group) => group.items.length)
);

function isCurrent(href) {
    const path = page.url.split('?')[0];

    if (href === '/admin') return path === '/admin';

    // Exact match for the settings screens: startsWith would light up
    // /admin/settings/site while standing on /admin/settings/store, because
    // they share a prefix only by accident of URL design.
    if (href.startsWith('/admin/settings/')) return path === href;

    return path.startsWith(href);
}

/**
 * The signed-in person's role, in words.
 *
 * Shown beside the name because the role decides what the panel offers: four
 * roles see four different sidebars, so a panel that does not say which one is
 * looking makes a missing menu read as a broken menu.
 */
const roleLabel = computed(() => {
    const role = user.value?.roles?.[0];

    return role ? t(`admin.role_${role.replace(/-/g, '_')}`) : '';
});

/** Two letters for the avatar; no photo upload, no gravatar call-out. */
const initials = computed(() =>
    (user.value?.name ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
);

// Any navigation closes the mobile drawer, so it never lingers over the
// screen the operator just opened.
router.on('navigate', () => {
    menuOpen.value = false;
});

function logout() {
    router.post('/admin/logout');
}
</script>

<template>
    <Head :title="title ? `${title} — ${t('admin.panel')}` : t('admin.panel')" />

    <div class="shell">
        <aside class="side" :class="{ 'side--open': menuOpen }">
            <div class="side__brand">
                <Logo lockup="horizontal" tone="white" />
            </div>

            <nav class="side__nav" :aria-label="t('admin.panel')">
                <div v-for="group in visibleGroups" :key="group.label" class="side__group">
                    <p class="side__group-label">
                        <span class="side__group-rule" aria-hidden="true" />
                        {{ group.label }}
                    </p>

                    <Link
                        v-for="item in group.items"
                        :key="item.href"
                        :href="item.href"
                        class="side__link"
                        :class="{ 'is-current': isCurrent(item.href), 'side__link--sub': item.sub }"
                        :aria-current="isCurrent(item.href) ? 'page' : undefined"
                        @click="menuOpen = false"
                    >
                        <NavIcon :name="item.icon ?? 'dot'" />
                        <span class="side__label">{{ item.label }}</span>
                        <!-- Screens whose place in the panel is agreed but
                             whose function is not built yet. Marked rather
                             than hidden, so the shape of the finished panel
                             is visible and nobody looks for a missing item. -->
                        <span v-if="item.soon" class="side__soon">{{ t('admin.soon') }}</span>
                    </Link>
                </div>
            </nav>

            <!--
                "View the site" is a primary action, not a footnote. It is the
                thing an editor reaches for after every change — to see whether
                the change worked — so it gets a button, an icon that says it
                leaves the panel, and a spoken note that it opens elsewhere.
            -->
            <!--
                "View the site" used to be repeated here at the foot of the
                sidebar. It lives in the top bar, which is on screen at every
                scroll position and on every admin screen, so the second copy
                was one more thing to read past rather than a second way out.
            -->
        </aside>

        <div class="main">
            <header class="topbar">
                <button
                    class="topbar__toggle"
                    type="button"
                    :aria-expanded="menuOpen"
                    :aria-label="menuOpen ? t('common.close_menu') : t('common.open_menu')"
                    @click="menuOpen = !menuOpen"
                >
                    <span aria-hidden="true">☰</span>
                </button>

                <h1 v-if="title" class="topbar__title">{{ title }}</h1>

                <!--
                    The account chip is a link, not a dropdown.

                    A menu that unfolds from the bar put five destinations one
                    hover away from vanishing, and every one of them had to be
                    reopened after each visit. They now live in a standing
                    column inside the profile page, so this is simply the way
                    in: who you are, and one click to everything about you.
                -->
                <Link class="account" href="/admin/profile">
                    <img v-if="user?.avatar" class="account__avatar account__avatar--img" :src="user.avatar" alt="" />
                    <span v-else class="account__avatar" aria-hidden="true">{{ initials }}</span>

                    <span class="account__who">
                        <span class="account__name">{{ user?.name }}</span>
                        <span class="account__role">{{ roleLabel }}</span>
                    </span>
                </Link>

                <a class="topbar__visit" href="/" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                         stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3" />
                    </svg>
                    <span class="topbar__visit-text">{{ t('admin.view_site') }}</span>
                    <span class="visually-hidden">{{ t('common.external_link') }}</span>
                </a>

                <button class="topbar__out" type="button" @click="logout">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                         stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" />
                    </svg>
                    <span class="topbar__out-text">{{ t('admin.logout') }}</span>
                </button>
            </header>

            <main class="content">
                <slot />
            </main>
        </div>

        <!--
            The same confirmation the visitor gets when an enquiry is sent.

            It replaces a static paragraph that was pushed in above the content:
            that banner moved the whole page down when it appeared, stayed
            until the next navigation, and was easy to miss entirely if the
            editor was looking at the bottom of a long form when they saved.

            A toast is the right shape for "that worked" — it arrives where the
            eye is not, announces itself to a screen reader, and leaves on its
            own. Errors carry `duration: 0` so they wait to be dismissed.
        -->
        <Toast
            :key="toastKey"
            :open="toastOpen"
            :type="toastType"
            :title="toastText"
            :duration="toastType === 'error' ? 0 : 4000"
            @close="toastOpen = false"
        />

        <!--
            Mounted once for the whole panel. Screens ask through
            `admin/confirm.js` and await an answer, so a handler can pose a
            question without also having to render one.
        -->
        <ConfirmDialog />
    </div>
</template>

<style scoped>
.shell {
    display: flex;
    min-block-size: 100vh;
    background: var(--paper-alt);
}

.side {
    position: fixed;
    inset-block: 0;
    inset-inline-start: 0;
    z-index: 60;
    inline-size: 260px;
    display: flex;
    flex-direction: column;
    background: var(--navy-900);
    color: var(--text-inverse);
    overflow-y: auto;
    transform: translateX(0);
    transition: transform var(--dur-el) var(--ease);
}

/*
 * Off-canvas on small screens.
 *
 * `inset-inline-start: 0` pins the panel to the LEFT edge in English and the
 * RIGHT edge in Arabic. `translateX` has no such awareness — it is physical —
 * so the direction that hides it has to be stated for each, and the two are
 * opposite: leftwards in LTR, rightwards in RTL.
 *
 * Both signs used to be inverted. On a phone the Arabic panel slid LEFT from
 * the right edge, which walked it across the viewport and parked 117px of navy
 * on top of the content — measured, on every admin screen, at 390px wide.
 */
@media (max-width: 1023px) {
    .side {
        transform: translateX(-105%);
    }

    html[dir='rtl'] .side {
        transform: translateX(105%);
    }

    /* Matched on both selectors: `html[dir='rtl'] .side` outranks a lone
       `.side--open`, so the opened panel would otherwise stay hidden in RTL. */
    .side--open,
    html[dir='rtl'] .side--open {
        transform: translateX(0);
    }
}

.side__brand {
    padding: var(--s-5);
    border-block-end: 1px solid var(--hairline-inverse);
}

.side__nav {
    flex: 1;
    padding: var(--s-4) var(--s-3);
}

.side__group + .side__group {
    margin-block-start: var(--s-5);
}

.side__group-label {
    padding-inline: var(--s-3);
    margin-block-end: var(--s-2);
    font-size: var(--fs-xs);
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
}

/* A child of the entry above it: indented, and marked by a short rule on the
   reading edge so the relationship survives on a narrow sidebar. */
.side__link--sub {
    margin-inline-start: var(--s-6);
    padding-inline-start: var(--s-4);
    border-inline-start: 1px solid rgb(255 255 255 / 0.18);
    font-size: var(--fs-sm);
}

.side__soon {
    margin-inline-start: auto;
    flex-shrink: 0;
    padding: 2px 6px;
    border-radius: var(--r-sm);
    background: rgb(255 255 255 / 0.12);
    color: var(--gold-400);
    font-size: 0.625rem;
    font-weight: 700;
    white-space: nowrap;
}

/*
 * `.side__link`, `.side__link:hover` and `.side__link.is-current` were
 * declared here as well as further down this file. Every property in the
 * earlier copy — display, padding, min-block-size, colour, background — was
 * overridden by the later one, so the earlier block styled nothing at all
 * while appearing to. It was also the copy that carried `min-block-size: 44px`,
 * which is how the sidebar came to miss the §10.8 target size by 4px with a
 * rule for it sitting right there in the file. The surviving block is the one
 * below, next to the icon and rail styles it belongs with.
 */

.main {
    flex: 1;
    min-inline-size: 0;
    margin-inline-start: 0;
}

@media (min-width: 1024px) {
    .main {
        margin-inline-start: 260px;
    }
}

.topbar {
    display: flex;
    align-items: center;
    gap: var(--s-4);
    padding: var(--s-4) var(--s-5);
    background: var(--paper);
    border-block-end: 1px solid var(--hairline);
    position: sticky;
    inset-block-start: 0;
    z-index: 40;
}

.topbar__toggle {
    inline-size: 44px;
    block-size: 44px;
    font-size: 20px;
}

@media (min-width: 1024px) {
    .topbar__toggle {
        display: none;
    }
}

.topbar__title {
    font-size: var(--fs-h3);
    margin-inline-end: auto;
    /*
     * A flex child defaults to `min-width: auto`, which means it refuses to
     * shrink below its own longest word. On a 360px screen that is enough to
     * push "view site" and "sign out" off the edge and make the whole page
     * scroll sideways — measured on /admin/content/training-programs, whose
     * title is the longest in the panel.
     *
     * The title is also the one thing on this bar the operator can afford to
     * lose the tail of: it names the screen they just tapped to reach, and
     * the sidebar still shows it in full. The controls cannot be lost.
     */
    min-inline-size: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.topbar__user {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    margin-inline-start: auto;
}

.topbar__name {
    font-size: var(--fs-sm);
    color: var(--text-muted);
}

.topbar__logout {
    font-size: var(--fs-sm);
    font-weight: 600;
    color: var(--action-600);
    min-block-size: 44px;
    padding-inline: var(--s-2);
}

.content {
    padding: var(--s-5);
}

@media (min-width: 1024px) {
    .content {
        padding: var(--s-7);
    }
}

/* ---- Account menu ---- */
.account {
    display: inline-flex;
    align-items: center;
    gap: var(--s-3);
    margin-inline-start: auto;
    min-block-size: 44px;
    padding-inline: var(--s-3);
    border-radius: var(--r-sm);
    color: var(--ink);
    transition: background-color var(--dur-micro) var(--ease);
}

.account:hover {
    background: var(--navy-100);
}

.account__avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    inline-size: 32px;
    block-size: 32px;
    border-radius: var(--r-sm);
    background: var(--navy-900);
    color: #fff;
    font-size: var(--fs-xs);
    font-weight: 700;
}

.account__name {
    font-size: var(--fs-sm);
    font-weight: 600;
}


/* ---- Sidebar ------------------------------------------------------- */
.side__group + .side__group {
    margin-block-start: var(--s-6);
}

/*
 * The group heading carries a short rule beside it. With five groups and
 * twenty-six links, a bare uppercase word was not enough separation — the eye
 * needs a horizontal break to know a new section started.
 */
.side__group-label {
    display: flex;
    align-items: center;
    gap: var(--s-2);
    padding-inline: var(--s-3);
    margin-block-end: var(--s-2);
    font-size: var(--fs-xs);
    font-weight: 700;
    letter-spacing: 0.04em;
    color: rgb(255 255 255 / 0.45);
}

.side__group-rule {
    inline-size: 14px;
    block-size: 1px;
    background: rgb(255 255 255 / 0.28);
    flex: 0 0 auto;
}

.side__link {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    padding: var(--s-2) var(--s-3);
    /* §10.8: 44px, not 40. Measured at 41.9px before this — the row is the
       most-tapped control in the panel on a phone, and it was the shortest. */
    min-block-size: 44px;
    border-radius: var(--r-sm);
    color: rgb(255 255 255 / 0.78);
    font-size: var(--fs-sm);
    font-weight: 600;
    /* A rail on the inline-start edge, transparent until active — so the
       active row shifts nothing when it lights up. */
    border-inline-start: 3px solid transparent;
    transition:
        background-color var(--dur-micro) var(--ease),
        color var(--dur-micro) var(--ease);
}

.side__link:hover {
    background: rgb(255 255 255 / 0.07);
    color: #fff;
}

.side__link.is-current {
    background: rgb(255 255 255 / 0.11);
    border-inline-start-color: var(--gold-400);
    color: #fff;
}

.side__link.is-current :deep(.ico) {
    opacity: 1;
    color: var(--gold-400);
}

.side__label {
    min-inline-size: 0;
    overflow-wrap: anywhere;
}

/* ---- Topbar -------------------------------------------------------- */
.topbar__visit {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    min-block-size: 44px;
    padding-inline: var(--s-4);
    border-radius: var(--r-sm);
    background: var(--navy-100);
    color: var(--navy-900);
    font-size: var(--fs-sm);
    font-weight: 700;
    white-space: nowrap;
}

.topbar__visit:hover {
    background: var(--navy-900);
    color: #fff;
}

.topbar__out {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    min-block-size: 44px;
    padding-inline: var(--s-4);
    border-radius: var(--r-sm);
    color: var(--action-600);
    font-size: var(--fs-sm);
    font-weight: 700;
    box-shadow: inset 0 0 0 1px var(--hairline);
    white-space: nowrap;
}

.topbar__out:hover {
    background: var(--action-600);
    color: #fff;
    box-shadow: none;
}

/* Below tablet the labels drop and the icons carry the meaning; both controls
   keep their 44px target. */
@media (max-width: 767px) {
    .topbar__visit-text,
    .topbar__out-text,
    .account__who {
        display: none;
    }

    .topbar__visit,
    .topbar__out {
        padding-inline: var(--s-3);
    }
}

/* ---- Account ------------------------------------------------------- */
.account__who {
    display: grid;
    text-align: start;
    line-height: 1.25;
}

.account__role {
    font-size: var(--fs-xs);
    font-weight: 500;
    color: var(--muted);
}

.account__avatar--img {
    object-fit: cover;
    padding: 0;
}
</style>