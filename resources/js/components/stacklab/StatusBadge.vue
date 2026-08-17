<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    status: 'connected' | 'provisioning' | 'deployed' | 'deploying';
    class?: string;
}>();

const label = computed(() => {
    const labels: Record<typeof props.status, string> = {
        connected: 'Connected',
        provisioning: 'Provisioning',
        deployed: 'Deployed',
        deploying: 'Deploying',
    };

    return labels[props.status];
});

const styles = computed(() => {
    if (props.status === 'provisioning' || props.status === 'deploying') {
        return 'bg-amber-50 text-amber-700';
    }

    return 'bg-orange-50 text-orange-700';
});

const dotStyles = computed(() => {
    if (props.status === 'provisioning' || props.status === 'deploying') {
        return 'bg-amber-400';
    }

    if (props.status === 'connected') {
        return 'bg-emerald-500';
    }

    return 'bg-brand';
});
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
                styles,
                props.class,
            )
        "
    >
        <span :class="cn('size-1.5 rounded-full', dotStyles)" />
        {{ label }}
    </span>
</template>
