<script setup lang="ts">
import { useForm, useHttp, usePoll } from '@inertiajs/vue3';
import { LockKeyhole } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { store as storeQueueWorker, status as workerStatus } from '@/actions/App/Http/Controllers/SiteQueueWorkerController';
import InputError from '@/components/InputError.vue';
import ProvisionStepper from '@/components/stacklab/ProvisionStepper.vue';
import StacklabSelect from '@/components/stacklab/StacklabSelect.vue';
import StatusBadge from '@/components/stacklab/StatusBadge.vue';
import ToggleSwitch from '@/components/stacklab/ToggleSwitch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type {
    QueueWorker,
    QueueWorkerDefaults,
    QueueWorkerRuntime,
    QueueWorkerStatusResponse,
    ServerOperation,
    SiteShow,
} from '@/types';

const fallbackDefaults: QueueWorkerDefaults = {
    connection: 'redis',
    queue: 'default',
    processes: 1,
    sleep: 3,
    timeout: 90,
    tries: 3,
    backoff: 0,
    max_jobs: 0,
    max_time: 0,
    stopwaitsecs: 3600,
    restart_on_deploy: true,
};

const props = withDefaults(
    defineProps<{
        site: SiteShow;
        workers: QueueWorker[];
        phpVersions: string[];
        defaults: QueueWorkerDefaults | null;
        operation: ServerOperation | null;
    }>(),
    {
        defaults: null,
    },
);

const showAdvanced = ref(false);
const runtime = ref<Record<string, QueueWorkerRuntime>>({});
const runtimeError = ref<string | null>(null);
let statusTimer: number | null = null;

const resolvedDefaults = computed(
    () => props.defaults ?? fallbackDefaults,
);

const form = useForm({
    name: '',
    connection: resolvedDefaults.value.connection,
    queue: resolvedDefaults.value.queue,
    processes: resolvedDefaults.value.processes,
    php_version: props.site.php_version ?? props.phpVersions[0] ?? '',
    sleep: resolvedDefaults.value.sleep,
    timeout: resolvedDefaults.value.timeout,
    tries: resolvedDefaults.value.tries,
    backoff: resolvedDefaults.value.backoff,
    max_jobs: resolvedDefaults.value.max_jobs,
    max_time: resolvedDefaults.value.max_time,
    stopwaitsecs: resolvedDefaults.value.stopwaitsecs,
    restart_on_deploy: resolvedDefaults.value.restart_on_deploy,
});

const canCreate = computed(() => props.site.can_manage_queues);
const isInstalling = computed(
    () =>
        props.operation?.status === 'pending' ||
        props.operation?.status === 'running' ||
        props.workers.some((worker) =>
            ['pending', 'installing'].includes(worker.status),
        ),
);

const shouldPoll = computed(
    () =>
        props.operation?.status === 'pending' ||
        props.operation?.status === 'running' ||
        props.workers.some((worker) =>
            ['pending', 'installing', 'updating', 'restarting', 'deleting'].includes(
                worker.status,
            ),
        ),
);

