<script setup lang="ts">
withDefaults(
    defineProps<{
        title: string;
        description: string;
        steps?: string[];
    }>(),
    {
        steps: () => [],
    },
);
</script>

<template>
    <div
        class="mt-8 rounded-xl border border-dashed border-neutral-200 bg-white px-6 py-14 text-center"
    >
        <p class="font-medium">{{ title }}</p>
        <p class="mx-auto mt-1 max-w-md text-sm text-neutral-500">
            {{ description }}
        </p>
        <ol
            v-if="steps.length > 0"
            class="mx-auto mt-8 flex max-w-xl flex-col gap-3 sm:flex-row sm:justify-center sm:gap-6"
        >
            <li
                v-for="(step, index) in steps"
                :key="step"
                class="flex items-center justify-center gap-2 text-sm text-neutral-600"
            >
                <span
                    class="grid size-6 shrink-0 place-items-center rounded-full border border-brand/25 bg-orange-50 text-xs font-semibold text-brand"
                >
                    {{ index + 1 }}
                </span>
                {{ step }}
            </li>
        </ol>
        <div class="mt-8">
            <slot />
        </div>
    </div>
</template>
