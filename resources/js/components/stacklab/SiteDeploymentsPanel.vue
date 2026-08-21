<script setup lang="ts">
import { Form, useForm, usePoll } from '@inertiajs/vue3';
import { Check, GitCommitHorizontal, Rocket, RotateCcw, X } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import { store as storeDeployment } from '@/actions/App/Http/Controllers/SiteDeployController';
import { store as storeRollback } from '@/actions/App/Http/Controllers/SiteRollbackController';
import InputError from '@/components/InputError.vue';
import ToggleSwitch from '@/components/stacklab/ToggleSwitch.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type {
    DeploymentOptions,
    GitHubAccount,
    ServerOperation,
    SiteRelease,
    SiteShow,
} from '@/types';

const optionLabels: Record<keyof DeploymentOptions, string> = {
    run_composer: 'Composer install',
    run_npm: 'NPM install & build',
    run_migrations: 'Run migrations',
    run_caches: 'Cache config, routes, and views',
    run_queue_restart: 'Restart queue workers',
    run_hook: 'Run deploy hook',
};

const props = defineProps<{
    site: SiteShow;
    github: GitHubAccount;
    operation: ServerOperation | null;
    releases: SiteRelease[];
}>();

const logEl = ref<HTMLPreElement | null>(null);
const rollbackTarget = ref<SiteRelease | null>(null);

const form = useForm({
    run_composer: props.site.deployment_options?.run_composer ?? true,
    run_npm: props.site.deployment_options?.run_npm ?? true,
    run_migrations: props.site.deployment_options?.run_migrations ?? true,
    run_caches: props.site.deployment_options?.run_caches ?? true,
    run_queue_restart:
        props.site.deployment_options?.run_queue_restart ?? false,
    run_hook: props.site.deployment_options?.run_hook ?? false,
});

const hasRepository = computed(() => Boolean(props.site.repository_url));

const isDeploying = computed(
    () =>
        props.site.status === 'deploying' ||
        props.operation?.status === 'pending' ||
        props.operation?.status === 'running',
);

const canDeploy = computed(
    () =>
        hasRepository.value &&
        props.github.connected &&
        !isDeploying.value &&
        !form.processing,
);

const liveLog = computed(() => {
    if (!props.operation) {
        return '';
    }

    return props.operation.steps
        .filter((step) => step.status !== 'pending')
        .map((step) => {
            const body = step.output?.trim() ?? '';

            return body === ''
                ? `==> ${step.name}`
                : `==> ${step.name}\n${body}`;
        })
        .join('\n\n');
});

const runningStep = computed(
    () =>
        props.operation?.steps.find((step) => step.status === 'running') ??
        null,
);

const { start, stop } = usePoll(
    1000,
    {
        only: ['site', 'operation', 'releases'],
    },
    {
        autoStart: false,
    },
);

watch(
    isDeploying,
    (active) => {
        if (active) {
            start();

            return;
        }

        stop();
    },
    { immediate: true },
);

watch(liveLog, async () => {
    await nextTick();

    if (logEl.value) {
        logEl.value.scrollTop = logEl.value.scrollHeight;
    }
});

const deploy = () => {
    form.submit(storeDeployment(props.site.uuid), {
        preserveScroll: true,
    });
};

const formatTime = (value: string | null): string => {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString();
};

const bagErrors = computed(
    () => form.errors as Record<string, string | undefined>,
);
</script>

