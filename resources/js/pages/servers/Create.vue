<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { ref } from 'vue';
import ServerAdvancedModal from '@/components/stacklab/ServerAdvancedModal.vue';
import ServerCredentialsModal from '@/components/stacklab/ServerCredentialsModal.vue';
import StacklabMark from '@/components/stacklab/StacklabMark.vue';
import StacklabSelect from '@/components/stacklab/StacklabSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { serverSizes } from '@/data/stacklab';
import { index as serversIndex } from '@/routes/servers';

defineOptions({
    layout: {
        nav: 'none',
        workspace: 'Personal',
    },
});

const type = ref('app');
const region = ref('nyc1');
const network = ref('managed');
const size = ref('small');
const showAdvanced = ref(false);
const showCredentials = ref(false);
</script>

<template>
    <Head title="Create server" />

    <div class="mx-auto max-w-2xl">
        <Link
            :href="serversIndex()"
            class="mb-6 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-900"
        >
            <ArrowLeft class="size-4" />
            Back
        </Link>

        <div class="mb-6 flex items-center gap-3">
            <StacklabMark class="size-9" />
            <h1 class="text-2xl font-semibold tracking-tight">
                Configure fragrant-forest
            </h1>
        </div>

        <div
            class="rounded-2xl border border-neutral-200/80 bg-white p-6 md:p-8"
        >
            <div class="grid gap-5">
                <div class="grid gap-2">
                    <Label
                        class="text-[11px] font-medium tracking-wider text-neutral-400 uppercase"
                        >Type</Label
                    >
                    <StacklabSelect v-model="type">
                        <option value="app">App server</option>
                        <option value="worker">Worker server</option>
                        <option value="database">Database server</option>
                    </StacklabSelect>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label class="text-sm font-normal text-neutral-500"
                            >Region</Label
                        >
                        <StacklabSelect v-model="region">
                            <option value="nyc1">New York 1</option>
                            <option value="sfo3">San Francisco 3</option>
                            <option value="fsn1">Falkenstein 1</option>
                        </StacklabSelect>
                    </div>
                    <div class="grid gap-2">
                        <Label class="text-sm font-normal text-neutral-500"
                            >Private network</Label
                        >
                        <StacklabSelect v-model="network">
                            <option value="managed">Stacklab managed</option>
                            <option value="none">None</option>
                        </StacklabSelect>
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <p class="font-medium">Server size</p>
                        <button type="button" class="text-sm text-brand">
                            Show more sizes
                        </button>
                    </div>
                    <div class="space-y-2">
                        <button
                            v-for="tier in serverSizes"
                            :key="tier.id"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left transition-colors"
                            :class="
                                size === tier.id
                                    ? 'border-brand bg-orange-50'
                                    : 'border-neutral-200 bg-white hover:bg-neutral-50'
                            "
                            @click="size = tier.id"
                        >
                            <StacklabMark class="size-8" />
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ tier.name }}</p>
                                <p class="text-sm text-neutral-500">
                                    {{ tier.specs }}
                                </p>
                            </div>
                            <p class="text-sm font-medium">{{ tier.price }}</p>
                        </button>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button
                            type="button"
                            class="text-sm text-brand"
                            @click="showAdvanced = true"
                        >
                            Advanced settings
                        </button>
                    </div>
                </div>

                <Button
                    class="h-11 w-full rounded-lg bg-neutral-950 text-white hover:bg-neutral-800"
                    @click="showCredentials = true"
                >
                    Create server
                </Button>
            </div>
        </div>
    </div>

    <ServerAdvancedModal v-model:open="showAdvanced" />
    <ServerCredentialsModal v-model:open="showCredentials" />
</template>
