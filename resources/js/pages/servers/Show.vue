<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, LockKeyhole, Plus } from '@lucide/vue';
import { ref, watchEffect } from 'vue';
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
import type { ServerShow } from '@/types';

defineOptions({
    layout: {
        nav: 'server',
        activeTab: 'overview',
    },
});

const props = defineProps<{
    server: ServerShow;
    sshFingerprint: string | null;
    sshHostKeyType: string | null;
}>();

watchEffect(() => {
    setLayoutProps({
        nav: 'server',
        workspace: props.server.name,
        activeTab: 'overview',
        serverUuid: props.server.uuid,
    });
});

const showSiteTypes = ref(false);
</script>

<template>
    <Head :title="server.name" />

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
                    {{ server.name }}
                </h1>
                <p class="text-sm text-neutral-500">
                    {{ server.host }} · {{ server.provider_label }}
                </p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <StatusBadge :status="server.connection_status" />
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
        v-if="!server.is_connected"
        :server="server"
        :fingerprint="sshFingerprint"
        :host-key-type="sshHostKeyType"
    />

    <div id="sites" class="mt-10 flex items-center justify-between">
        <h2 class="text-xl font-semibold">Sites</h2>
        <Button
            v-if="server.is_connected"
            variant="outline"
            class="h-9 rounded-lg border-neutral-200 bg-white shadow-none"
            @click="showSiteTypes = true"
        >
            <Plus class="size-4" />
            Create site
        </Button>
    </div>

    <div
        v-if="server.is_connected"
        class="mt-4 rounded-xl border border-dashed border-neutral-200 bg-white px-6 py-12 text-center"
    >
        <p class="font-medium">No sites yet</p>
        <p class="mt-1 text-sm text-neutral-500">
            Create a site on this server when you are ready to deploy.
        </p>
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
                Confirm the SSH connection above before creating sites or
                provisioning this server.
            </p>
        </div>
    </div>

    <SiteTypeModal v-if="server.is_connected" v-model:open="showSiteTypes" />
</template>
