<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    ArrowUpRight,
    BookOpen,
    Boxes,
    Braces,
    GitBranch,
    GitCommitHorizontal,
    KeyRound,
    Layers,
    Menu,
    Play,
    Repeat2,
    Rocket,
    RotateCcw,
    ScrollText,
    Server,
    ShieldCheck,
    SquareTerminal,
    Terminal,
    Unplug,
    Wrench,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, h, onMounted, onUnmounted, ref } from 'vue';
import type { Component } from 'vue';
import { login, register } from '@/routes';
import { create as createServer } from '@/routes/servers';

type TerminalStep = {
    prefix: string;
    prefixClass: string;
    text: string;
    lineClass: string;
    speed: [number, number];
    pause: number;
    status: string;
};

type TerminalLine = {
    id: number;
    prefix: string;
    prefixClass: string;
    text: string;
    lineClass: string;
    showCursor: boolean;
    visible: boolean;
};

const GithubMark = (props: { class?: string }) =>
    h(
        'svg',
        {
            xmlns: 'http://www.w3.org/2000/svg',
            viewBox: '0 0 24 24',
            fill: 'none',
            stroke: 'currentColor',
            'stroke-width': 2,
            'stroke-linecap': 'round',
            'stroke-linejoin': 'round',
            'aria-hidden': 'true',
            class: props.class,
        },
        [
            h('path', {
                d: 'M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4',
            }),
            h('path', { d: 'M9 18c-4.51 2-5-2-7-2' }),
        ],
    );

const navLinks = [
    { href: '#features', label: 'Features' },
    { href: '#workflow', label: 'Workflow' },
    { href: '#github', label: 'GitHub' },
    { href: '#docs', label: 'Docs' },
] as const;

const capabilities: { icon: Component; label: string }[] = [
    { icon: KeyRound, label: 'SSH-first provisioning' },
    { icon: GithubMark, label: 'GitHub integrated' },
    { icon: Repeat2, label: 'Repeatable releases' },
    { icon: Unplug, label: 'No agent lock-in' },
];

const features: { icon: LucideIcon; title: string; body: string }[] = [
    {
        icon: KeyRound,
        title: 'Connect over SSH',
        body: 'Use standard SSH credentials. StackLab connects to the machine you already own without installing a permanent management agent.',
    },
    {
        icon: Boxes,
        title: 'Provision the stack',
        body: 'Install and configure the services your app needs — Nginx, PHP, Node.js, Redis, databases, and the system packages around them.',
    },
    {
        icon: GitBranch,
        title: 'Deploy from GitHub',
        body: 'Connect a repository, choose a branch, and turn a commit into a release with a predictable deployment sequence.',
    },
    {
        icon: ScrollText,
        title: 'See every step',
        body: 'Follow command output and provisioning state in real time so failures are visible instead of buried behind a vague spinner.',
    },
    {
        icon: Braces,
        title: 'Environment variables',
        body: 'Keep application configuration alongside the deployment target without baking secrets or environment-specific values into your repository.',
    },
    {
        icon: RotateCcw,
        title: 'Release-based deploys',
        body: 'Deploy into versioned releases and switch the active version cleanly, keeping deployment structure easy to inspect and recover.',
    },
];

