<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { ChevronsUpDown, Search } from '@lucide/vue';
import { computed } from 'vue';
import StacklabMark from '@/components/stacklab/StacklabMark.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Toaster } from '@/components/ui/sonner';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { login, register } from '@/routes';
import { index as serversIndex } from '@/routes/servers';
import { index as sitesIndex } from '@/routes/sites';

export type StacklabNav = 'app' | 'server' | 'site' | 'none';

const props = withDefaults(
    defineProps<{
        nav?: StacklabNav;
        workspace?: string;
        activeTab?: string;
    }>(),
    {
        nav: 'app',
        workspace: 'Personal',
        activeTab: '',
    },
);

const page = usePage();
const auth = computed(() => page.props.auth);
const { isCurrentUrl } = useCurrentUrl();

const initials = computed(() => {
    const name = auth.value?.user?.name;

    return name ? getInitials(name) : 'SL';
});

const appTabs = computed(() => [
    { label: 'Servers', href: serversIndex() },
    { label: 'Sites', href: sitesIndex() },
    { label: 'Login', href: login() },
    { label: 'Register', href: register() },
]);

const serverTabs = computed(() => [
    { label: 'Overview', href: '/servers/fragrant-forest', key: 'overview' },
    { label: 'Sites', href: '/servers/fragrant-forest#sites', key: 'sites' },
    { label: 'Servers', href: serversIndex().url, key: 'servers' },
]);

const siteTabs = computed(() => [
    { label: 'Overview', href: '/sites/chirper', key: 'overview' },
    { label: 'Deployments', href: '/sites/chirper', key: 'deployments' },
    { label: 'Servers', href: serversIndex().url, key: 'servers' },
]);

const isTabActive = (
    label: string,
    href: NonNullable<InertiaLinkProps['href']>,
) => {
    if (props.activeTab) {
        return props.activeTab.toLowerCase() === label.toLowerCase();
    }

    return isCurrentUrl(href);
};
</script>

<template>
    <div class="min-h-svh bg-[#F9F9F7] text-neutral-950">
        <header class="border-b border-neutral-200/80 bg-white">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-6">
                <Link
                    :href="serversIndex()"
                    class="flex items-center gap-2 font-semibold tracking-tight"
                >
                    <StacklabMark class="size-7" />
                    <span>stacklab.app</span>
                </Link>

                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 bg-[#F9F9F7] px-3 py-1 text-sm text-neutral-700"
                >
                    {{ workspace }}
                    <ChevronsUpDown class="size-3.5 text-neutral-400" />
                </button>

                <div
                    class="ml-auto flex max-w-md flex-1 items-center justify-end gap-3"
                >
                    <div
                        class="hidden h-10 w-full max-w-sm items-center rounded-full border border-neutral-200 bg-[#F9F9F7] px-3 text-sm text-neutral-400 sm:flex"
                    >
                        <Search class="mr-2 size-4" />
                        <span class="flex-1">Search</span>
                        <kbd
                            class="rounded-md border border-neutral-200 bg-white px-1.5 py-0.5 text-[10px] text-neutral-500"
                            >⌘ K</kbd
                        >
                    </div>

                    <DropdownMenu v-if="auth.user">
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-9 rounded-full p-0"
                            >
                                <Avatar class="size-9">
                                    <AvatarImage
                                        v-if="auth.user.avatar"
                                        :src="auth.user.avatar"
                                        :alt="auth.user.name"
                                    />
                                    <AvatarFallback
                                        class="bg-neutral-950 text-xs font-medium text-white"
                                    >
                                        {{ initials }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <div
                        v-else
                        class="flex size-9 items-center justify-center rounded-full bg-neutral-950 text-xs font-medium text-white"
                    >
                        {{ initials }}
                    </div>
                </div>
            </div>
        </header>

        <nav
            v-if="nav !== 'none'"
            class="border-b border-neutral-200/80 bg-white"
        >
            <div class="mx-auto flex max-w-6xl gap-6 px-6">
                <template v-if="nav === 'app'">
                    <Link
                        v-for="tab in appTabs"
                        :key="tab.label"
                        :href="tab.href"
                        class="relative py-3 text-sm text-neutral-500 transition-colors hover:text-neutral-950"
                        :class="
                            isTabActive(tab.label, tab.href)
                                ? 'font-medium text-neutral-950'
                                : ''
                        "
                    >
                        {{ tab.label }}
                        <span
                            v-if="isTabActive(tab.label, tab.href)"
                            class="absolute inset-x-0 -bottom-px h-0.5 bg-neutral-950"
                        />
                    </Link>
                </template>
                <template v-else-if="nav === 'server'">
                    <Link
                        v-for="tab in serverTabs"
                        :key="tab.label"
                        :href="tab.href"
                        class="relative py-3 text-sm text-neutral-500 transition-colors hover:text-neutral-950"
                        :class="
                            (activeTab || 'overview') === tab.key
                                ? 'font-medium text-neutral-950'
                                : ''
                        "
                    >
                        {{ tab.label }}
                        <span
                            v-if="(activeTab || 'overview') === tab.key"
                            class="absolute inset-x-0 -bottom-px h-0.5 bg-neutral-950"
                        />
                    </Link>
                </template>
                <template v-else-if="nav === 'site'">
                    <Link
                        v-for="tab in siteTabs"
                        :key="tab.label"
                        :href="tab.href"
                        class="relative py-3 text-sm text-neutral-500 transition-colors hover:text-neutral-950"
                        :class="
                            (activeTab || 'deployments') === tab.key
                                ? 'font-medium text-neutral-950'
                                : ''
                        "
                    >
                        {{ tab.label }}
                        <span
                            v-if="(activeTab || 'deployments') === tab.key"
                            class="absolute inset-x-0 -bottom-px h-0.5 bg-neutral-950"
                        />
                    </Link>
                </template>
            </div>
        </nav>

        <main class="mx-auto w-full max-w-6xl px-6 py-8">
            <slot />
        </main>

        <Toaster />
    </div>
</template>
