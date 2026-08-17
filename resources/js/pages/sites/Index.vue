<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Globe, Plus } from '@lucide/vue';
import { ref } from 'vue';
import SiteTypeModal from '@/components/stacklab/SiteTypeModal.vue';
import StatusBadge from '@/components/stacklab/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { sites } from '@/data/stacklab';
import { show as siteShow } from '@/routes/sites';

defineOptions({
    layout: {
        nav: 'app',
        workspace: 'Personal',
        activeTab: 'Sites',
    },
});

const showSiteTypes = ref(false);
</script>

<template>
    <Head title="Sites" />

    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Sites</h1>
            <p class="mt-1 text-sm text-neutral-500">2 sites across 1 server</p>
        </div>
        <Button
            class="h-10 rounded-lg bg-neutral-950 px-4 text-white hover:bg-neutral-800"
            @click="showSiteTypes = true"
        >
            <Plus class="size-4" />
            Create site
        </Button>
    </div>

    <div
        class="mt-8 overflow-hidden rounded-xl border border-neutral-200/80 bg-white"
    >
        <Link
            v-for="site in sites"
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
