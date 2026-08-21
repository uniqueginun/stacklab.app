<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Globe, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/stacklab/EmptyState.vue';
import SiteTypeModal from '@/components/stacklab/SiteTypeModal.vue';
import StatusBadge from '@/components/stacklab/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { show as siteShow } from '@/routes/sites';
import type { SiteIndex } from '@/types';

defineOptions({
    layout: {
        nav: 'app',
        activeTab: 'Sites',
    },
});

const props = defineProps<{
    sites: SiteIndex[];
}>();

const showSiteTypes = ref(false);

const subtitle = computed(() => {
    if (props.sites.length === 0) {
        return 'No sites yet';
    }

    const servers = new Set(props.sites.map((site) => site.server.uuid)).size;
    const siteLabel = props.sites.length === 1 ? 'site' : 'sites';
    const serverLabel = servers === 1 ? 'server' : 'servers';

    return `${props.sites.length} ${siteLabel} across ${servers} ${serverLabel}`;
});
</script>

<template>
    <Head title="Sites" />

    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Sites</h1>
            <p class="mt-1 text-sm text-neutral-500">{{ subtitle }}</p>
        </div>
        <Button
            class="h-10 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
            @click="showSiteTypes = true"
        >
            <Plus class="size-4" />
            Create site
        </Button>
    </div>

    <EmptyState
        v-if="sites.length === 0"
        title="No sites yet"
        description="Create a site on a provisioned server, then connect GitHub to ship a release."
        :steps="['Pick a server', 'Create the site', 'Deploy from GitHub']"
    >
        <Button
            class="h-10 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
            @click="showSiteTypes = true"
        >
            Create site
        </Button>
    </EmptyState>

    <div
        v-else
        class="mt-8 overflow-hidden rounded-xl border border-neutral-200/80 bg-white"
    >
        <Link
            v-for="site in sites"
            :key="site.uuid"
            :href="siteShow(site.uuid)"
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
                    {{ site.server.name }} · {{ site.type }}
                    <span v-if="site.repository_url">
                        · {{ site.repository_url
                        }}{{
                            site.repository_branch
                                ? ` · ${site.repository_branch}`
                                : ''
                        }}
                    </span>
                </p>
            </div>
            <StatusBadge :status="site.status" :label="site.status_label" />
        </Link>
    </div>

    <SiteTypeModal v-model:open="showSiteTypes" />
</template>
