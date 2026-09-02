<?php

namespace App\Http\Requests;

use App\Models\QueueWorker;
use App\Models\Site;
use App\Support\QueueWorkers\QueueWorkerSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateQueueWorkerRequest extends StoreQueueWorkerRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');
        $worker = $this->route('queue_worker');

        return $site instanceof Site
            && $worker instanceof QueueWorker
            && Gate::allows('update', $site);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $site = $this->route('site');
        $worker = $this->route('queue_worker');
        $siteId = $site instanceof Site ? $site->id : 0;

        $rules['name'] = [
            'required',
            'string',
            'max:63',
            'regex:'.QueueWorkerSettings::NamePattern,
            Rule::unique((new QueueWorker)->getTable(), 'name')
                ->where('site_id', $siteId)
                ->ignore($worker instanceof QueueWorker ? $worker : null),
        ];

        return $rules;
    }
}
