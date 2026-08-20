<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RunSiteCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Site $site */
        $site = $this->route('site');

        return Gate::allows('update', $site);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('command')) {
            return;
        }

        $this->merge([
            'command' => trim((string) $this->input('command')),
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'command' => ['required', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (str_contains($this->string('command')->toString(), "\0")) {
                    $validator->errors()->add('command', 'The command must be valid UTF-8 text.');
                }
            },
        ];
    }

    public function command(): string
    {
        return $this->string('command')->toString();
    }
}
