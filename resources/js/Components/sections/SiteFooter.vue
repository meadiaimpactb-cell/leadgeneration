<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import Logo from '@/Components/ui/Logo.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * Footer (§11.1): nav, contact, social, the informational store link, legal.
 *
 * The store link is present for anyone who wants to go there and carries no
 * sales push — §4 is explicit that this site never steers a visitor toward a
 * direct purchase.
 */
const { t } = useTranslation();
const page = usePage();

const settings = computed(() => page.props.settings ?? {});
const locale = computed(() => page.props.locale);

const mainLinks = computed(() => page.props.navigation?.footer_main ?? []);
const legalLinks = computed(() => page.props.navigation?.footer_legal ?? []);

const email = computed(() => settings.value['contact.email'] ?? null);
const phone = computed(() => settings.value['contact.phone'] ?? null);
const storeUrl = computed(() => settings.value['store.url'] ?? null);
const storeLabel = computed(() => settings.value[`store.label.${locale.value}`] ?? null);
const social = computed(() => settings.value['contact.social'] ?? []);
const copyright = computed(() => settings.value[`site.copyright.${locale.value}`] ?? null);
</script>

<template>
    <footer class="footer on-dark">
        <Container>
            <div class="footer__grid">
                <div class="footer__brand">
                    <Logo lockup="stacked" tone="white" />
                </div>

                <nav v-if="mainLinks.length" class="footer__col" :aria-label="t('common.menu')">
                    <Link
                        v-for="item in mainLinks"
                        :key="item.id"
                        :href="item.url"
                        class="footer__link link-weave"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div v-if="email || phone" class="footer__col">
                    <a v-if="email" class="footer__link link-weave latin" :href="`mailto:${email}`">
                        {{ email }}
                    </a>
                    <a v-if="phone" class="footer__link link-weave latin" :href="`tel:${phone}`">
                        {{ phone }}
                    </a>
                </div>

                <div v-if="social.length || storeUrl" class="footer__col">
                    <a
                        v-for="channel in social"
                        :key="channel.url"
                        class="footer__link link-weave"
                        :href="channel.url"
                        rel="noopener noreferrer"
                        target="_blank"
                    >
                        {{ channel.label }}
                        <span class="visually-hidden">{{ t('common.external_link') }}</span>
                    </a>

                    <!-- Informational only. No price, no buy (§4). -->
                    <a
                        v-if="storeUrl && storeLabel"
                        class="footer__link link-weave"
                        :href="storeUrl"
                        rel="noopener noreferrer"
                        target="_blank"
                    >
                        {{ storeLabel }}
                        <span class="visually-hidden">{{ t('common.external_link') }}</span>
                    </a>
                </div>
            </div>

            <div class="footer__base">
                <p v-if="copyright" class="footer__copyright">{{ copyright }}</p>

                <nav v-if="legalLinks.length" class="footer__legal" aria-label="legal">
                    <Link
                        v-for="item in legalLinks"
                        :key="item.id"
                        :href="item.url"
                        class="footer__link link-weave"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </div>
        </Container>
    </footer>
</template>

<style scoped>
.footer {
    padding-block: var(--s-10) var(--s-7);
}

.footer__grid {
    display: grid;
    gap: var(--s-7);
    grid-template-columns: 1fr;
}

.footer__col {
    display: flex;
    flex-direction: column;
    gap: var(--s-1);
}

.footer__link {
    color: rgba(255, 255, 255, 0.82);
    font-size: var(--fs-sm);
    padding-block: var(--s-2);
    min-block-size: 44px;
    display: flex;
    align-items: center;
}

.footer__link:hover {
    color: var(--text-inverse);
}

.footer__base {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-4);
    justify-content: space-between;
    align-items: center;
    margin-block-start: var(--s-9);
    padding-block-start: var(--s-5);
    border-block-start: 1px solid var(--hairline-inverse);
}

.footer__copyright {
    color: rgba(255, 255, 255, 0.64);
    font-size: var(--fs-xs);
}

.footer__legal {
    display: flex;
    gap: var(--s-5);
}

@media (min-width: 640px) {
    .footer__grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .footer__grid {
        grid-template-columns: 1.5fr 1fr 1fr 1fr;
    }
}
</style>
