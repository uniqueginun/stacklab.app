<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { siteTypes, type SiteType } from '@/data/stacklab';
import { create as sitesCreate } from '@/routes/sites';

const props = withDefaults(
    defineProps<{
        open: boolean;
        selected?: SiteType;
        redirect?: boolean;
        serverUuid?: string;
    }>(),
    {
        selected: 'Laravel',
        redirect: true,
        serverUuid: '',
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    select: [type: SiteType];
}>();

const initials = (name: string) => name.slice(0, 2);

const choose = (type: SiteType) => {
    emit('select', type);
    emit('update:open', false);

    if (props.redirect) {
        router.visit(
            sitesCreate({
                query: {
                    type,
                    ...(props.serverUuid ? { server: props.serverUuid } : {}),
                },
            }),
        );
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-w-md gap-0 rounded-2xl border-neutral-200 bg-white p-6 sm:max-w-md"
        >
            <div>
                <DialogTitle class="text-lg font-semibold"
                    >Create a new site</DialogTitle
                >
                <DialogDescription class="mt-1 text-sm text-neutral-500">
                    Select the type of site you want to create. Each type has
                    different configurations and features.
                </DialogDescription>
            </div>

            <div class="mt-6 max-h-[420px] space-y-5 overflow-y-auto pr-1">
                <div v-for="group in siteTypes" :key="group.category">
                    <p
                        class="mb-2 text-[11px] font-medium tracking-wider text-neutral-400 uppercase"
                    >
                        {{ group.category }}
                    </p>
                    <div class="space-y-1.5">
                        <button
                            v-for="item in group.items"
                            :key="item"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-xl border px-3 py-2.5 text-left text-sm transition-colors"
                            :class="
                                props.selected === item
                                    ? 'border-brand bg-orange-50/60'
                                    : 'border-transparent hover:bg-neutral-50'
                            "
                            @click="choose(item)"
                        >
                            <span
                                class="flex size-9 items-center justify-center rounded-full bg-brand text-xs font-medium text-white"
                            >
                                {{ initials(item) }}
                            </span>
                            {{ item }}
                        </button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
