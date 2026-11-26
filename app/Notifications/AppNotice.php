<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * An in-app notice (the bell), no e-mail: attendance corrections, payroll,
 * annual reviews, recruitment. It keeps the French sentence as a key with its
 * values, so the bell shows it in each reader's language, and a link to the
 * page to open.
 */
class AppNotice extends Notification
{
    use Queueable;

    /** @param array<string, string|int> $params */
    public function __construct(
        private readonly string $key,
        private readonly array $params = [],
        private readonly ?string $url = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            // The French text, for anything that does not translate.
            'message' => strtr($this->key, collect($this->params)->mapWithKeys(fn ($v, $k) => [":{$k}" => (string) $v])->all()),
            'key' => $this->key,
            'params' => $this->params,
            'url' => $this->url,
        ];
    }

    /** Active Admin and RH accounts, except the given ones. */
    public static function hr(User ...$except): Collection
    {
        $ids = array_map(fn (User $u) => $u->id, $except);

        return User::active()->whereIn('role', [Role::Admin, Role::Hr])->whereNotIn('id', $ids)->get();
    }
}
