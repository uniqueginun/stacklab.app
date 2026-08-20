<?php

namespace App\Models;

use Database\Factories\GitHubConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $github_id
 * @property string $username
 * @property string $token
 */
#[Fillable(['user_id', 'github_id', 'username', 'token'])]
#[Hidden(['token'])]
class GitHubConnection extends Model
{
    /** @use HasFactory<GitHubConnectionFactory> */
    use HasFactory;

    protected $table = 'github_connections';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
        ];
    }
}