<template>
    <div class="space-y-4">
        <section
            class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white"
        >
            <div
                class="flex flex-col gap-4 border-b border-neutral-100 px-6 py-5 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h2 class="font-semibold">Deployments</h2>
                    <p class="mt-1 text-sm text-neutral-500">
                        Deploy
                        {{
                            site.repository_url
                                ? `${site.repository_url}${site.repository_branch ? ` · ${site.repository_branch}` : ''}`
                                : 'this site'
                        }}
                        from GitHub.
                    </p>
                </div>
                <Button
                    type="button"
                    :disabled="!canDeploy"
                    class="h-10 shrink-0 rounded-lg bg-brand px-4 text-white hover:bg-brand/90 disabled:opacity-50"
                    @click="deploy"
                >
                    <Spinner v-if="form.processing" class="size-4" />
                    <Rocket v-else class="size-4" />
                    {{ isDeploying ? 'Deploying…' : 'Deploy now' }}
                </Button>
            </div>

            <div
                v-if="site.is_laravel && site.deployment_options"
                class="grid gap-3 border-b border-neutral-100 px-6 py-5 sm:grid-cols-2"
            >
                <label
                    v-for="(label, key) in optionLabels"
                    :key="key"
                    class="flex items-center justify-between gap-3 rounded-xl border border-neutral-200 px-4 py-3"
                    :class="isDeploying ? 'opacity-60' : ''"
                >
                    <span class="text-sm">{{ label }}</span>
                    <ToggleSwitch
                        :model-value="form[key]"
                        :disabled="isDeploying || form.processing"
                        @update:model-value="form[key] = $event"
                    />
                </label>
            </div>

            <div v-if="bagErrors.site" class="px-6 pb-3">
                <InputError :message="bagErrors.site" />
            </div>

            <p v-if="!hasRepository" class="px-6 pb-5 text-sm text-neutral-500">
                Connect GitHub on the source control tab before you can deploy.
            </p>
            <p
                v-else-if="!github.connected"
                class="px-6 pb-5 text-sm text-neutral-500"
            >
                Reconnect GitHub before deploying so Stacklab can resolve the
                branch tip.
            </p>
        </section>

        <section
            v-if="operation"
            class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white"
        >
            <div class="border-b border-neutral-100 px-6 py-5">
                <h2 class="font-semibold">
                    {{
                        operation.status === 'failed'
                            ? 'Deployment failed'
                            : operation.status === 'succeeded'
                              ? operation.type === 'rollback'
                                  ? 'Rollback finished'
                                  : 'Deployment finished'
                              : operation.type === 'rollback'
                                ? 'Rolling back'
                                : 'Deploying'
                    }}
                </h2>
                <p
                    v-if="operation.failure_message"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ operation.failure_message }}
                </p>
                <p
                    v-else-if="runningStep"
                    class="mt-1 text-sm text-neutral-500"
                >
                    {{ runningStep.name }}
                </p>
            </div>

            <ol class="grid gap-2 px-6 py-4 sm:grid-cols-2">
                <li
                    v-for="step in operation.steps"
                    :key="step.id"
                    class="flex items-center gap-2 text-sm"
                >
                    <span
                        class="flex size-5 items-center justify-center rounded-full"
                        :class="{
                            'bg-neutral-950 text-white':
                                step.status === 'succeeded',
                            'border-2 border-brand': step.status === 'running',
                            'border border-neutral-300':
                                step.status === 'pending',
                            'bg-red-600 text-white': step.status === 'failed',
                        }"
                    >
                        <Check
                            v-if="step.status === 'succeeded'"
                            class="size-3"
                            stroke-width="3"
                        />
                        <X
                            v-else-if="step.status === 'failed'"
                            class="size-3"
                            stroke-width="3"
                        />
                        <span
                            v-else
                            class="size-1.5 rounded-full"
                            :class="
                                step.status === 'running'
                                    ? 'bg-brand'
                                    : 'bg-neutral-300'
                            "
                        />
                    </span>
                    <span
                        :class="
                            step.status === 'pending'
                                ? 'text-neutral-400'
                                : 'text-neutral-900'
                        "
                    >
                        {{ step.name }}
                    </span>
                </li>
            </ol>

            <div class="px-6 pb-6">
                <p
                    class="mb-2 text-xs font-medium tracking-wide text-neutral-400 uppercase"
                >
                    Deployment log
                </p>
                <pre
                    ref="logEl"
                    class="max-h-[36rem] min-h-96 overflow-auto rounded-xl border border-neutral-200 bg-neutral-50 p-4 font-mono text-xs leading-6 break-words whitespace-pre-wrap text-neutral-800"
                    >{{
                        liveLog ||
                        (isDeploying
                            ? 'Waiting for remote output…'
                            : 'No output recorded for this deployment.')
                    }}</pre>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white"
        >
            <div class="border-b border-neutral-100 px-6 py-5">
                <h2 class="font-semibold">Releases</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    Previous successful releases can be restored on this server.
                </p>
            </div>

            <div v-if="releases.length === 0" class="px-6 py-16 text-center">
                <span
                    class="mx-auto flex size-12 items-center justify-center rounded-full bg-neutral-100 text-neutral-500"
                >
                    <Rocket class="size-5" />
                </span>
                <p class="mt-4 font-medium">No deployments yet</p>
                <p class="mt-1 text-sm text-neutral-500">
                    {{
                        hasRepository
                            ? 'Deployments will show up here once you ship a release.'
                            : 'Connect GitHub on the source control tab before you can deploy.'
                    }}
                </p>
            </div>

            <div v-else>
                <div
                    v-for="release in releases"
                    :key="release.uuid"
                    class="flex flex-col gap-3 border-b border-neutral-100 px-6 py-4 last:border-b-0 sm:flex-row sm:items-center"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-neutral-600"
                    >
                        <GitCommitHorizontal class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-mono text-sm font-medium">
                                {{ release.short_sha }}
                            </p>
                            <span
                                v-if="release.is_current"
                                class="rounded-full bg-orange-50 px-2 py-0.5 text-xs font-medium text-brand"
                            >
                                Current
                            </span>
                            <span class="text-xs text-neutral-400">
                                {{ release.status_label }}
                            </span>
                        </div>
                        <p class="mt-0.5 truncate text-sm text-neutral-500">
                            {{ release.commit_message || 'No commit message' }}
                        </p>
                        <p
                            v-if="release.activated_at || release.created_at"
                            class="mt-0.5 text-xs text-neutral-400"
                        >
                            {{
                                formatTime(
                                    release.activated_at ?? release.created_at,
                                )
                            }}
                        </p>
                    </div>
                    <Button
                        v-if="release.can_rollback"
                        type="button"
                        variant="outline"
                        :disabled="isDeploying"
                        class="h-9 shrink-0 rounded-lg border-neutral-200 bg-white shadow-none"
                        @click="rollbackTarget = release"
                    >
                        <RotateCcw class="size-4" />
                        Rollback
                    </Button>
                </div>
            </div>
        </section>

        <Dialog
            :open="rollbackTarget !== null"
            @update:open="(open) => !open && (rollbackTarget = null)"
        >
            <DialogContent v-if="rollbackTarget">
                <Form
                    v-bind="
                        storeRollback.form({
                            site: site.uuid,
                            release: rollbackTarget.uuid,
                        })
                    "
                    class="space-y-6"
                    v-slot="{ processing, errors }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Roll back to this release?</DialogTitle>
                        <DialogDescription>
                            {{ site.domain }} will be restored to
                            <span class="font-mono">{{
                                rollbackTarget.short_sha
                            }}</span
                            >. The current release stays on disk until it is
                            pruned.
                        </DialogDescription>
                    </DialogHeader>
                    <InputError :message="errors.release" />
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary" type="button"
                                >Cancel</Button
                            >
                        </DialogClose>
                        <Button
                            type="submit"
                            :disabled="processing || isDeploying"
                            class="rounded-lg bg-brand text-white hover:bg-brand/90"
                        >
                            <Spinner v-if="processing" class="size-4" />
                            Start rollback
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
