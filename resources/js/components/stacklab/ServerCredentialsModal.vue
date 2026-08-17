<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CircleAlert, Copy, Download } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { show as serverShow } from '@/routes/servers';

defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const sudoPassword = 'Afbe=.*n4!S9bk$fKX8}';
const databasePassword = 'Ya10nLjTP7Xvt2FDIijj';

const copy = async (value: string) => {
    await navigator.clipboard.writeText(value);
};

const download = () => {
    const contents = `Sudo password: ${sudoPassword}\nDatabase password: ${databasePassword}\n`;
    const blob = new Blob([contents], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'server-credentials.txt';
    link.click();
    URL.revokeObjectURL(url);
};

const confirm = () => {
    emit('update:open', false);
    router.visit(serverShow());
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-w-md gap-5 rounded-2xl border-neutral-200 bg-white p-6 sm:max-w-md"
        >
            <div>
                <DialogTitle class="text-lg font-semibold"
                    >Important server credentials</DialogTitle
                >
                <DialogDescription class="mt-1 text-sm text-neutral-500">
                    Your server has been created. Please store these credentials
                    safely.
                </DialogDescription>
            </div>

            <div
                class="flex items-center gap-3 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2.5 text-sm text-orange-700"
            >
                <CircleAlert class="size-4 shrink-0 text-brand" />
                These details will not be shown again.
            </div>

            <div class="overflow-hidden rounded-lg border border-neutral-200">
                <div
                    class="flex items-center justify-between border-b border-neutral-200 px-4 py-3"
                >
                    <div>
                        <p class="text-xs text-neutral-400">Sudo password</p>
                        <p class="font-mono text-sm">{{ sudoPassword }}</p>
                    </div>
                    <button
                        type="button"
                        class="text-neutral-400 hover:text-neutral-700"
                        @click="copy(sudoPassword)"
                    >
                        <Copy class="size-4" />
                    </button>
                </div>
                <div class="flex items-center justify-between px-4 py-3">
                    <div>
                        <p class="text-xs text-neutral-400">
                            Database password
                        </p>
                        <p class="font-mono text-sm">{{ databasePassword }}</p>
                    </div>
                    <button
                        type="button"
                        class="text-neutral-400 hover:text-neutral-700"
                        @click="copy(databasePassword)"
                    >
                        <Copy class="size-4" />
                    </button>
                </div>
            </div>

            <button
                type="button"
                class="mx-auto flex items-center gap-2 text-sm text-neutral-500 hover:text-neutral-800"
                @click="download"
            >
                <Download class="size-4" />
                Download as .txt
            </button>

            <Button
                class="h-11 w-full rounded-lg bg-neutral-950 text-white hover:bg-neutral-800"
                @click="confirm"
            >
                I have stored these credentials
            </Button>
        </DialogContent>
    </Dialog>
</template>
