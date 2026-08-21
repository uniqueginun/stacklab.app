<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Reset password',
        description: 'Choose a new password for your account.',
        boxed: false,
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="Reset password" />

    <div class="rounded-2xl border border-neutral-200/80 bg-white p-8">
        <Form
            v-bind="update.form()"
            :transform="(data) => ({ ...data, token, email })"
            :reset-on-success="['password', 'password_confirmation']"
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
                    v-model="inputEmail"
                    readonly
                    class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label
                    for="password"
                    class="text-sm font-normal text-neutral-500"
                    >Password</Label
                >
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    autofocus
                    placeholder="At least 8 characters"
                    :passwordrules="passwordRules"
                    class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label
                    for="password_confirmation"
                    class="text-sm font-normal text-neutral-500"
                    >Confirm password</Label
                >
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    placeholder="Re-enter your password"
                    :passwordrules="passwordRules"
                    class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="h-11 w-full rounded-lg bg-brand text-white hover:bg-brand/90"
                :disabled="processing"
                data-test="reset-password-button"
            >
                <Spinner v-if="processing" />
                Reset password
            </Button>
        </Form>
    </div>
</template>
