<?php

namespace App\Http\Requests;

use App\Models\Server;
use App\Support\MysqlVersions;
use App\Support\ProvisioningProfiles;
use App\Support\SupportedPlatforms;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServerProvisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $server = $this->route('server');

        return $server instanceof Server
            && $server->user_id === $this->user()?->id
            && $server->isConnected();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(ProvisioningProfiles $profiles): array
    {
        $needsPhp = $this->needsPhp($profiles);
        $needsMysql = $this->needsMysql($profiles);

        return [
            'profile' => ['required', 'string', Rule::in($profiles->keys())],
            'php_version' => [Rule::requiredIf($needsPhp), 'nullable', 'string', Rule::in($this->allowedPhpVersions())],
            'mysql_version' => [Rule::requiredIf($needsMysql), 'nullable', 'string', Rule::in(MysqlVersions::all())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'php_version' => 'PHP version',
            'mysql_version' => 'MySQL version',
        ];
    }

    private function needsPhp(ProvisioningProfiles $profiles): bool
    {
        $profile = $this->string('profile')->toString();

        if (! in_array($profile, $profiles->keys(), true)) {
            return false;
        }

        return $profiles->requiresPhp($profile);
    }

    private function needsMysql(ProvisioningProfiles $profiles): bool
    {
        $profile = $this->string('profile')->toString();

        if (! in_array($profile, $profiles->keys(), true)) {
            return false;
        }

        return $profiles->requiresMysql($profile);
    }

    /**
     * @return list<string>
     */
    private function allowedPhpVersions(): array
    {
        $server = $this->route('server');

        if (! $server instanceof Server) {
            return [];
        }

        return SupportedPlatforms::phpVersionsFor($server);
    }
}
