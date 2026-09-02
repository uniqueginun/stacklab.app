<?php

namespace App\Http\Requests;

use App\Models\QueueWorker;
use App\Models\Site;
use App\Support\QueueWorkers\QueueWorkerSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreQueueWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site instanceof Site && Gate::allows('update', $site);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('queue')) {
            $queue = collect(explode(',', (string) $this->input('queue')))
                ->map(fn (mixed $name): string => trim((string) $name))
                ->filter(fn (string $name): bool => $name !== '')
                ->implode(',');

            $this->merge(['queue' => $queue]);
        }

        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $site = $this->route('site');
        $siteId = $site instanceof Site ? $site->id : 0;
        $phpVersions = $site instanceof Site ? QueueWorkerSettings::phpVersionsFor($site) : [];

        return [
            'name' => [
                'required',
                'string',
                'max:63',
                'regex:'.QueueWorkerSettings::NamePattern,
                Rule::unique((new QueueWorker)->getTable(), 'name')->where('site_id', $siteId),
            ],
            'connection' => [
                'required',
                'string',
                'max:63',
                'regex:'.QueueWorkerSettings::ConnectionPattern,
            ],
            'queue' => ['required', 'string', 'max:255'],
            'php_version' => ['required', 'string', Rule::in($phpVersions)],
            'processes' => [
                'required',
                'integer',
                'min:'.QueueWorkerSettings::MinProcesses,
                'max:'.QueueWorkerSettings::MaxProcesses,
            ],
            'sleep' => [
                'sometimes',
                'integer',
                'min:'.QueueWorkerSettings::MinSleep,
                'max:'.QueueWorkerSettings::MaxSleep,
            ],
            'timeout' => [
                'sometimes',
                'integer',
                'min:'.QueueWorkerSettings::MinTimeout,
                'max:'.QueueWorkerSettings::MaxTimeout,
            ],
            'tries' => [
                'sometimes',
                'integer',
                'min:'.QueueWorkerSettings::MinTries,
                'max:'.QueueWorkerSettings::MaxTries,
            ],
            'backoff' => [
                'sometimes',
                'integer',
                'min:'.QueueWorkerSettings::MinBackoff,
                'max:'.QueueWorkerSettings::MaxBackoff,
            ],
            'max_jobs' => [
                'sometimes',
                'integer',
                'min:'.QueueWorkerSettings::MinMaxJobs,
                'max:'.QueueWorkerSettings::MaxMaxJobs,
            ],
            'max_time' => [
                'sometimes',
                'integer',
                'min:'.QueueWorkerSettings::MinMaxTime,
                'max:'.QueueWorkerSettings::MaxMaxTime,
            ],
            'stopwaitsecs' => [
                'sometimes',
                'integer',
                'min:'.QueueWorkerSettings::MinStopWait,
                'max:'.QueueWorkerSettings::MaxStopWait,
            ],
            'restart_on_deploy' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $queue = $this->string('queue')->toString();

                foreach (array_filter(array_map(trim(...), explode(',', $queue))) as $name) {
                    if (preg_match(QueueWorkerSettings::QueueNamePattern, $name) !== 1) {
                        $validator->errors()->add('queue', 'Queue names may only contain letters, numbers, dots, dashes, and underscores.');

                        break;
                    }
                }

                $timeout = $this->integer('timeout', QueueWorkerSettings::defaults()['timeout']);
                $stopWait = $this->integer('stopwaitsecs', QueueWorkerSettings::defaults()['stopwaitsecs']);

                if ($stopWait < $timeout) {
                    $validator->errors()->add(
                        'stopwaitsecs',
                        'Supervisor stop wait must be greater than or equal to the worker timeout.',
                    );
                }
            },
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     connection: string,
     *     queue: string,
     *     php_version: string,
     *     processes: int,
     *     sleep: int,
     *     timeout: int,
     *     tries: int,
     *     backoff: int,
     *     max_jobs: int,
     *     max_time: int,
     *     stopwaitsecs: int,
     *     restart_on_deploy: bool
     * }
     */
    public function workerAttributes(): array
    {
        $defaults = QueueWorkerSettings::defaults();
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'connection' => $validated['connection'],
            'queue' => $validated['queue'],
            'php_version' => $validated['php_version'],
            'processes' => (int) $validated['processes'],
            'sleep' => (int) ($validated['sleep'] ?? $defaults['sleep']),
            'timeout' => (int) ($validated['timeout'] ?? $defaults['timeout']),
            'tries' => (int) ($validated['tries'] ?? $defaults['tries']),
            'backoff' => (int) ($validated['backoff'] ?? $defaults['backoff']),
            'max_jobs' => (int) ($validated['max_jobs'] ?? $defaults['max_jobs']),
            'max_time' => (int) ($validated['max_time'] ?? $defaults['max_time']),
            'stopwaitsecs' => (int) ($validated['stopwaitsecs'] ?? $defaults['stopwaitsecs']),
            'restart_on_deploy' => array_key_exists('restart_on_deploy', $validated)
                ? (bool) $validated['restart_on_deploy']
                : $defaults['restart_on_deploy'],
        ];
    }
}
