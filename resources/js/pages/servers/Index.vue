<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import StacklabMark from '@/components/stacklab/StacklabMark.vue';
import StatusBadge from '@/components/stacklab/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { servers } from '@/data/stacklab';
import { create as serversCreate, show as serverShow } from '@/routes/servers';

defineOptions({
    layout: {
        nav: 'app',
        workspace: 'Personal',
        activeTab: 'Servers',
    },
});
</script>

<template>
    <Head title="Servers" />

    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Servers</h1>
            <p class="mt-1 text-sm text-neutral-500">
                3 servers across 3 providers · 7 sites
            </p>
        </div>
        <Button
            as-child
            class="h-10 rounded-lg bg-neutral-950 px-4 text-white hover:bg-neutral-800"
        >
            <Link :href="serversCreate()">
                <Plus class="size-4" />
                Create server
            </Link>
        </Button>
    </div>

    <div
        class="mt-8 overflow-hidden rounded-xl border border-neutral-200/80 bg-white"
    >
        <Link
            v-for="server in servers"
            :key="server.slug"
            :href="server.slug === 'fragrant-forest' ? serverShow() : '#'"
            class="flex items-center gap-4 border-b border-neutral-100 px-5 py-4 last:border-b-0 hover:bg-neutral-50/80"
        >
            <StacklabMark class="size-9 rounded-lg" />
            <div class="min-w-0 flex-1">
                <p class="font-medium">{{ server.name }}</p>
                <p class="text-sm text-neutral-500">
                    {{ server.ip }} · {{ server.provider }}
                </p>
            </div>
            <div class="hidden min-w-40 sm:block">
                <p class="text-sm text-neutral-600">{{ server.region }}</p>
                <p class="text-sm text-neutral-500">
                    {{ server.size }} · {{ server.vcpu }} · {{ server.ram }}
                </p>
            </div>
            <p class="hidden w-20 text-sm text-neutral-500 md:block">
                {{ server.sitesCount }}
                {{ server.sitesCount === 1 ? 'site' : 'sites' }}
            </p>
            <StatusBadge :status="server.status" />
        </Link>
    </div>
</template>
