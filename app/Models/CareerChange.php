<?php

namespace App\Models;

use App\Enums\CareerChangeKind;
use App\Enums\StaffCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of an employee's career: promotion, raise, transfer… It records the
 * values before and after; a field that did not change is left empty (the
 * department, which may become "none", has its own flag).
 *
 * @property int $user_id
 * @property int|null $contract_id
 * @property \Illuminate\Support\Carbon $effective_date
 * @property CareerChangeKind $kind
 * @property string|null $previous_job_title
 * @property string|null $job_title
 * @property bool $department_changed
 * @property int|null $previous_department_id
 * @property int|null $department_id
 * @property string|null $previous_department
 * @property string|null $department
 * @property StaffCategory|null $previous_category
 * @property StaffCategory|null $category
 * @property string|null $previous_gross_salary
 * @property string|null $gross_salary
 * @property string|null $notes
 */
class CareerChange extends Model
{
    protected $fillable = [
        'user_id', 'contract_id', 'effective_date', 'kind',
        'previous_job_title', 'job_title',
        'department_changed', 'previous_department_id', 'department_id', 'previous_department', 'department',
        'previous_category', 'category',
        'previous_gross_salary', 'gross_salary',
        'notes', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'effective_date' => 'date:Y-m-d',
        'kind' => CareerChangeKind::class,
        'department_changed' => 'boolean',
        'previous_category' => StaffCategory::class,
        'category' => StaffCategory::class,
        'previous_gross_salary' => 'encrypted',
        'gross_salary' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(EmploymentContract::class, 'contract_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function changesSalary(): bool
    {
        return $this->gross_salary !== null;
    }

    /**
     * The change as the pages show it (HR, Paie and the employee only: it holds salaries).
     *
     * @return array<string, mixed>
     */
    public function present(): array
    {
        $money = fn (?string $value) => $value === null ? null : (float) $value;

        return [
            'id' => $this->id,
            'effective_date' => $this->effective_date->toDateString(),
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'job_title' => $this->job_title === null ? null : ['from' => $this->previous_job_title, 'to' => $this->job_title],
            'department' => $this->department_changed ? ['from' => $this->previous_department, 'to' => $this->department] : null,
            'category' => $this->category === null ? null : ['from' => $this->previous_category?->label(), 'to' => $this->category->label()],
            'gross_salary' => $this->gross_salary === null ? null : ['from' => $money($this->previous_gross_salary), 'to' => $money($this->gross_salary)],
            'notes' => $this->notes,
            'author' => $this->relationLoaded('author') ? $this->author?->name : null,
        ];
    }
}
