<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const {
    title = '',
    description = '',
    boxed = true,
} = defineProps<{
    title?: string;
    description?: string;
    boxed?: boolean;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description" :boxed="boxed">
        <slot />
    </AuthLayout>
</template>
