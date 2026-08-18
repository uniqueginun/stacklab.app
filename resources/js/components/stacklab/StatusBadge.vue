<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import type { ConnectionStatus } from '@/types';

type Status = ConnectionStatus | 'provisioning' | 'deployed' | 'deploying';

const props = defineProps<{
    status: Status;
    class?: string;
}>();

const label = computed(() => {
    const labels: Record<Status, string> = {
        connected: 'Connected',
        provisioning: 'Provisioning',
        deployed: 'Deployed',
        deploying: 'Deploying',
        unverified: 'Unverified server',
        pending_confirmation: 'Pending confirmation',
        failed: 'Connection failed',
    };

    return labels[props.status];
});

const styles = computed(() => {
    if (props.status === 'failed') {
        return 'bg-red-50 text-red-700';
    }

    if (props.status === 'unverified') {
        return 'bg-neutral-100 text-neutral-600';
    }

    if (
        props.status === 'provisioning' ||
        props.status === 'deploying' ||
        props.status === 'pending_confirmation'
    ) {
        return 'bg-amber-50 text-amber-700';
    }

    return 'bg-orange-50 text-orange-700';
});

const dotStyles = computed(() => {
    if (props.status === 'failed') {
        return 'bg-red-500';
    }

    if (props.status === 'unverified') {
        return 'bg-neutral-400';
    }

    if (
        props.status === 'provisioning' ||
        props.status === 'deploying' ||
        props.status === 'pending_confirmation'
    ) {
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
