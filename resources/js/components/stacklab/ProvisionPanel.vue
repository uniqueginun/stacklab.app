<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import StacklabSelect from '@/components/stacklab/StacklabSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { provision } from '@/routes/servers';
import type {
    ProvisioningProfileOption,
    ServerShow,
} from '@/types';

const props = defineProps<{
    server: ServerShow;
    profiles: ProvisioningProfileOption[];
}>();

const form = useForm({
    profile: props.profiles[0]?.key ?? 'php',
    php_version: props.server.default_php_version,
    mysql_version: props.server.default_mysql_version,
});

const selectedProfile = computed(
    () => props.profiles.find((item) => item.key === form.profile) ?? null,
);

const submit = () => {
    form.submit(provision(props.server.uuid));
};
</script>

<template>
    <div
        class="rounded-xl border border-neutral-200/80 bg-white px-6 py-6 shadow-sm"
    >
        <h2 class="text-lg font-semibold tracking-tight">
            Provision this server
        </h2>
        <p class="mt-1 text-sm text-neutral-500">
            Choose a stack. Packages are installed over SSH and sites stay
            locked until this finishes.
        </p>

        <form class="mt-6 space-y-6" @submit.prevent="submit">
            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    v-for="profile in profiles"
                    :key="profile.key"
                    type="button"
                    class="rounded-xl border px-4 py-4 text-left transition-colors"
                    :class="
                        form.profile === profile.key
                            ? 'border-brand bg-orange-50/60'
                            : 'border-neutral-200 hover:bg-neutral-50'
                    "
                    @click="form.profile = profile.key"
                >
                    <p class="font-medium">{{ profile.label }}</p>
                    <p class="mt-1 text-sm text-neutral-500">
                        {{ profile.description }}
                    </p>
                </button>
            </div>
            <InputError :message="form.errors.profile" />

            <div
                v-if="selectedProfile?.requires_php"
                class="grid gap-5 sm:grid-cols-2"
            >
                <div class="grid gap-2">
                    <Label
                        for="php_version"
                        class="text-sm font-normal text-neutral-500"
                        >PHP version</Label
                    >
                    <StacklabSelect
                        id="php_version"
                        v-model="form.php_version"
                    >
                        <option
                            v-for="version in server.php_versions"
                            :key="version"
                            :value="version"
                        >
                            PHP {{ version }}
                        </option>
                    </StacklabSelect>
                    <InputError :message="form.errors.php_version" />
                    <p v-if="server.php_hint" class="text-sm text-neutral-400">
                        {{ server.php_hint }}
                    </p>
                </div>

                <div v-if="selectedProfile.requires_mysql" class="grid gap-2">
                    <Label
                        for="mysql_version"
                        class="text-sm font-normal text-neutral-500"
                        >MySQL version</Label
                    >
                    <StacklabSelect
                        id="mysql_version"
                        v-model="form.mysql_version"
                    >
                        <option
                            v-for="version in server.mysql_versions"
                            :key="version"
                            :value="version"
                        >
                            MySQL {{ version }}
                        </option>
                    </StacklabSelect>
                    <InputError :message="form.errors.mysql_version" />
                </div>
            </div>

            <InputError :message="form.errors.server" />

            <Button
                type="submit"
                :disabled="form.processing"
                class="h-10 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
            >
                <Spinner v-if="form.processing" />
                Start provisioning
            </Button>
        </form>
    </div>
</template>
