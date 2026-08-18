<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import StacklabMark from '@/components/stacklab/StacklabMark.vue';
import StatusBadge from '@/components/stacklab/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { create as serversCreate, show as serverShow } from '@/routes/servers';
import type { ServerIndex } from '@/types';

defineOptions({
    layout: {
        nav: 'app',
        workspace: 'Personal',
        activeTab: 'Servers',
    },
});

const props = defineProps<{
    servers: ServerIndex[];
}>();

const subtitle = computed(() =>
    props.servers.length === 1 ? '1 server' : `${props.servers.length} servers`,
);
</script>

<template>
    <Head title="Servers" />

    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Servers</h1>
            <p class="mt-1 text-sm text-neutral-500">
                {{ subtitle }}
            </p>
        </div>
        <Button
            as-child
            class="h-10 rounded-lg bg-neutral-950 px-4 text-white hover:bg-neutral-800"
        >
            <Link :href="serversCreate()">
                <Plus class="size-4" />
                Connect server
            </Link>
        </Button>
    </div>

    <div
        v-if="servers.length === 0"
        class="mt-8 rounded-xl border border-dashed border-neutral-200 bg-white px-6 py-16 text-center"
    >
        <p class="font-medium">No servers yet</p>
        <p class="mt-1 text-sm text-neutral-500">
            Connect an existing VPS to provision Nginx, PHP, and your database.
        </p>
        <Button
            as-child
            class="mt-6 h-10 rounded-lg bg-neutral-950 px-4 text-white hover:bg-neutral-800"
        >
            <Link :href="serversCreate()">Connect server</Link>
        </Button>
    </div>

    <div
        v-else
        class="mt-8 overflow-hidden rounded-xl border border-neutral-200/80 bg-white"
    >
        <Link
            v-for="server in servers"
            :key="server.uuid"
            :href="serverShow(server.uuid)"
            class="flex items-center gap-4 border-b border-neutral-100 px-5 py-4 last:border-b-0 hover:bg-neutral-50/80"
        >
            <StacklabMark class="size-9 rounded-lg" />
            <div class="min-w-0 flex-1">
                <p class="font-medium">{{ server.name }}</p>
                <p class="text-sm text-neutral-500">
                    {{ server.host }} · {{ server.provider_label }}
                </p>
            </div>
            <StatusBadge :status="server.connection_status" />
        </Link>
    </div>
</template>
