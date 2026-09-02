<?php

namespace App\Http\Requests;

use App\Models\QueueWorker;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ManageQueueWorkerRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
