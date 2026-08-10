import { translate } from '@/plugins/i18n';

/**
 * `const { t } = useTranslation()` inside <script setup>.
 *
 * A composable rather than only a global property so that string lookups are
 * explicit in the setup block and testable in isolation.
 */
export function useTranslation() {
    return { t: translate };
}
