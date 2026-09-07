/**
 * The admin sidebar, defined once.
 *
 * It used to live inline in AdminLayout.vue, which meant the only way to know
 * what the panel contains was to read a template. It is a map of the whole
 * back office and it is now a list — ordered, grouped, and permission-tagged
 * in one place, so adding a screen is one line and nothing can drift out of
 * step with it.
 *
 * Each entry:
 *   icon    a NavIcon name; unknown names fall through to a neutral dot
 *   label   a translation key, resolved by the caller — never a literal
 *   href    the admin path
 *   can     the permission required to see it; omit for "any admin"
 *   soon    true while the screen is a placeholder awaiting its phase
 *   hidden  true if Amad Craft asked for the entry to be taken out of the
 *           menu. The screen still opens for anyone with its URL — this is a
 *           menu decision, not a removal — so putting it back is deleting one
 *           word here.
 *   legacy  true if the screen only edits pages the landing-page decision
 *           retired; hidden while `site.legacy_pages` is off, and back the
 *           moment it is on. The screens themselves still open — this hides
 *           the entry, it does not remove the editor.
 *
 * Order and grouping here are the client's approved structure. Keep them.
 */
export const NAV_GROUPS = [
    {
        key: 'overview',
        label: 'admin.nav_overview',
        items: [
            { icon: 'dashboard', label: 'admin.dashboard', href: '/admin' },
            { icon: 'leads', label: 'admin.leads', href: '/admin/leads', can: 'leads.view' },
        ],
    },
    {
        key: 'content',
        label: 'admin.nav_content',
        items: [
            { icon: 'pages', label: 'admin.pages', href: '/admin/pages', can: 'pages.view' },
            { icon: 'solutions', label: 'admin.solutions', href: '/admin/content/solutions', can: 'solutions.manage' , legacy: true },
            /*
             * The four audience segments, as a sub-entry of Solutions.
             *
             * They stopped being "القطاعات" — a parallel top-level content
             * type — and became what they actually are: the segments a
             * solution is offered to. But they are still full records with
             * their own public pages, so the editor stays in the menu.
             * A live screen the menu cannot reach is a screen the client has
             * to be told about in words, which is the thing this panel exists
             * to avoid.
             */
            { icon: 'sectors', label: 'admin.solution_segments', href: '/admin/content/sectors', can: 'sectors.manage', sub: true , legacy: true },
            { icon: 'products', label: 'admin.products', href: '/admin/content/products', can: 'products.manage' , legacy: true },
            { icon: 'categories', label: 'admin.product_categories', href: '/admin/content/product-categories', can: 'products.manage' , legacy: true },
            { icon: 'impact', label: 'admin.impact_metrics', href: '/admin/content/impact-metrics', can: 'impact.manage' , legacy: true },
            { icon: 'stories', label: 'admin.stories', href: '/admin/content/stories', can: 'stories.manage' , legacy: true },
            { icon: 'reports', label: 'admin.reports', href: '/admin/content/reports', can: 'reports.manage' , legacy: true },
            { icon: 'training', label: 'admin.training', href: '/admin/content/training-programs', can: 'training.manage' , legacy: true },
            { icon: 'partners', label: 'admin.partners', href: '/admin/content/partners', can: 'partners.manage' , legacy: true },
            { icon: 'media', label: 'admin.media_library', href: '/admin/media', can: 'media.manage' },
        ],
    },
    {
        key: 'campaigns',
        label: 'admin.nav_campaigns',
        items: [
            { icon: 'campaigns', label: 'admin.campaigns_landing', href: '/admin/campaigns', can: 'campaigns.view' , legacy: true },
        ],
    },
    {
        key: 'clients',
        label: 'admin.nav_clients',
        items: [
            { icon: 'crm', label: 'admin.crm_link', href: '/admin/integrations/crm', can: 'settings.manage' },
            { icon: 'bell', label: 'admin.notifications', href: '/admin/integrations/notifications', can: 'settings.manage' },
            { icon: 'fields', label: 'admin.lead_fields', href: '/admin/lead-fields', can: 'settings.manage' },
            /*
             * Hidden at Amad Craft's request (7 September 2026).
             *
             * The visitor's on-screen thank-you falls back to the shipped wording when
             * no setting is written, so hiding this loses the ability to reword it,
             * not the message itself (§6.2 step 4).
             */
            { icon: 'message', label: 'settings.screen.confirmations', href: '/admin/integrations/confirmations', can: 'settings.manage' , hidden: true },
            /*
             * Hidden at Amad Craft's request (7 September 2026).
             *
             * The honeypot and the rate limit are in the request path and keep working;
             * this screen was only ever the placeholder for the spam TAB (§6.1).
             */
            { icon: 'shield', label: 'admin.spam_guard', href: '/admin/integrations/spam', can: 'settings.manage', soon: true , hidden: true },
        ],
    },
    {
        key: 'visibility',
        label: 'admin.nav_visibility',
        items: [
            { icon: 'seo', label: 'settings.screen.seo', href: '/admin/settings/seo', can: 'settings.manage' },
            { icon: 'keywords', label: 'settings.screen.keywords', href: '/admin/seo/keywords', can: 'pages.view' },
            { icon: 'robots', label: 'settings.screen.robots', href: '/admin/settings/robots', can: 'settings.manage' },
            { icon: 'sitemap', label: 'admin.sitemap', href: '/admin/seo/sitemap', can: 'pages.view' },
            { icon: 'tracking', label: 'settings.screen.tracking', href: '/admin/settings/tracking', can: 'settings.manage' },
            { icon: 'redirects', label: 'admin.redirects', href: '/admin/redirects', can: 'redirects.manage' },
        ],
    },
    {
        key: 'system',
        label: 'admin.nav_system',
        items: [
            { icon: 'navigation', label: 'admin.navigation', href: '/admin/navigation', can: 'navigation.manage' },
            { icon: 'users', label: 'admin.users_roles', href: '/admin/users', can: 'users.manage' },
            { icon: 'activity', label: 'admin.activity_log', href: '/admin/activity', can: 'settings.manage' },
        ],
    },
    {
        key: 'settings',
        label: 'admin.nav_settings',
        items: [
            { icon: 'site', label: 'settings.screen.site', href: '/admin/settings/site', can: 'settings.manage' },
            { icon: 'brand', label: 'admin.brand', href: '/admin/brand', can: 'settings.manage' },
            { icon: 'contact', label: 'settings.screen.contact', href: '/admin/settings/contact', can: 'settings.manage' },
            { icon: 'languages', label: 'admin.languages', href: '/admin/languages', can: 'settings.manage' },
            { icon: 'store', label: 'settings.screen.store', href: '/admin/settings/store', can: 'settings.manage' },
            /*
             * Hidden at Amad Craft's request (7 September 2026).
             *
             * Backups keep running from scripts/backup-db.ps1; this screen was the
             * placeholder that would have listed them.
             */
            { icon: 'backup', label: 'admin.backups', href: '/admin/backups', can: 'settings.manage', soon: true , hidden: true },
            { icon: 'advanced', label: 'settings.screen.advanced', href: '/admin/settings/advanced', can: 'settings.manage' },
        ],
    },
];

/** Every href the sidebar can show — used by the reachability test. */
export const NAV_HREFS = NAV_GROUPS.flatMap((g) => g.items.map((i) => i.href));
