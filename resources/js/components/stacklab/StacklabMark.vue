<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
    }>(),
    {
        class: 'size-8',
    },
);
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        fill="none"
        :class="cn('shrink-0', $props.class)"
        aria-hidden="true"
    >
        <rect width="32" height="32" rx="8" class="fill-brand" />
        <path
            d="M8.5 12.2 16 8.5l7.5 3.7v.4L16 16.3 8.5 12.6v-.4Z"
            class="fill-white"
        />
        <path
            d="M8.5 16.4 16 12.7l7.5 3.7v.4L16 20.5l-7.5-3.7v-.4Z"
            class="fill-white/90"
        />
        <path
            d="M8.5 20.6 16 16.9l7.5 3.7v.4L16 24.7l-7.5-3.7v-.4Z"
            class="fill-white/80"
        />
    </svg>
</template>
