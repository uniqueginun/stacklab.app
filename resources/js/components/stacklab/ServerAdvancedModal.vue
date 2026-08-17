<script setup lang="ts">
import { ref } from 'vue';
import StacklabSelect from '@/components/stacklab/StacklabSelect.vue';
import ToggleSwitch from '@/components/stacklab/ToggleSwitch.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const os = ref('ubuntu-24.04');
const php = ref('8.4');
const database = ref('mysql-8');
const addSshKey = ref(true);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-w-md gap-6 rounded-2xl border-neutral-200 bg-white p-6 sm:max-w-md"
        >
            <DialogTitle class="text-lg font-semibold"
                >Edit advanced settings</DialogTitle
            >

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label class="text-sm font-normal text-neutral-500"
                        >Server OS</Label
                    >
                    <StacklabSelect id="server-os" v-model="os">
                        <option value="ubuntu-24.04">Ubuntu 24.04</option>
                        <option value="ubuntu-22.04">Ubuntu 22.04</option>
                    </StacklabSelect>
                </div>
                <div class="grid gap-2">
                    <Label class="text-sm font-normal text-neutral-500"
                        >PHP version</Label
                    >
                    <StacklabSelect id="server-php" v-model="php">
                        <option value="8.4">PHP 8.4</option>
                        <option value="8.3">PHP 8.3</option>
                        <option value="8.2">PHP 8.2</option>
                    </StacklabSelect>
                </div>
                <div class="grid gap-2">
                    <Label class="text-sm font-normal text-neutral-500"
                        >Database type</Label
                    >
                    <StacklabSelect id="server-db" v-model="database">
                        <option value="mysql-8">MySQL 8</option>
                        <option value="postgres-16">PostgreSQL 16</option>
                        <option value="mariadb">MariaDB</option>
                    </StacklabSelect>
                </div>
                <div class="flex items-center justify-between gap-4 py-1">
                    <p class="text-sm text-neutral-700">
                        Add server's SSH key to source control providers
                    </p>
                    <ToggleSwitch v-model="addSshKey" />
                </div>
            </div>

            <Button
                class="h-11 w-full rounded-lg bg-neutral-950 text-white hover:bg-neutral-800"
                @click="emit('update:open', false)"
            >
                Update
            </Button>
        </DialogContent>
    </Dialog>
</template>
