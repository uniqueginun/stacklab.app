<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Check } from '@lucide/vue';
import { ref } from 'vue';
import SiteAdvancedModal from '@/components/stacklab/SiteAdvancedModal.vue';
import SiteTypeModal from '@/components/stacklab/SiteTypeModal.vue';
import StacklabMark from '@/components/stacklab/StacklabMark.vue';
import StacklabSelect from '@/components/stacklab/StacklabSelect.vue';
import ToggleSwitch from '@/components/stacklab/ToggleSwitch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as serversIndex } from '@/routes/servers';
import { show as siteShow } from '@/routes/sites';

defineOptions({
    layout: {
        nav: 'none',
        workspace: 'fragrant-forest',
    },
});

const siteType = ref('Laravel');
const provider = ref('github');
const repository = ref('1005hoon/chirper');
const branch = ref('main');
const connectDatabase = ref(true);
const database = ref('stacklab');
const subdomain = ref('chirper-ledy7qla');
const installComposer = ref(true);
const generateDeployKey = ref(false);
const showTypes = ref(false);
const showAdvanced = ref(false);

const steps = [
    { label: 'Configuring Nginx', status: 'done' },
    { label: 'Cloning Git repository', status: 'done' },
    { label: 'Creating environment file', status: 'done' },
    {
        label: 'Installing dependencies',
        status: 'active',
        detail: 'We are installing your application dependencies.',
    },
    { label: 'Running database migrations', status: 'pending' },
    { label: 'Making final touches', status: 'pending' },
] as const;
</script>

<template>
    <Head title="Install a Laravel application" />

    <Link
        :href="serversIndex()"
        class="mb-6 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-900"
    >
        <ArrowLeft class="size-4" />
        Back
    </Link>

    <div class="mb-6 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <StacklabMark class="size-9" />
            <h1 class="text-2xl font-semibold tracking-tight">
                Install a {{ siteType }} application
            </h1>
        </div>
        <button
            type="button"
            class="text-sm text-brand"
            @click="showTypes = true"
        >
            Change type
        </button>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-[1fr_280px]">
        <div
            class="rounded-2xl border border-neutral-200/80 bg-white p-6 md:p-8"
        >
            <div class="grid gap-5">
                <div class="grid gap-2">
                    <Label class="text-sm font-normal text-neutral-500"
                        >Source control provider</Label
                    >
                    <StacklabSelect v-model="provider">
                        <option value="github">GitHub</option>
                        <option value="gitlab">GitLab</option>
                    </StacklabSelect>
                </div>
                <div class="grid gap-2">
                    <Label class="text-sm font-normal text-neutral-500"
                        >Repository</Label
                    >
                    <StacklabSelect v-model="repository">
                        <option value="1005hoon/chirper">
                            1005hoon/chirper
                        </option>
                        <option value="1005hoon/zts">1005hoon/zts</option>
                    </StacklabSelect>
                </div>
                <div class="grid gap-2">
                    <Label class="text-sm font-normal text-neutral-500"
                        >Branch</Label
                    >
                    <StacklabSelect v-model="branch">
                        <option value="main">main</option>
                        <option value="develop">develop</option>
                    </StacklabSelect>
                </div>

                <div class="rounded-xl border border-neutral-100 p-4">
                    <div class="mb-3 flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium">
                                Connect to Database
                            </p>
                            <p class="mt-1 text-xs text-neutral-500">
                                Select or create a new database to connect to
                                your site
                            </p>
                        </div>
                        <ToggleSwitch v-model="connectDatabase" />
                    </div>
                    <StacklabSelect v-if="connectDatabase" v-model="database">
                        <option value="stacklab">stacklab</option>
                    </StacklabSelect>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <Label class="text-sm font-normal text-neutral-500"
                            >Stacklab Domain</Label
                        >
                        <button type="button" class="text-sm text-brand">
                            Use custom domain
                        </button>
                    </div>
                    <div
                        class="flex overflow-hidden rounded-lg border border-neutral-200"
                    >
                        <Input
                            v-model="subdomain"
                            class="h-11 rounded-none border-0 shadow-none focus-visible:ring-0"
                        />
                        <span
                            class="flex items-center bg-neutral-50 px-3 text-sm text-neutral-400"
                            >.on-stacklab.app</span
                        >
                    </div>
                    <p class="mt-2 text-xs text-neutral-500">
                        Each site includes a Stacklab domain that can be
                        disabled later.
                    </p>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm">Install Composer dependencies</p>
                    <ToggleSwitch v-model="installComposer" />
                </div>
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm">
                        Generate a site deploy key for your source control
                        provider
                    </p>
                    <ToggleSwitch v-model="generateDeployKey" />
                </div>

                <div class="flex justify-end">
                    <button
                        type="button"
                        class="text-sm text-brand"
                        @click="showAdvanced = true"
                    >
                        Advanced settings
                    </button>
                </div>

                <Button
                    class="h-11 w-full rounded-lg bg-neutral-950 text-white hover:bg-neutral-800"
                    @click="router.visit(siteShow())"
                >
                    Create site
                </Button>
            </div>
        </div>

        <aside class="rounded-2xl border border-neutral-200/80 bg-white p-5">
            <p class="mb-4 font-semibold">Installing</p>
            <ol class="relative space-y-5">
                <li
                    v-for="(step, index) in steps"
                    :key="step.label"
                    class="relative flex gap-3"
                >
                    <span
                        v-if="index < steps.length - 1"
                        class="absolute top-6 left-[9px] h-[calc(100%+8px)] w-px bg-neutral-200"
                    />
                    <span
                        class="relative z-10 mt-0.5 flex size-[18px] shrink-0 items-center justify-center rounded-full"
                        :class="{
                            'bg-neutral-950 text-white': step.status === 'done',
                            'border-2 border-brand': step.status === 'active',
                            'border border-neutral-300':
                                step.status === 'pending',
                        }"
                    >
                        <Check v-if="step.status === 'done'" class="size-3" />
                        <span
                            v-else-if="step.status === 'active'"
                            class="size-1.5 rounded-full bg-brand"
                        />
                        <span
                            v-else
                            class="size-1 rounded-full bg-neutral-300"
                        />
                    </span>
                    <div>
                        <p
                            class="text-sm"
                            :class="
                                step.status === 'pending'
                                    ? 'text-neutral-400'
                                    : 'text-neutral-900'
                            "
                        >
                            {{ step.label }}
                        </p>
                        <p
                            v-if="'detail' in step"
                            class="mt-0.5 text-xs text-neutral-500"
                        >
                            {{ step.detail }}
                        </p>
                    </div>
                </li>
            </ol>
        </aside>
    </div>

    <SiteTypeModal
        v-model:open="showTypes"
        :selected="siteType"
        :redirect="false"
        @select="siteType = $event"
    />
    <SiteAdvancedModal v-model:open="showAdvanced" />
</template>
