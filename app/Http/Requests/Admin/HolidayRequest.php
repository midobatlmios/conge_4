<?php

namespace App\Http\Requests\Admin;

use App\Models\Holiday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/** Admin adding or editing a public holiday. Access is checked by the role:admin,rh route middleware. */
class HolidayRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date_format:Y-m-d'],
            'recurring' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nom', 'recurring' => 'répétition'];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $date = Carbon::parse($this->input('date'));
                $recurring = $this->boolean('recurring');
                $current = $this->route('holiday');

                $clash = Holiday::query()
                    ->when($current, fn ($q) => $q->whereKeyNot($current->id))
                    ->get()
                    ->first(fn (Holiday $other) => $other->date->format('m-d') === $date->format('m-d')
                        && ($other->recurring || $recurring || $other->date->isSameDay($date)));

                if ($clash) {
                    $validator->errors()->add('date', "Ce jour est déjà férié : « {$clash->name} ».");
                }
            },
        ];
    }

    /**
     * Validated data, with a repeating holiday's year set to 2000: only month and
     * day matter, and 2000 is a leap year so 29 February stays valid.
     *
     * @return array{name: string, date: string, recurring: bool}
     */
    public function holidayData(): array
    {
        $recurring = $this->boolean('recurring');
        $date = Carbon::parse($this->validated('date'));

        return [
            'name' => $this->validated('name'),
            'date' => $recurring ? '2000-'.$date->format('m-d') : $date->toDateString(),
            'recurring' => $recurring,
        ];
    }
}
