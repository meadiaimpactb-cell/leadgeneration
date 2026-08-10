<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Workspace from '@/Components/admin/Workspace.vue';
import Panel from '@/Components/admin/Panel.vue';
import Field from '@/Components/admin/Field.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * The operator's own account (§9.2, §19.3).
 *
 * A cover, a portrait, an identity block, and a standing column of tabs — each
 * tab a real URL, so moving between them is a normal navigation rather than a
 * menu that has to be reopened every time.
 *
 * Only tabs backed by working features are listed. §9.2 makes 2FA mandatory
 * for super-admin and it is not built yet, so the security tab says so in
 * words instead of showing a switch that does nothing.
 */
const props = defineProps({
    tab: { type: String, default: 'details' },
    profile: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});

const { t } = useTranslation();
const { date, dateTime } = useFormat();

const details = reactive({ name: props.profile.name, email: props.profile.email });
const secret = reactive({ current_password: '', password: '', password_confirmation: '' });
const busy = ref(null);

const initials = computed(() =>
    (props.profile.name ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
);

const roleLabel = computed(() => {
    const role = props.profile.roles?.[0];

    return role ? t(`admin.role_${role.replace(/-/g, '_')}`) : '';
});

const TAB_ICONS = {
    details: 'profile',
    appearance: 'brand',
    password: 'advanced',
    security: 'robots',
};

function saveDetails() {
    busy.value = 'details';
    router.put('/admin/profile', details, {
        preserveScroll: true,
        onFinish: () => (busy.value = null),
    });
}

function savePassword() {
    busy.value = 'password';
    router.put('/admin/profile/password', secret, {
        preserveScroll: true,
        onSuccess: () => {
            secret.current_password = '';
            secret.password = '';
            secret.password_confirmation = '';
        },
        onFinish: () => (busy.value = null),
    });
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

function removeImage(collection) {
    router.delete(`/admin/profile/image/${collection}`, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout :title="t('admin.profile')">
        <Workspace editable>
            <!-- Details -->
            <Panel v-if="tab === 'details'" :title="t('admin.tab_details')">
                <div class="grid">
                    <Field v-model="details.name" :label="t('admin.name')" :error="errors.name" />
                    <Field
                        v-model="details.email"
                        :label="t('admin.email')"
                        type="email"
                        dir="ltr"
                        :error="errors.email"
                    />
                </div>

                <div class="bar">
                    <button class="btn btn--cta" type="button" :disabled="busy" @click="saveDetails">
                        {{ busy === 'details' ? t('admin.saving') : t('admin.save') }}
                    </button>
                </div>
            </Panel>

            <!-- Appearance -->
            <Panel v-else-if="tab === 'appearance'" :title="t('admin.tab_appearance')">
                <p class="intro">{{ t('admin.tab_appearance_intro') }}</p>

                <div class="slots">
                    <div class="slot">
                        <h3 class="slot__title">{{ t('admin.avatar_add') }}</h3>
                        <div class="slot__frame">
                            <img v-if="profile.avatar" class="slot__img" :src="profile.avatar" alt="" />
                            <span v-else class="slot__empty">{{ t('admin.avatar_none') }}</span>
                        </div>
                        <div class="slot__actions">
                            <label class="btn btn--ghost slot__upload">
                                <span>{{ busy === 'avatar' ? t('admin.saving') : t('admin.upload') }}</span>
                                <input type="file" accept="image/*" @change="(e) => upload('avatar', e)" />
                            </label>
                            <button
                                v-if="profile.avatar"
                                class="btn btn--ghost slot__del"
                                type="button"
                                @click="removeImage('avatar')"
                            >
                                {{ t('admin.delete') }}
                            </button>
                        </div>
                    </div>

                    <div class="slot">
                        <h3 class="slot__title">{{ t('admin.cover_add') }}</h3>
                        <div class="slot__frame slot__frame--wide">
                            <img v-if="profile.cover" class="slot__img" :src="profile.cover" alt="" />
                            <span v-else class="slot__empty">{{ t('admin.cover_none') }}</span>
                        </div>
                        <div class="slot__actions">
                            <label class="btn btn--ghost slot__upload">
                                <span>{{ busy === 'cover' ? t('admin.saving') : t('admin.upload') }}</span>
                                <input type="file" accept="image/*" @change="(e) => upload('cover', e)" />
                            </label>
                            <button
                                v-if="profile.cover"
                                class="btn btn--ghost slot__del"
                                type="button"
                                @click="removeImage('cover')"
                            >
                                {{ t('admin.delete') }}
                            </button>
                        </div>
                    </div>
                </div>
            </Panel>

            <!-- Password -->
            <Panel v-else-if="tab === 'password'" :title="t('admin.change_password')">
                <p class="intro">{{ t('admin.password_hint') }}</p>

                <div class="grid">
                    <Field
                        v-model="secret.current_password"
                        :label="t('admin.current_password')"
                        type="password"
                        dir="ltr"
                        :error="errors.current_password"
                    />
                    <span />
                    <Field
                        v-model="secret.password"
                        :label="t('admin.new_password')"
                        type="password"
                        dir="ltr"
                        :error="errors.password"
                    />
                    <Field
                        v-model="secret.password_confirmation"
                        :label="t('admin.confirm_password')"
                        type="password"
                        dir="ltr"
                    />
                </div>

                <div class="bar">
                    <button class="btn btn--cta" type="button" :disabled="busy" @click="savePassword">
                        {{ busy === 'password' ? t('admin.saving') : t('admin.change_password') }}
                    </button>
                </div>
            </Panel>

            <!-- Security -->
            <Panel v-else :title="t('admin.tab_security')">
                <dl class="facts">
                    <dt>{{ t('admin.last_login') }}</dt>
                    <dd>{{ profile.lastLoginAt ? dateTime(profile.lastLoginAt) : '—' }}</dd>
                    <dt>{{ t('admin.member_since') }}</dt>
                    <dd>{{ profile.createdAt ? date(profile.createdAt) : '—' }}</dd>
                    <dt>{{ t('admin.role') }}</dt>
                    <dd>{{ roleLabel }}</dd>
                </dl>

                <!-- Stated, not implied. §9.2 requires 2FA for super-admin
                     and it is not built; a switch that did nothing would be
                     worse than this sentence. -->
                <p class="pending">{{ t('admin.twofa_pending') }}</p>
            </Panel>
        </Workspace>
    </AdminLayout>
</template>

<style scoped>
/* ---- Header ---- */




.hero__coverBtn input {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    opacity: 0;
}






.hero__camera input {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    opacity: 0;
}














/* ---- Tabs beside content ---- */





.tab:hover {
    background: var(--paper-alt);
}


.tab.is-current :deep(.ico) {
    opacity: 1;
    color: var(--gold-400);
}




.tab.is-current .tab__note {
    color: rgb(255 255 255 / 0.7);
}

/* ---- Panes ---- */
.intro {
    color: var(--muted);
    font-size: var(--fs-sm);
    margin-block-end: var(--s-5);
    max-inline-size: 70ch;
}

.grid {
    display: grid;
    gap: var(--s-5);
    grid-template-columns: 1fr;
}

.bar {
    margin-block-start: var(--s-6);
}

.slots {
    display: grid;
    gap: var(--s-5);
    grid-template-columns: 1fr;
}

.slot__title {
    font-size: var(--fs-sm);
    font-weight: 700;
    margin-block-end: var(--s-2);
}

.slot__frame {
    display: flex;
    align-items: center;
    justify-content: center;
    min-block-size: 140px;
    padding: var(--s-3);
    border-radius: var(--r-sm);
    border: 1px solid var(--hairline);
    background: var(--paper-alt);
}

.slot__img {
    max-inline-size: 100%;
    max-block-size: 120px;
    object-fit: contain;
}

.slot__empty {
    font-size: var(--fs-xs);
    color: var(--muted);
}

.slot__actions {
    display: flex;
    gap: var(--s-2);
    margin-block-start: var(--s-3);
}

.slot__upload {
    position: relative;
    cursor: pointer;
}

.slot__upload input {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    opacity: 0;
}

.slot__del {
    color: var(--action-600);
}

.facts {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: var(--s-2) var(--s-5);
    font-size: var(--fs-sm);
}

.facts dt {
    color: var(--muted);
}

.facts dd {
    margin: 0;
    font-weight: 600;
}

.pending {
    margin-block-start: var(--s-5);
    padding: var(--s-3) var(--s-4);
    border-radius: var(--r-sm);
    background: var(--gold-100);
    color: var(--navy-900);
    font-size: var(--fs-sm);
    line-height: 1.8;
    max-inline-size: 75ch;
}

@media (min-width: 900px) {
    .split {
        grid-template-columns: 280px 1fr;
    }

    .grid,
    .slots {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
