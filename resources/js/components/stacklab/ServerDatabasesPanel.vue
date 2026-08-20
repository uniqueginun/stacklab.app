<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    Check,
    Clipboard,
    Database,
    Eye,
    EyeOff,
    LockKeyhole,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/stacklab/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/servers/databases';
import type { ServerDatabase, ServerOperation, ServerShow } from '@/types';

const props = defineProps<{
    server: ServerShow;
    databases: ServerDatabase[];
    operation: ServerOperation | null;
}>();

const revealed = ref<Record<string, boolean>>({});
const copied = ref<string | null>(null);

const canCreate = computed(() => props.server.has_mysql);
const isCreating = computed(
    () =>
        props.operation?.status === 'pending' ||
        props.operation?.status === 'running',
);

const reveal = (uuid: string) => {
    revealed.value = {
        ...revealed.value,
        [uuid]: !revealed.value[uuid],
    };
};

const copy = async (value: string, uuid: string) => {
    await navigator.clipboard.writeText(value);
    copied.value = uuid;
    window.setTimeout(() => {
        if (copied.value === uuid) {
            copied.value = null;
        }
    }, 2000);
};

const badgeStatus = (status: ServerDatabase['status']) => {
    if (status === 'ready') {
        return 'ready';
    }

    if (status === 'failed') {
        return 'failed';
    }

    return 'pending';
};
</script>

<template>
    <section class="mt-10 space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold">Databases</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    MySQL databases on this server. Passwords are stored
                    encrypted — reveal one to copy it.
                </p>
            </div>
        </div>

        <div
            v-if="!canCreate"
            class="flex items-center gap-4 rounded-xl border border-neutral-200 bg-neutral-50 px-6 py-6"
        >
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-neutral-200 text-neutral-600"
            >
                <LockKeyhole class="size-4" />
            </span>
            <div>
                <p class="font-medium">MySQL is not available</p>
                <p class="mt-1 text-sm text-neutral-500">
                    {{
                        server.is_provisioned
                            ? 'This server profile does not include MySQL.'
                            : 'Provision a PHP server before creating databases.'
                    }}
                </p>
            </div>
        </div>

        <Form
            v-else
            v-bind="store.form(server.uuid)"
            reset-on-success
            class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white"
            v-slot="{ errors, processing }"
        >
            <div class="border-b border-neutral-100 px-6 py-5">
                <h3 class="font-semibold">Create database</h3>
                <p class="mt-1 text-sm text-neutral-500">
                    Stacklab generates a username and password. Copy the
                    password after creation — you can also reveal it later on
                    this page.
                </p>
            </div>
            <div
                class="grid gap-4 px-6 py-5 sm:grid-cols-[1fr_auto] sm:items-end"
            >
                <div class="grid gap-2">
                    <Label for="database_name">Database name</Label>
                    <Input
                        id="database_name"
                        name="name"
                        maxlength="64"
                        placeholder="my_app"
                        :disabled="processing || isCreating"
                    />
                    <InputError :message="errors.name" />
                </div>
                <Button
                    type="submit"
                    class="h-9 rounded-lg"
                    :disabled="processing || isCreating"
                >
                    <Spinner v-if="processing || isCreating" />
                    Create database
                </Button>
            </div>
        </Form>

        <div
            v-if="databases.length > 0"
            class="overflow-hidden rounded-xl border border-neutral-200/80 bg-white"
        >
            <div
                v-for="database in databases"
                :key="database.uuid"
                class="grid gap-4 border-b border-neutral-100 px-5 py-4 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start"
            >
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="flex size-9 items-center justify-center rounded-full bg-orange-50 text-brand"
                        >
                            <Database class="size-4" />
                        </span>
                        <p class="font-medium">{{ database.name }}</p>
                        <StatusBadge
                            :status="badgeStatus(database.status)"
                            :label="
                                database.status === 'ready'
                                    ? 'Ready'
                                    : database.status === 'failed'
                                      ? 'Failed'
                                      : 'Creating'
                            "
                        />
                    </div>
                    <dl class="mt-3 grid gap-2 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <dt class="w-24 shrink-0 text-neutral-400">
                                Username
                            </dt>
                            <dd class="font-mono text-neutral-800">
                                {{ database.username }}
                            </dd>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 text-neutral-500"
                                :aria-label="'Copy username'"
                                @click="
                                    copy(
                                        database.username,
                                        `${database.uuid}-user`,
                                    )
                                "
                            >
                                <Check
                                    v-if="copied === `${database.uuid}-user`"
                                    class="size-4"
                                />
                                <Clipboard v-else class="size-4" />
                            </Button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <dt class="w-24 shrink-0 text-neutral-400">
                                Password
                            </dt>
                            <dd class="min-w-0 font-mono text-neutral-800">
                                {{
                                    revealed[database.uuid]
                                        ? database.password
                                        : '••••••••••••'
                                }}
                            </dd>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 text-neutral-500"
                                :aria-label="
                                    revealed[database.uuid]
                                        ? 'Hide password'
                                        : 'Show password'
                                "
                                @click="reveal(database.uuid)"
                            >
                                <EyeOff
                                    v-if="revealed[database.uuid]"
                                    class="size-4"
                                />
                                <Eye v-else class="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 text-neutral-500"
                                aria-label="Copy password"
                                @click="
                                    copy(
                                        database.password,
                                        `${database.uuid}-pass`,
                                    )
                                "
                            >
                                <Check
                                    v-if="copied === `${database.uuid}-pass`"
                                    class="size-4"
                                />
                                <Clipboard v-else class="size-4" />
                            </Button>
                        </div>
                    </dl>
                    <p
                        v-if="database.failure_message"
                        class="mt-2 text-sm text-red-600"
                    >
                        {{ database.failure_message }}
                    </p>
                </div>
            </div>
        </div>

        <div
            v-else-if="canCreate"
            class="rounded-xl border border-dashed border-neutral-200 bg-white px-6 py-12 text-center"
        >
            <p class="font-medium">No databases yet</p>
            <p class="mt-1 text-sm text-neutral-500">
                Create a database and copy the generated password for your
                application’s .env file.
            </p>
        </div>
    </section>
</template>
