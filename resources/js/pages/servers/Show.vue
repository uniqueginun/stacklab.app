<script setup lang="ts">
import { Form, Head, Link, setLayoutProps, usePoll } from '@inertiajs/vue3';
import { ArrowLeft, Globe, LockKeyhole, Plus } from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import ProvisionPanel from '@/components/stacklab/ProvisionPanel.vue';
import ProvisionStepper from '@/components/stacklab/ProvisionStepper.vue';
import ServerDatabasesPanel from '@/components/stacklab/ServerDatabasesPanel.vue';
import SiteTypeModal from '@/components/stacklab/SiteTypeModal.vue';
import SshSetupPanel from '@/components/stacklab/SshSetupPanel.vue';
import StacklabMark from '@/components/stacklab/StacklabMark.vue';
import StatusBadge from '@/components/stacklab/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { destroy, index as serversIndex } from '@/routes/servers';
import { show as siteShow } from '@/routes/sites';
import type {
    ProvisioningProfileOption,
    ServerDatabase,
    ServerOperation,
    ServerShow,
    ServerShowTab,
    SiteIndex,
} from '@/types';

defineOptions({
    layout: {
        nav: 'server',
        activeTab: 'overview',
    },
});

const props = withDefaults(
    defineProps<{
        server: ServerShow;
        profiles: ProvisioningProfileOption[];
        operation: ServerOperation | null;
        sshFingerprint: string | null;
        sshHostKeyType: string | null;
        sites: SiteIndex[];
        tab?: ServerShowTab;
        databases?: ServerDatabase[];
    }>(),
    {
        tab: 'overview',
        databases: () => [],
    },
);

watchEffect(() => {
    setLayoutProps({
        nav: 'server',
        workspace: props.server.name,
        activeTab: props.tab,
        serverUuid: props.server.uuid,
    });
});

const showSiteTypes = ref(false);

const isProvisioning = computed(
    () =>
        props.tab === 'overview' &&
        (props.operation?.status === 'pending' ||
            props.operation?.status === 'running'),
);

const badgeStatus = computed(() =>
    isProvisioning.value ? 'provisioning' : props.server.connection_status,
);

const showStepper = computed(
    () => props.tab === 'overview' && props.operation !== null,
);
const showProvisionForm = computed(
    () =>
        props.tab === 'overview' &&
        props.server.can_provision &&
        !isProvisioning.value,
);

const shouldPoll = computed(
    () =>
        props.operation?.status === 'pending' ||
        props.operation?.status === 'running' ||
        (props.tab === 'databases' &&
            props.databases.some((database) => database.status === 'pending')),
);

const { start, stop } = usePoll(
    2000,
    {
        only: ['server', 'operation', 'databases'],
    },
    {
        autoStart: false,
    },
);

watch(
    shouldPoll,
    (active) => {
        if (active) {
            start();

            return;
        }

        stop();
    },
    { immediate: true },
);
</script>

