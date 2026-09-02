<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TermsModal, {
    type LegalDocument,
} from '@/components/stacklab/TermsModal.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Create your account',
        description: 'Connect a server over SSH, then deploy from GitHub.',
        boxed: false,
    },
});

const agreed = ref(false);
const showLegal = ref(false);
const legalDocument = ref<LegalDocument>('terms');

const openLegal = (document: LegalDocument) => {
    legalDocument.value = document;
    showLegal.value = true;
};

const features = [
    'SSH-first server provisioning',
    'Deploy directly from GitHub',
    'Visible, repeatable releases',
];
</script>

<template>
    <Head title="Register" />

    <div class="rounded-2xl border border-neutral-200/80 bg-white p-8">
        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <div class="grid gap-2">
                <Label for="name" class="text-sm font-normal text-neutral-500"
                    >Full name</Label
                >
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Ada Lovelace"
                    class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email" class="text-sm font-normal text-neutral-500"
                    >Work email</Label
                >
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="you@company.com"
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
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
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
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Re-enter your password"
                    :passwordrules="passwordRules"
                    class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="grid gap-2">
                <Label
                    for="terms"
                    class="flex items-start gap-2 text-sm font-normal text-neutral-500"
                >
                    <Checkbox
                        id="terms"
                        :tabindex="5"
                        :checked="agreed"
                        class="mt-0.5 border-neutral-300 data-[state=checked]:border-brand data-[state=checked]:bg-brand data-[state=checked]:text-white"
                        @update:checked="agreed = $event === true"
                    />
                    <span>
                        I agree to the
                        <button
                            type="button"
                            class="text-brand hover:text-brand/80"
                            @click.prevent.stop="openLegal('terms')"
                        >
                            Terms of Service
                        </button>
                        and
                        <button
                            type="button"
                            class="text-brand hover:text-brand/80"
                            @click.prevent.stop="openLegal('privacy')"
                        >
                            Privacy Policy
                        </button>
                        .
                    </span>
                </Label>
                <input
                    type="hidden"
                    name="terms"
                    :value="agreed ? '1' : '0'"
                />
                <InputError :message="errors.terms" />
            </div>

            <Button
                type="submit"
                class="h-11 w-full rounded-lg bg-brand text-white hover:bg-brand/90"
                tabindex="6"
                :disabled="processing || !agreed"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Create account
            </Button>
        </Form>

        <div class="mt-6 border-t border-neutral-200 pt-6">
            <ul class="space-y-3">
                <li
                    v-for="feature in features"
                    :key="feature"
                    class="flex items-center gap-2 text-sm text-neutral-500"
                >
                    <Check class="size-4 text-brand" />
                    {{ feature }}
                </li>
            </ul>
        </div>
    </div>

    <p class="mt-6 text-center text-sm text-neutral-500">
        Already have an account?
        <Link
            :href="login()"
            class="font-medium text-brand hover:text-brand/80"
            :tabindex="7"
            >Sign in</Link
        >
    </p>

    <TermsModal
        v-model:open="showLegal"
        :document="legalDocument"
        @accept="agreed = true"
    />
</template>
