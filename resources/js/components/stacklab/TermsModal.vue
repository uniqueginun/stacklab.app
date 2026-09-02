<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';

export type LegalDocument = 'terms' | 'privacy';

const props = defineProps<{
    open: boolean;
    document: LegalDocument;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    accept: [];
}>();

const title = computed(() =>
    props.document === 'privacy' ? 'Privacy Policy' : 'Terms of Service',
);

const description = computed(() =>
    props.document === 'privacy'
        ? 'How StackLab collects, uses, and stores your information.'
        : 'The rules for using StackLab to provision servers and deploy sites.',
);

const accept = () => {
    emit('accept');
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="flex max-h-[85vh] max-w-lg flex-col gap-0 overflow-hidden rounded-2xl border-neutral-200 bg-white p-0 sm:max-w-lg"
        >
            <div class="border-b border-neutral-200 px-6 py-5">
                <DialogTitle class="text-lg font-semibold">{{
                    title
                }}</DialogTitle>
                <DialogDescription class="mt-1 text-sm text-neutral-500">
                    {{ description }}
                </DialogDescription>
            </div>

            <div
                class="space-y-4 overflow-y-auto px-6 py-5 text-sm leading-6 text-neutral-600"
            >
                <template v-if="document === 'terms'">
                    <p>
                        Last updated September 2, 2026. These Terms of Service
                        (“Terms”) govern access to stacklab.app and the StackLab
                        server provisioning and deployment service (“Service”).
                        By creating an account you agree to these Terms.
                    </p>
                    <div>
                        <h3 class="font-semibold text-neutral-950">
                            The service
                        </h3>
                        <p class="mt-1">
                            StackLab helps you connect Linux servers over SSH,
                            install a software stack you choose, create sites,
                            and deploy from GitHub. You keep ownership of your
                            servers, domains, code, and data. StackLab does not
                            host customer websites on its own infrastructure.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-neutral-950">
                            Your account
                        </h3>
                        <p class="mt-1">
                            You must provide accurate registration details and
                            keep your password confidential. You are responsible
                            for activity under your account, including SSH keys
                            StackLab generates for your servers and any GitHub
                            connection you authorize.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-neutral-950">
                            Servers and access
                        </h3>
                        <p class="mt-1">
                            By connecting a server you authorize StackLab to
                            authenticate over SSH, install software, manage
                            Nginx, PHP, databases, SSL certificates, queue
                            workers, and related configuration, and run
                            deployment commands you request. You confirm you
                            have the right to grant that access and that using
                            the Service on that server does not violate a
                            hosting provider’s terms.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-neutral-950">
                            Acceptable use
                        </h3>
                        <p class="mt-1">
                            Do not use StackLab to attack other systems, mine
                            cryptocurrency without authorization, send spam,
                            host illegal content, or interfere with the Service.
                            We may suspend or close an account that we
                            reasonably believe violates these Terms or the law.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-neutral-950">
                            Availability
                        </h3>
                        <p class="mt-1">
                            We aim to keep the Service reliable, but we do not
                            guarantee uninterrupted access. Provisioning,
                            deployments, and SSL issuance depend on your
                            server, network, DNS, and third parties such as
                            GitHub and certificate authorities.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-neutral-950">
                            Liability
                        </h3>
                        <p class="mt-1">
                            The Service is provided as-is. To the fullest extent
                            permitted by law, StackLab is not liable for lost
                            profits, lost data, server downtime, or indirect
                            damages arising from your use of the Service. You
                            remain responsible for backups and for the security
                            of servers you connect.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-neutral-950">Changes</h3>
                        <p class="mt-1">
                            We may update these Terms. Continued use of the
                            Service after an update constitutes acceptance of
                            the revised Terms. If you do not agree, stop using
                            the Service and delete your account.
                        </p>
                    </div>
                </template>

                <template v-else>
                    <p>
                        Last updated September 2, 2026. This Privacy Policy
                        explains what StackLab collects when you use
                        stacklab.app and how that information is used.
                    </p>
                    <div>
                        <h3 class="font-semibold text-neutral-950">
                            Information we collect
                        </h3>
                        <ul class="mt-1 list-disc space-y-1 pl-5">
                            <li>
                                Account details you submit, including name and
                                email address.
                            </li>
                            <li>
                                Server connection details such as hostname, SSH
                                user, port, and provisioning choices.
                            </li>
                            <li>
                                Site configuration, deployment history, and
                                operational logs needed to run the Service.
                            </li>
                            <li>
                                GitHub account identifiers and repository
                                metadata if you connect GitHub.
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-semibold text-neutral-950">
                            How we use it
                        </h3>
                        <p class="mt-1">
                            We use this information to create your account,
                            connect to servers you add, provision software,
                            deploy sites, issue certificates, provide support,
                            and keep the Service secure. We do not sell your
                            personal information.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-neutral-950">
                            SSH keys and secrets
                        </h3>
                        <p class="mt-1">
                            StackLab stores SSH keys and related credentials so
                            it can perform the actions you request. Treat your
                            StackLab account as privileged access to those
                            servers. Revoke access by deleting the server or
                            account from StackLab.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-neutral-950">
                            Sharing
                        </h3>
                        <p class="mt-1">
                            We share information with infrastructure providers
                            that host the Service, and with third parties you
                            connect such as GitHub, only as needed to operate
                            the features you use or as required by law.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-neutral-950">
                            Retention
                        </h3>
                        <p class="mt-1">
                            We keep account and server records while your
                            account is active and for a reasonable period
                            afterward if needed for security, backups, or legal
                            obligations. You may request deletion of your
                            account and associated data.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-neutral-950">Contact</h3>
                        <p class="mt-1">
                            Questions about these Terms or this Privacy Policy
                            can be sent to the email address associated with
                            your StackLab workspace.
                        </p>
                    </div>
                </template>
            </div>

            <DialogFooter
                class="border-t border-neutral-200 px-6 py-4 sm:justify-end"
            >
                <Button
                    type="button"
                    class="h-10 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
                    @click="accept"
                >
                    I agree
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
