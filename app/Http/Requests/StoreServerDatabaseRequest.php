<?php

namespace App\Http\Requests;

use App\Models\Server;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServerDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $server = $this->route('server');

        return $server instanceof Server
            && $server->user_id === $this->user()?->id
            && $server->isConnected()
            && $server->hasMysql();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $server = $this->route('server');
        $serverId = $server instanceof Server ? $server->id : 0;

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:64',
                'regex:/^[A-Za-z][A-Za-z0-9_]*$/',
                Rule::notIn(['mysql', 'sys', 'information_schema', 'performance_schema', 'root', 'mariadb']),
                Rule::unique('server_databases', 'name')->where('server_id', $serverId),
            ],
        ];
    }
}
