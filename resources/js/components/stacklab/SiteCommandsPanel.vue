<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/sites/commands';
import type { SiteCommandResult, SiteShow } from '@/types';

const presets = [
    { label: 'config:clear', command: 'php artisan config:clear' },
    { label: 'cache:clear', command: 'php artisan cache:clear' },
    { label: 'route:clear', command: 'php artisan route:clear' },
    { label: 'view:clear', command: 'php artisan view:clear' },
    { label: 'migrate --force', command: 'php artisan migrate --force' },
    { label: 'queue:restart', command: 'php artisan queue:restart' },
];

const props = defineProps<{
    site: SiteShow;
}>();

const runner = useHttp<{ command: string }, SiteCommandResult>({
    command: '',
});

const canRun = computed(
    () =>
        props.site.status === 'deployed' && props.site.current_release !== null,
);

const result = computed(() => runner.response);

const bagErrors = computed(
    () => runner.errors as Record<string, string | undefined>,
);

const run = (command?: string) => {
    if (command) {
        runner.command = command;
    }

    if (!canRun.value || runner.command.trim() === '') {
        return;
    }

    runner.post(store.url(props.site.uuid));
};
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white"
    >
        <div class="border-b border-neutral-100 px-6 py-5">
            <h2 class="font-semibold">Commands</h2>
            <p class="mt-1 text-sm text-neutral-500">
                Run Artisan and other shell commands in the current release.
            </p>
        </div>

        <div v-if="!canRun" class="px-6 py-16 text-center">
            <p class="text-sm text-neutral-500">
                Deploy the site before running commands.
            </p>
        </div>

        <form v-else class="grid gap-5 px-6 py-6" @submit.prevent="run()">
            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="preset in presets"
                    :key="preset.command"
                    type="button"
                    variant="outline"
                    class="h-9 rounded-lg border-neutral-200 bg-white shadow-none"
                    :disabled="runner.processing"
                    @click="run(preset.command)"
                >
                    {{ preset.label }}
                </Button>
            </div>

            <div class="grid gap-2">
                <Label
                    for="site-command"
                    class="text-sm font-normal text-neutral-500"
                >
                    Command
                </Label>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input
                        id="site-command"
                        v-model="runner.command"
                        name="command"
                        type="text"
                        spellcheck="false"
                        placeholder="php artisan config:clear"
                        :disabled="runner.processing"
                        class="h-10 w-full rounded-lg border border-neutral-200 bg-white px-3 font-mono text-sm text-neutral-900 outline-none focus:border-neutral-400 disabled:cursor-not-allowed disabled:bg-neutral-50"
                    />
                    <Button
                        type="submit"
                        class="h-10 shrink-0 rounded-lg bg-neutral-950 px-4 text-white hover:bg-neutral-800"
                        :disabled="
                            runner.processing || runner.command.trim() === ''
                        "
                    >
                        <Spinner v-if="runner.processing" class="size-4" />
                        {{ runner.processing ? 'Running…' : 'Run command' }}
                    </Button>
                </div>
                <InputError :message="bagErrors.command" />
                <InputError :message="bagErrors.site" />
            </div>
        </form>

        <div v-if="result" class="border-t border-neutral-100 px-6 py-6">
            <div class="mb-3 flex items-center justify-between gap-3">
                <p class="truncate font-mono text-sm text-neutral-600">
                    {{ result.command }}
                </p>
                <span
                    class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="
                        result.exit_code === 0
                            ? 'bg-emerald-50 text-emerald-800'
                            : 'bg-red-50 text-red-700'
                    "
                >
                    exit {{ result.exit_code }}
                </span>
            </div>
            <pre
                class="max-h-80 overflow-auto rounded-xl bg-neutral-950 p-4 font-mono text-xs leading-5 text-neutral-100"
                >{{ result.output || 'Command finished with no output.' }}</pre>
        </div>

        <div
            class="border-t border-neutral-100 bg-neutral-50/70 px-6 py-3 text-sm text-neutral-500"
        >
            <template v-if="result">
                Ran in
                <span class="font-medium text-neutral-700">{{
                    result.working_directory
                }}</span>
            </template>
            <template v-else>
                Commands run in
                <span class="font-medium text-neutral-700">current</span>
                on the server.
            </template>
        </div>
    </section>
</template>
