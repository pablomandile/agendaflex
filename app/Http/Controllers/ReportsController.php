<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): Response
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $tz = app(CurrentCompany::class)->get()->timezone;

        // Default: mes en curso
        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'], $tz)->startOfDay()
            : CarbonImmutable::now($tz)->startOfMonth();

        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'], $tz)->endOfDay()
            : CarbonImmutable::now($tz)->endOfMonth();

        return Inertia::render('reports/Index', [
            'report' => $reports->build($from->utc(), $to->utc()),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'currency' => app(CurrentCompany::class)->get()->currency,
        ]);
    }
}
