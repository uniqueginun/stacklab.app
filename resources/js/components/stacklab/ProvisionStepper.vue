<script setup lang="ts">
import { Check, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { ServerOperation } from '@/types';

const props = defineProps<{
    operation: ServerOperation;
}>();

const expandedStepId = ref<number | null>(null);

const heading = computed(() => {
    if (props.operation.status === 'failed') {
        return 'Provisioning failed';
    }

    if (props.operation.status === 'succeeded') {
        return 'Provisioned';
    }

    return 'Installing';
});

const runningCopy = (name: string): string => {
    if (name.startsWith('Install ')) {
        return `We are installing ${name.slice('Install '.length)}.`;
    }

    if (name.startsWith('Verify ')) {
        return `We are verifying ${name.slice('Verify '.length).toLowerCase()}.`;
    }

    return `We are running ${name.toLowerCase()}.`;
};

const toggleFailed = (id: number) => {
    expandedStepId.value = expandedStepId.value === id ? null : id;
};
</script>

<template>
    <div
        class="rounded-xl border border-neutral-200/80 bg-white px-6 py-5 shadow-sm"
    >
        <h2 class="text-lg font-semibold tracking-tight">{{ heading }}</h2>
        <p
            v-if="operation.status === 'failed' && operation.failure_message"
            class="mt-1 text-sm text-red-600"
        >
            {{ operation.failure_message }}
        </p>

        <ol class="mt-5">
            <li
                v-for="(step, index) in operation.steps"
                :key="step.id"
                class="flex gap-3"
            >
                <div class="flex w-5 shrink-0 flex-col items-center">
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
                        v-if="index < operation.steps.length - 1"
                        class="mt-1 mb-1 w-px flex-1 bg-neutral-200"
                    />
                </div>

                <div
                    class="min-w-0 flex-1"
                    :class="
                        index < operation.steps.length - 1 ? 'pb-5' : 'pb-0.5'
                    "
                >
                    <button
                        v-if="step.status === 'failed'"
                        type="button"
                        class="w-full text-left"
                        @click="toggleFailed(step.id)"
                    >
                        <p class="text-sm font-medium text-neutral-900">
                            {{ step.name }}
                        </p>
                        <p class="mt-0.5 text-sm text-red-600">
                            Click to see the error
                        </p>
                    </button>
                    <div v-else>
                        <p
                            class="text-sm font-medium"
                            :class="
                                step.status === 'pending'
                                    ? 'text-neutral-400'
                                    : 'text-neutral-900'
                            "
                        >
                            {{ step.name }}
                        </p>
                        <p
                            v-if="step.status === 'running'"
                            class="mt-0.5 text-sm text-neutral-500"
                        >
                            {{ runningCopy(step.name) }}
                        </p>
                    </div>

                    <div
                        v-if="
                            step.status === 'failed' &&
                            expandedStepId === step.id
                        "
                        class="mt-3 space-y-2"
                    >
                        <p
                            v-if="step.error_message"
                            class="text-sm text-red-700"
                        >
                            {{ step.error_message }}
                        </p>
                        <pre
                            v-if="step.output"
                            class="max-h-64 overflow-auto rounded-lg bg-neutral-950 p-3 text-xs leading-5 text-neutral-100"
                            >{{ step.output }}</pre
                        >
                    </div>
                </div>
            </li>
        </ol>
    </div>
</template>
