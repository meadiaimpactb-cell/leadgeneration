<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import Logo from '@/Components/ui/Logo.vue';
import Field from '@/Components/admin/Field.vue';
import Button from '@/Components/ui/Button.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * Admin sign-in (§9.2). Rate limited server-side with a lockout after five
 * attempts; this screen only reports what the server decided.
 */
const { t } = useTranslation();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/admin/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head :title="t('admin.sign_in')" />

    <div class="login">
        <div class="login__card">
            <Logo lockup="stacked" tone="navy" class="login__logo" />

            <h1 class="login__title">{{ t('admin.sign_in') }}</h1>

            <form class="login__form" @submit.prevent="submit">
                <Field
                    v-model="form.email"
                    :label="t('admin.email')"
                    type="email"
                    dir="ltr"
                    :error="form.errors.email"
                    required
                />

                <Field
                    v-model="form.password"
                    :label="t('admin.password')"
                    type="password"
                    dir="ltr"
                    :error="form.errors.password"
                    required
                />

                <Field
                    v-model="form.remember"
                    :label="t('admin.remember')"
                    type="checkbox"
                />

                <Button type="submit" variant="primary" :loading="form.processing">
                    {{ form.processing ? t('admin.saving') : t('admin.sign_in') }}
                </Button>
            </form>
        </div>
    </div>
</template>

<style scoped>
.login {
    min-block-size: 100vh;
    display: grid;
    place-items: center;
    padding: var(--s-5);
    background: var(--navy-900);
}

.login__card {
    inline-size: 100%;
    max-inline-size: 420px;
    padding: var(--s-8);
    background: var(--paper);
    border-radius: var(--r-md);
}

.login__logo {
    margin-inline: auto;
}

.login__title {
    margin-block: var(--s-6) var(--s-5);
    font-size: var(--fs-h2);
    text-align: center;
}

.login__form {
    display: flex;
    flex-direction: column;
    gap: var(--s-4);
}
</style>
