<?php

namespace App\Http\Requests;

use App\Models\Site;
use App\Support\SiteDeploymentOptions;
use Illuminate\Foundation\Http\FormRequest;

class StoreSiteDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('site')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->isLaravelSite()) {
            return;
        }

        $options = [];

        foreach (SiteDeploymentOptions::keys() as $key) {
            $options[$key] = $this->boolean($key);
        }

        $this->merge($options);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if (! $this->isLaravelSite()) {
            return [];
        }

        $rules = [];

        foreach (SiteDeploymentOptions::keys() as $key) {
            $rules[$key] = ['required', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array{
     *     run_composer: bool,
     *     run_npm: bool,
     *     run_migrations: bool,
     *     run_caches: bool,
     *     run_queue_restart: bool,
     *     run_hook: bool
     * }|null
     */
    public function deploymentOptions(): ?array
    {
        if (! $this->isLaravelSite()) {
            return null;
        }

        return SiteDeploymentOptions::normalize($this->validated());
    }

    private function isLaravelSite(): bool
    {
        $site = $this->route('site');

        return $site instanceof Site && $site->isLaravel();
    }
}
