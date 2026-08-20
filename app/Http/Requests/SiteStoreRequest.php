<?php

namespace App\Http\Requests;

use App\Models\Server;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteStoreRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    public const TYPES = ['Laravel', 'PHP', 'HTML'];

    private bool $resolvedConnectedServer = false;

    private ?Server $connectedServer = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'server' => ['required', 'uuid', function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->connectedServer() === null) {
                    $fail(__('The selected server is invalid.'));
                }
            }],
            'type' => ['required', 'string', Rule::in(self::TYPES)],
            'domain' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sites', 'domain')->where(
                    fn ($query) => $query->where('server_id', $this->connectedServer()?->id ?? 0)
                ),
            ],
            'web_directory' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'web_directory' => 'web directory',
        ];
    }

    public function connectedServer(): ?Server
    {
        if ($this->resolvedConnectedServer) {
            return $this->connectedServer;
        }

        $this->resolvedConnectedServer = true;

        $uuid = $this->input('server');

        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        $server = Server::query()
            ->where('uuid', $uuid)
            ->where('user_id', $this->user()->id)
            ->first();

        if ($server === null || ! $server->isConnected() || ! $server->isProvisioned()) {
            return null;
        }

        return $this->connectedServer = $server;
    }
}
