<script setup lang="ts">
import { EllipsisVertical } from '@lucide/vue';
import { ref } from 'vue';
import StacklabSelect from '@/components/stacklab/StacklabSelect.vue';
import ToggleSwitch from '@/components/stacklab/ToggleSwitch.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const webDirectory = ref('/public');
const php = ref('8.4');
const isolation = ref(false);
const pushToDeploy = ref(true);
const zeroDowntime = ref(true);
const sharedPaths = ref([{ from: 'storage', to: 'storage' }]);

const addPath = () => {
    sharedPaths.value.push({ from: '', to: '' });
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-w-md gap-5 rounded-2xl border-neutral-200 bg-white p-6 sm:max-w-md"
        >
            <DialogTitle class="text-lg font-semibold"
                >Advanced settings</DialogTitle
            >

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label class="text-sm font-normal text-neutral-500"
                        >Web directory</Label
                    >
                    <Input
                        v-model="webDirectory"
                        class="h-11 rounded-lg border-neutral-200 bg-white shadow-none"
                    />
                </div>
                <div class="grid gap-2">
                    <Label class="text-sm font-normal text-neutral-500"
                        >PHP version</Label
                    >
                    <StacklabSelect id="site-php" v-model="php">
                        <option value="8.4">PHP 8.4</option>
                        <option value="8.3">PHP 8.3</option>
                        <option value="8.2">PHP 8.2</option>
                    </StacklabSelect>
                </div>

                <div class="flex items-start justify-between gap-4 py-1">
                    <div>
                        <p class="text-sm font-medium">Use website isolation</p>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            Stacklab configures an isolated PHP-FPM process for
                            the given site.
                        </p>
                    </div>
                    <ToggleSwitch v-model="isolation" />
                </div>
                <div class="flex items-start justify-between gap-4 py-1">
                    <div>
                        <p class="text-sm font-medium">Push to deploy</p>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            The site will be deployed automatically when you
                            push to the repository.
                        </p>
                    </div>
                    <ToggleSwitch v-model="pushToDeploy" />
                </div>
                <div class="flex items-center justify-between gap-4 py-1">
                    <p class="text-sm font-medium">Zero downtime deployments</p>
                    <ToggleSwitch v-model="zeroDowntime" />
                </div>

                <div class="rounded-xl border border-neutral-200 p-4">
                    <div class="mb-1 flex items-center justify-between">
                        <p class="text-sm font-medium">Shared paths</p>
                        <button
                            type="button"
                            class="rounded-md border border-neutral-200 px-2.5 py-1 text-xs"
                            @click="addPath"
                        >
                            Add path
                        </button>
                    </div>
                    <p class="mb-3 text-xs text-neutral-500">
                        Files or directories that stay the same between
                        deployments.
                    </p>
                    <div
                        class="grid grid-cols-[1fr_1fr_auto] gap-2 text-xs text-neutral-400"
                    >
                        <span>From</span>
                        <span>To</span>
                        <span />
                    </div>
                    <div
                        v-for="(path, index) in sharedPaths"
                        :key="index"
                        class="mt-2 grid grid-cols-[1fr_1fr_auto] items-center gap-2"
                    >
                        <span class="text-sm">{{ path.from || '—' }}</span>
                        <span class="text-sm">{{ path.to || '—' }}</span>
                        <EllipsisVertical class="size-4 text-neutral-400" />
                    </div>
                </div>
            </div>

            <Button
                class="h-11 w-full rounded-lg bg-brand text-white hover:bg-brand/90"
                @click="emit('update:open', false)"
            >
                Update
            </Button>
        </DialogContent>
    </Dialog>
</template>
