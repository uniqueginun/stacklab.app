<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: 'Confirm password',
        description:
            'This is a secure area. Confirm your password to continue.',
        boxed: false,
    },
});
</script>

<template>
    <Head title="Confirm password" />

    <div class="rounded-2xl border border-neutral-200/80 bg-white p-8">
        <PasskeyVerify
            :routes="{
                options: confirmOptions(),
                submit: confirmStore(),
            }"
            label="Confirm with passkey"
            loading-label="Confirming..."
            separator="Or confirm with password"
        />

        <Form
            v-bind="store.form()"
            reset-on-success
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <div class="grid gap-2">
                <Label
                    for="password"
                    class="text-sm font-normal text-neutral-500"
                    >Password</Label
                >
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    autofocus
                    class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                />
                <InputError :message="errors.password" />
            </div>

            <Button
                class="h-11 w-full rounded-lg bg-brand text-white hover:bg-brand/90"
                :disabled="processing"
                data-test="confirm-password-button"
            >
                <Spinner v-if="processing" />
                Confirm password
            </Button>
        </Form>
    </div>
</template>
