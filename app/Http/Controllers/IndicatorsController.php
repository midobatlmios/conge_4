<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\User;
use App\Services\HrIndicators;
use App\Services\HrIndicatorsReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * HR indicators per year and department, and the year-end report as Excel or PDF.
 * Behind the role:admin,rh,paie middleware.
 */
class IndicatorsController extends Controller
{
    public function __construct(private readonly HrIndicators $indicators) {}

    public function index(Request $request): Response
    {
        [$year, $department] = $this->filters($request);

        return Inertia::render('Admin/Indicators', [
            ...$this->indicators->compute($year, $department?->id),
            'years' => $this->years(),
            'departmentId' => $department?->id,
            'departmentOptions' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, HrIndicatorsReport $report): BinaryFileResponse
    {
        [$year, $department] = $this->filters($request);
        $data = $this->indicators->compute($year, $department?->id);

        return response()
            ->download(
                $report->toXlsx($data, $department?->name, CompanySetting::current()->name ?? config('app.name')),
                HrIndicatorsReport::fileName($year, $department?->name),
            )
            ->deleteFileAfterSend();
    }

    public function exportPdf(Request $request, HrIndicatorsReport $report): HttpResponse
    {
        [$year, $department] = $this->filters($request);

        return $report->toPdf($this->indicators->compute($year, $department?->id), $department?->name)
            ->download(HrIndicatorsReport::fileName($year, $department?->name, 'pdf'));
    }

    /** @return array{0: int, 1: ?Department}The requested year (default: this one) and department. */
    private function filters(Request $request): array
    {
        $years = $this->years();
        $year = in_array($request->integer('year'), $years, true) ? $request->integer('year') : now()->year;

        return [$year, $request->integer('department') ? Department::find($request->integer('department')) : null];
    }

    /** @return list<int> From the first hire (at most 10 years back) to this year, newest first. */
    private function years(): array
    {
        $first = User::min('hire_date');
        $from = max(now()->year - 9, $first ? (int) substr((string) $first, 0, 4) : now()->year);

        return range(now()->year, $from);
    }
}
