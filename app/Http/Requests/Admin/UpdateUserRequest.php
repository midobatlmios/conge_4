<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Admin editing an account. Access is checked by the role:admin route middleware. */
class UpdateUserRequest extends FormRequest
{
    public function target(): User
    {
        return $this->route('user');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->target()->id)],
            'role' => ['required', Rule::enum(Role::class)],
            // Optional: only changed when filled in.
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $target = $this->target();

                // Never leave the app without an administrator.
                if (! $target->isAdmin() || $this->input('role') === Role::Admin->value) {
                    return;
                }

                if ($target->is($this->user())) {
                    $validator->errors()->add('role', "Vous ne pouvez pas retirer votre propre rôle d'administrateur.");
                } elseif ($target->isLastAdmin()) {
                    $validator->errors()->add('role', 'Impossible de retirer le dernier administrateur.');
                }
            },
        ];
    }
}
