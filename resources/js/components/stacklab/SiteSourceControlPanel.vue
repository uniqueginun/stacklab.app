<script setup lang="ts">
import { Form, router, useForm, useHttp } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import StacklabSelect from '@/components/stacklab/StacklabSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { destroy, redirect } from '@/routes/connections/provider';
import { edit, update } from '@/routes/sites/repository';
import type { GitHubAccount, SiteRepositoryCatalog, SiteShow } from '@/types';

const props = defineProps<{
    site: SiteShow;
    github: GitHubAccount;
}>();

const { currentUrl } = useCurrentUrl();

const form = useForm({
    repository: props.site.repository_url ?? '',
    branch: props.site.repository_branch ?? '',
});

const catalog = useHttp<Record<string, never>, SiteRepositoryCatalog>({});
const connectingGithub = ref(false);

const repositories = computed(() => catalog.response?.repositories ?? []);
const branches = computed(() => catalog.response?.branches ?? []);
const hasAttachedRepository = computed(() =>
    Boolean(props.site.repository_url),
);

const loadCatalog = (repository?: string) => {
    if (!props.github.connected) {
        return;
    }

    catalog.get(
        edit.url(
            props.site.uuid,
            repository ? { query: { repository } } : undefined,
        ),
        {
            onSuccess: (data) => {
                if (form.branch !== '' || data.branches.length === 0) {
                    return;
                }

                const selected = data.repositories.find(
                    (item) => item.full_name === form.repository,
                );
                const preferred = selected?.default_branch;

                form.branch =
                    preferred &&
                    data.branches.some((item) => item.name === preferred)
                        ? preferred
                        : data.branches[0].name;
            },
        },
    );
};

onMounted(() => {
    loadCatalog(form.repository || undefined);
});

watch(
    () => form.repository,
    (repository, previous) => {
        if (repository === previous) {
            return;
        }

        if (previous !== undefined) {
            form.branch = '';
        }

        loadCatalog(repository || undefined);
    },
);

const submit = () => {
    form.submit(update(props.site.uuid), {
        preserveScroll: true,
    });
};

const connectGithub = () => {
    if (connectingGithub.value) {
        return;
    }

    connectingGithub.value = true;

    router.visit(
        redirect.url('github', { query: { return: currentUrl.value } }),
        {
            onFinish: () => {
                connectingGithub.value = false;
            },
            onCancel: () => {
                connectingGithub.value = false;
            },
        },
    );
};
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white"
    >
        <div class="border-b border-neutral-100 px-6 py-5">
            <h2 class="font-semibold">Source control</h2>
            <p class="mt-1 text-sm text-neutral-500">
                Connect a GitHub repository to this site. GitHub is the only
                provider Stacklab supports right now.
            </p>
        </div>

        <div class="flex flex-col gap-5 px-6 py-6 sm:flex-row sm:items-center">
            <span
                class="flex size-12 items-center justify-center rounded-xl bg-neutral-950 text-white"
            >
                <svg
                    class="size-6"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0 1 12 6.844a9.56 9.56 0 0 1 2.504.337c1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0 0 22 12.017C22 6.484 17.522 2 12 2Z"
                    />
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-medium">GitHub</p>
                <p class="mt-0.5 text-sm text-neutral-500">
                    <template v-if="github.connected">
                        Connected as
                        <span class="font-medium text-neutral-700"
                            >@{{ github.username }}</span
                        >.
                    </template>
                    <template v-else>
                        Not connected. Link an account, then pick a repository
                        and branch.
                    </template>
                </p>
            </div>
            <Form
                v-if="github.connected"
                v-bind="destroy.form('github')"
                :options="{ preserveScroll: true }"
                class="shrink-0"
                v-slot="{ processing }"
            >
                <Button
                    type="submit"
                    variant="outline"
                    class="h-10 rounded-lg border-neutral-200 bg-white shadow-none"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" class="size-4" />
                    {{ processing ? 'Disconnecting…' : 'Disconnect' }}
                </Button>
            </Form>
            <Button
                v-else
                class="h-10 shrink-0 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
                :disabled="connectingGithub"
                @click="connectGithub"
            >
                <Spinner v-if="connectingGithub" class="size-4" />
                {{ connectingGithub ? 'Connecting…' : 'Connect GitHub' }}
            </Button>
        </div>

        <div
            v-if="github.connected"
            class="border-t border-neutral-100 px-6 py-6"
        >
            <div
                v-if="catalog.processing && repositories.length === 0"
                class="flex items-center gap-2 text-sm text-neutral-500"
            >
                <Spinner class="size-4" />
                Loading repositories…
            </div>

            <form class="grid gap-5" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label
                        for="repository"
                        class="text-sm font-normal text-neutral-500"
                        >Repository</Label
                    >
                    <StacklabSelect
                        id="repository"
                        v-model="form.repository"
                        name="repository"
                        :disabled="catalog.processing || form.processing"
                    >
                        <option value="" disabled>Select a repository</option>
                        <option
                            v-for="repository in repositories"
                            :key="repository.id"
                            :value="repository.full_name"
                        >
                            {{ repository.full_name
                            }}{{ repository.private ? ' · private' : '' }}
                        </option>
                    </StacklabSelect>
                    <InputError :message="form.errors.repository" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="branch"
                        class="text-sm font-normal text-neutral-500"
                        >Branch</Label
                    >
                    <StacklabSelect
                        id="branch"
                        v-model="form.branch"
                        name="branch"
                        :disabled="
                            catalog.processing ||
                            form.processing ||
                            !form.repository
                        "
                    >
                        <option value="" disabled>Select a branch</option>
                        <option
                            v-for="branch in branches"
                            :key="branch.name"
                            :value="branch.name"
                        >
                            {{ branch.name }}
                        </option>
                    </StacklabSelect>
                    <InputError :message="form.errors.branch" />
                </div>

                <div>
                    <Button
                        type="submit"
                        class="h-10 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
                        :disabled="
                            form.processing ||
                            catalog.processing ||
                            !form.repository ||
                            !form.branch
                        "
                    >
                        <Spinner v-if="form.processing" class="size-4" />
                        {{
                            form.processing
                                ? 'Attaching…'
                                : hasAttachedRepository
                                  ? 'Update repository'
                                  : 'Attach repository'
                        }}
                    </Button>
                </div>
            </form>
        </div>

        <div
            class="border-t border-neutral-100 bg-neutral-50/70 px-6 py-3 text-sm text-neutral-500"
        >
            <template v-if="hasAttachedRepository">
                Linked to
                <span class="font-medium text-neutral-700">{{
                    site.repository_url
                }}</span>
                <template v-if="site.repository_branch">
                    · {{ site.repository_branch }}
                </template>
            </template>
            <template v-else-if="github.connected">
                Choose a repository and branch to install a deploy key on this
                server.
            </template>
            <template v-else>
                Connect GitHub to start linking a repository to this site.
            </template>
        </div>
    </section>
</template>
