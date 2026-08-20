<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
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
import {
    databases as serverDatabases,
    index as serversIndex,
    show as serverShow,
} from '@/routes/servers';
import {
    commands as siteCommands,
    deployments as siteDeployments,
    environment as siteEnvironment,
    index as sitesIndex,
    show as siteShow,
    source as siteSource,
} from '@/routes/sites';

export type StacklabNav = 'app' | 'server' | 'site' | 'none';

const props = withDefaults(
    defineProps<{
        nav?: StacklabNav;
        workspace?: string;
        activeTab?: string;
        serverUuid?: string;
        siteUuid?: string;
        siteIsPhp?: boolean;
        siteIsLaravel?: boolean;
    }>(),
    {
        nav: 'app',
        workspace: 'Personal',
        activeTab: '',
        serverUuid: '',
        siteUuid: '',
        siteIsPhp: false,
        siteIsLaravel: false,
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
]);

const serverTabs = computed(() => {
    const overview = props.serverUuid
        ? serverShow(props.serverUuid)
        : serversIndex();
    const sites = props.serverUuid
        ? `${serverShow.url(props.serverUuid)}#sites`
        : `${serversIndex().url}#sites`;

    return [
        { label: 'Overview', href: overview, key: 'overview' },
        { label: 'Sites', href: sites, key: 'sites' },
        {
            label: 'Databases',
            href: props.serverUuid
                ? serverDatabases(props.serverUuid)
                : serversIndex(),
            key: 'databases',
        },
    ];
});

const siteTabs = computed(() => {
    const info = props.siteUuid ? siteShow(props.siteUuid) : sitesIndex();
    const source = props.siteUuid ? siteSource(props.siteUuid) : sitesIndex();
    const deployments = props.siteUuid
        ? siteDeployments(props.siteUuid)
        : sitesIndex();

    const tabs = [
        { label: 'Site info', href: info, key: 'info' },
        { label: 'Source control', href: source, key: 'source' },
        { label: 'Deployments', href: deployments, key: 'deployments' },
    ];

    if (props.siteIsPhp && props.siteUuid) {
        tabs.push({
            label: 'Environment',
            href: siteEnvironment(props.siteUuid),
            key: 'environment',
        });
    }

    if (props.siteIsLaravel && props.siteUuid) {
        tabs.push({
            label: 'Commands',
            href: siteCommands(props.siteUuid),
            key: 'commands',
        });
    }

    return tabs;
});

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

                <span
                    class="inline-flex items-center rounded-full border border-neutral-200 bg-[#F9F9F7] px-3 py-1 text-sm text-neutral-700"
                >
                    {{ workspace }}
                </span>

                <div class="ml-auto flex items-center justify-end gap-3">
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
                            (activeTab || 'info') === tab.key
                                ? 'font-medium text-neutral-950'
                                : ''
                        "
                    >
                        {{ tab.label }}
                        <span
                            v-if="(activeTab || 'info') === tab.key"
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
