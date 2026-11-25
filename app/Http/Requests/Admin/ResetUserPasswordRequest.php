<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Admin setting a new password for an account. Access is checked by the role:admin,rh route middleware. */
class ResetUserPasswordRequest extends FormRequest
{
    /** RH resets plain employees' passwords only (an Admin, anyone's). */
    public function authorize(): bool
    {
        return $this->user()->canManageAccount($this->route('user'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
