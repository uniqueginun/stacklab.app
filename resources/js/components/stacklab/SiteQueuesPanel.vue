<script setup lang="ts">
import { useForm, useHttp, usePoll } from '@inertiajs/vue3';
import { LockKeyhole } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    destroy as destroyQueueWorker,
    gracefulRestart as gracefulRestartQueueWorker,
    logs as workerLogs,
    restart as restartQueueWorker,
    status as workerStatus,
    store as storeQueueWorker,
    update as updateQueueWorker,
} from '@/actions/App/Http/Controllers/SiteQueueWorkerController';
import InputError from '@/components/InputError.vue';
import ProvisionStepper from '@/components/stacklab/ProvisionStepper.vue';
import StacklabSelect from '@/components/stacklab/StacklabSelect.vue';
import StatusBadge from '@/components/stacklab/StatusBadge.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type {
    QueueWorker,
    QueueWorkerDefaults,
    QueueWorkerLogsResponse,
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

const busyStatuses = [
    'pending',
    'installing',
    'updating',
    'restarting',
    'deleting',
];

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
const showEditAdvanced = ref(false);
const runtime = ref<Record<string, QueueWorkerRuntime>>({});
const runtimeError = ref<string | null>(null);
const editing = ref<QueueWorker | null>(null);
const confirmDelete = ref<QueueWorker | null>(null);
const confirmGraceful = ref<QueueWorker | null>(null);
const logsWorker = ref<QueueWorker | null>(null);
const logOutput = ref('');
let statusTimer: number | null = null;

const resolvedDefaults = computed(
    () => props.defaults ?? fallbackDefaults,
);

