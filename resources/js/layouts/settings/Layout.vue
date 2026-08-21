<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Security',
        href: editSecurity(),
    },
    {
        title: 'Appearance',
        href: editAppearance(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div>
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Settings</h1>
            <p class="mt-1 text-sm text-neutral-500">
                Manage your profile and account.
            </p>
        </div>

        <div class="mt-8 flex flex-col gap-8 lg:flex-row">
            <aside class="w-full lg:w-48">
                <nav
                    class="flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible"
                    aria-label="Settings"
                >
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        class="relative shrink-0 rounded-lg px-3 py-2 text-sm text-neutral-500 transition-colors hover:bg-white hover:text-neutral-950"
                        :class="
                            isCurrentOrParentUrl(item.href)
                                ? 'bg-white font-medium text-neutral-950 shadow-sm'
                                : ''
                        "
                    >
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <div
                class="min-w-0 flex-1 rounded-2xl border border-neutral-200/80 bg-white p-6 md:p-8"
            >
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
