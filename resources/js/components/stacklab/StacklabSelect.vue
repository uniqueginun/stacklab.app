<script setup lang="ts">
import { ChevronsUpDown } from '@lucide/vue';
import { cn } from '@/lib/utils';

defineProps<{
    id?: string;
    modelValue: string;
    class?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div class="relative">
        <select
            :id="id"
            :value="modelValue"
            :class="
                cn(
                    'h-11 w-full appearance-none rounded-lg border border-neutral-200 bg-white px-3 pr-10 text-sm text-neutral-900 transition-colors outline-none focus:border-neutral-400',
                    $props.class,
                )
            "
            @change="
                emit(
                    'update:modelValue',
                    ($event.target as HTMLSelectElement).value,
                )
            "
        >
            <slot />
        </select>
        <ChevronsUpDown
            class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-neutral-400"
        />
    </div>
</template>
