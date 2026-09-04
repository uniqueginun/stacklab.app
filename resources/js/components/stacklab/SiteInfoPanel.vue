<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';
import { show as serverShow } from '@/routes/servers';
import { source as siteSource } from '@/routes/sites';
import type { SiteShow } from '@/types';

const props = defineProps<{
    site: SiteShow;
}>();

const formatTime = (value: string | null): string => {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleString();
};

const details = computed(() => [
    { label: 'Domain', value: props.site.domain, href: props.site.url },
    { label: 'Type', value: props.site.type },
    { label: 'Status', value: props.site.status_label },
    { label: 'Web directory', value: props.site.web_directory || '/' },
    { label: 'Site path', value: props.site.root_path || 'Not set' },
    { label: 'PHP version', value: props.site.php_version || 'Not set' },
    {
        label: 'Repository',
        value: props.site.repository_url || 'Not connected',
    },
    {
        label: 'Branch',
        value: props.site.repository_branch || '—',
    },
    {
        label: 'Current release',
        value: props.site.current_release
            ? `${props.site.current_release.short_sha}${
                  props.site.current_release.commit_message
                      ? ` · ${props.site.current_release.commit_message}`
                      : ''
              }`
            : 'No release yet',
    },
    {
        label: 'Last deployed',
        value: formatTime(props.site.last_deployed_at),
    },
    {
        label: 'Created',
        value: formatTime(props.site.created_at),
    },
]);
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white"
    >
        <div class="border-b border-neutral-100 px-6 py-5">
            <h2 class="font-semibold">Site info</h2>
            <p class="mt-1 text-sm text-neutral-500">
                Paths, stack, and the current release for this site.
            </p>
        </div>

        <dl class="grid gap-px bg-neutral-100 sm:grid-cols-2">
            <div
                v-for="detail in details"
                :key="detail.label"
                class="bg-white px-6 py-4"
            >
                <dt class="text-xs text-neutral-400">{{ detail.label }}</dt>
                <dd class="mt-1 truncate font-medium">
                    <a
                        v-if="detail.href"
                        :href="detail.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex max-w-full items-center gap-1 hover:text-neutral-900"
                    >
                        <span class="truncate">{{ detail.value }}</span>
                        <ArrowUpRight
                            class="size-3.5 shrink-0 text-neutral-400"
                            aria-hidden="true"
                        />
                    </a>
                    <template v-else>{{ detail.value }}</template>
                </dd>
            </div>
            <div class="bg-white px-6 py-4">
                <dt class="text-xs text-neutral-400">Server</dt>
                <dd class="mt-1 font-medium">
                    <Link
                        :href="serverShow(site.server.uuid)"
                        class="hover:text-neutral-900"
                    >
                        {{ site.server.name }}
                    </Link>
                    <span class="text-neutral-500">
                        · {{ site.server.host }}
                    </span>
                </dd>
            </div>
        </dl>

        <div
            v-if="!site.repository_url"
            class="border-t border-neutral-100 bg-neutral-50/70 px-6 py-3 text-sm text-neutral-500"
        >
            Connect a repository on
            <Link
                :href="siteSource(site.uuid)"
                class="font-medium text-neutral-700 hover:text-neutral-900"
            >
                source control
            </Link>
            before you can deploy.
        </div>
    </section>
</template>
