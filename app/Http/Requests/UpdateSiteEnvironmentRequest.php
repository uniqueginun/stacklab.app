<?php

namespace App\Http\Requests;

use App\Actions\Sites\UpdateSiteEnvironment;
use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSiteEnvironmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Site $site */
        $site = $this->route('site');

        return Gate::allows('update', $site);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'contents' => ['present', 'string', 'max:'.UpdateSiteEnvironment::MaxBytes],
        ];
    }
}
