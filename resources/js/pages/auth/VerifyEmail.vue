<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Verify your email',
        description:
            'Click the link we just sent to confirm your email address.',
        boxed: false,
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Email verification" />

    <div class="rounded-2xl border border-neutral-200/80 bg-white p-8">
        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            A new verification link has been sent to the email address you
            provided during registration.
        </div>

        <Form
            v-bind="send.form()"
            class="flex flex-col gap-4"
            v-slot="{ processing }"
        >
            <Button
                :disabled="processing"
                class="h-11 w-full rounded-lg bg-brand text-white hover:bg-brand/90"
            >
                <Spinner v-if="processing" />
                Resend verification email
            </Button>
        </Form>
    </div>

    <p class="mt-6 text-center text-sm text-neutral-500">
        <Link
            :href="logout()"
            method="post"
            as="button"
            class="font-medium text-brand hover:text-brand/80"
        >
            Log out
        </Link>
    </p>
</template>
