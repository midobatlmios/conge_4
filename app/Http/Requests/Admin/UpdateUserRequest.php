<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Admin or RH editing an account (role:admin,rh route middleware). RH only
 * edits plain employees and cannot give a role; only an Admin can.
 */
class UpdateUserRequest extends FormRequest
{
    use ValidatesEmployeeRecord;

    public function authorize(): bool
    {
        return $this->user()->canManageAccount($this->target());
    }

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
            'role' => ['required', Rule::enum(Role::class)->only($this->user()->assignableRoles())],
            // Optional: only changed when filled in.
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            ...$this->employeeRecordRules($this->target()),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...$this->employeeRecordMessages(),
            'role.enum' => 'Seul un administrateur peut attribuer ce rôle.',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->checkManagerChain($validator, $this->target()),
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
