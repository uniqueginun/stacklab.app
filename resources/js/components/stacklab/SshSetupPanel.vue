<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Check, Clipboard, KeyRound, ShieldCheck, Terminal } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { connect, confirm, verify } from '@/routes/servers/ssh';
import type { ServerShow } from '@/types';

const props = defineProps<{
    server: ServerShow;
    fingerprint: string | null;
    hostKeyType: string | null;
}>();

const copied = ref<'key' | 'command' | null>(null);

const steps = ['Generate key', 'Authorize access', 'Confirm fingerprint'];

const fingerprintCommand = computed(() => {
    const keyFiles: Record<string, string> = {
        'ssh-ed25519': 'ssh_host_ed25519_key.pub',
        'ecdsa-sha2-nistp256': 'ssh_host_ecdsa_key.pub',
        'ssh-rsa': 'ssh_host_rsa_key.pub',
    };

    return `ssh-keygen -lf /etc/ssh/${
        keyFiles[props.hostKeyType ?? ''] ?? 'ssh_host_ed25519_key.pub'
    }`;
});

const currentStep = computed(() => {
    if (props.fingerprint) {
        return 3;
    }

    if (props.server.ssh_public_key) {
        return 2;
    }

    return 1;
});

const copy = async (value: string, target: 'key' | 'command') => {
    await navigator.clipboard.writeText(value);
    copied.value = target;
    window.setTimeout(() => {
        if (copied.value === target) {
            copied.value = null;
        }
    }, 2000);
};
</script>

<template>
    <section
        class="mt-8 overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm"
    >
        <div class="border-b border-amber-100 bg-amber-50/70 px-6 py-5">
            <div class="flex items-start gap-3">
                <KeyRound class="mt-0.5 size-5 shrink-0 text-amber-700" />
                <div>
                    <h2 class="font-semibold">Finish connecting your server</h2>
                    <p class="mt-1 text-sm text-neutral-600">
                        Server actions stay locked until Stacklab can connect
                        securely over SSH.
                    </p>
                </div>
            </div>
        </div>

        <ol class="grid border-b border-neutral-100 sm:grid-cols-3">
            <li
                v-for="(step, index) in steps"
                :key="step"
                class="flex items-center gap-2 border-b border-neutral-100 px-5 py-3 text-sm last:border-b-0 sm:border-r sm:border-b-0 sm:last:border-r-0"
                :class="
                    currentStep >= index + 1
                        ? 'text-neutral-900'
                        : 'text-neutral-400'
                "
            >
                <span
                    class="flex size-5 items-center justify-center rounded-full text-xs"
                    :class="
                        currentStep > index + 1
                            ? 'bg-emerald-100 text-emerald-700'
                            : currentStep === index + 1
                              ? 'bg-amber-100 text-amber-800'
                              : 'bg-neutral-100 text-neutral-400'
                    "
                >
                    <Check v-if="currentStep > index + 1" class="size-3" />
                    <span v-else>{{ index + 1 }}</span>
                </span>
                {{ step }}
            </li>
        </ol>

        <div class="p-6">
            <Form
                v-if="!server.ssh_public_key"
                v-bind="connect.form(server.uuid)"
                v-slot="{ processing, errors }"
            >
                <h3 class="font-medium">Generate a management SSH key</h3>
                <p class="mt-1 text-sm text-neutral-500">
                    Stacklab creates a unique key pair for this server. You will
                    only install the public key on your machine.
                </p>
                <p v-if="errors.message" class="mt-3 text-sm text-red-600">
                    {{ errors.message }}
                </p>
                <Button class="mt-5" type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Generate SSH key
                </Button>
            </Form>

            <div v-else-if="!fingerprint">
                <h3 class="font-medium">Add this public key to your server</h3>
                <p class="mt-1 text-sm text-neutral-500">
                    Sign in to {{ server.ssh_user }}@{{ server.host }}, then add
                    this entire key as a new line in
                    <code>~/.ssh/authorized_keys</code>.
                </p>

                <div class="relative mt-4 rounded-xl bg-neutral-950 p-4 pr-12">
                    <code
                        class="block text-xs leading-5 break-all text-neutral-100"
                        >{{ server.ssh_public_key }}</code
                    >
                    <button
                        type="button"
                        class="absolute top-3 right-3 rounded-md p-2 text-neutral-400 hover:bg-white/10 hover:text-white"
                        aria-label="Copy public key"
                        @click="copy(server.ssh_public_key!, 'key')"
                    >
                        <Check v-if="copied === 'key'" class="size-4" />
                        <Clipboard v-else class="size-4" />
                    </button>
                </div>

                <div
                    class="mt-4 flex gap-3 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm"
                >
                    <Terminal class="mt-0.5 size-4 shrink-0 text-neutral-500" />
                    <p class="text-neutral-600">
                        Make sure the <code>.ssh</code> directory is mode
                        <code>700</code> and <code>authorized_keys</code> is
                        mode <code>600</code>.
                    </p>
                </div>

                <Form
                    v-bind="verify.form(server.uuid)"
                    class="mt-5"
                    v-slot="{ processing, errors }"
                >
                    <p v-if="errors.message" class="mb-3 text-sm text-red-600">
                        {{ errors.message }}
                    </p>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        I added the key — verify server
                    </Button>
                </Form>
            </div>

            <div v-else>
                <div class="flex items-start gap-3">
                    <ShieldCheck
                        class="mt-0.5 size-5 shrink-0 text-amber-700"
                    />
                    <div>
                        <h3 class="font-medium">
                            Compare the server fingerprint
                        </h3>
                        <p class="mt-1 text-sm text-neutral-500">
                            Run this command directly on your server. Only
                            confirm if its SHA256 fingerprint exactly matches
                            the value below.
                        </p>
                    </div>
                </div>

                <div
                    class="relative mt-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4 pr-12"
                >
                    <code class="text-sm break-all">{{
                        fingerprintCommand
                    }}</code>
                    <button
                        type="button"
                        class="absolute top-2.5 right-3 rounded-md p-2 text-neutral-500 hover:bg-neutral-200"
                        aria-label="Copy fingerprint command"
                        @click="copy(fingerprintCommand, 'command')"
                    >
                        <Check v-if="copied === 'command'" class="size-4" />
                        <Clipboard v-else class="size-4" />
                    </button>
                </div>

                <div class="mt-4 rounded-xl bg-neutral-950 p-4">
                    <p class="text-xs text-neutral-400">
                        Fingerprint detected by Stacklab
                    </p>
                    <code
                        class="mt-1 block text-sm font-semibold break-all text-white"
                        >{{ fingerprint }}</code
                    >
                </div>

                <Form
                    v-bind="confirm.form(server.uuid)"
                    class="mt-5"
                    v-slot="{ processing, errors }"
                >
                    <p
                        v-if="errors.message || errors.connection"
                        class="mb-3 text-sm text-red-600"
                    >
                        {{ errors.message || errors.connection }}
                    </p>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Fingerprints match — confirm connection
                    </Button>
                </Form>
            </div>
        </div>
    </section>
</template>
