<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Sign in',
        description: 'Provision servers and ship sites in minutes.',
        boxed: false,
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const remember = ref(true);
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div class="rounded-2xl border border-neutral-200/80 bg-white p-8">
        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
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
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="you@company.com"
                    class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label
                        for="password"
                        class="text-sm font-normal text-neutral-500"
                        >Password</Label
                    >
                    <Link
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm text-brand hover:text-brand/80"
                        :tabindex="5"
                    >
                        Forgot password?
                    </Link>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                />
                <InputError :message="errors.password" />
            </div>

            <Label
                for="remember"
                class="flex items-center gap-2 text-sm font-normal text-neutral-500"
            >
                <Checkbox
                    id="remember"
                    :tabindex="3"
                    v-model:checked="remember"
                    class="border-neutral-300 data-[state=checked]:border-brand data-[state=checked]:bg-brand data-[state=checked]:text-white"
                />
                Remember me for 30 days
            </Label>
            <input v-if="remember" type="hidden" name="remember" value="1" />

            <Button
                type="submit"
                class="h-11 w-full rounded-lg bg-brand text-white hover:bg-brand/90"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Sign in
            </Button>
        </Form>

        <PasskeyVerify
            :show-separator="false"
            label="Continue with a passkey"
            class="mt-6 h-11 w-full rounded-lg border-neutral-200 bg-white font-medium text-neutral-950 shadow-none hover:bg-neutral-50"
        />
    </div>

    <p class="mt-6 text-center text-sm text-neutral-500">
        New to StackLab?
        <Link
            :href="register()"
            class="font-medium text-brand hover:text-brand/80"
            :tabindex="6"
        >
            Create an account
        </Link>
    </p>
</template>