<template>
    <Head
        :title="
            tab === 'databases' ? `Databases · ${server.name}` : server.name
        "
    />

    <Link
        :href="serversIndex()"
        class="mb-6 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-900"
    >
        <ArrowLeft class="size-4" />
        Servers
    </Link>

    <div class="flex items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <StacklabMark class="size-10" />
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ server.name }}
                </h1>
                <p class="text-sm text-neutral-500">
                    {{ server.host }} · {{ server.provider_label }}
                </p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <StatusBadge :status="badgeStatus" />
            <Dialog>
                <DialogTrigger as-child>
                    <Button
                        variant="outline"
                        class="h-9 rounded-lg border-neutral-200 bg-white text-red-600 shadow-none hover:bg-red-50 hover:text-red-700"
                    >
                        Delete
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="destroy.form(server.uuid)"
                        class="space-y-6"
                        v-slot="{ processing }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>Delete this server?</DialogTitle>
                            <DialogDescription>
                                {{ server.name }} will be removed from
                                stacklab.app. This cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                            >
                                <Spinner v-if="processing" />
                                Delete server
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
            class="rounded-xl border border-neutral-200/80 bg-white px-5 py-4 shadow-sm"
        >
            <p class="text-xs text-neutral-400">Host</p>
            <p class="mt-1 font-semibold">{{ server.host }}</p>
        </div>
        <div
            class="rounded-xl border border-neutral-200/80 bg-white px-5 py-4 shadow-sm"
        >
            <p class="text-xs text-neutral-400">Provider</p>
            <p class="mt-1 font-semibold">{{ server.provider_label }}</p>
        </div>
        <div
            class="rounded-xl border border-neutral-200/80 bg-white px-5 py-4 shadow-sm"
        >
            <p class="text-xs text-neutral-400">SSH user</p>
            <p class="mt-1 font-semibold">{{ server.ssh_user }}</p>
        </div>
        <div
            class="rounded-xl border border-neutral-200/80 bg-white px-5 py-4 shadow-sm"
        >
            <p class="text-xs text-neutral-400">SSH port</p>
            <p class="mt-1 font-semibold">{{ server.ssh_port }}</p>
        </div>
    </div>

    <SshSetupPanel
        v-if="tab === 'overview' && !server.is_connected"
        :server="server"
        :fingerprint="sshFingerprint"
        :host-key-type="sshHostKeyType"
    />

    <div v-else-if="tab === 'overview'" class="mt-8 space-y-4">
        <ProvisionStepper
            v-if="showStepper && operation"
            :operation="operation"
        />
        <ProvisionPanel
            v-if="showProvisionForm"
            :server="server"
            :profiles="profiles"
        />
    </div>

    <template v-if="tab === 'overview'">
        <div id="sites" class="mt-10 flex items-center justify-between">
            <h2 class="text-xl font-semibold">Sites</h2>
            <Button
                v-if="server.is_provisioned"
                class="h-9 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
                @click="showSiteTypes = true"
            >
                <Plus class="size-4" />
                Create site
            </Button>
        </div>

        <div
            v-if="sites.length > 0"
            class="mt-4 overflow-hidden rounded-xl border border-neutral-200/80 bg-white"
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
                        {{ site.type }}
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

        <div
            v-else-if="server.is_provisioned"
            class="mt-4 rounded-xl border border-dashed border-neutral-200 bg-white px-6 py-12 text-center"
        >
            <p class="font-medium">No sites yet</p>
            <p class="mt-1 text-sm text-neutral-500">
                Create a site on this server, then connect GitHub to deploy.
            </p>
            <Button
                class="mt-6 h-10 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
                @click="showSiteTypes = true"
            >
                Create site
            </Button>
        </div>

        <div
            v-else
            class="mt-4 flex items-center gap-4 rounded-xl border border-neutral-200 bg-neutral-50 px-6 py-6"
        >
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-neutral-200 text-neutral-600"
            >
                <LockKeyhole class="size-4" />
            </span>
            <div>
                <p class="font-medium">Server actions are locked</p>
                <p class="mt-1 text-sm text-neutral-500">
                    {{
                        server.is_connected
                            ? 'Provision this server before creating sites.'
                            : 'Confirm the SSH connection above before creating sites or provisioning this server.'
                    }}
                </p>
            </div>
        </div>

        <SiteTypeModal
            v-if="server.is_provisioned"
            v-model:open="showSiteTypes"
            :server-uuid="server.uuid"
        />
    </template>

    <template v-if="tab === 'databases'">
        <div
            v-if="
                operation &&
                ['pending', 'running', 'failed'].includes(operation.status)
            "
            class="mt-8"
        >
            <ProvisionStepper :operation="operation" />
        </div>
        <ServerDatabasesPanel
            :server="server"
            :databases="databases"
            :operation="operation"
        />
    </template>
</template>
