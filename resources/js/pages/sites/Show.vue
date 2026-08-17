<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUpFromLine,
    CheckCircle2,
    Clock,
    GitBranch,
    MousePointer2,
    Sparkles,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { currentSite } from '@/data/stacklab';
import { show as serverShow } from '@/routes/servers';
import { create as sitesCreate } from '@/routes/sites';

defineOptions({
    layout: {
        nav: 'site',
        workspace: 'chirper.on-stacklab.app',
        activeTab: 'deployments',
    },
});

const buildLogs = [
    { text: '=> Warming up deployment workers', accent: false },
    {
        text: '=> Preparing to build site chirper.on-stacklab.app for commit 48dc5d5d9886b430307674a4390726666f474e0b',
        accent: false,
    },
    { text: '=> Zero downtime deployments enabled', accent: true },
    { text: '=> Build ready to be deployed', accent: true },
];

const deployLogs = [
    '- Installing league/config (v1.2.0): Extracting archive',
    '- Installing league/commonmark (2.9.0): Extracting archive',
    '  0/77 [>---------------------------]   0%',
    ' 20/77 [=======---------------------]  25%',
    ' 48/77 [=================>----------]  62%',
];
</script>

<template>
    <Head title="Deployment details" />

    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">
            Deployment details · {{ currentSite.commit }}
        </h1>
        <div
            class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-neutral-600"
        >
            <span class="inline-flex items-center gap-1.5">
                <span
                    class="flex size-5 items-center justify-center rounded-full bg-orange-50 text-brand"
                >
                    <ArrowUpFromLine class="size-3" />
                </span>
                Deploying
            </span>
            <span class="inline-flex items-center gap-1.5">
                <MousePointer2 class="size-3.5 text-neutral-400" />
                Manual
            </span>
            <span class="inline-flex items-center gap-1.5">
                <GitBranch class="size-3.5 text-neutral-400" />
                stylize this
            </span>
            <span class="inline-flex items-center gap-1.5">
                <Clock class="size-3.5 text-neutral-400" />
                Just now
            </span>
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-orange-50 px-2.5 py-1 text-xs font-medium text-orange-700"
            >
                <span class="size-1.5 rounded-full bg-brand" />
                Deploying
            </span>
        </div>
    </div>

    <div class="grid gap-5">
        <section class="rounded-2xl border border-neutral-200/80 bg-white p-5">
            <div class="mb-3 flex items-center gap-2 font-medium">
                <CheckCircle2 class="size-4 text-neutral-400" />
                Build logs
            </div>
            <pre
                class="overflow-x-auto rounded-xl bg-neutral-50 p-4 font-mono text-xs leading-6 text-neutral-700"
            ><span v-for="line in buildLogs" :key="line.text" :class="line.accent ? 'text-brand' : ''">{{ line.text }}
</span></pre>
        </section>

        <section class="rounded-2xl border border-neutral-200/80 bg-white p-5">
            <div class="mb-3 flex items-center gap-2 font-medium">
                <Sparkles class="size-4 text-neutral-400" />
                Deployment logs
            </div>
            <pre
                class="overflow-x-auto rounded-xl bg-neutral-50 p-4 font-mono text-xs leading-6 text-neutral-700"
                >{{ deployLogs.join('\n') }}</pre>
        </section>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <Button
            as-child
            variant="outline"
            class="h-10 rounded-lg border-neutral-200 bg-white shadow-none"
        >
            <Link :href="serverShow()">Back to fragrant-forest</Link>
        </Button>
        <Button
            as-child
            class="h-10 rounded-lg bg-neutral-950 text-white hover:bg-neutral-800"
        >
            <Link :href="sitesCreate()">Create another site</Link>
        </Button>
    </div>

    <div
        class="fixed bottom-6 left-1/2 z-40 flex -translate-x-1/2 items-center gap-2 rounded-full bg-neutral-800 px-4 py-2 text-sm text-white shadow-lg"
    >
        <span class="size-2 animate-pulse rounded-full bg-brand" />
        <span>
            Deploying
            <strong>{{ currentSite.commit }}</strong>
            on
            <strong>{{ currentSite.domain }}</strong>
        </span>
    </div>
</template>
