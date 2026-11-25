<?php

namespace App\Http\Controllers;

use App\Services\PayrollReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Monthly leave summary for payroll. Every route is behind the role:admin,rh,paie middleware. */
class PayrollController extends Controller
{
    public function __construct(private readonly PayrollReport $report) {}

    public function index(Request $request): Response
    {
        $month = $this->month($request);

        return Inertia::render('Admin/Payroll', [
            'month' => $month->format('Y-m'),
            'rows' => $this->report->rows($month),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $month = $this->month($request);

        return response()
            ->download($this->report->toXlsx($month), PayrollReport::fileName($month))
            ->deleteFileAfterSend();
    }

    /** The requested month (YYYY-MM), by default the previous one: payroll is done once a month is over. */
    private function month(Request $request): Carbon
    {
        $month = (string) $request->query('month');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay()
            : now()->subMonthNoOverflow()->startOfMonth();
    }
}
