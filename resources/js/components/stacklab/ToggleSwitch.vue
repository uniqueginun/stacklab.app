<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    modelValue: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();

const label = computed(() => (props.modelValue ? 'On' : 'Off'));
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :aria-label="label"
        :disabled="disabled"
        class="relative inline-flex h-6 w-10 shrink-0 cursor-pointer items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-brand/40 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
        :class="modelValue ? 'bg-brand' : 'bg-neutral-200'"
        @click="!disabled && emit('update:modelValue', !modelValue)"
    >
        <span
            class="inline-block size-5 rounded-full bg-white shadow-sm transition-transform"
            :class="modelValue ? 'translate-x-[18px]' : 'translate-x-0.5'"
        />
    </button>
</template>
