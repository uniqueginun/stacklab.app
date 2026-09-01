<?php

namespace App\Operations\Aftermath;

use App\Enums\SiteCertificateStatus;
use App\Models\Operation;
use App\Models\OperationStep;
use App\Models\Server;
use App\Models\SiteCertificate;
use App\Operations\Aftermath\Contracts\HandlesFailedOperation;
use App\Operations\Aftermath\Contracts\StepAftermath;
use App\Support\StepExecutionResult;
use Illuminate\Support\Carbon;

final class FinalizeSslAftermath implements HandlesFailedOperation, StepAftermath
{
    public static function key(): string
    {
        return 'finalize_ssl';
    }

    public function handle(
        Server $server,
        Operation $operation,
        OperationStep $step,
        StepExecutionResult $result,
    ): void {
        if (! $result->success) {
            return;
        }

        $certificate = $this->certificate($operation);

        if ($certificate === null) {
            return;
        }

        if ($step->recipe === 'ssl.csr.generate@v1') {
            $csr = $this->csrFromResult($result);

            $certificate->forceFill([
                'status' => SiteCertificateStatus::AWAITING_CERTIFICATE,
                'csr' => $csr,
                'failure_message' => null,
            ])->save();

            return;
        }

        if ($step->recipe === 'ssl.deactivate@v1') {
            $certificate->delete();

            return;
        }

        $expiresAt = $this->expiresAt($result);

        $certificate->forceFill([
            'status' => SiteCertificateStatus::ACTIVE,
            'failure_message' => null,
            'activated_at' => now(),
            'expires_at' => $expiresAt,
        ])->save();

        $certificate->wipePrivateMaterials();
    }

    public function failed(Operation $operation, ?string $message): void
    {
        if ($operation->type !== 'ssl') {
            return;
        }

        $certificate = $this->certificate($operation);

        if ($certificate === null) {
            return;
        }

        $isDeactivate = $operation->steps()
            ->where('recipe', 'ssl.deactivate@v1')
            ->exists();

        if ($isDeactivate) {
            $certificate->forceFill([
                'failure_message' => $message,
            ])->save();

            return;
        }

        $isCsrInstall = $operation->steps()
            ->where('recipe', 'ssl.csr.install@v1')
            ->exists();

        if ($isCsrInstall) {
            $certificate->forceFill([
                'status' => SiteCertificateStatus::AWAITING_CERTIFICATE,
                'failure_message' => $message,
            ])->save();

            return;
        }

        $certificate->forceFill([
            'status' => SiteCertificateStatus::FAILED,
            'failure_message' => $message,
        ])->save();
    }

    private function certificate(Operation $operation): ?SiteCertificate
    {
        $certificateId = data_get($operation->plan_snapshot, 'certificate_id');

        if (! is_int($certificateId) && ! is_numeric($certificateId)) {
            return null;
        }

        return SiteCertificate::query()->find((int) $certificateId);
    }

    private function csrFromResult(StepExecutionResult $result): ?string
    {
        $encoded = $result->data['csr_b64'] ?? null;

        if (! is_string($encoded) || $encoded === '') {
            return null;
        }

        $decoded = base64_decode($encoded, true);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }

    private function expiresAt(StepExecutionResult $result): ?Carbon
    {
        $value = $result->data['expires_at'] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