const emptyForm = () => ({
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

const form = useForm(emptyForm());
const editForm = useForm(emptyForm());
const actionForm = useForm({});

const canManage = computed(() => props.site.can_manage_queues);
const serverBusy = computed(
    () =>
        props.operation?.status === 'pending' ||
        props.operation?.status === 'running',
);
const hasBlockingOperation = computed(
    () =>
        serverBusy.value ||
        props.workers.some((worker) => busyStatuses.includes(worker.status)),
);

const shouldPoll = computed(
    () =>
        serverBusy.value ||
        props.workers.some((worker) => busyStatuses.includes(worker.status)),
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
const logsHttp = useHttp<Record<string, never>, QueueWorkerLogsResponse>({});

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

const workerParams = (worker: QueueWorker) => ({
    site: props.site.uuid,
    queue_worker: worker.uuid,
});

const canAct = (worker: QueueWorker) =>
    canManage.value && !serverBusy.value && !busyStatuses.includes(worker.status);

const submit = () => {
    form.submit(storeQueueWorker(props.site.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.defaults(emptyForm());
        },
    });
};

const openEdit = (worker: QueueWorker) => {
    editing.value = worker;
    showEditAdvanced.value = false;
    editForm.defaults({
        name: worker.name,
        connection: worker.connection,
        queue: worker.queue,
        processes: worker.processes,
        php_version: worker.php_version,
        sleep: worker.sleep,
        timeout: worker.timeout,
        tries: worker.tries,
        backoff: worker.backoff,
        max_jobs: worker.max_jobs,
        max_time: worker.max_time,
        stopwaitsecs: worker.stopwaitsecs,
        restart_on_deploy: worker.restart_on_deploy,
    });
    editForm.reset();
    editForm.clearErrors();
};

const submitEdit = () => {
    if (!editing.value) {
        return;
    }

    editForm.submit(updateQueueWorker(workerParams(editing.value)), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
};

const restartWorker = (worker: QueueWorker) => {
    actionForm.submit(restartQueueWorker(workerParams(worker)), {
        preserveScroll: true,
    });
};

const submitGracefulRestart = () => {
    if (!confirmGraceful.value) {
        return;
    }

    actionForm.submit(gracefulRestartQueueWorker(workerParams(confirmGraceful.value)), {
        preserveScroll: true,
        onSuccess: () => {
            confirmGraceful.value = null;
        },
    });
};

const submitDelete = () => {
    if (!confirmDelete.value) {
        return;
    }

    actionForm.submit(destroyQueueWorker(workerParams(confirmDelete.value)), {
        preserveScroll: true,
        onSuccess: () => {
            confirmDelete.value = null;
        },
    });
};

const openLogs = (worker: QueueWorker) => {
    logsWorker.value = worker;
    logOutput.value = '';
    logsHttp.get(workerLogs.url(workerParams(worker)), {
        onSuccess: (data) => {
            logOutput.value = data.output ?? '';
        },
        onError: () => {
            logOutput.value = '';
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

        <div v-if="!canManage" class="flex items-center gap-4 rounded-xl border border-neutral-200 bg-neutral-50 px-6 py-6">
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
                        :disabled="form.processing || hasBlockingOperation"
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
                        :disabled="form.processing || hasBlockingOperation"
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
                        :disabled="form.processing || hasBlockingOperation"
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
                        :disabled="form.processing || hasBlockingOperation"
                    />
                    <InputError :message="form.errors.processes" />
                </div>
                <div class="grid gap-2">
                    <Label for="worker_php_version">PHP version</Label>
                    <StacklabSelect
                        id="worker_php_version"
                        v-model="form.php_version"
                        :disabled="form.processing || hasBlockingOperation"
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
                        :disabled="form.processing || hasBlockingOperation"
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
                        :disabled="form.processing || hasBlockingOperation"
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
                        :disabled="form.processing || hasBlockingOperation"
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
                        :disabled="form.processing || hasBlockingOperation"
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
                        :disabled="form.processing || hasBlockingOperation"
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
                        :disabled="form.processing || hasBlockingOperation"
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
                        :disabled="form.processing || hasBlockingOperation"
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
                        :disabled="form.processing || hasBlockingOperation"
                    />
                </div>
            </div>

            <div class="flex flex-col items-end gap-2 border-t border-neutral-100 px-6 py-4">
                <InputError :message="form.errors.site" />
                <Button
                    type="submit"
                    class="h-10 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
                    :disabled="form.processing || hasBlockingOperation"
                >
                    <Spinner v-if="form.processing || hasBlockingOperation" />
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
                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-9 rounded-lg border-neutral-200 bg-white shadow-none"
                        :disabled="!canAct(worker) || actionForm.processing"
                        @click="restartWorker(worker)"
                    >
                        Restart
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-9 rounded-lg border-neutral-200 bg-white shadow-none"
                        :disabled="!canAct(worker) || actionForm.processing"
                        @click="confirmGraceful = worker"
                    >
                        Graceful restart
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-9 rounded-lg border-neutral-200 bg-white shadow-none"
                        :disabled="!canAct(worker) || logsHttp.processing"
                        @click="openLogs(worker)"
                    >
                        Logs
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-9 rounded-lg border-neutral-200 bg-white shadow-none"
                        :disabled="!canAct(worker) || editForm.processing"
                        @click="openEdit(worker)"
                    >
                        Edit
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-9 rounded-lg border-neutral-200 bg-white text-red-600 shadow-none hover:bg-red-50 hover:text-red-700"
                        :disabled="!canAct(worker) || actionForm.processing"
                        @click="confirmDelete = worker"
                    >
                        Delete
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-else-if="canManage"
            class="rounded-xl border border-dashed border-neutral-200 bg-white px-6 py-12 text-center"
        >
            <p class="font-medium">No queue workers yet</p>
            <p class="mt-1 text-sm text-neutral-500">
                Create a worker to run
                <span class="font-medium">php artisan queue:work</span>
                under Supervisor.
            </p>
        </div>

        <Dialog
            :open="editing !== null"
            @update:open="(open) => !open && (editing = null)"
        >
            <DialogContent v-if="editing" class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form class="space-y-6" @submit.prevent="submitEdit">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Edit {{ editing.name }}</DialogTitle>
                        <DialogDescription>
                            Changing connection, queue, PHP, or process settings
                            regenerates the Supervisor program.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit_worker_name">Name</Label>
                            <Input
                                id="edit_worker_name"
                                v-model="editForm.name"
                                maxlength="63"
                                :disabled="editForm.processing"
                            />
                            <InputError :message="editForm.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_worker_connection">Connection</Label>
                            <Input
                                id="edit_worker_connection"
                                v-model="editForm.connection"
                                maxlength="63"
                                :disabled="editForm.processing"
                            />
                            <InputError :message="editForm.errors.connection" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_worker_queue">Queue</Label>
                            <Input
                                id="edit_worker_queue"
                                v-model="editForm.queue"
                                maxlength="255"
                                :disabled="editForm.processing"
                            />
                            <InputError :message="editForm.errors.queue" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_worker_processes">Processes</Label>
                            <Input
                                id="edit_worker_processes"
                                v-model.number="editForm.processes"
                                type="number"
                                min="1"
                                max="10"
                                :disabled="editForm.processing"
                            />
                            <InputError :message="editForm.errors.processes" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_worker_php_version">PHP version</Label>
                            <StacklabSelect
                                id="edit_worker_php_version"
                                v-model="editForm.php_version"
                                :disabled="editForm.processing"
                            >
                                <option
                                    v-for="version in phpVersions"
                                    :key="version"
                                    :value="version"
                                >
                                    PHP {{ version }}
                                </option>
                            </StacklabSelect>
                            <InputError :message="editForm.errors.php_version" />
                        </div>
                    </div>

                    <button
                        type="button"
                        class="text-sm font-medium text-neutral-700 hover:text-neutral-950"
                        @click="showEditAdvanced = !showEditAdvanced"
                    >
                        {{ showEditAdvanced ? 'Hide advanced' : 'Show advanced' }}
                    </button>

                    <div
                        v-if="showEditAdvanced"
                        class="grid gap-5 sm:grid-cols-2"
                    >
                        <div class="grid gap-2">
                            <Label for="edit_worker_sleep">Sleep seconds</Label>
                            <Input
                                id="edit_worker_sleep"
                                v-model.number="editForm.sleep"
                                type="number"
                                min="0"
                                :disabled="editForm.processing"
                            />
                            <InputError :message="editForm.errors.sleep" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_worker_timeout">Timeout seconds</Label>
                            <Input
                                id="edit_worker_timeout"
                                v-model.number="editForm.timeout"
                                type="number"
                                min="1"
                                :disabled="editForm.processing"
                            />
                            <InputError :message="editForm.errors.timeout" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_worker_tries">Tries</Label>
                            <Input
                                id="edit_worker_tries"
                                v-model.number="editForm.tries"
                                type="number"
                                min="0"
                                :disabled="editForm.processing"
                            />
                            <InputError :message="editForm.errors.tries" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_worker_backoff">Backoff seconds</Label>
                            <Input
                                id="edit_worker_backoff"
                                v-model.number="editForm.backoff"
                                type="number"
                                min="0"
                                :disabled="editForm.processing"
                            />
                            <InputError :message="editForm.errors.backoff" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_worker_max_jobs">Maximum jobs</Label>
                            <Input
                                id="edit_worker_max_jobs"
                                v-model.number="editForm.max_jobs"
                                type="number"
                                min="0"
                                :disabled="editForm.processing"
                            />
                            <InputError :message="editForm.errors.max_jobs" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_worker_max_time">Maximum runtime</Label>
                            <Input
                                id="edit_worker_max_time"
                                v-model.number="editForm.max_time"
                                type="number"
                                min="0"
                                :disabled="editForm.processing"
                            />
                            <InputError :message="editForm.errors.max_time" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_worker_stopwaitsecs">Supervisor stop wait</Label>
                            <Input
                                id="edit_worker_stopwaitsecs"
                                v-model.number="editForm.stopwaitsecs"
                                type="number"
                                min="1"
                                :disabled="editForm.processing"
                            />
                            <InputError :message="editForm.errors.stopwaitsecs" />
                        </div>
                        <div class="flex items-center justify-between gap-4 rounded-xl border border-neutral-200 px-4 py-3 sm:col-span-2">
                            <div>
                                <p class="text-sm font-medium">Restart on deploy</p>
                                <p class="mt-0.5 text-xs text-neutral-500">
                                    After a successful release switch, signal Laravel
                                    queue workers for this site.
                                </p>
                            </div>
                            <ToggleSwitch
                                v-model="editForm.restart_on_deploy"
                                :disabled="editForm.processing"
                            />
                        </div>
                    </div>

                    <InputError :message="editForm.errors.site" />
                    <InputError :message="editForm.errors.queue_worker" />

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary" type="button">Cancel</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="editForm.processing">
                            <Spinner v-if="editForm.processing" />
                            Save worker
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="confirmGraceful !== null"
            @update:open="(open) => !open && (confirmGraceful = null)"
        >
            <DialogContent v-if="confirmGraceful">
                <DialogHeader class="space-y-3">
                    <DialogTitle>Gracefully restart queue workers?</DialogTitle>
                    <DialogDescription>
                        This runs
                        <span class="font-medium">php artisan queue:restart</span>
                        for the site. Every Laravel queue worker that shares this
                        application's cache prefix will restart after finishing its
                        current job, not only this Supervisor program.
                    </DialogDescription>
                </DialogHeader>
                <InputError :message="actionForm.errors.queue_worker" />
                <InputError :message="actionForm.errors.site" />
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">Cancel</Button>
                    </DialogClose>
                    <Button
                        type="button"
                        :disabled="actionForm.processing"
                        @click="submitGracefulRestart"
                    >
                        <Spinner v-if="actionForm.processing" />
                        Graceful restart
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="confirmDelete !== null"
            @update:open="(open) => !open && (confirmDelete = null)"
        >
            <DialogContent v-if="confirmDelete">
                <DialogHeader class="space-y-3">
                    <DialogTitle>Delete {{ confirmDelete.name }}?</DialogTitle>
                    <DialogDescription>
                        This stops the Supervisor program and removes only this
                        worker's configuration. Other workers on the site are not
                        changed.
                    </DialogDescription>
                </DialogHeader>
                <InputError :message="actionForm.errors.queue_worker" />
                <InputError :message="actionForm.errors.site" />
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">Cancel</Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="actionForm.processing"
                        @click="submitDelete"
                    >
                        <Spinner v-if="actionForm.processing" />
                        Delete worker
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="logsWorker !== null"
            @update:open="(open) => !open && (logsWorker = null)"
        >
            <DialogContent v-if="logsWorker" class="sm:max-w-3xl">
                <DialogHeader class="space-y-3">
                    <DialogTitle>{{ logsWorker.name }} logs</DialogTitle>
                    <DialogDescription>
                        Last 200 lines from the managed Supervisor log. Secrets are
                        redacted.
                    </DialogDescription>
                </DialogHeader>
                <pre
                    class="max-h-[28rem] min-h-48 overflow-auto rounded-xl border border-neutral-200 bg-neutral-50 p-4 font-mono text-xs leading-6 break-words whitespace-pre-wrap text-neutral-800"
                    >{{
                        logsHttp.processing
                            ? 'Loading logs…'
                            : logOutput || 'No log output yet.'
                    }}</pre>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">Close</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
