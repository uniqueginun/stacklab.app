<script setup lang="ts">
import { Form, useForm, usePoll } from '@inertiajs/vue3';
import { Check, Lock, Shield, X } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import { destroy as destroyCertificate } from '@/actions/App/Http/Controllers/SiteCertificateController';
import { store as storeCsr } from '@/actions/App/Http/Controllers/SiteCsrController';
import { store as storeExisting } from '@/actions/App/Http/Controllers/SiteExistingCertificateController';
import { store as storeLetsEncrypt } from '@/actions/App/Http/Controllers/SiteLetsEncryptController';
import { store as storeSigned } from '@/actions/App/Http/Controllers/SiteSignedCertificateController';
import InputError from '@/components/InputError.vue';
import ToggleSwitch from '@/components/stacklab/ToggleSwitch.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type {
    ServerOperation,
    SiteCertificate,
    SiteShow,
} from '@/types';

type SslPanel = 'choose' | 'letsencrypt' | 'existing' | 'csr';

const props = defineProps<{
    site: SiteShow;
    certificate: SiteCertificate | null;
    operation: ServerOperation | null;
}>();

const panel = ref<SslPanel>('choose');
const confirmDelete = ref(false);
const copied = ref(false);
const logEl = ref<HTMLPreElement | null>(null);

const letsEncryptForm = useForm({
    include_www: false,
});

const existingForm = useForm({
    certificate: '',
    private_key: '',
    chain: '',
});

const csrForm = useForm({
    country: '',
    state: '',
    locality: '',
    organization: '',
    organizational_unit: '',
    email: '',
});

const signedForm = useForm({
    certificate: '',
    chain: '',
});

const isWorking = computed(
    () =>
        props.certificate?.status === 'pending' ||
        props.operation?.status === 'pending' ||
        props.operation?.status === 'running',
);

const canStart = computed(
    () =>
        props.site.can_manage_ssl &&
        !isWorking.value &&
        props.certificate?.status !== 'active' &&
        props.certificate?.status !== 'awaiting_certificate',
);

const liveLog = computed(() => {
    if (!props.operation) {
        return '';
    }

    return props.operation.steps
        .filter((step) => step.status !== 'pending')
        .map((step) => {
            const body = step.output?.trim() ?? '';

            return body === ''
                ? `==> ${step.name}`
                : `==> ${step.name}\n${body}`;
        })
        .join('\n\n');
});

const runningStep = computed(
    () =>
        props.operation?.steps.find((step) => step.status === 'running') ??
        null,
);

const { start, stop } = usePoll(
    1000,
    {
        only: ['site', 'operation', 'certificate'],
    },
    {
        autoStart: false,
    },
);

watch(
    isWorking,
    (active) => {
        if (active) {
            start();

            return;
        }

        stop();
    },
    { immediate: true },
);

watch(liveLog, async () => {
    await nextTick();

    if (logEl.value) {
        logEl.value.scrollTop = logEl.value.scrollHeight;
    }
});

watch(
    () => props.certificate?.status,
    () => {
        if (
            props.certificate?.status === 'awaiting_certificate' ||
            props.certificate?.status === 'active'
        ) {
            panel.value = 'choose';
        }
    },
);

const obtainLetsEncrypt = () => {
    letsEncryptForm.submit(storeLetsEncrypt(props.site.uuid), {
        preserveScroll: true,
    });
};

const installExisting = () => {
    existingForm.submit(storeExisting(props.site.uuid), {
        preserveScroll: true,
    });
};

const generateCsr = () => {
    csrForm.submit(storeCsr(props.site.uuid), {
        preserveScroll: true,
    });
};

const installSigned = () => {
    if (!props.certificate) {
        return;
    }

    signedForm.submit(
        storeSigned({
            site: props.site.uuid,
            certificate: props.certificate.uuid,
        }),
        {
            preserveScroll: true,
        },
    );
};

const copyCsr = async () => {
    if (!props.certificate?.csr) {
        return;
    }

    await navigator.clipboard.writeText(props.certificate.csr);
    copied.value = true;
    window.setTimeout(() => {
        copied.value = false;
    }, 2000);
};

const formatTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};

const letsEncryptErrors = computed(
    () => letsEncryptForm.errors as Record<string, string | undefined>,
);
const existingErrors = computed(
    () => existingForm.errors as Record<string, string | undefined>,
);
const csrErrors = computed(
    () => csrForm.errors as Record<string, string | undefined>,
);
const signedErrors = computed(
    () => signedForm.errors as Record<string, string | undefined>,
);
</script>

<template>
    <div class="space-y-4">
        <section
            class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white"
        >
            <div class="border-b border-neutral-100 px-6 py-5">
                <h2 class="font-semibold">SSL</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    Activate HTTPS with a free Let's Encrypt certificate, paste
                    a certificate you bought (Namecheap and similar), or
                    generate a CSR on this server. HTTP is redirected to HTTPS
                    once a certificate is active.
                </p>
            </div>

            <div
                v-if="!site.can_manage_ssl"
                class="px-6 py-16 text-center text-sm text-neutral-500"
            >
                Deploy the site first so Nginx is serving this domain on port
                80. Point DNS at
                <span class="font-medium text-neutral-700">{{
                    site.server.host
                }}</span>
                before requesting a certificate.
            </div>

            <div v-else-if="certificate?.status === 'active'" class="px-6 py-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="font-medium">{{ certificate.type_label }}</p>
                        <p class="mt-1 text-sm text-neutral-500">
                            {{ certificate.domains.join(', ') }}
                        </p>
                        <p class="mt-1 text-sm text-neutral-500">
                            Expires {{ formatTime(certificate.expires_at) }}
                        </p>
                        <p
                            v-if="certificate.failure_message"
                            class="mt-2 text-sm text-red-600"
                        >
                            {{ certificate.failure_message }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="isWorking"
                        class="h-10 rounded-lg border-neutral-200 bg-white shadow-none"
                        @click="confirmDelete = true"
                    >
                        Delete certificate
                    </Button>
                </div>
                <p class="mt-4 text-sm text-neutral-500">
                    Visitors on port 80 are redirected to HTTPS. Let's Encrypt
                    certificates renew automatically on the server.
                </p>
            </div>

            <div
                v-else-if="certificate?.status === 'awaiting_certificate'"
                class="grid gap-4 px-6 py-6"
            >
                <p
                    v-if="certificate.failure_message"
                    class="text-sm text-red-600"
                >
                    {{ certificate.failure_message }} Paste the issued
                    certificate and CA bundle again to retry. Do not discard the
                    CSR unless you will reissue at the CA.
                </p>
                <p v-else class="text-sm text-neutral-500">
                    Submit this CSR to your certificate authority (for example
                    Namecheap), then paste the issued certificate and CA bundle
                    below. The private key stays on the server.
                </p>
                <div class="grid gap-2">
                    <Label class="text-sm font-normal text-neutral-500"
                        >Certificate signing request</Label
                    >
                    <textarea
                        readonly
                        :value="certificate.csr ?? ''"
                        class="min-h-40 w-full rounded-xl border border-neutral-200 bg-neutral-50 p-4 font-mono text-xs leading-5 text-neutral-800"
                    />
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-9 rounded-lg border-neutral-200 bg-white shadow-none"
                            @click="copyCsr"
                        >
                            {{ copied ? 'Copied' : 'Copy CSR' }}
                        </Button>
                    </div>
                </div>
                <form class="grid gap-4" @submit.prevent="installSigned">
                    <div class="grid gap-2">
                        <Label
                            for="signed-certificate"
                            class="text-sm font-normal text-neutral-500"
                            >Certificate</Label
                        >
                        <textarea
                            id="signed-certificate"
                            v-model="signedForm.certificate"
                            class="min-h-36 w-full rounded-xl border border-neutral-200 bg-white p-4 font-mono text-xs leading-5 outline-none focus:border-neutral-400"
                            :disabled="signedForm.processing || isWorking"
                        />
                        <InputError :message="signedErrors.certificate" />
                    </div>
                    <div class="grid gap-2">
                        <Label
                            for="signed-chain"
                            class="text-sm font-normal text-neutral-500"
                            >CA bundle / chain (optional)</Label
                        >
                        <textarea
                            id="signed-chain"
                            v-model="signedForm.chain"
                            class="min-h-28 w-full rounded-xl border border-neutral-200 bg-white p-4 font-mono text-xs leading-5 outline-none focus:border-neutral-400"
                            :disabled="signedForm.processing || isWorking"
                        />
                        <InputError :message="signedErrors.chain" />
                    </div>
                    <InputError :message="signedErrors.site" />
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            class="h-10 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
                            :disabled="signedForm.processing || isWorking"
                        >
                            <Spinner
                                v-if="signedForm.processing"
                                class="size-4"
                            />
                            Install certificate
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 rounded-lg border-neutral-200 bg-white shadow-none"
                            :disabled="isWorking"
                            @click="confirmDelete = true"
                        >
                            Discard CSR
                        </Button>
                    </div>
                </form>
            </div>

            <div v-else-if="isWorking" class="px-6 py-6 text-sm text-neutral-500">
                Activating HTTPS for {{ site.domain }}. The domain must already
                resolve to this server and port 80 must be reachable.
            </div>

            <div v-else-if="canStart" class="px-6 py-6">
                <p
                    v-if="certificate?.status === 'failed'"
                    class="mb-4 text-sm text-red-600"
                >
                    {{
                        certificate.failure_message ||
                        'The previous SSL operation failed. Try again.'
                    }}
                </p>

                <div
                    v-if="panel === 'choose'"
                    class="grid gap-3 sm:grid-cols-3"
                >
                    <button
                        type="button"
                        class="rounded-2xl border border-neutral-200 p-4 text-left hover:border-neutral-300"
                        @click="panel = 'letsencrypt'"
                    >
                        <Lock class="size-4 text-brand" />
                        <p class="mt-3 font-medium">Let's Encrypt</p>
                        <p class="mt-1 text-sm text-neutral-500">
                            Free certificate with automatic renewal. DNS must
                            point at this server.
                        </p>
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl border border-neutral-200 p-4 text-left hover:border-neutral-300"
                        @click="panel = 'existing'"
                    >
                        <Shield class="size-4 text-brand" />
                        <p class="mt-3 font-medium">Existing certificate</p>
                        <p class="mt-1 text-sm text-neutral-500">
                            Paste a certificate, private key, and CA bundle from
                            Namecheap or another CA.
                        </p>
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl border border-neutral-200 p-4 text-left hover:border-neutral-300"
                        @click="panel = 'csr'"
                    >
                        <Shield class="size-4 text-neutral-500" />
                        <p class="mt-3 font-medium">Create CSR</p>
                        <p class="mt-1 text-sm text-neutral-500">
                            Generate a signing request on the server, then
                            install the issued certificate.
                        </p>
                    </button>
                </div>

                <form
                    v-else-if="panel === 'letsencrypt'"
                    class="grid gap-4"
                    @submit.prevent="obtainLetsEncrypt"
                >
                    <p class="text-sm text-neutral-500">
                        Let's Encrypt will verify
                        <span class="font-medium text-neutral-700">{{
                            site.domain
                        }}</span>
                        over HTTP on port 80.
                    </p>
                    <label
                        v-if="site.can_include_www"
                        class="flex items-center justify-between gap-3 rounded-xl border border-neutral-200 px-4 py-3"
                    >
                        <span class="text-sm"
                            >Also cover www.{{ site.domain }}</span
                        >
                        <ToggleSwitch
                            :model-value="letsEncryptForm.include_www"
                            :disabled="letsEncryptForm.processing"
                            @update:model-value="
                                letsEncryptForm.include_www = $event
                            "
                        />
                    </label>
                    <InputError :message="letsEncryptErrors.site" />
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            class="h-10 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
                            :disabled="letsEncryptForm.processing"
                        >
                            <Spinner
                                v-if="letsEncryptForm.processing"
                                class="size-4"
                            />
                            Obtain certificate
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 rounded-lg border-neutral-200 bg-white shadow-none"
                            @click="panel = 'choose'"
                        >
                            Back
                        </Button>
                    </div>
                </form>

                <form
                    v-else-if="panel === 'existing'"
                    class="grid gap-4"
                    @submit.prevent="installExisting"
                >
                    <p class="text-sm text-neutral-500">
                        Buy or download the certificate from your CA, then paste
                        the PEM files here. Custom certificates are not renewed
                        automatically.
                    </p>
                    <div class="grid gap-2">
                        <Label
                            for="existing-certificate"
                            class="text-sm font-normal text-neutral-500"
                            >Certificate</Label
                        >
                        <textarea
                            id="existing-certificate"
                            v-model="existingForm.certificate"
                            class="min-h-36 w-full rounded-xl border border-neutral-200 p-4 font-mono text-xs leading-5 outline-none focus:border-neutral-400"
                            :disabled="existingForm.processing"
                        />
                        <InputError :message="existingErrors.certificate" />
                    </div>
                    <div class="grid gap-2">
                        <Label
                            for="existing-key"
                            class="text-sm font-normal text-neutral-500"
                            >Private key</Label
                        >
                        <textarea
                            id="existing-key"
                            v-model="existingForm.private_key"
                            class="min-h-28 w-full rounded-xl border border-neutral-200 p-4 font-mono text-xs leading-5 outline-none focus:border-neutral-400"
                            :disabled="existingForm.processing"
                        />
                        <InputError :message="existingErrors.private_key" />
                    </div>
                    <div class="grid gap-2">
                        <Label
                            for="existing-chain"
                            class="text-sm font-normal text-neutral-500"
                            >CA bundle / chain (optional)</Label
                        >
                        <textarea
                            id="existing-chain"
                            v-model="existingForm.chain"
                            class="min-h-28 w-full rounded-xl border border-neutral-200 p-4 font-mono text-xs leading-5 outline-none focus:border-neutral-400"
                            :disabled="existingForm.processing"
                        />
                        <InputError :message="existingErrors.chain" />
                    </div>
                    <InputError :message="existingErrors.site" />
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            class="h-10 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
                            :disabled="existingForm.processing"
                        >
                            <Spinner
                                v-if="existingForm.processing"
                                class="size-4"
                            />
                            Install certificate
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 rounded-lg border-neutral-200 bg-white shadow-none"
                            @click="panel = 'choose'"
                        >
                            Back
                        </Button>
                    </div>
                </form>

                <form
                    v-else
                    class="grid gap-4"
                    @submit.prevent="generateCsr"
                >
                    <p class="text-sm text-neutral-500">
                        We'll generate the private key on the server. Copy the
                        CSR into Namecheap (or another CA), then come back to
                        install the signed certificate.
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label
                                for="csr-country"
                                class="text-sm font-normal text-neutral-500"
                                >Country (2 letters)</Label
                            >
                            <Input
                                id="csr-country"
                                v-model="csrForm.country"
                                maxlength="2"
                                :disabled="csrForm.processing"
                            />
                            <InputError :message="csrErrors.country" />
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="csr-state"
                                class="text-sm font-normal text-neutral-500"
                                >State / region</Label
                            >
                            <Input
                                id="csr-state"
                                v-model="csrForm.state"
                                :disabled="csrForm.processing"
                            />
                            <InputError :message="csrErrors.state" />
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="csr-locality"
                                class="text-sm font-normal text-neutral-500"
                                >City</Label
                            >
                            <Input
                                id="csr-locality"
                                v-model="csrForm.locality"
                                :disabled="csrForm.processing"
                            />
                            <InputError :message="csrErrors.locality" />
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="csr-organization"
                                class="text-sm font-normal text-neutral-500"
                                >Organization</Label
                            >
                            <Input
                                id="csr-organization"
                                v-model="csrForm.organization"
                                :disabled="csrForm.processing"
                            />
                            <InputError :message="csrErrors.organization" />
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="csr-ou"
                                class="text-sm font-normal text-neutral-500"
                                >Organizational unit (optional)</Label
                            >
                            <Input
                                id="csr-ou"
                                v-model="csrForm.organizational_unit"
                                :disabled="csrForm.processing"
                            />
                            <InputError
                                :message="csrErrors.organizational_unit"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="csr-email"
                                class="text-sm font-normal text-neutral-500"
                                >Email (optional)</Label
                            >
                            <Input
                                id="csr-email"
                                v-model="csrForm.email"
                                type="email"
                                :disabled="csrForm.processing"
                            />
                            <InputError :message="csrErrors.email" />
                        </div>
                    </div>
                    <InputError :message="csrErrors.site" />
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            class="h-10 rounded-lg bg-brand px-4 text-white hover:bg-brand/90"
                            :disabled="csrForm.processing"
                        >
                            <Spinner v-if="csrForm.processing" class="size-4" />
                            Generate CSR
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 rounded-lg border-neutral-200 bg-white shadow-none"
                            @click="panel = 'choose'"
                        >
                            Back
                        </Button>
                    </div>
                </form>
            </div>
        </section>

        <section
            v-if="operation"
            class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white"
        >
            <div class="border-b border-neutral-100 px-6 py-5">
                <h2 class="font-semibold">
                    {{
                        operation.status === 'failed'
                            ? 'SSL operation failed'
                            : operation.status === 'succeeded'
                              ? 'SSL operation finished'
                              : 'Activating HTTPS'
                    }}
                </h2>
                <p
                    v-if="operation.failure_message"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ operation.failure_message }}
                </p>
                <p
                    v-else-if="runningStep"
                    class="mt-1 text-sm text-neutral-500"
                >
                    {{ runningStep.name }}
                </p>
            </div>

            <ol class="grid gap-2 px-6 py-4 sm:grid-cols-2">
                <li
                    v-for="step in operation.steps"
                    :key="step.id"
                    class="flex items-center gap-2 text-sm"
                >
                    <span
                        class="flex size-5 items-center justify-center rounded-full"
                        :class="{
                            'bg-neutral-950 text-white':
                                step.status === 'succeeded',
                            'border-2 border-brand': step.status === 'running',
                            'border border-neutral-300':
                                step.status === 'pending',
                            'bg-red-600 text-white': step.status === 'failed',
                        }"
                    >
                        <Check
                            v-if="step.status === 'succeeded'"
                            class="size-3"
                            stroke-width="3"
                        />
                        <X
                            v-else-if="step.status === 'failed'"
                            class="size-3"
                            stroke-width="3"
                        />
                        <span
                            v-else
                            class="size-1.5 rounded-full"
                            :class="
                                step.status === 'running'
                                    ? 'bg-brand'
                                    : 'bg-neutral-300'
                            "
                        />
                    </span>
                    <span
                        :class="
                            step.status === 'pending'
                                ? 'text-neutral-400'
                                : 'text-neutral-900'
                        "
                    >
                        {{ step.name }}
                    </span>
                </li>
            </ol>

            <div class="px-6 pb-6">
                <p
                    class="mb-2 text-xs font-medium tracking-wide text-neutral-400 uppercase"
                >
                    Operation log
                </p>
                <pre
                    ref="logEl"
                    class="max-h-[36rem] min-h-48 overflow-auto rounded-xl border border-neutral-200 bg-neutral-50 p-4 font-mono text-xs leading-6 break-words whitespace-pre-wrap text-neutral-800"
                    >{{
                        liveLog ||
                        (isWorking
                            ? 'Waiting for remote output…'
                            : 'No output recorded for this operation.')
                    }}</pre>
            </div>
        </section>

        <Dialog
            :open="confirmDelete"
            @update:open="(open) => !open && (confirmDelete = false)"
        >
            <DialogContent v-if="certificate">
                <Form
                    v-bind="
                        destroyCertificate.form({
                            site: site.uuid,
                            certificate: certificate.uuid,
                        })
                    "
                    class="space-y-6"
                    v-slot="{ processing, errors }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Remove this certificate?</DialogTitle>
                        <DialogDescription>
                            {{ site.domain }} will be served over HTTP until
                            you install another certificate.
                        </DialogDescription>
                    </DialogHeader>
                    <InputError :message="errors.certificate" />
                    <InputError :message="errors.site" />
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary" type="button"
                                >Cancel</Button
                            >
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                        >
                            Delete certificate
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
