<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StacklabMark from '@/components/stacklab/StacklabMark.vue';
import { home } from '@/routes';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        boxed?: boolean;
    }>(),
    {
        title: '',
        description: '',
        boxed: true,
    },
);
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center bg-[#F9F9F7] px-6 py-10 text-neutral-950"
    >
        <div class="mb-8 flex flex-col items-center text-center">
            <Link
                :href="home()"
                class="mb-5 flex flex-col items-center"
                aria-label="StackLab home"
            >
                <StacklabMark class="size-11" />
                <span
                    class="mt-3 text-[15px] font-semibold tracking-tight text-neutral-950"
                    >StackLab</span
                >
            </Link>
            <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
            <p
                v-if="description"
                class="mt-2 max-w-sm text-sm text-neutral-500"
            >
                {{ description }}
            </p>
        </div>

        <div
            class="w-full max-w-[420px]"
            :class="
                boxed
                    ? 'rounded-2xl border border-neutral-200/80 bg-white p-8'
                    : ''
            "
        >
            <slot />
        </div>
    </div>
</template>
