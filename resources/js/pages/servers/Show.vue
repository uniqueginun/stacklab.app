<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Globe, Plus } from '@lucide/vue';
import { ref } from 'vue';
import SiteTypeModal from '@/components/stacklab/SiteTypeModal.vue';
import StacklabMark from '@/components/stacklab/StacklabMark.vue';
import StatusBadge from '@/components/stacklab/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { currentServer, sites } from '@/data/stacklab';
import { index as serversIndex } from '@/routes/servers';
import { show as siteShow } from '@/routes/sites';

defineOptions({
    layout: {
        nav: 'server',
        workspace: 'fragrant-forest',
        activeTab: 'overview',
    },
});

const showSiteTypes = ref(false);
const serverSites = sites.filter(
    (site) => site.serverSlug === currentServer.slug,
);
</script>

<template>
    <Head :title="currentServer.name" />

    <Link
        :href="serversIndex()"
        class="mb-6 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-900"
    >
        <ArrowLeft class="size-4" />
        Back
    </Link>

    <div class="flex items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <StacklabMark class="size-10" />
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ currentServer.name }}
                </h1>
                <p class="text-sm text-neutral-500">
                    {{ currentServer.ip }} · {{ currentServer.provider }} ·
                    {{ currentServer.region }}
                </p>
            </div>
        </div>
        <StatusBadge status="connected" />
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
            class="rounded-xl border border-neutral-200/80 bg-white px-5 py-4 shadow-sm"
        >
            <p class="text-xs text-neutral-400">Server OS</p>
            <p class="mt-1 font-semibold">{{ currentServer.os }}</p>
        </div>
        <div
            class="rounded-xl border border-neutral-200/80 bg-white px-5 py-4 shadow-sm"
        >
            <p class="text-xs text-neutral-400">PHP version</p>
            <p class="mt-1 font-semibold">{{ currentServer.php }}</p>
        </div>
        <div
            class="rounded-xl border border-neutral-200/80 bg-white px-5 py-4 shadow-sm"
        >
            <p class="text-xs text-neutral-400">Database</p>
            <p class="mt-1 font-semibold">{{ currentServer.database }}</p>
        </div>
        <div
            class="rounded-xl border border-neutral-200/80 bg-white px-5 py-4 shadow-sm"
        >
            <p class="text-xs text-neutral-400">Size</p>
            <p class="mt-1 font-semibold">
                {{ currentServer.size }} - {{ currentServer.vcpu }} -
                {{ currentServer.ram }}
            </p>
        </div>
    </div>

    <div id="sites" class="mt-10 flex items-center justify-between">
        <h2 class="text-xl font-semibold">Sites</h2>
        <Button
            variant="outline"
            class="h-9 rounded-lg border-neutral-200 bg-white shadow-none"
            @click="showSiteTypes = true"
        >
            <Plus class="size-4" />
            Create site
        </Button>
    </div>

    <div
        class="mt-4 overflow-hidden rounded-xl border border-neutral-200/80 bg-white"
    >
        <Link
            v-for="site in serverSites"
            :key="site.slug"
            :href="site.slug === 'chirper' ? siteShow() : '#'"
            class="flex items-center gap-4 border-b border-neutral-100 px-5 py-4 last:border-b-0 hover:bg-neutral-50/80"
        >
            <span
                class="flex size-9 items-center justify-center rounded-full bg-orange-50 text-brand"
            >
                <Globe class="size-4" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-medium">{{ site.domain }}</p>
                <p class="text-sm text-neutral-500">
                    {{ site.repository }} · {{ site.branch }} ·
                    {{ site.stack }}
                </p>
            </div>
            <p class="hidden font-mono text-sm text-neutral-400 sm:block">
                {{ site.commit }}
            </p>
            <StatusBadge :status="site.status" />
        </Link>
    </div>

    <SiteTypeModal v-model:open="showSiteTypes" />
</template>
