<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, ArrowUpRight, Globe } from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import SiteCommandsPanel from '@/components/stacklab/SiteCommandsPanel.vue';
import SiteDeploymentsPanel from '@/components/stacklab/SiteDeploymentsPanel.vue';
import SiteEnvironmentPanel from '@/components/stacklab/SiteEnvironmentPanel.vue';
import SiteInfoPanel from '@/components/stacklab/SiteInfoPanel.vue';
import SiteQueuesPanel from '@/components/stacklab/SiteQueuesPanel.vue';
import SiteSourceControlPanel from '@/components/stacklab/SiteSourceControlPanel.vue';
import SiteSslPanel from '@/components/stacklab/SiteSslPanel.vue';
import StatusBadge from '@/components/stacklab/StatusBadge.vue';
import { show as serverShow } from '@/routes/servers';
import { index as sitesIndex } from '@/routes/sites';
import type {
    GitHubAccount,
    QueueWorker,
    QueueWorkerDefaults,
    ServerOperation,
    SiteCertificate,
    SiteRelease,
    SiteShow,
} from '@/types';

defineOptions({
    layout: {
        nav: 'site',
        activeTab: 'info',
    },
});

const props = withDefaults(
    defineProps<{
        site: SiteShow;
        tab:
            | 'info'
            | 'source'
            | 'deployments'
            | 'environment'
            | 'commands'
            | 'queues'
            | 'ssl';
        github: GitHubAccount;
        operation: ServerOperation | null;
        releases: SiteRelease[];
        certificate: SiteCertificate | null;
        workers?: QueueWorker[];
        php_versions?: string[];
        queue_worker_defaults?: QueueWorkerDefaults | null;
    }>(),
    {
        workers: () => [],
        php_versions: () => [],
        queue_worker_defaults: null,
    },
);

watchEffect(() => {
    setLayoutProps({
        nav: 'site',
        workspace: props.site.domain,
        activeTab: props.tab,
        siteUuid: props.site.uuid,
        siteIsPhp: props.site.is_php,
        siteIsLaravel: props.site.is_laravel,
    });
});

const pageTitle = computed(() => {
    if (props.tab === 'deployments') {
        return `Deployments · ${props.site.domain}`;
    }

    if (props.tab === 'source') {
        return `Source control · ${props.site.domain}`;
    }

    if (props.tab === 'environment') {
        return `Environment · ${props.site.domain}`;
    }

    if (props.tab === 'commands') {
        return `Commands · ${props.site.domain}`;
    }

    if (props.tab === 'queues') {
        return `Queues · ${props.site.domain}`;
    }

    if (props.tab === 'ssl') {
        return `SSL · ${props.site.domain}`;
    }

    return props.site.domain;
});
</script>

<template>
    <Head :title="pageTitle" />

    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <Link
                :href="sitesIndex()"
                class="mb-3 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-900"
            >
                <ArrowLeft class="size-4" />
                Sites
            </Link>
            <div class="flex items-center gap-3">
                <span
                    class="flex size-10 items-center justify-center rounded-full bg-orange-50 text-brand"
                >
                    <Globe class="size-4" />
                </span>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        <a
                            :href="site.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hover:text-neutral-700"
                        >
                            {{ site.domain }}
                        </a>
                    </h1>
                    <p class="mt-0.5 text-sm text-neutral-500">
                        {{ site.type }} ·
                        <Link
                            :href="serverShow(site.server.uuid)"
                            class="hover:text-neutral-900"
                        >
                            {{ site.server.name }}
                        </Link>
                        · {{ site.server.host }}
                    </p>
                </div>
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-3">
            <a
                :href="site.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm font-medium text-neutral-700 transition hover:border-neutral-300 hover:text-neutral-900"
            >
                Visit site
                <ArrowUpRight class="size-3.5" aria-hidden="true" />
            </a>
            <StatusBadge :status="site.status" :label="site.status_label" />
        </div>
    </div>

    <SiteInfoPanel v-if="tab === 'info'" :site="site" />
    <SiteSourceControlPanel
        v-else-if="tab === 'source'"
        :site="site"
        :github="github"
    />
    <SiteEnvironmentPanel v-else-if="tab === 'environment'" :site="site" />
    <SiteCommandsPanel v-else-if="tab === 'commands'" :site="site" />
    <SiteQueuesPanel
        v-else-if="tab === 'queues'"
        :site="site"
        :workers="workers"
        :php-versions="php_versions"
        :defaults="queue_worker_defaults"
        :operation="operation"
    />
    <SiteSslPanel
        v-else-if="tab === 'ssl'"
        :site="site"
        :certificate="certificate"
        :operation="operation"
    />
    <SiteDeploymentsPanel
        v-else-if="tab === 'deployments'"
        :site="site"
        :github="github"
        :operation="operation"
        :releases="releases"
    />
</template>
