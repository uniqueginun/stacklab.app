<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, Circle, Cloud, Server } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import StacklabMark from '@/components/stacklab/StacklabMark.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index as serversIndex, store } from '@/routes/servers';
import type { ServerProvider } from '@/types';

defineOptions({
    layout: {
        nav: 'none',
    },
});

const provider = ref<ServerProvider>('digitalocean');

const providers = [
    {
        id: 'digitalocean' as const,
        name: 'DigitalOcean',
        description: 'Connect an existing droplet by IP and SSH credentials.',
        icon: Cloud,
    },
    {
        id: 'custom' as const,
        name: 'Custom VPS',
        description:
            'Any provider — Hetzner, AWS, Vultr, Linode, or your own VM.',
        icon: Server,
    },
];
</script>

<template>
    <Head title="Connect a server" />

    <div class="mx-auto max-w-2xl">
        <Link
            :href="serversIndex()"
            class="mb-6 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-900"
        >
            <ArrowLeft class="size-4" />
            Back
        </Link>

        <div class="mb-6 flex items-start gap-3">
            <StacklabMark class="mt-0.5 size-9 shrink-0" />
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Connect a server
                </h1>
                <p class="mt-1 text-sm text-neutral-500">
                    Connect an existing server you already have. stacklab.app
                    will install Nginx, PHP, and your database over SSH.
                </p>
            </div>
        </div>

        <div
            class="rounded-2xl border border-neutral-200/80 bg-white p-6 md:p-8"
        >
            <Form
                v-bind="store.form()"
                v-slot="{ errors, processing }"
                class="grid gap-6"
            >
                <div class="grid gap-2">
                    <Label
                        for="name"
                        class="text-sm font-normal text-neutral-500"
                        >Server name</Label
                    >
                    <Input
                        id="name"
                        name="name"
                        type="text"
                        required
                        autofocus
                        placeholder="e.g. fragrant-forest"
                        class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                    />
                    <InputError :message="errors.name" />
                    <p class="text-sm text-neutral-400">
                        A label to identify this server across stacklab.app.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label class="text-sm font-normal text-neutral-500"
                        >Provider</Label
                    >
                    <input type="hidden" name="provider" :value="provider" />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="option in providers"
                            :key="option.id"
                            type="button"
                            class="relative rounded-xl border p-4 text-left transition-colors"
                            :class="
                                provider === option.id
                                    ? 'border-brand bg-orange-50'
                                    : 'border-neutral-200 bg-white hover:bg-neutral-50'
                            "
                            @click="provider = option.id"
                        >
                            <component
                                :is="provider === option.id ? Check : Circle"
                                class="absolute top-3 right-3 size-4"
                                :class="
                                    provider === option.id
                                        ? 'text-brand'
                                        : 'text-neutral-300'
                                "
                            />
                            <span
                                class="mb-3 flex size-9 items-center justify-center rounded-lg bg-orange-50 text-brand"
                            >
                                <component :is="option.icon" class="size-4" />
                            </span>
                            <p class="font-medium">{{ option.name }}</p>
                            <p class="mt-1 text-sm text-neutral-500">
                                {{ option.description }}
                            </p>
                        </button>
                    </div>
                    <InputError :message="errors.provider" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="host"
                        class="text-sm font-normal text-neutral-500"
                        >Host</Label
                    >
                    <Input
                        id="host"
                        name="host"
                        type="text"
                        required
                        placeholder="167.99.1.1"
                        class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                    />
                    <InputError :message="errors.host" />
                    <p class="text-sm text-neutral-400">
                        The public IP address or hostname of your server.
                    </p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label
                            for="ssh_user"
                            class="text-sm font-normal text-neutral-500"
                            >SSH user</Label
                        >
                        <Input
                            id="ssh_user"
                            name="ssh_user"
                            type="text"
                            required
                            placeholder="root"
                            class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                        />
                        <InputError :message="errors.ssh_user" />
                    </div>
                    <div class="grid gap-2">
                        <Label
                            for="ssh_port"
                            class="text-sm font-normal text-neutral-500"
                            >SSH port</Label
                        >
                        <Input
                            id="ssh_port"
                            name="ssh_port"
                            type="number"
                            required
                            min="1"
                            max="65535"
                            default-value="22"
                            class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                        />
                        <InputError :message="errors.ssh_port" />
                    </div>
                </div>

                <Button
                    type="submit"
                    class="h-11 w-full rounded-lg bg-brand text-white hover:bg-brand/90"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Connect server
                </Button>
            </Form>
        </div>
    </div>
</template>
