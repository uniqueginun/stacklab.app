<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/stacklab/EmptyState.vue';
import StacklabMark from '@/components/stacklab/StacklabMark.vue';
import StatusBadge from '@/components/stacklab/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { create as serversCreate, show as serverShow } from '@/routes/servers';
import type { ServerIndex } from '@/types';

defineOptions({
    layout: {
        nav: 'app',
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
            class="h-10 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
        >
            <Link :href="serversCreate()">
                <Plus class="size-4" />
                Connect server
            </Link>
        </Button>
    </div>

    <EmptyState
        v-if="servers.length === 0"
        title="No servers yet"
        description="Connect a VPS you already own. StackLab provisions the stack over SSH, then you deploy from GitHub."
        :steps="[
            'Connect over SSH',
            'Provision the stack',
            'Deploy from GitHub',
        ]"
    >
        <Button
            as-child
            class="h-10 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
        >
            <Link :href="serversCreate()">Connect server</Link>
        </Button>
    </EmptyState>

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
