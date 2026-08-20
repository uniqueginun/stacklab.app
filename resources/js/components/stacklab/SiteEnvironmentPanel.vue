<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit, update } from '@/routes/sites/environment';
import type { SiteEnvironmentFile, SiteShow } from '@/types';

const props = defineProps<{
    site: SiteShow;
}>();

const form = useForm({
    contents: '',
});

const file = useHttp<Record<string, never>, SiteEnvironmentFile>({});

const canEdit = computed(() => props.site.status === 'deployed');
const isLoading = computed(() => file.response === null && !file.hasErrors);
const hasLoadedContents = computed(() => file.response?.contents != null);
const environmentPath = computed(
    () =>
        file.response?.path ??
        (props.site.root_path
            ? `${props.site.root_path}/shared/.env`
            : 'shared/.env'),
);
const fileErrors = computed(
    () => file.errors as Record<string, string | undefined>,
);
const formErrors = computed(
    () => form.errors as Record<string, string | undefined>,
);

onMounted(() => {
    file.get(edit.url(props.site.uuid), {
        onSuccess: (data) => {
            if (data.contents === null) {
                return;
            }

            form.defaults({ contents: data.contents });
            form.reset();
        },
    });
});

const submit = () => {
    form.submit(update(props.site.uuid), {
        preserveScroll: true,
    });
};
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white"
    >
        <div class="border-b border-neutral-100 px-6 py-5">
            <h2 class="font-semibold">Environment</h2>
            <p class="mt-1 text-sm text-neutral-500">
                Edit the remote
                <span class="font-medium text-neutral-700">.env</span>
                file for this site.
            </p>
        </div>

        <div
            v-if="isLoading"
            class="flex min-h-96 flex-col items-center justify-center gap-3 px-6 py-16"
        >
            <Spinner class="size-6 text-neutral-400" />
            <p class="text-sm text-neutral-500">Loading environment file…</p>
        </div>

        <div v-else-if="file.hasErrors" class="px-6 py-16 text-center">
            <p class="text-sm text-neutral-500">
                Unable to load the environment file.
            </p>
            <InputError class="mt-3" :message="fileErrors.contents" />
            <InputError :message="fileErrors.site" />
        </div>

        <div
            v-else-if="!canEdit || !hasLoadedContents"
            class="px-6 py-16 text-center"
        >
            <p class="text-sm text-neutral-500">
                Deploy the site before editing the environment file.
            </p>
        </div>

        <form v-else class="grid gap-4 px-6 py-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label
                    for="environment-contents"
                    class="text-sm font-normal text-neutral-500"
                >
                    {{ environmentPath }}
                </Label>
                <textarea
                    id="environment-contents"
                    v-model="form.contents"
                    name="contents"
                    spellcheck="false"
                    :disabled="form.processing"
                    class="min-h-96 w-full rounded-xl border border-neutral-200 bg-neutral-950 p-4 font-mono text-xs leading-5 text-neutral-100 outline-none focus:border-neutral-400 disabled:opacity-60"
                />
                <InputError :message="formErrors.contents" />
                <InputError :message="formErrors.site" />
            </div>

            <div>
                <Button
                    type="submit"
                    class="h-10 rounded-lg bg-neutral-950 px-4 text-white hover:bg-neutral-800"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" class="size-4" />
                    {{ form.processing ? 'Saving…' : 'Save environment' }}
                </Button>
            </div>
        </form>

        <div
            class="border-t border-neutral-100 bg-neutral-50/70 px-6 py-3 text-sm text-neutral-500"
        >
            Changes are written to
            <span class="font-medium text-neutral-700">shared/.env</span>
            on the server.
        </div>
    </section>
</template>