const { start, stop } = usePoll(
    2000,
    {
        only: ['workers', 'operation', 'site'],
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

const statusHttp = useHttp<Record<string, never>, QueueWorkerStatusResponse>(
    {},
);

const hasInstalledWorkers = computed(() =>
    props.workers.some((worker) => worker.status === 'installed'),
);

const refreshRuntime = () => {
    if (!hasInstalledWorkers.value) {
        runtime.value = {};
        runtimeError.value = null;

        return;
    }

    statusHttp.get(workerStatus.url(props.site.uuid), {
        onSuccess: (data) => {
            runtime.value = data.workers ?? {};
            runtimeError.value = data.error;
        },
        onError: () => {
            runtimeError.value = 'Unable to refresh Supervisor status.';
        },
    });
};

watch(hasInstalledWorkers, (installed) => {
    if (installed) {
        refreshRuntime();
    }
});

onMounted(() => {
    refreshRuntime();
    statusTimer = window.setInterval(refreshRuntime, 5000);
});

onUnmounted(() => {
    if (statusTimer !== null) {
        window.clearInterval(statusTimer);
    }
});

const submit = () => {
    form.submit(storeQueueWorker(props.site.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.defaults({
                ...resolvedDefaults.value,
                name: '',
                php_version: props.site.php_version ?? props.phpVersions[0] ?? '',
            });
        },
    });
};

const badgeStatus = (status: QueueWorker['status']) => {
    if (status === 'installed') {
        return 'ready';
    }

    if (status === 'failed') {
        return 'failed';
    }

    if (status === 'pending') {
        return 'pending';
    }

    return 'deploying';
};

const processLabel = (worker: QueueWorker) => {
    const live = runtime.value[worker.uuid];

    if (worker.status !== 'installed' || !live) {
        return `${worker.processes} configured`;
    }

    return `${live.running_processes}/${live.configured_processes} running`;
};

const runtimeLabel = (worker: QueueWorker) => {
    const live = runtime.value[worker.uuid];

    if (worker.status !== 'installed') {
        return 'Not running';
    }

    if (!live) {
        return 'Checking';
    }

    if (live.healthy) {
        return 'Healthy';
    }

    if (live.missing) {
        return 'Unavailable';
    }

    return 'Unhealthy';
};
</script>

<template>
    <section class="space-y-6">
        <div
            v-if="
                operation &&
                ['pending', 'running', 'failed'].includes(operation.status)
            "
        >
            <ProvisionStepper :operation="operation" />
        </div>

        <div v-if="!canCreate" class="flex items-center gap-4 rounded-xl border border-neutral-200 bg-neutral-50 px-6 py-6">
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-neutral-200 text-neutral-600"
            >
                <LockKeyhole class="size-4" />
            </span>
            <div>
                <p class="font-medium">Deploy this Laravel site first</p>
                <p class="mt-1 text-sm text-neutral-500">
                    Queue workers run from the current release. Deploy the site
                    before creating a worker.
                </p>
            </div>
        </div>

        <form
            v-else
            class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white"
            @submit.prevent="submit"
        >
            <div class="border-b border-neutral-100 px-6 py-5">
                <h2 class="font-semibold">Create queue worker</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    StackLab generates a Supervisor program from these settings.
                    Workers are not started from a raw shell command.
                </p>
            </div>

            <div class="grid gap-5 px-6 py-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="worker_name">Name</Label>
                    <Input
                        id="worker_name"
                        v-model="form.name"
                        maxlength="63"
                        placeholder="emails-worker"
                        :disabled="form.processing || isInstalling"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="worker_connection">Connection</Label>
                    <Input
                        id="worker_connection"
                        v-model="form.connection"
                        maxlength="63"
                        placeholder="redis"
                        :disabled="form.processing || isInstalling"
                    />
                    <InputError :message="form.errors.connection" />
                </div>
                <div class="grid gap-2">
                    <Label for="worker_queue">Queue</Label>
                    <Input
                        id="worker_queue"
                        v-model="form.queue"
                        maxlength="255"
                        placeholder="default"
                        :disabled="form.processing || isInstalling"
                    />
                    <p class="text-xs text-neutral-400">
                        Comma-separated Laravel queue names.
                    </p>
                    <InputError :message="form.errors.queue" />
                </div>
                <div class="grid gap-2">
                    <Label for="worker_processes">Processes</Label>
                    <Input
                        id="worker_processes"
                        v-model.number="form.processes"
                        type="number"
                        min="1"
                        max="10"
                        :disabled="form.processing || isInstalling"
                    />
                    <InputError :message="form.errors.processes" />
                </div>
                <div class="grid gap-2">
                    <Label for="worker_php_version">PHP version</Label>
                    <StacklabSelect
                        id="worker_php_version"
                        v-model="form.php_version"
                        :disabled="form.processing || isInstalling"
                    >
                        <option
                            v-for="version in phpVersions"
                            :key="version"
                            :value="version"
                        >
                            PHP {{ version }}
                        </option>
                    </StacklabSelect>
                    <InputError :message="form.errors.php_version" />
                </div>
            </div>

            <div class="border-t border-neutral-100 px-6 py-4">
                <button
                    type="button"
                    class="text-sm font-medium text-neutral-700 hover:text-neutral-950"
                    @click="showAdvanced = !showAdvanced"
                >
                    {{ showAdvanced ? 'Hide advanced' : 'Show advanced' }}
                </button>
            </div>

            <div
                v-if="showAdvanced"
                class="grid gap-5 border-t border-neutral-100 px-6 py-6 sm:grid-cols-2"
            >
                <div class="grid gap-2">
                    <Label for="worker_sleep">Sleep seconds</Label>
                    <Input
                        id="worker_sleep"
                        v-model.number="form.sleep"
                        type="number"
                        min="0"
                        :disabled="form.processing || isInstalling"
                    />
                    <InputError :message="form.errors.sleep" />
                </div>
                <div class="grid gap-2">
                    <Label for="worker_timeout">Timeout seconds</Label>
                    <Input
                        id="worker_timeout"
                        v-model.number="form.timeout"
                        type="number"
                        min="1"
                        :disabled="form.processing || isInstalling"
                    />
                    <InputError :message="form.errors.timeout" />
                </div>
                <div class="grid gap-2">
                    <Label for="worker_tries">Tries</Label>
                    <Input
                        id="worker_tries"
                        v-model.number="form.tries"
                        type="number"
                        min="0"
                        :disabled="form.processing || isInstalling"
                    />
                    <InputError :message="form.errors.tries" />
                </div>
                <div class="grid gap-2">
                    <Label for="worker_backoff">Backoff seconds</Label>
                    <Input
                        id="worker_backoff"
                        v-model.number="form.backoff"
                        type="number"
                        min="0"
                        :disabled="form.processing || isInstalling"
                    />
                    <InputError :message="form.errors.backoff" />
                </div>
                <div class="grid gap-2">
                    <Label for="worker_max_jobs">Maximum jobs</Label>
                    <Input
                        id="worker_max_jobs"
                        v-model.number="form.max_jobs"
                        type="number"
                        min="0"
                        :disabled="form.processing || isInstalling"
                    />
                    <p class="text-xs text-neutral-400">0 is unlimited.</p>
                    <InputError :message="form.errors.max_jobs" />
                </div>
                <div class="grid gap-2">
                    <Label for="worker_max_time">Maximum runtime</Label>
                    <Input
                        id="worker_max_time"
                        v-model.number="form.max_time"
                        type="number"
                        min="0"
                        :disabled="form.processing || isInstalling"
                    />
                    <p class="text-xs text-neutral-400">0 is unlimited.</p>
                    <InputError :message="form.errors.max_time" />
                </div>
                <div class="grid gap-2">
                    <Label for="worker_stopwaitsecs">Supervisor stop wait</Label>
                    <Input
                        id="worker_stopwaitsecs"
                        v-model.number="form.stopwaitsecs"
                        type="number"
                        min="1"
                        :disabled="form.processing || isInstalling"
                    />
                    <InputError :message="form.errors.stopwaitsecs" />
                </div>
                <div class="flex items-center justify-between gap-4 rounded-xl border border-neutral-200 px-4 py-3">
                    <div>
                        <p class="text-sm font-medium">Restart on deploy</p>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            After a successful release switch, signal Laravel
                            queue workers for this site.
                        </p>
                    </div>
                    <ToggleSwitch
                        v-model="form.restart_on_deploy"
                        :disabled="form.processing || isInstalling"
                    />
                </div>
            </div>

            <div class="flex flex-col items-end gap-2 border-t border-neutral-100 px-6 py-4">
                <InputError :message="form.errors.site" />
                <Button
                    type="submit"
                    class="h-10 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
                    :disabled="form.processing || isInstalling"
                >
                    <Spinner v-if="form.processing || isInstalling" />
                    Create worker
                </Button>
            </div>
        </form>

        <p v-if="runtimeError" class="text-sm text-red-600">
            {{ runtimeError }}
        </p>

        <div
            v-if="workers.length > 0"
            class="overflow-hidden rounded-xl border border-neutral-200/80 bg-white"
        >
            <div
                v-for="worker in workers"
                :key="worker.uuid"
                class="border-b border-neutral-100 px-5 py-4 last:border-b-0"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <p class="font-medium">{{ worker.name }}</p>
                    <StatusBadge
                        :status="badgeStatus(worker.status)"
                        :label="worker.status_label"
                    />
                </div>
                <dl class="mt-3 grid gap-1 text-sm text-neutral-600 sm:grid-cols-2">
                    <div>Connection: {{ worker.connection }}</div>
                    <div>Queue: {{ worker.queue }}</div>
                    <div>PHP: {{ worker.php_version }}</div>
                    <div>Processes: {{ processLabel(worker) }}</div>
                    <div>Configuration: {{ worker.status_label }}</div>
                    <div>Runtime: {{ runtimeLabel(worker) }}</div>
                </dl>
                <p
                    v-if="worker.failure_message"
                    class="mt-2 text-sm text-red-600"
                >
                    {{ worker.failure_message }}
                </p>
            </div>
        </div>

        <div
            v-else-if="canCreate"
            class="rounded-xl border border-dashed border-neutral-200 bg-white px-6 py-12 text-center"
        >
            <p class="font-medium">No queue workers yet</p>
            <p class="mt-1 text-sm text-neutral-500">
                Create a worker to run
                <span class="font-medium">php artisan queue:work</span>
                under Supervisor.
            </p>
        </div>
    </section>
</template>
