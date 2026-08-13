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
            { icon: 'solutions', label: 'admin.solutions', href: '/admin/content/solutions', can: 'solutions.manage' },
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
            { icon: 'sectors', label: 'admin.solution_segments', href: '/admin/content/sectors', can: 'sectors.manage', sub: true },
            { icon: 'products', label: 'admin.products', href: '/admin/content/products', can: 'products.manage' },
            { icon: 'categories', label: 'admin.product_categories', href: '/admin/content/product-categories', can: 'products.manage' },
            { icon: 'impact', label: 'admin.impact_metrics', href: '/admin/content/impact-metrics', can: 'impact.manage' },
            { icon: 'stories', label: 'admin.stories', href: '/admin/content/stories', can: 'stories.manage' },
            { icon: 'reports', label: 'admin.reports', href: '/admin/content/reports', can: 'reports.manage' },
            { icon: 'training', label: 'admin.training', href: '/admin/content/training-programs', can: 'training.manage' },
            { icon: 'partners', label: 'admin.partners', href: '/admin/content/partners', can: 'partners.manage' },
            { icon: 'media', label: 'admin.media_library', href: '/admin/media', can: 'media.manage' },
        ],
    },
    {
        key: 'campaigns',
        label: 'admin.nav_campaigns',
        items: [
            { icon: 'campaigns', label: 'admin.campaigns_landing', href: '/admin/campaigns', can: 'campaigns.view' },
        ],
    },
    {
        key: 'clients',
        label: 'admin.nav_clients',
        items: [
            { icon: 'crm', label: 'admin.crm_link', href: '/admin/integrations/crm', can: 'settings.manage' },
            { icon: 'bell', label: 'admin.notifications', href: '/admin/integrations/notifications', can: 'settings.manage', soon: true },
            { icon: 'fields', label: 'admin.lead_fields', href: '/admin/lead-fields', can: 'settings.manage' },
            { icon: 'message', label: 'admin.confirmations', href: '/admin/integrations/confirmations', can: 'settings.manage', soon: true },
            { icon: 'shield', label: 'admin.spam_guard', href: '/admin/integrations/spam', can: 'settings.manage', soon: true },
        ],
    },
    {
        key: 'visibility',
        label: 'admin.nav_visibility',
        items: [
            { icon: 'seo', label: 'settings.screen.seo', href: '/admin/settings/seo', can: 'settings.manage' },
            { icon: 'keywords', label: 'settings.screen.keywords', href: '/admin/seo/keywords', can: 'pages.view' },
            { icon: 'keywords', label: 'settings.screen.page_keywords', href: '/admin/seo/page-keywords', can: 'pages.view' },
            { icon: 'robots', label: 'settings.screen.robots', href: '/admin/settings/robots', can: 'settings.manage' },
            { icon: 'sitemap', label: 'admin.sitemap', href: '/admin/seo/sitemap', can: 'settings.manage', soon: true },
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
            { icon: 'activity', label: 'admin.activity_log', href: '/admin/activity', can: 'settings.manage', soon: true },
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
            { icon: 'backup', label: 'admin.backups', href: '/admin/backups', can: 'settings.manage', soon: true },
            { icon: 'advanced', label: 'settings.screen.advanced', href: '/admin/settings/advanced', can: 'settings.manage' },
        ],
    },
];

/** Every href the sidebar can show — used by the reachability test. */
export const NAV_HREFS = NAV_GROUPS.flatMap((g) => g.items.map((i) => i.href));
