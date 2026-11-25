<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin creating an employee account. Access is checked by the role:admin,rh route middleware. */
class StoreUserRequest extends FormRequest
{
    use ValidatesEmployeeRecord;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            // RH creates plain employees; only an Admin gives the Admin, RH or Paie role.
            'role' => ['required', Rule::enum(Role::class)->only($this->user()->assignableRoles())],
            ...$this->employeeRecordRules(),
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
}