const terminalSequence: TerminalStep[] = [
    {
        prefix: '$',
        prefixClass: 'text-stack-orange',
        text: 'stacklab provision production',
        lineClass: 'text-zinc-500',
        speed: [28, 54],
        pause: 520,
        status: 'Starting provisioning session',
    },
    {
        prefix: '›',
        prefixClass: 'text-zinc-600',
        text: 'Authenticating SSH key...',
        lineClass: 'text-zinc-600',
        speed: [12, 26],
        pause: 480,
        status: 'Authenticating SSH key',
    },
    {
        prefix: '✓',
        prefixClass: 'text-emerald-400',
        text: 'Secure SSH connection established',
        lineClass: 'text-zinc-400',
        speed: [10, 22],
        pause: 430,
        status: 'SSH connection established',
    },
    {
        prefix: '›',
        prefixClass: 'text-zinc-600',
        text: 'Detecting Ubuntu 24.04 LTS',
        lineClass: 'text-zinc-600',
        speed: [11, 23],
        pause: 410,
        status: 'Inspecting server environment',
    },
    {
        prefix: '✓',
        prefixClass: 'text-emerald-400',
        text: 'Server prerequisites verified',
        lineClass: 'text-zinc-400',
        speed: [10, 21],
        pause: 450,
        status: 'Prerequisites verified',
    },
    {
        prefix: '›',
        prefixClass: 'text-zinc-600',
        text: 'Installing PHP 8.4, Nginx, Redis',
        lineClass: 'text-zinc-600',
        speed: [10, 22],
        pause: 720,
        status: 'Installing server packages',
    },
    {
        prefix: '✓',
        prefixClass: 'text-emerald-400',
        text: 'Services configured and enabled',
        lineClass: 'text-zinc-400',
        speed: [10, 20],
        pause: 460,
        status: 'Configuring services',
    },
    {
        prefix: '›',
        prefixClass: 'text-zinc-600',
        text: 'Linking GitHub repository',
        lineClass: 'text-zinc-600',
        speed: [11, 23],
        pause: 480,
        status: 'Connecting GitHub repository',
    },
    {
        prefix: '✓',
        prefixClass: 'text-emerald-400',
        text: 'github.com/stacklab/demo.git',
        lineClass: 'text-zinc-400',
        speed: [10, 20],
        pause: 440,
        status: 'Repository connected',
    },
    {
        prefix: '›',
        prefixClass: 'text-zinc-600',
        text: 'Deploying branch main',
        lineClass: 'text-zinc-600',
        speed: [12, 24],
        pause: 620,
        status: 'Deploying main branch',
    },
    {
        prefix: '◆',
        prefixClass: 'text-stack-orange',
        text: 'Release #104 is live',
        lineClass: 'text-zinc-300',
        speed: [18, 34],
        pause: 460,
        status: 'Activating release #104',
    },
    {
        prefix: '✓',
        prefixClass: 'text-emerald-400',
        text: 'Deployment complete',
        lineClass: 'text-white',
        speed: [24, 44],
        pause: 2400,
        status: 'Release #104 is healthy',
    },
];

const page = usePage();
const user = computed(() => page.props.auth.user);
const startHref = computed(() => (user.value ? createServer() : register()));

const pageRoot = ref<HTMLElement | null>(null);
const mobileMenuOpen = ref(false);
const terminalLines = ref<TerminalLine[]>([]);
const terminalStatus = ref('Watching deployment health');
const terminalTime = ref('00:00');

let cancelled = false;
let revealObserver: IntersectionObserver | null = null;
let terminalTimer: ReturnType<typeof setInterval> | null = null;
let nextLineId = 0;

function closeMobileMenu(): void {
    mobileMenuOpen.value = false;
}

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
}

function randomBetween(min: number, max: number): number {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function createTerminalLine(item: TerminalStep): TerminalLine {
    const line: TerminalLine = {
        id: nextLineId++,
        prefix: item.prefix,
        prefixClass: item.prefixClass,
        text: '',
        lineClass: item.lineClass,
        showCursor: true,
        visible: false,
    };

    terminalLines.value.push(line);
    requestAnimationFrame(() => {
        line.visible = true;
    });

    return line;
}

async function typeTerminalText(
    line: TerminalLine,
    text: string,
    speed: [number, number],
): Promise<void> {
    line.text = '';

    for (const character of text) {
        if (cancelled) {
            return;
        }

        line.text += character;
        await sleep(randomBetween(speed[0], speed[1]));
    }
}

async function eraseTerminalLines(): Promise<void> {
    for (let index = terminalLines.value.length - 1; index >= 0; index--) {
        if (cancelled) {
            return;
        }

        const line = terminalLines.value[index];

        if (!line) {
            continue;
        }

        line.showCursor = true;

        while (line.text.length) {
            if (cancelled) {
                return;
            }

            line.text = line.text.slice(0, -1);
            await sleep(randomBetween(7, 14));
        }

        line.visible = false;
        await sleep(38);
        terminalLines.value.splice(index, 1);
    }
}

function renderTerminalStatic(): void {
    terminalLines.value = terminalSequence.map((item) => ({
        id: nextLineId++,
        prefix: item.prefix,
        prefixClass: item.prefixClass,
        text: item.text,
        lineClass: item.lineClass,
        showCursor: false,
        visible: true,
    }));
    terminalStatus.value = 'Release #104 is healthy';
    terminalTime.value = '00:38';
}

function clearTerminalTimer(): void {
    if (terminalTimer) {
        window.clearInterval(terminalTimer);
        terminalTimer = null;
    }
}

async function runTerminalSequence(): Promise<void> {
    while (!cancelled) {
        terminalLines.value = [];
        const startedAt = Date.now();
        clearTerminalTimer();
        terminalTimer = window.setInterval(() => {
            const seconds = Math.floor((Date.now() - startedAt) / 1000);
            terminalTime.value = `00:${String(seconds).padStart(2, '0')}`;
        }, 250);

        for (const item of terminalSequence) {
            if (cancelled) {
                return;
            }

            terminalStatus.value = item.status;
            const line = createTerminalLine(item);
            await typeTerminalText(line, item.text, item.speed);
            line.showCursor = false;
            await sleep(item.pause);
        }

        clearTerminalTimer();
        await eraseTerminalLines();
        terminalTime.value = '00:00';
        terminalStatus.value = 'Ready for next deployment';
        await sleep(650);
    }
}

onMounted(() => {
    const reducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    if (!reducedMotion) {
        document.documentElement.classList.add('scroll-smooth');
    }

    const revealItems = pageRoot.value?.querySelectorAll('.reveal') ?? [];

    if (reducedMotion || !('IntersectionObserver' in window)) {
        revealItems.forEach((item) => item.classList.add('visible'));
    } else {
        revealObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        revealObserver?.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.12 },
        );

        revealItems.forEach((item) => revealObserver?.observe(item));
    }

    if (reducedMotion) {
        renderTerminalStatic();
    } else {
        void runTerminalSequence();
    }
});

