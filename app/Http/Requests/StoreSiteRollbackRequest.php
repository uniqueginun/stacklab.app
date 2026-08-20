<?php

namespace App\Http\Requests;

use App\Models\Release;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;

class StoreSiteRollbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');
        $release = $this->route('release');

        return $site instanceof Site
            && $release instanceof Release
            && ($this->user()?->can('update', $site) ?? false)
            && ($this->user()?->can('rollback', $release) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
