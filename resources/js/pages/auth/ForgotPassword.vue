<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot password',
        description: 'Enter your email and we will send a reset link.',
        boxed: false,
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Forgot password" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div class="rounded-2xl border border-neutral-200/80 bg-white p-8">
        <Form
            v-bind="email.form()"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <div class="grid gap-2">
                <Label for="email" class="text-sm font-normal text-neutral-500"
                    >Email</Label
                >
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="email"
                    autofocus
                    placeholder="you@company.com"
                    class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                class="h-11 w-full rounded-lg bg-brand text-white hover:bg-brand/90"
                :disabled="processing"
                data-test="email-password-reset-link-button"
            >
                <Spinner v-if="processing" />
                Email password reset link
            </Button>
        </Form>
    </div>

    <p class="mt-6 text-center text-sm text-neutral-500">
        Remembered it?
        <Link
            :href="login()"
            class="font-medium text-brand hover:text-brand/80"
        >
            Sign in
        </Link>
    </p>
</template>