onUnmounted(() => {
    cancelled = true;
    clearTerminalTimer();
    revealObserver?.disconnect();
    document.documentElement.classList.remove('scroll-smooth');
});
</script>

<template>
    <Head title="StackLab — Provision. Deploy. Ship.">
        <meta
            head-key="description"
            name="description"
            content="StackLab provisions servers over SSH and deploys applications from GitHub with a clean, repeatable workflow."
        />
        <meta head-key="theme-color" name="theme-color" content="#09090b" />
    </Head>

    <div
        ref="pageRoot"
        class="welcome-page min-h-screen overflow-x-hidden font-sans text-zinc-100 antialiased"
    >
        <div class="grid-bg pointer-events-none fixed inset-0 -z-20"></div>
        <div class="noise pointer-events-none fixed inset-0 -z-10"></div>

        <header
            class="sticky top-0 z-50 border-b border-white/[0.06] bg-[#09090b]/80 backdrop-blur-xl"
        >
            <nav
                class="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 sm:px-6 lg:px-8"
                aria-label="Primary navigation"
            >
                <a
                    href="#top"
                    class="group flex items-center gap-2.5"
                    aria-label="StackLab home"
                >
                    <span
                        class="grid h-8 w-8 place-items-center rounded-lg bg-stack-orange text-[#17100a] shadow-orange transition-transform group-hover:-rotate-3"
                    >
                        <Layers
                            class="size-[1.125rem]"
                            :stroke-width="2.4"
                            aria-hidden="true"
                        />
                    </span>
                    <span
                        class="text-[15px] font-semibold tracking-tight text-white"
                        >StackLab</span
                    >
                </a>

                <div
                    class="hidden items-center gap-7 text-sm text-zinc-400 md:flex"
                >
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="transition-colors hover:text-white"
                    >
                        {{ link.label }}
                    </a>
                </div>

                <div class="hidden items-center gap-5 md:flex">
                    <Link
                        v-if="!user"
                        :href="login()"
                        class="text-sm text-zinc-400 transition-colors hover:text-white"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="startHref"
                        class="inline-flex items-center gap-2 rounded-lg bg-stack-orange px-4 py-2 text-sm font-semibold text-[#17100a] shadow-orange transition hover:bg-stack-orange-soft focus:ring-2 focus:ring-stack-orange/60 focus:ring-offset-2 focus:ring-offset-[#09090b] focus:outline-none"
                    >
                        Get started
                        <ArrowUpRight class="h-4 w-4" aria-hidden="true" />
                    </Link>
                </div>

                <button
                    type="button"
                    class="grid h-9 w-9 place-items-center rounded-lg border border-white/10 text-zinc-300 transition hover:border-white/20 hover:bg-white/[0.04] hover:text-white focus:ring-2 focus:ring-stack-orange/60 focus:outline-none md:hidden"
                    aria-controls="mobile-menu"
                    :aria-expanded="mobileMenuOpen"
                    aria-label="Toggle navigation"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <Menu class="h-5 w-5" aria-hidden="true" />
                </button>
            </nav>

            <div
                v-show="mobileMenuOpen"
                id="mobile-menu"
                class="border-t border-white/[0.06] bg-[#09090b]/95 px-5 py-4 backdrop-blur-xl md:hidden"
            >
                <div class="mx-auto flex max-w-7xl flex-col gap-1 text-sm">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-lg px-3 py-2.5 text-zinc-300 hover:bg-white/[0.04] hover:text-white"
                        @click="closeMobileMenu"
                    >
                        {{ link.label }}
                    </a>
                    <Link
                        v-if="!user"
                        :href="login()"
                        class="rounded-lg px-3 py-2.5 text-zinc-300 hover:bg-white/[0.04] hover:text-white"
                        @click="closeMobileMenu"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="startHref"
                        class="mt-2 inline-flex items-center justify-center gap-2 rounded-lg bg-stack-orange px-4 py-2.5 font-semibold text-[#17100a]"
                        @click="closeMobileMenu"
                    >
                        Get started
                        <ArrowRight class="h-4 w-4" aria-hidden="true" />
                    </Link>
                </div>
            </div>
        </header>

        <main id="top">
            <section class="relative isolate overflow-hidden">
                <div
                    class="pointer-events-none absolute top-16 left-1/2 -z-10 h-[34rem] w-[34rem] -translate-x-1/2 rounded-full bg-stack-orange/[0.055] blur-3xl"
                ></div>

                <div
                    class="mx-auto grid max-w-7xl gap-14 px-5 pt-20 pb-20 sm:px-6 sm:pt-24 lg:grid-cols-[0.96fr_1.04fr] lg:items-center lg:gap-16 lg:px-8 lg:pt-28 lg:pb-28"
                >
                    <div class="max-w-2xl">
                        <div
                            class="mb-6 inline-flex items-center gap-2 rounded-full border border-stack-orange/20 bg-stack-orange/[0.07] px-3 py-1.5 text-xs font-medium text-stack-orange-soft"
                        >
                            <span
                                class="status-dot h-1.5 w-1.5 rounded-full bg-stack-orange"
                            ></span>
                            Server provisioning, without the ceremony
                        </div>

                        <h1
                            class="max-w-3xl text-5xl leading-[1.02] font-semibold tracking-[-0.045em] text-white sm:text-6xl lg:text-[4.35rem]"
                        >
                            Ship servers.<br />
                            <span class="text-zinc-500"
                                >Not setup scripts.</span
                            >
                        </h1>

                        <p
                            class="mt-6 max-w-xl text-base leading-7 text-zinc-400 sm:text-lg sm:leading-8"
                        >
                            Connect any server over SSH, provision the stack you
                            need, then deploy directly from GitHub. StackLab
                            keeps infrastructure work visible, repeatable, and
                            simple.
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Link
                                :href="startHref"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-stack-orange px-5 py-3 text-sm font-semibold text-[#17100a] shadow-orange transition hover:-translate-y-0.5 hover:bg-stack-orange-soft focus:ring-2 focus:ring-stack-orange/60 focus:ring-offset-2 focus:ring-offset-[#09090b] focus:outline-none"
                            >
                                Provision a server
                                <ArrowRight
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />
                            </Link>
                            <a
                                href="#workflow"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/[0.025] px-5 py-3 text-sm font-medium text-zinc-200 transition hover:-translate-y-0.5 hover:border-white/20 hover:bg-white/[0.05]"
                            >
                                <Play
                                    class="h-4 w-4 text-zinc-500"
                                    aria-hidden="true"
                                />
                                See how it works
                            </a>
                        </div>

                        <div
                            class="mt-9 flex flex-wrap gap-x-5 gap-y-3 text-xs text-zinc-500"
                        >
                            <span class="inline-flex items-center gap-2">
                                <KeyRound
                                    class="h-3.5 w-3.5"
                                    aria-hidden="true"
                                />
                                SSH key access
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <GithubMark class="h-3.5 w-3.5" />
                                GitHub deploys
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <ShieldCheck
                                    class="h-3.5 w-3.5"
                                    aria-hidden="true"
                                />
                                No server agent
                            </span>
                        </div>
                    </div>

                    <div
                        class="reveal relative mx-auto w-full max-w-2xl lg:mx-0"
                    >
                        <div
                            class="absolute -inset-6 -z-10 rounded-[2rem] bg-stack-orange/[0.055] blur-2xl"
                        ></div>

                        <div
                            class="overflow-hidden rounded-2xl border border-white/10 bg-[#0d0d0f] shadow-glow"
                        >
                            <div
                                class="flex h-12 items-center justify-between border-b border-white/[0.07] bg-white/[0.018] px-4 sm:px-5"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="h-2.5 w-2.5 rounded-full bg-zinc-700"
                                    ></span>
                                    <span
                                        class="h-2.5 w-2.5 rounded-full bg-zinc-700"
                                    ></span>
                                    <span
                                        class="h-2.5 w-2.5 rounded-full bg-stack-orange/70"
                                    ></span>
                                </div>
                                <div
                                    class="flex items-center gap-2 font-mono text-[11px] text-zinc-500"
                                >
                                    <SquareTerminal
                                        class="h-3.5 w-3.5"
                                        aria-hidden="true"
                                    />
                                    deploy@stacklab
                                </div>
                                <div class="w-12"></div>
                            </div>

                            <div
                                class="terminal-scan relative flex h-[24rem] flex-col overflow-hidden p-5 font-mono text-[12px] leading-6 sm:h-[26rem] sm:p-6 sm:text-[13px]"
                            >
                                <div
                                    class="mb-5 flex shrink-0 items-center justify-between rounded-xl border border-white/[0.07] bg-white/[0.018] px-4 py-3"
                                >
                                    <div>
                                        <p
                                            class="text-[10px] tracking-[0.18em] text-zinc-600 uppercase"
                                        >
                                            Target
                                        </p>
                                        <p class="mt-0.5 text-zinc-300">
                                            ubuntu@192.0.2.18
                                        </p>
                                    </div>
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border border-emerald-400/15 bg-emerald-400/[0.06] px-2.5 py-1 text-[10px] text-emerald-400"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full bg-emerald-400"
                                        ></span>
                                        Connected
                                    </span>
                                </div>

                                <div
                                    class="flex min-h-0 flex-1 flex-col justify-end overflow-hidden"
                                    aria-live="polite"
                                    aria-atomic="false"
                                >
                                    <div class="space-y-1.5">
                                        <p
                                            v-for="line in terminalLines"
                                            :key="line.id"
                                            class="terminal-line"
                                            :class="[
                                                line.lineClass,
                                                { 'is-visible': line.visible },
                                            ]"
                                        >
                                            <span
                                                class="mr-2"
                                                :class="line.prefixClass"
                                                >{{ line.prefix }}</span
                                            ><span>{{ line.text }}</span
                                            ><span
                                                v-if="line.showCursor"
                                                class="terminal-cursor"
                                                aria-hidden="true"
                                            ></span>
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-6 shrink-0 border-t border-white/[0.06] pt-4"
                                >
                                    <div
                                        class="flex items-center justify-between gap-4"
                                    >
                                        <div
                                            class="flex items-center gap-2 text-[11px] text-zinc-500"
                                        >
                                            <span
                                                class="h-2 w-2 animate-pulse rounded-full bg-stack-orange"
                                            ></span>
                                            <span>{{ terminalStatus }}</span>
                                        </div>
                                        <span
                                            class="text-[11px] text-zinc-600"
                                            >{{ terminalTime }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="absolute -bottom-5 -left-4 hidden items-center gap-3 rounded-xl border border-white/10 bg-[#131315]/95 px-3.5 py-3 shadow-xl backdrop-blur sm:flex"
                        >
                            <span
                                class="grid h-8 w-8 place-items-center rounded-lg bg-stack-orange/10 text-stack-orange"
                            >
                                <GithubMark class="h-4 w-4" />
                            </span>
                            <div>
                                <p
                                    class="text-[10px] tracking-[0.15em] text-zinc-600 uppercase"
                                >
                                    Source
                                </p>
                                <p class="text-xs font-medium text-zinc-300">
                                    main · a7e29c1
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-y border-white/[0.06] bg-white/[0.012]">
                <div
                    class="mx-auto grid max-w-7xl divide-y divide-white/[0.06] px-5 sm:grid-cols-2 sm:divide-x sm:divide-y-0 sm:px-6 lg:grid-cols-4 lg:px-8"
                >
                    <div
                        v-for="capability in capabilities"
                        :key="capability.label"
                        class="flex items-center gap-3 py-5 sm:px-5 lg:px-6"
                    >
                        <component
                            :is="capability.icon"
                            class="h-4 w-4 text-stack-orange"
                            aria-hidden="true"
                        />
                        <span class="text-sm text-zinc-400">{{
                            capability.label
                        }}</span>
                    </div>
                </div>
            </section>

            <section
                id="features"
                class="mx-auto max-w-7xl px-5 py-24 sm:px-6 lg:px-8 lg:py-32"
            >
                <div class="reveal max-w-2xl">
                    <p
                        class="mb-3 font-mono text-xs tracking-[0.22em] text-stack-orange uppercase"
                    >
                        Built for the path to production
                    </p>
                    <h2
                        class="text-3xl font-semibold tracking-[-0.035em] text-white sm:text-4xl"
                    >
                        Everything between a clean server and shipped code.
                    </h2>
                    <p class="mt-4 text-base leading-7 text-zinc-500">
                        StackLab handles the repeatable infrastructure work
                        while keeping the server and deployment flow
                        understandable.
                    </p>
                </div>

                <div class="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="feature in features"
                        :key="feature.title"
                        class="feature-card reveal relative overflow-hidden rounded-2xl border border-white/[0.07] bg-white/[0.018] p-6 transition hover:-translate-y-1 hover:border-white/[0.13]"
                    >
                        <span
                            class="mb-5 grid h-10 w-10 place-items-center rounded-xl border border-stack-orange/15 bg-stack-orange/[0.07] text-stack-orange"
                        >
                            <component
                                :is="feature.icon"
                                class="h-5 w-5"
                                aria-hidden="true"
                            />
                        </span>
                        <h3 class="text-base font-semibold text-white">
                            {{ feature.title }}
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-zinc-500">
                            {{ feature.body }}
                        </p>
                    </article>
                </div>
            </section>

            <section
                id="workflow"
                class="border-y border-white/[0.06] bg-[#0c0c0e]"
            >
                <div
                    class="mx-auto max-w-7xl px-5 py-24 sm:px-6 lg:px-8 lg:py-32"
                >
                    <div
                        class="reveal flex max-w-3xl flex-col justify-between gap-6 lg:max-w-none lg:flex-row lg:items-end"
                    >
                        <div class="max-w-2xl">
                            <p
                                class="mb-3 font-mono text-xs tracking-[0.22em] text-stack-orange uppercase"
                            >
                                Three steps
                            </p>
                            <h2
                                class="text-3xl font-semibold tracking-[-0.035em] text-white sm:text-4xl"
                            >
                                From IP address to production.
                            </h2>
                        </div>
                        <p class="max-w-md text-sm leading-6 text-zinc-500">
                            No custom image. No proprietary agent. Start with a
                            reachable Linux server and finish with your GitHub
                            project live.
                        </p>
                    </div>

                    <div class="relative mt-14 grid gap-5 lg:grid-cols-3">
                        <div
                            class="pointer-events-none absolute top-8 right-[17%] left-[17%] hidden h-px bg-gradient-to-r from-transparent via-stack-orange/35 to-transparent lg:block"
                        ></div>

                        <article
                            class="reveal relative rounded-2xl border border-white/[0.07] bg-white/[0.018] p-6 lg:p-7"
                        >
                            <div
                                class="mb-10 flex items-center justify-between"
                            >
                                <span
                                    class="grid h-9 w-9 place-items-center rounded-full border border-stack-orange/25 bg-[#0c0c0e] font-mono text-xs font-semibold text-stack-orange"
                                    >01</span
                                >
                                <Server
                                    class="h-5 w-5 text-zinc-600"
                                    aria-hidden="true"
                                />
                            </div>
                            <h3 class="text-lg font-semibold text-white">
                                Connect your server
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-zinc-500">
                                Add the server IP, SSH user, and key. StackLab
                                verifies access and inspects the machine before
                                making changes.
                            </p>
                            <div
                                class="mt-5 rounded-xl border border-white/[0.06] bg-black/20 px-4 py-3 font-mono text-[11px] text-zinc-500"
                            >
                                <span class="text-stack-orange">ssh</span>
                                ubuntu@192.0.2.18
                            </div>
                        </article>

                        <article
                            class="reveal relative rounded-2xl border border-white/[0.07] bg-white/[0.018] p-6 lg:p-7"
                        >
                            <div
                                class="mb-10 flex items-center justify-between"
                            >
                                <span
                                    class="grid h-9 w-9 place-items-center rounded-full border border-stack-orange/25 bg-[#0c0c0e] font-mono text-xs font-semibold text-stack-orange"
                                    >02</span
                                >
                                <Wrench
                                    class="h-5 w-5 text-zinc-600"
                                    aria-hidden="true"
                                />
                            </div>
                            <h3 class="text-lg font-semibold text-white">
                                Provision the stack
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-zinc-500">
                                Choose what the server should run. StackLab
                                executes the setup in explicit steps and
                                validates each service.
                            </p>
                            <div
                                class="mt-5 flex flex-wrap gap-2 font-mono text-[10px] text-zinc-400"
                            >
                                <span
                                    class="rounded-md border border-white/[0.07] bg-black/20 px-2.5 py-1.5"
                                    >nginx</span
                                >
                                <span
                                    class="rounded-md border border-white/[0.07] bg-black/20 px-2.5 py-1.5"
                                    >php 8.4</span
                                >
                                <span
                                    class="rounded-md border border-white/[0.07] bg-black/20 px-2.5 py-1.5"
                                    >redis</span
                                >
                            </div>
                        </article>

                        <article
                            class="reveal relative rounded-2xl border border-stack-orange/15 bg-stack-orange/[0.035] p-6 lg:p-7"
                        >
                            <div
                                class="mb-10 flex items-center justify-between"
                            >
                                <span
                                    class="grid h-9 w-9 place-items-center rounded-full border border-stack-orange/30 bg-[#0c0c0e] font-mono text-xs font-semibold text-stack-orange"
                                    >03</span
                                >
                                <Rocket
                                    class="h-5 w-5 text-stack-orange/70"
                                    aria-hidden="true"
                                />
                            </div>
                            <h3 class="text-lg font-semibold text-white">
                                Deploy from GitHub
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-zinc-500">
                                Select a repository and branch, define the
                                deployment command, and ship a release to the
                                configured server.
                            </p>
                            <div
                                class="mt-5 rounded-xl border border-stack-orange/10 bg-black/20 px-4 py-3 font-mono text-[11px] text-zinc-500"
                            >
                                <span class="text-emerald-400">✓</span> main →
                                release/104
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section
                id="github"
                class="mx-auto grid max-w-7xl gap-14 px-5 py-24 sm:px-6 lg:grid-cols-2 lg:items-center lg:gap-20 lg:px-8 lg:py-32"
            >
                <div class="reveal">
                    <div
                        class="mb-5 inline-flex items-center gap-2 rounded-lg border border-white/[0.08] bg-white/[0.025] px-3 py-2 text-xs text-zinc-400"
                    >
                        <GithubMark class="h-4 w-4 text-white" />
                        GitHub → StackLab → Server
                    </div>
                    <h2
                        class="max-w-xl text-3xl font-semibold tracking-[-0.035em] text-white sm:text-4xl"
                    >
                        A deploy flow you can actually follow.
                    </h2>
                    <p class="mt-5 max-w-xl text-base leading-7 text-zinc-500">
                        A deployment is just a sequence of understandable
                        operations: fetch the commit, prepare a release, install
                        dependencies, run your commands, and activate it.
                    </p>

                    <div class="mt-8 space-y-5">
                        <div class="flex gap-4">
                            <span
                                class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-stack-orange/[0.08] text-stack-orange"
                            >
                                <GitCommitHorizontal
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />
                            </span>
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-200">
                                    Deploy a specific commit
                                </h3>
                                <p class="mt-1 text-sm leading-6 text-zinc-500">
                                    Every release points back to source control,
                                    so you always know exactly what code is
                                    running.
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <span
                                class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-stack-orange/[0.08] text-stack-orange"
                            >
                                <Terminal class="h-4 w-4" aria-hidden="true" />
                            </span>
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-200">
                                    Run your own deploy commands
                                </h3>
                                <p class="mt-1 text-sm leading-6 text-zinc-500">
                                    Keep framework-specific work explicit, from
                                    Composer and migrations to npm builds or
                                    application caches.
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <span
                                class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-stack-orange/[0.08] text-stack-orange"
                            >
                                <Activity class="h-4 w-4" aria-hidden="true" />
                            </span>
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-200">
                                    Know where it failed
                                </h3>
                                <p class="mt-1 text-sm leading-6 text-zinc-500">
                                    Each command reports its output and state
                                    instead of collapsing an entire deployment
                                    into one opaque status.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="reveal overflow-hidden rounded-2xl border border-white/[0.08] bg-[#0d0d0f] shadow-glow"
                >
                    <div
                        class="flex items-center justify-between border-b border-white/[0.07] px-5 py-4"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="grid h-8 w-8 place-items-center rounded-lg border border-white/[0.08] bg-white/[0.025]"
                            >
                                <GithubMark class="h-4 w-4" />
                            </span>
                            <div>
                                <p class="text-xs font-medium text-zinc-200">
                                    stacklab/demo
                                </p>
                                <p
                                    class="mt-0.5 font-mono text-[10px] text-zinc-600"
                                >
                                    main · a7e29c1
                                </p>
                            </div>
                        </div>
                        <span
                            class="rounded-full border border-emerald-400/15 bg-emerald-400/[0.06] px-2.5 py-1 text-[10px] font-medium text-emerald-400"
                            >Live</span
                        >
                    </div>

                    <div class="p-5 sm:p-6">
                        <p
                            class="mb-4 font-mono text-[10px] tracking-[0.18em] text-zinc-600 uppercase"
                        >
                            Deployment command
                        </p>
                        <div
                            class="overflow-x-auto rounded-xl border border-white/[0.06] bg-black/25 p-4 font-mono text-[11px] leading-6 text-zinc-500 sm:text-xs"
                        >
                            <p>
                                <span class="text-zinc-700">01</span>
                                <span class="ml-3 text-stack-orange">git</span>
                                fetch --depth=1 origin main
                            </p>
                            <p>
                                <span class="text-zinc-700">02</span>
                                <span class="ml-3 text-stack-orange"
                                    >composer</span
                                >
                                install --no-dev
                            </p>
                            <p>
                                <span class="text-zinc-700">03</span>
                                <span class="ml-3 text-stack-orange">php</span>
                                artisan migrate --force
                            </p>
                            <p>
                                <span class="text-zinc-700">04</span>
                                <span class="ml-3 text-stack-orange">npm</span>
                                ci && npm run build
                            </p>
                            <p>
                                <span class="text-zinc-700">05</span>
                                <span class="ml-3 text-stack-orange">php</span>
                                artisan optimize
                            </p>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            <div
                                class="rounded-xl border border-white/[0.06] bg-white/[0.018] p-3.5"
                            >
                                <p class="font-mono text-[10px] text-zinc-600">
                                    COMMIT
                                </p>
                                <p
                                    class="mt-1.5 text-xs font-medium text-zinc-300"
                                >
                                    a7e29c1
                                </p>
                            </div>
                            <div
                                class="rounded-xl border border-white/[0.06] bg-white/[0.018] p-3.5"
                            >
                                <p class="font-mono text-[10px] text-zinc-600">
                                    RELEASE
                                </p>
                                <p
                                    class="mt-1.5 text-xs font-medium text-zinc-300"
                                >
                                    #104
                                </p>
                            </div>
                            <div
                                class="rounded-xl border border-white/[0.06] bg-white/[0.018] p-3.5"
                            >
                                <p class="font-mono text-[10px] text-zinc-600">
                                    STATUS
                                </p>
                                <p
                                    class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-emerald-400"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-emerald-400"
                                    ></span>
                                    Healthy
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="docs"
                class="mx-auto max-w-7xl px-5 pb-24 sm:px-6 lg:px-8 lg:pb-32"
            >
                <div
                    class="reveal grid gap-5 rounded-2xl border border-white/[0.07] bg-white/[0.018] p-6 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-center"
                >
                    <div class="flex gap-4">
                        <span
                            class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-white/[0.08] bg-black/20 text-zinc-400"
                        >
                            <BookOpen class="h-5 w-5" aria-hidden="true" />
                        </span>
                        <div>
                            <h2 class="text-base font-semibold text-white">
                                Prefer to know what happens under the hood?
                            </h2>
                            <p
                                class="mt-1.5 max-w-2xl text-sm leading-6 text-zinc-500"
                            >
                                StackLab is designed around explicit
                                provisioning steps, commands, validation, and
                                logs — infrastructure should be understandable,
                                not magical.
                            </p>
                        </div>
                    </div>
                    <a
                        href="#workflow"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-white/10 px-4 py-2.5 text-sm font-medium text-zinc-300 transition hover:border-white/20 hover:bg-white/[0.04] hover:text-white"
                    >
                        Read the workflow
                        <ArrowRight class="h-4 w-4" aria-hidden="true" />
                    </a>
                </div>
            </section>

            <section
                id="start"
                class="relative overflow-hidden border-t border-white/[0.06]"
            >
                <div
                    class="absolute inset-x-0 bottom-0 -z-10 mx-auto h-72 max-w-5xl bg-stack-orange/[0.07] blur-[120px]"
                ></div>
                <div
                    class="mx-auto max-w-4xl px-5 py-24 text-center sm:px-6 lg:py-32"
                >
                    <div class="reveal">
                        <span
                            class="mx-auto mb-6 grid h-12 w-12 place-items-center rounded-2xl bg-stack-orange text-[#17100a] shadow-orange"
                        >
                            <Layers
                                class="h-6 w-6"
                                :stroke-width="2.3"
                                aria-hidden="true"
                            />
                        </span>
                        <h2
                            class="text-3xl font-semibold tracking-[-0.04em] text-white sm:text-5xl"
                        >
                            Your server. Your code.<br />
                            <span class="text-zinc-500"
                                >StackLab handles the path between.</span
                            >
                        </h2>
                        <p
                            class="mx-auto mt-5 max-w-xl text-base leading-7 text-zinc-500"
                        >
                            Connect over SSH, provision the machine, attach a
                            GitHub repository, and ship the first release.
                        </p>
                        <div
                            class="mt-8 flex flex-col justify-center gap-3 sm:flex-row"
                        >
                            <Link
                                :href="startHref"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-stack-orange px-5 py-3 text-sm font-semibold text-[#17100a] shadow-orange transition hover:-translate-y-0.5 hover:bg-stack-orange-soft"
                            >
                                Create your first server
                                <ArrowUpRight
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />
                            </Link>
                            <a
                                href="#github"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/[0.025] px-5 py-3 text-sm font-medium text-zinc-300 transition hover:border-white/20 hover:bg-white/[0.05] hover:text-white"
                            >
                                <GithubMark class="h-4 w-4" />
                                View GitHub flow
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/[0.06]">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-8 text-xs text-zinc-600 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"
            >
                <div class="flex items-center gap-2.5">
                    <span
                        class="grid h-7 w-7 place-items-center rounded-lg bg-stack-orange/10 text-stack-orange"
                    >
                        <Layers class="h-3.5 w-3.5" aria-hidden="true" />
                    </span>
                    <span class="font-medium text-zinc-400">StackLab</span>
                    <span>© 2026</span>
                </div>
                <div class="flex flex-wrap items-center gap-5">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="transition hover:text-zinc-300"
                    >
                        {{ link.label }}
                    </a>
                </div>
            </div>
        </footer>
    </div>
</template>
