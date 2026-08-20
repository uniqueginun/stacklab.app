<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import SiteTypeModal from '@/components/stacklab/SiteTypeModal.vue';
import StacklabMark from '@/components/stacklab/StacklabMark.vue';
import StacklabSelect from '@/components/stacklab/StacklabSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { isSiteType, type SiteType } from '@/data/stacklab';
import { index as serversIndex } from '@/routes/servers';
import { index as sitesIndex, store } from '@/routes/sites';
import type { SiteCreateServer } from '@/types';

defineOptions({
    layout: {
        nav: 'none',
        workspace: 'Personal',
    },
});

const props = withDefaults(
    defineProps<{
        type?: string | null;
        server?: string | null;
        servers: SiteCreateServer[];
    }>(),
    {
        type: null,
        server: null,
    },
);

const defaultWebDirectory = (type: SiteType) =>
    type === 'HTML' ? '/' : '/public';

const initialType = (
    isSiteType(props.type) ? props.type : 'Laravel'
) as SiteType;

const initialServer =
    props.servers.find((item) => item.uuid === props.server) ??
    props.servers[0] ??
    null;

const form = useForm({
    server: initialServer?.uuid ?? '',
    type: initialType,
    domain: '',
    web_directory: defaultWebDirectory(initialType),
});

const webDirectoryTouched = ref(false);
const showTypes = ref(false);

const pageTitle = computed(() =>
    form.type === 'HTML'
        ? 'Create a static site'
        : `Install a ${form.type} application`,
);

watch(
    () => form.type,
    (type) => {
        if (!webDirectoryTouched.value) {
            form.web_directory = defaultWebDirectory(type);
        }
    },
);

const submit = () => {
    form.submit(store());
};
</script>

<template>
    <Head :title="pageTitle" />

    <div class="mx-auto max-w-2xl">
        <Link
            :href="sitesIndex()"
            class="mb-6 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-900"
        >
            <ArrowLeft class="size-4" />
            Back
        </Link>

        <div class="mb-6 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <StacklabMark class="size-9" />
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ pageTitle }}
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

        <div
            v-if="servers.length === 0"
            class="rounded-2xl border border-dashed border-neutral-200 bg-white px-6 py-16 text-center"
        >
            <p class="font-medium">No provisioned servers</p>
            <p class="mt-1 text-sm text-neutral-500">
                Provision a connected server before creating a site.
            </p>
            <Button
                as-child
                class="mt-6 h-10 rounded-lg bg-neutral-950 px-4 text-white hover:bg-neutral-800"
            >
                <Link :href="serversIndex()">View servers</Link>
            </Button>
        </div>

        <div
            v-else
            class="rounded-2xl border border-neutral-200/80 bg-white p-6 md:p-8"
        >
            <form class="grid gap-5" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label
                        for="server"
                        class="text-sm font-normal text-neutral-500"
                        >Server</Label
                    >
                    <StacklabSelect id="server" v-model="form.server">
                        <option
                            v-for="item in servers"
                            :key="item.uuid"
                            :value="item.uuid"
                        >
                            {{ item.name }} · {{ item.host
                            }}{{ item.os_label ? ` · ${item.os_label}` : '' }}
                        </option>
                    </StacklabSelect>
                    <InputError :message="form.errors.server" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="domain"
                        class="text-sm font-normal text-neutral-500"
                        >Domain</Label
                    >
                    <Input
                        id="domain"
                        v-model="form.domain"
                        type="text"
                        required
                        autofocus
                        placeholder="stacklab.app"
                        class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                    />
                    <InputError :message="form.errors.domain" />
                    <p class="text-sm text-neutral-400">
                        Used as the Nginx server name for this site.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label
                        for="web_directory"
                        class="text-sm font-normal text-neutral-500"
                        >Web directory</Label
                    >
                    <Input
                        id="web_directory"
                        v-model="form.web_directory"
                        type="text"
                        required
                        placeholder="/public"
                        class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                        @input="webDirectoryTouched = true"
                    />
                    <InputError :message="form.errors.web_directory" />
                    <p class="text-sm text-neutral-400">
                        The directory Nginx will serve, relative to the site
                        root.
                    </p>
                </div>

                <InputError :message="form.errors.type" />

                <Button
                    type="submit"
                    class="h-11 w-full rounded-lg bg-neutral-950 text-white hover:bg-neutral-800"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Create site
                </Button>
            </form>
        </div>
    </div>

    <SiteTypeModal
        v-model:open="showTypes"
        :selected="form.type"
        :redirect="false"
        @select="form.type = $event"
    />
</template>
