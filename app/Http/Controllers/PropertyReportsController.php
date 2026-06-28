<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exports\GenericReportExport;
use App\Services\ErrorDispatcher;
use App\Models\Property;
use App\Models\Unit;
use App\Models\LeaseAgreement;
use App\Models\Client;
use App\Models\Deposit;
use App\Models\DepositAllocation;
use App\Models\TenantExit;
use App\Models\TenantExitDepositRefund;
use Maatwebsite\Excel\Facades\Excel;

class PropertyReportsController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // 1. PORTFOLIO OVERVIEW
    // ──────────────────────────────────────────────────────────────────────────

    public function portfolio(): View
    {
        $zones = DB::table('sys_set_zones')->orderBy('zone_name')->get(['id', 'zone_name']);
        return view('reports.property.portfolio-overview', compact('zones'));
    }

    public function getPortfolio(Request $request): JsonResponse
    {
        try {
            $records = Property::query()
                ->withCount([
                    'units as total_units',
                    'units as occupied_units' => fn($q) => $q->where('occupancy_status_id', '!=', 31),
                    'units as vacant_units'   => fn($q) => $q->where('occupancy_status_id', 31),
                ])
                ->withSum('units as total_rent', 'rent_amount')
                ->when($request->filled('zone_id'), fn($q) => $q->where('zone_id', $request->zone_id))
                ->orderBy('property_name')
                ->get();

            $data = $records->map(function ($r, $i) {
                return [
                    'no'            => $i + 1,
                    'reference'     => $r->reference_number ?? '-',
                    'property'      => $r->property_name,
                    'total_units'   => $r->total_units,
                    'occupied'      => $r->occupied_units,
                    'vacant'        => $r->vacant_units,
                    'occupancy_pct' => $r->total_units > 0
                        ? number_format(($r->occupied_units / $r->total_units) * 100, 1) . '%'
                        : '0.0%',
                    'total_rent'    => number_format($r->total_rent ?? 0, 2),
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getPortfolio: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportPortfolio(Request $request)
    {
        try {
            $records = Property::query()
                ->withCount([
                    'units as total_units',
                    'units as occupied_units' => fn($q) => $q->where('occupancy_status_id', '!=', 31),
                    'units as vacant_units'   => fn($q) => $q->where('occupancy_status_id', 31),
                ])
                ->withSum('units as total_rent', 'rent_amount')
                ->when($request->filled('zone_id'), fn($q) => $q->where('zone_id', $request->zone_id))
                ->orderBy('property_name')
                ->get();

            $rows = $records->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->reference_number ?? '-',
                    $r->property_name,
                    $r->total_units,
                    $r->occupied_units,
                    $r->vacant_units,
                    $r->total_units > 0
                        ? number_format(($r->occupied_units / $r->total_units) * 100, 1) . '%'
                        : '0.0%',
                    number_format($r->total_rent ?? 0, 2),
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Property Portfolio Overview',
                    headings:  ['#', 'Ref', 'Property', 'Total Units', 'Occupied', 'Vacant', 'Occupancy %', 'Total Rent Value (KES)'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 14, 'C' => 32, 'D' => 12, 'E' => 10, 'F' => 10, 'G' => 12, 'H' => 22],
                ),
                'portfolio-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportPortfolio: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. OCCUPANCY REPORT
    // ──────────────────────────────────────────────────────────────────────────

    public function occupancy(): View
    {
        $properties = Property::orderBy('property_name')->get(['id', 'property_name']);
        $zones      = DB::table('sys_set_zones')->orderBy('zone_name')->get(['id', 'zone_name']);
        return view('reports.property.occupancy', compact('properties', 'zones'));
    }

    public function getOccupancy(Request $request): JsonResponse
    {
        try {
            $query = Unit::query()
                ->join('sys_pm_properties as p', 'p.id', '=', 'sys_pm_units.property_id')
                ->leftJoin('sys_pm_lease_agreements as la', function ($j) {
                    $j->on('la.unit_id', '=', 'sys_pm_units.id')
                      ->where('la.current_status_id', 1)
                      ->whereRaw('la.date_start <= NOW()')
                      ->whereRaw('la.date_end >= NOW()');
                })
                ->leftJoin('sys_pm_clients as tc', 'tc.id', '=', 'la.tenant_id')
                ->leftJoin('sys_pm_unit_types as ut', 'ut.id', '=', 'sys_pm_units.unit_type_id')
                ->select([
                    'p.property_name',
                    'sys_pm_units.unit_code',
                    'sys_pm_units.unit_name',
                    'ut.type_name as unit_type',
                    'sys_pm_units.rent_amount',
                    'tc.names as tenant_name',
                    'tc.reference_number as tenant_ref',
                    'la.ref_number as lease_ref',
                    'la.date_end as lease_end',
                    'sys_pm_units.occupancy_status_id',
                ])
                ->where('p.active', 1)
                ->when($request->filled('property_id'), fn($q) => $q->where('sys_pm_units.property_id', $request->property_id))
                ->when($request->filled('zone_id'), fn($q) => $q->where('sys_pm_units.zone_id', $request->zone_id))
                ->orderBy('p.property_name')
                ->orderBy('sys_pm_units.unit_code');

            $records = $query->get();

            $data = $records->map(function ($r, $i) {
                return [
                    'no'          => $i + 1,
                    'property'    => $r->property_name,
                    'unit_code'   => $r->unit_code,
                    'unit_name'   => $r->unit_name,
                    'unit_type'   => $r->unit_type ?? '-',
                    'rent'        => number_format($r->rent_amount ?? 0, 2),
                    'tenant_name' => $r->tenant_name ?? '-',
                    'tenant_ref'  => $r->tenant_ref ?? '-',
                    'lease_ref'   => $r->lease_ref ?? '-',
                    'lease_end'   => $r->lease_end ? \Carbon\Carbon::parse($r->lease_end)->format('d/m/Y') : '-',
                    'status'      => $r->occupancy_status_id == 31 ? 'Vacant' : 'Occupied',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getOccupancy: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportOccupancy(Request $request)
    {
        try {
            $query = Unit::query()
                ->join('sys_pm_properties as p', 'p.id', '=', 'sys_pm_units.property_id')
                ->leftJoin('sys_pm_lease_agreements as la', function ($j) {
                    $j->on('la.unit_id', '=', 'sys_pm_units.id')
                      ->where('la.current_status_id', 1)
                      ->whereRaw('la.date_start <= NOW()')
                      ->whereRaw('la.date_end >= NOW()');
                })
                ->leftJoin('sys_pm_clients as tc', 'tc.id', '=', 'la.tenant_id')
                ->leftJoin('sys_pm_unit_types as ut', 'ut.id', '=', 'sys_pm_units.unit_type_id')
                ->select([
                    'p.property_name',
                    'sys_pm_units.unit_code',
                    'sys_pm_units.unit_name',
                    'ut.type_name as unit_type',
                    'sys_pm_units.rent_amount',
                    'tc.names as tenant_name',
                    'tc.reference_number as tenant_ref',
                    'la.ref_number as lease_ref',
                    'la.date_end as lease_end',
                    'sys_pm_units.occupancy_status_id',
                ])
                ->where('p.active', 1)
                ->when($request->filled('property_id'), fn($q) => $q->where('sys_pm_units.property_id', $request->property_id))
                ->when($request->filled('zone_id'), fn($q) => $q->where('sys_pm_units.zone_id', $request->zone_id))
                ->orderBy('p.property_name')
                ->orderBy('sys_pm_units.unit_code')
                ->get();

            $rows = $query->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->property_name,
                    $r->unit_code,
                    $r->unit_name,
                    $r->unit_type ?? '-',
                    number_format($r->rent_amount ?? 0, 2),
                    $r->tenant_name ?? '-',
                    $r->tenant_ref ?? '-',
                    $r->lease_ref ?? '-',
                    $r->lease_end ? \Carbon\Carbon::parse($r->lease_end)->format('d/m/Y') : '-',
                    $r->occupancy_status_id == 31 ? 'Vacant' : 'Occupied',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Occupancy Report',
                    headings:  ['#', 'Property', 'Unit Code', 'Unit Name', 'Type', 'Rent (KES)', 'Tenant Name', 'Tenant Ref', 'Lease Ref', 'Lease End', 'Status'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 28, 'C' => 12, 'D' => 20, 'E' => 16, 'F' => 14, 'G' => 26, 'H' => 14, 'I' => 16, 'J' => 14, 'K' => 12],
                ),
                'occupancy-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportOccupancy: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. VACANCY REPORT
    // ──────────────────────────────────────────────────────────────────────────

    public function vacancy(): View
    {
        $properties = Property::orderBy('property_name')->get(['id', 'property_name']);
        $zones      = DB::table('sys_set_zones')->orderBy('zone_name')->get(['id', 'zone_name']);
        return view('reports.property.vacancy', compact('properties', 'zones'));
    }

    public function getVacancy(Request $request): JsonResponse
    {
        try {
            $records = Unit::query()
                ->join('sys_pm_properties as p', 'p.id', '=', 'sys_pm_units.property_id')
                ->leftJoin('sys_pm_unit_types as ut', 'ut.id', '=', 'sys_pm_units.unit_type_id')
                ->leftJoin('sys_pm_lease_agreements as la', function ($j) {
                    $j->on('la.unit_id', '=', 'sys_pm_units.id');
                })
                ->select([
                    'p.property_name',
                    'sys_pm_units.unit_code',
                    'sys_pm_units.unit_name',
                    'ut.type_name as unit_type',
                    'sys_pm_units.rent_amount',
                    DB::raw('MAX(la.date_end) as last_lease_end'),
                ])
                ->where('sys_pm_units.occupancy_status_id', 31)
                ->where('p.active', 1)
                ->when($request->filled('property_id'), fn($q) => $q->where('sys_pm_units.property_id', $request->property_id))
                ->when($request->filled('zone_id'), fn($q) => $q->where('sys_pm_units.zone_id', $request->zone_id))
                ->groupBy(
                    'p.property_name',
                    'sys_pm_units.unit_code',
                    'sys_pm_units.unit_name',
                    'ut.type_name',
                    'sys_pm_units.rent_amount'
                )
                ->orderBy('p.property_name')
                ->orderBy('sys_pm_units.unit_code')
                ->get();

            $data = $records->map(function ($r, $i) {
                return [
                    'no'              => $i + 1,
                    'property'        => $r->property_name,
                    'unit_code'       => $r->unit_code,
                    'unit_name'       => $r->unit_name,
                    'unit_type'       => $r->unit_type ?? '-',
                    'rent_amount'     => number_format($r->rent_amount ?? 0, 2),
                    'last_lease_end'  => $r->last_lease_end
                        ? \Carbon\Carbon::parse($r->last_lease_end)->format('d/m/Y')
                        : 'Never Leased',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getVacancy: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportVacancy(Request $request)
    {
        try {
            $records = Unit::query()
                ->join('sys_pm_properties as p', 'p.id', '=', 'sys_pm_units.property_id')
                ->leftJoin('sys_pm_unit_types as ut', 'ut.id', '=', 'sys_pm_units.unit_type_id')
                ->leftJoin('sys_pm_lease_agreements as la', function ($j) {
                    $j->on('la.unit_id', '=', 'sys_pm_units.id');
                })
                ->select([
                    'p.property_name',
                    'sys_pm_units.unit_code',
                    'sys_pm_units.unit_name',
                    'ut.type_name as unit_type',
                    'sys_pm_units.rent_amount',
                    DB::raw('MAX(la.date_end) as last_lease_end'),
                ])
                ->where('sys_pm_units.occupancy_status_id', 31)
                ->where('p.active', 1)
                ->when($request->filled('property_id'), fn($q) => $q->where('sys_pm_units.property_id', $request->property_id))
                ->when($request->filled('zone_id'), fn($q) => $q->where('sys_pm_units.zone_id', $request->zone_id))
                ->groupBy(
                    'p.property_name',
                    'sys_pm_units.unit_code',
                    'sys_pm_units.unit_name',
                    'ut.type_name',
                    'sys_pm_units.rent_amount'
                )
                ->orderBy('p.property_name')
                ->get();

            $rows = $records->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->property_name,
                    $r->unit_code,
                    $r->unit_name,
                    $r->unit_type ?? '-',
                    number_format($r->rent_amount ?? 0, 2),
                    $r->last_lease_end
                        ? \Carbon\Carbon::parse($r->last_lease_end)->format('d/m/Y')
                        : 'Never Leased',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Vacancy Report',
                    headings:  ['#', 'Property', 'Unit Code', 'Unit Name', 'Unit Type', 'Rent Amount (KES)', 'Last Lease End Date'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 28, 'C' => 12, 'D' => 20, 'E' => 16, 'F' => 18, 'G' => 20],
                ),
                'vacancy-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportVacancy: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. RENT ROLL
    // ──────────────────────────────────────────────────────────────────────────

    public function rentRoll(): View
    {
        $properties = Property::orderBy('property_name')->get(['id', 'property_name']);
        return view('reports.property.rent-roll', compact('properties'));
    }

    public function getRentRoll(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'property_id' => 'required|integer',
                'month'       => 'required|string|regex:/^\d{4}-\d{2}$/',
            ]);

            $monthStart = \Carbon\Carbon::parse($request->month . '-01')->startOfMonth();
            $monthEnd   = $monthStart->copy()->endOfMonth();

            $records = LeaseAgreement::query()
                ->join('sys_pm_properties as p', 'p.id', '=', 'sys_pm_lease_agreements.property_id')
                ->join('sys_pm_units as u', 'u.id', '=', 'sys_pm_lease_agreements.unit_id')
                ->join('sys_pm_clients as tc', 'tc.id', '=', 'sys_pm_lease_agreements.tenant_id')
                ->leftJoin('sys_pm_debtors_invoices as inv', function ($j) use ($monthStart, $monthEnd) {
                    $j->on('inv.tenant_id', '=', 'sys_pm_lease_agreements.tenant_id')
                      ->on('inv.unit_id', '=', 'sys_pm_lease_agreements.unit_id')
                      ->whereBetween('inv.date_time', [$monthStart, $monthEnd]);
                })
                ->select([
                    'p.property_name',
                    'u.unit_code',
                    'u.unit_name',
                    'tc.names as tenant_name',
                    'u.rent_amount as expected_rent',
                    'inv.reference_number as invoice_ref',
                    'inv.total_amount as invoice_total',
                    DB::raw('(inv.total_amount - COALESCE(inv.current_balance, inv.total_amount, 0)) as amount_paid'),
                    DB::raw('COALESCE(inv.current_balance, 0) as outstanding'),
                ])
                ->where('sys_pm_lease_agreements.property_id', $request->property_id)
                ->where('sys_pm_lease_agreements.current_status_id', 1)
                ->whereDate('sys_pm_lease_agreements.date_start', '<=', $monthEnd)
                ->whereDate('sys_pm_lease_agreements.date_end', '>=', $monthStart)
                ->orderBy('p.property_name')
                ->orderBy('u.unit_code')
                ->get();

            $data = $records->map(function ($r, $i) {
                return [
                    'no'             => $i + 1,
                    'property'       => $r->property_name,
                    'unit'           => $r->unit_code . ' - ' . $r->unit_name,
                    'tenant'         => $r->tenant_name,
                    'expected_rent'  => number_format($r->expected_rent ?? 0, 2),
                    'invoice_ref'    => $r->invoice_ref ?? '-',
                    'invoice_total'  => number_format($r->invoice_total ?? 0, 2),
                    'amount_paid'    => number_format(max(0, $r->amount_paid ?? 0), 2),
                    'outstanding'    => number_format($r->outstanding ?? 0, 2),
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getRentRoll: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportRentRoll(Request $request)
    {
        try {
            $monthStart = \Carbon\Carbon::parse($request->month . '-01')->startOfMonth();
            $monthEnd   = $monthStart->copy()->endOfMonth();

            $records = LeaseAgreement::query()
                ->join('sys_pm_properties as p', 'p.id', '=', 'sys_pm_lease_agreements.property_id')
                ->join('sys_pm_units as u', 'u.id', '=', 'sys_pm_lease_agreements.unit_id')
                ->join('sys_pm_clients as tc', 'tc.id', '=', 'sys_pm_lease_agreements.tenant_id')
                ->leftJoin('sys_pm_debtors_invoices as inv', function ($j) use ($monthStart, $monthEnd) {
                    $j->on('inv.tenant_id', '=', 'sys_pm_lease_agreements.tenant_id')
                      ->on('inv.unit_id', '=', 'sys_pm_lease_agreements.unit_id')
                      ->whereBetween('inv.date_time', [$monthStart, $monthEnd]);
                })
                ->select([
                    'p.property_name',
                    'u.unit_code',
                    'u.unit_name',
                    'tc.names as tenant_name',
                    'u.rent_amount as expected_rent',
                    'inv.reference_number as invoice_ref',
                    'inv.total_amount as invoice_total',
                    DB::raw('(inv.total_amount - COALESCE(inv.current_balance, inv.total_amount, 0)) as amount_paid'),
                    DB::raw('COALESCE(inv.current_balance, 0) as outstanding'),
                ])
                ->where('sys_pm_lease_agreements.property_id', $request->property_id)
                ->where('sys_pm_lease_agreements.current_status_id', 1)
                ->whereDate('sys_pm_lease_agreements.date_start', '<=', $monthEnd)
                ->whereDate('sys_pm_lease_agreements.date_end', '>=', $monthStart)
                ->orderBy('p.property_name')
                ->orderBy('u.unit_code')
                ->get();

            $rows = $records->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->property_name,
                    $r->unit_code . ' - ' . $r->unit_name,
                    $r->tenant_name,
                    number_format($r->expected_rent ?? 0, 2),
                    $r->invoice_ref ?? '-',
                    number_format($r->invoice_total ?? 0, 2),
                    number_format(max(0, $r->amount_paid ?? 0), 2),
                    number_format($r->outstanding ?? 0, 2),
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Rent Roll - ' . \Carbon\Carbon::parse($request->month . '-01')->format('F Y'),
                    headings:  ['#', 'Property', 'Unit', 'Tenant', 'Expected Rent', 'Invoice Ref', 'Invoice Total', 'Amount Paid', 'Outstanding'],
                    dataRows:  $rows,
                    meta:      ['Period: ' . \Carbon\Carbon::parse($request->month . '-01')->format('F Y'), 'Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 26, 'C' => 22, 'D' => 26, 'E' => 16, 'F' => 16, 'G' => 16, 'H' => 14, 'I' => 14],
                ),
                'rent-roll-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportRentRoll: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. LEASE REGISTER
    // ──────────────────────────────────────────────────────────────────────────

    public function leaseRegister(): View
    {
        $properties = Property::orderBy('property_name')->get(['id', 'property_name']);
        return view('reports.property.lease-register', compact('properties'));
    }

    public function getLeaseRegister(Request $request): JsonResponse
    {
        try {
            $records = LeaseAgreement::query()
                ->join('sys_pm_properties as p', 'p.id', '=', 'sys_pm_lease_agreements.property_id')
                ->join('sys_pm_units as u', 'u.id', '=', 'sys_pm_lease_agreements.unit_id')
                ->join('sys_pm_clients as tc', 'tc.id', '=', 'sys_pm_lease_agreements.tenant_id')
                ->select([
                    'sys_pm_lease_agreements.ref_number',
                    'p.property_name',
                    'u.unit_code',
                    'u.unit_name',
                    'tc.names as tenant_name',
                    'sys_pm_lease_agreements.date_start',
                    'sys_pm_lease_agreements.date_end',
                    'u.rent_amount',
                    'sys_pm_lease_agreements.escalation_rate',
                    'sys_pm_lease_agreements.lease_status',
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('sys_pm_lease_agreements.property_id', $request->property_id))
                ->when($request->filled('lease_status'), fn($q) => $q->where('sys_pm_lease_agreements.lease_status', $request->lease_status))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('sys_pm_lease_agreements.date_start', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('sys_pm_lease_agreements.date_start', '<=', $request->date_to))
                ->orderBy('p.property_name')
                ->orderBy('sys_pm_lease_agreements.date_start', 'desc')
                ->get();

            $data = $records->map(function ($r, $i) {
                return [
                    'no'              => $i + 1,
                    'lease_ref'       => $r->ref_number ?? '-',
                    'property'        => $r->property_name,
                    'unit'            => $r->unit_code . ' - ' . $r->unit_name,
                    'tenant'          => $r->tenant_name,
                    'date_start'      => $r->date_start ? \Carbon\Carbon::parse($r->date_start)->format('d/m/Y') : '-',
                    'date_end'        => $r->date_end ? \Carbon\Carbon::parse($r->date_end)->format('d/m/Y') : '-',
                    'rent'            => number_format($r->rent_amount ?? 0, 2),
                    'escalation_rate' => $r->escalation_rate ? number_format($r->escalation_rate, 2) . '%' : '-',
                    'status'          => $r->lease_status ?? '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getLeaseRegister: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportLeaseRegister(Request $request)
    {
        try {
            $records = LeaseAgreement::query()
                ->join('sys_pm_properties as p', 'p.id', '=', 'sys_pm_lease_agreements.property_id')
                ->join('sys_pm_units as u', 'u.id', '=', 'sys_pm_lease_agreements.unit_id')
                ->join('sys_pm_clients as tc', 'tc.id', '=', 'sys_pm_lease_agreements.tenant_id')
                ->select([
                    'sys_pm_lease_agreements.ref_number',
                    'p.property_name',
                    'u.unit_code',
                    'u.unit_name',
                    'tc.names as tenant_name',
                    'sys_pm_lease_agreements.date_start',
                    'sys_pm_lease_agreements.date_end',
                    'u.rent_amount',
                    'sys_pm_lease_agreements.escalation_rate',
                    'sys_pm_lease_agreements.lease_status',
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('sys_pm_lease_agreements.property_id', $request->property_id))
                ->when($request->filled('lease_status'), fn($q) => $q->where('sys_pm_lease_agreements.lease_status', $request->lease_status))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('sys_pm_lease_agreements.date_start', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('sys_pm_lease_agreements.date_start', '<=', $request->date_to))
                ->orderBy('p.property_name')
                ->orderBy('sys_pm_lease_agreements.date_start', 'desc')
                ->get();

            $rows = $records->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->ref_number ?? '-',
                    $r->property_name,
                    $r->unit_code . ' - ' . $r->unit_name,
                    $r->tenant_name,
                    $r->date_start ? \Carbon\Carbon::parse($r->date_start)->format('d/m/Y') : '-',
                    $r->date_end ? \Carbon\Carbon::parse($r->date_end)->format('d/m/Y') : '-',
                    number_format($r->rent_amount ?? 0, 2),
                    $r->escalation_rate ? number_format($r->escalation_rate, 2) . '%' : '-',
                    $r->lease_status ?? '-',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Lease Register',
                    headings:  ['#', 'Lease Ref', 'Property', 'Unit', 'Tenant', 'Start Date', 'End Date', 'Rent (KES)', 'Escalation Rate (%)', 'Status'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 16, 'C' => 28, 'D' => 22, 'E' => 26, 'F' => 13, 'G' => 13, 'H' => 14, 'I' => 18, 'J' => 14],
                ),
                'lease-register-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportLeaseRegister: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. LEASE EXPIRY
    // ──────────────────────────────────────────────────────────────────────────

    public function leaseExpiry(): View
    {
        $properties = Property::orderBy('property_name')->get(['id', 'property_name']);
        return view('reports.property.lease-expiry', compact('properties'));
    }

    public function getLeaseExpiry(Request $request): JsonResponse
    {
        try {
            $window  = (int) ($request->input('window', 30));
            $cutoff  = now()->addDays($window);

            $records = LeaseAgreement::query()
                ->join('sys_pm_properties as p', 'p.id', '=', 'sys_pm_lease_agreements.property_id')
                ->join('sys_pm_units as u', 'u.id', '=', 'sys_pm_lease_agreements.unit_id')
                ->join('sys_pm_clients as tc', 'tc.id', '=', 'sys_pm_lease_agreements.tenant_id')
                ->select([
                    'sys_pm_lease_agreements.ref_number',
                    'p.property_name',
                    'u.unit_code',
                    'u.unit_name',
                    'tc.names as tenant_name',
                    'tc.mobile_number',
                    'sys_pm_lease_agreements.date_end',
                    'u.rent_amount',
                ])
                ->where('sys_pm_lease_agreements.lease_status', 'Active')
                ->whereDate('sys_pm_lease_agreements.date_end', '>=', now())
                ->whereDate('sys_pm_lease_agreements.date_end', '<=', $cutoff)
                ->when($request->filled('property_id'), fn($q) => $q->where('sys_pm_lease_agreements.property_id', $request->property_id))
                ->orderBy('sys_pm_lease_agreements.date_end')
                ->get();

            $data = $records->map(function ($r, $i) {
                $daysRemaining = now()->diffInDays(\Carbon\Carbon::parse($r->date_end), false);
                return [
                    'no'             => $i + 1,
                    'lease_ref'      => $r->ref_number ?? '-',
                    'property'       => $r->property_name,
                    'unit'           => $r->unit_code . ' - ' . $r->unit_name,
                    'tenant'         => $r->tenant_name,
                    'mobile'         => $r->mobile_number ?? '-',
                    'expiry_date'    => $r->date_end ? \Carbon\Carbon::parse($r->date_end)->format('d/m/Y') : '-',
                    'days_remaining' => max(0, (int) $daysRemaining),
                    'rent'           => number_format($r->rent_amount ?? 0, 2),
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getLeaseExpiry: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportLeaseExpiry(Request $request)
    {
        try {
            $window  = (int) ($request->input('window', 30));
            $cutoff  = now()->addDays($window);

            $records = LeaseAgreement::query()
                ->join('sys_pm_properties as p', 'p.id', '=', 'sys_pm_lease_agreements.property_id')
                ->join('sys_pm_units as u', 'u.id', '=', 'sys_pm_lease_agreements.unit_id')
                ->join('sys_pm_clients as tc', 'tc.id', '=', 'sys_pm_lease_agreements.tenant_id')
                ->select([
                    'sys_pm_lease_agreements.ref_number',
                    'p.property_name',
                    'u.unit_code',
                    'u.unit_name',
                    'tc.names as tenant_name',
                    'tc.mobile_number',
                    'sys_pm_lease_agreements.date_end',
                    'u.rent_amount',
                ])
                ->where('sys_pm_lease_agreements.lease_status', 'Active')
                ->whereDate('sys_pm_lease_agreements.date_end', '>=', now())
                ->whereDate('sys_pm_lease_agreements.date_end', '<=', $cutoff)
                ->when($request->filled('property_id'), fn($q) => $q->where('sys_pm_lease_agreements.property_id', $request->property_id))
                ->orderBy('sys_pm_lease_agreements.date_end')
                ->get();

            $rows = $records->map(function ($r, $i) {
                $daysRemaining = now()->diffInDays(\Carbon\Carbon::parse($r->date_end), false);
                return [
                    $i + 1,
                    $r->ref_number ?? '-',
                    $r->property_name,
                    $r->unit_code . ' - ' . $r->unit_name,
                    $r->tenant_name,
                    $r->mobile_number ?? '-',
                    $r->date_end ? \Carbon\Carbon::parse($r->date_end)->format('d/m/Y') : '-',
                    max(0, (int) $daysRemaining),
                    number_format($r->rent_amount ?? 0, 2),
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Lease Expiry Report (Next ' . $window . ' Days)',
                    headings:  ['#', 'Lease Ref', 'Property', 'Unit', 'Tenant', 'Tenant Mobile', 'Expiry Date', 'Days Remaining', 'Rent (KES)'],
                    dataRows:  $rows,
                    meta:      ['Window: Next ' . $window . ' days', 'Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 16, 'C' => 26, 'D' => 22, 'E' => 26, 'F' => 16, 'G' => 14, 'H' => 16, 'I' => 14],
                ),
                'lease-expiry-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportLeaseExpiry: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. TENANT ROSTER
    // ──────────────────────────────────────────────────────────────────────────

    public function tenantRoster(): View
    {
        $properties = Property::orderBy('property_name')->get(['id', 'property_name']);
        $zones      = DB::table('sys_set_zones')->orderBy('zone_name')->get(['id', 'zone_name']);
        return view('reports.property.tenant-roster', compact('properties', 'zones'));
    }

    public function getTenantRoster(Request $request): JsonResponse
    {
        try {
            $records = Client::tenants()
                ->join('sys_pm_lease_agreements as la', function ($j) {
                    $j->on('la.tenant_id', '=', 'sys_pm_clients.id')
                      ->where('la.current_status_id', 1)
                      ->whereRaw('la.date_start <= NOW()')
                      ->whereRaw('la.date_end >= NOW()');
                })
                ->join('sys_pm_properties as p', 'p.id', '=', 'la.property_id')
                ->join('sys_pm_units as u', 'u.id', '=', 'la.unit_id')
                ->select([
                    'sys_pm_clients.reference_number as tenant_ref',
                    'sys_pm_clients.names as tenant_name',
                    'sys_pm_clients.mobile_number',
                    'sys_pm_clients.email_address',
                    'p.property_name',
                    'u.unit_code',
                    'u.unit_name',
                    'la.date_start',
                    'la.date_end',
                    'u.rent_amount',
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('la.property_id', $request->property_id))
                ->when($request->filled('zone_id'), fn($q) => $q->where('p.zone_id', $request->zone_id))
                ->when($request->filled('search'), function ($q) use ($request) {
                    $term = '%' . $request->search . '%';
                    $q->where(function ($s) use ($term) {
                        $s->where('sys_pm_clients.names', 'like', $term)
                          ->orWhere('sys_pm_clients.reference_number', 'like', $term)
                          ->orWhere('sys_pm_clients.mobile_number', 'like', $term);
                    });
                })
                ->orderBy('sys_pm_clients.names')
                ->get();

            $data = $records->map(function ($r, $i) {
                return [
                    'no'           => $i + 1,
                    'tenant_ref'   => $r->tenant_ref ?? '-',
                    'tenant_name'  => $r->tenant_name,
                    'mobile'       => $r->mobile_number ?? '-',
                    'email'        => $r->email_address ?? '-',
                    'property'     => $r->property_name,
                    'unit'         => $r->unit_code . ' - ' . $r->unit_name,
                    'lease_start'  => $r->date_start ? \Carbon\Carbon::parse($r->date_start)->format('d/m/Y') : '-',
                    'lease_end'    => $r->date_end ? \Carbon\Carbon::parse($r->date_end)->format('d/m/Y') : '-',
                    'rent'         => number_format($r->rent_amount ?? 0, 2),
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getTenantRoster: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportTenantRoster(Request $request)
    {
        try {
            $records = Client::tenants()
                ->join('sys_pm_lease_agreements as la', function ($j) {
                    $j->on('la.tenant_id', '=', 'sys_pm_clients.id')
                      ->where('la.current_status_id', 1)
                      ->whereRaw('la.date_start <= NOW()')
                      ->whereRaw('la.date_end >= NOW()');
                })
                ->join('sys_pm_properties as p', 'p.id', '=', 'la.property_id')
                ->join('sys_pm_units as u', 'u.id', '=', 'la.unit_id')
                ->select([
                    'sys_pm_clients.reference_number as tenant_ref',
                    'sys_pm_clients.names as tenant_name',
                    'sys_pm_clients.mobile_number',
                    'sys_pm_clients.email_address',
                    'p.property_name',
                    'u.unit_code',
                    'u.unit_name',
                    'la.date_start',
                    'la.date_end',
                    'u.rent_amount',
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('la.property_id', $request->property_id))
                ->when($request->filled('zone_id'), fn($q) => $q->where('p.zone_id', $request->zone_id))
                ->when($request->filled('search'), function ($q) use ($request) {
                    $term = '%' . $request->search . '%';
                    $q->where(function ($s) use ($term) {
                        $s->where('sys_pm_clients.names', 'like', $term)
                          ->orWhere('sys_pm_clients.reference_number', 'like', $term);
                    });
                })
                ->orderBy('sys_pm_clients.names')
                ->get();

            $rows = $records->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->tenant_ref ?? '-',
                    $r->tenant_name,
                    $r->mobile_number ?? '-',
                    $r->email_address ?? '-',
                    $r->property_name,
                    $r->unit_code . ' - ' . $r->unit_name,
                    $r->date_start ? \Carbon\Carbon::parse($r->date_start)->format('d/m/Y') : '-',
                    $r->date_end ? \Carbon\Carbon::parse($r->date_end)->format('d/m/Y') : '-',
                    number_format($r->rent_amount ?? 0, 2),
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Tenant Roster',
                    headings:  ['#', 'Tenant Ref', 'Name', 'Mobile', 'Email', 'Property', 'Unit', 'Lease Start', 'Lease End', 'Rent (KES)'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 14, 'C' => 26, 'D' => 16, 'E' => 26, 'F' => 26, 'G' => 22, 'H' => 13, 'I' => 13, 'J' => 14],
                ),
                'tenant-roster-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportTenantRoster: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. DEPOSIT REGISTER
    // ──────────────────────────────────────────────────────────────────────────

    public function depositRegister(): View
    {
        $properties = Property::orderBy('property_name')->get(['id', 'property_name']);
        return view('reports.property.deposit-register', compact('properties'));
    }

    public function getDepositRegister(Request $request): JsonResponse
    {
        try {
            $records = Deposit::query()
                ->join('sys_pm_properties as p', 'p.id', '=', 'sys_pm_deposits.property_id')
                ->join('sys_pm_units as u', 'u.id', '=', 'sys_pm_deposits.unit_id')
                ->join('sys_pm_clients as tc', 'tc.id', '=', 'sys_pm_deposits.tenant_id')
                ->leftJoin(
                    DB::raw('(SELECT deposit_id, SUM(amount_allocated) as total_allocated FROM sys_pm_deposit_allocations GROUP BY deposit_id) as da'),
                    'da.deposit_id',
                    '=',
                    'sys_pm_deposits.id'
                )
                ->select([
                    'sys_pm_deposits.date_time',
                    'tc.names as tenant_name',
                    'p.property_name',
                    'u.unit_code',
                    'u.unit_name',
                    'sys_pm_deposits.amount',
                    DB::raw('COALESCE(da.total_allocated, 0) as allocated'),
                    DB::raw('sys_pm_deposits.amount - COALESCE(da.total_allocated, 0) as unallocated'),
                    'sys_pm_deposits.payment_mode',
                    'sys_pm_deposits.reference_number',
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('sys_pm_deposits.property_id', $request->property_id))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('sys_pm_deposits.date_time', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('sys_pm_deposits.date_time', '<=', $request->date_to))
                ->orderBy('sys_pm_deposits.date_time', 'desc')
                ->get();

            $data = $records->map(function ($r, $i) {
                return [
                    'no'          => $i + 1,
                    'date'        => $r->date_time ? \Carbon\Carbon::parse($r->date_time)->format('d/m/Y') : '-',
                    'tenant'      => $r->tenant_name,
                    'property'    => $r->property_name,
                    'unit'        => $r->unit_code . ' - ' . $r->unit_name,
                    'amount'      => number_format($r->amount ?? 0, 2),
                    'allocated'   => number_format($r->allocated ?? 0, 2),
                    'unallocated' => number_format($r->unallocated ?? 0, 2),
                    'mode'        => $r->payment_mode ?? '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getDepositRegister: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportDepositRegister(Request $request)
    {
        try {
            $records = Deposit::query()
                ->join('sys_pm_properties as p', 'p.id', '=', 'sys_pm_deposits.property_id')
                ->join('sys_pm_units as u', 'u.id', '=', 'sys_pm_deposits.unit_id')
                ->join('sys_pm_clients as tc', 'tc.id', '=', 'sys_pm_deposits.tenant_id')
                ->leftJoin(
                    DB::raw('(SELECT deposit_id, SUM(amount_allocated) as total_allocated FROM sys_pm_deposit_allocations GROUP BY deposit_id) as da'),
                    'da.deposit_id',
                    '=',
                    'sys_pm_deposits.id'
                )
                ->select([
                    'sys_pm_deposits.date_time',
                    'tc.names as tenant_name',
                    'p.property_name',
                    'u.unit_code',
                    'u.unit_name',
                    'sys_pm_deposits.amount',
                    DB::raw('COALESCE(da.total_allocated, 0) as allocated'),
                    DB::raw('sys_pm_deposits.amount - COALESCE(da.total_allocated, 0) as unallocated'),
                    'sys_pm_deposits.payment_mode',
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('sys_pm_deposits.property_id', $request->property_id))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('sys_pm_deposits.date_time', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('sys_pm_deposits.date_time', '<=', $request->date_to))
                ->orderBy('sys_pm_deposits.date_time', 'desc')
                ->get();

            $rows = $records->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->date_time ? \Carbon\Carbon::parse($r->date_time)->format('d/m/Y') : '-',
                    $r->tenant_name,
                    $r->property_name,
                    $r->unit_code . ' - ' . $r->unit_name,
                    number_format($r->amount ?? 0, 2),
                    number_format($r->allocated ?? 0, 2),
                    number_format($r->unallocated ?? 0, 2),
                    $r->payment_mode ?? '-',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Deposit Register',
                    headings:  ['#', 'Date', 'Tenant', 'Property', 'Unit', 'Amount (KES)', 'Allocated (KES)', 'Unallocated (KES)', 'Mode'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 13, 'C' => 26, 'D' => 26, 'E' => 22, 'F' => 16, 'G' => 16, 'H' => 18, 'I' => 16],
                ),
                'deposit-register-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportDepositRegister: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10. RENT ESCALATION REGISTER
    // ──────────────────────────────────────────────────────────────────────────

    public function rentEscalations(): View
    {
        $properties = Property::orderBy('property_name')->get(['id', 'property_name']);
        return view('reports.property.rent-escalations', compact('properties'));
    }

    public function getRentEscalations(Request $request): JsonResponse
    {
        try {
            $records = DB::table('cb_lease_escalations as e')
                ->leftJoin('sys_pm_lease_agreements as la', 'la.id', '=', 'e.lease_agreement_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'e.property_id')
                ->leftJoin('sys_pm_units as u', 'u.id', '=', 'e.unit_id')
                ->leftJoin('sys_pm_clients as tc', 'tc.id', '=', 'la.tenant_id')
                ->select([
                    'e.id',
                    'e.reference_number as escalation_ref',
                    'p.property_name',
                    'u.unit_name',
                    'u.unit_code',
                    'tc.names as tenant_name',
                    'e.previous_amount',
                    'e.new_amount',
                    'e.escalation_type',
                    'e.effective_date',
                    'e.status',
                    'e.notification_sent_at as approved_date',
                    'e.applied_at as applied_date',
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('e.property_id', $request->property_id))
                ->when($request->filled('status'), fn($q) => $q->where('e.status', $request->status))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('e.effective_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('e.effective_date', '<=', $request->date_to))
                ->orderBy('e.effective_date', 'desc')
                ->get();

            $data = $records->map(function ($r, $i) {
                return [
                    'no'              => $i + 1,
                    'escalation_ref'  => $r->escalation_ref ?? $r->id,
                    'property'        => $r->property_name ?? '-',
                    'unit'            => ($r->unit_code ? $r->unit_code . ' - ' : '') . ($r->unit_name ?? '-'),
                    'tenant'          => $r->tenant_name ?? '-',
                    'previous_amount' => number_format($r->previous_amount ?? 0, 2),
                    'new_amount'      => number_format($r->new_amount ?? 0, 2),
                    'escalation_type' => $r->escalation_type ?? '-',
                    'effective_date'  => $r->effective_date ? \Carbon\Carbon::parse($r->effective_date)->format('d/m/Y') : '-',
                    'status'          => $r->status ?? '-',
                    'approved_date'   => $r->approved_date ? \Carbon\Carbon::parse($r->approved_date)->format('d/m/Y') : '-',
                    'applied_date'    => $r->applied_date ? \Carbon\Carbon::parse($r->applied_date)->format('d/m/Y') : '-',
                ];
            });

            // Chart: count of escalations per property
            $chartData = DB::table('cb_lease_escalations as e')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'e.property_id')
                ->select('p.property_name', DB::raw('COUNT(e.id) as total'))
                ->when($request->filled('property_id'), fn($q) => $q->where('e.property_id', $request->property_id))
                ->when($request->filled('status'), fn($q) => $q->where('e.status', $request->status))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('e.effective_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('e.effective_date', '<=', $request->date_to))
                ->groupBy('p.property_name')
                ->orderBy('p.property_name')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
                'chart'   => [
                    'labels' => $chartData->pluck('property_name')->toArray(),
                    'values' => $chartData->pluck('total')->toArray(),
                ],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getRentEscalations: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportRentEscalations(Request $request)
    {
        try {
            $records = DB::table('cb_lease_escalations as e')
                ->leftJoin('sys_pm_lease_agreements as la', 'la.id', '=', 'e.lease_agreement_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'e.property_id')
                ->leftJoin('sys_pm_units as u', 'u.id', '=', 'e.unit_id')
                ->leftJoin('sys_pm_clients as tc', 'tc.id', '=', 'la.tenant_id')
                ->select([
                    'e.id', 'e.reference_number as escalation_ref', 'p.property_name',
                    'u.unit_name', 'u.unit_code', 'tc.names as tenant_name',
                    'e.previous_amount', 'e.new_amount', 'e.escalation_type',
                    'e.effective_date', 'e.status', 'e.notification_sent_at as approved_date', 'e.applied_at as applied_date',
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('e.property_id', $request->property_id))
                ->when($request->filled('status'), fn($q) => $q->where('e.status', $request->status))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('e.effective_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('e.effective_date', '<=', $request->date_to))
                ->orderBy('e.effective_date', 'desc')
                ->get();

            $rows = $records->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->escalation_ref ?? $r->id,
                    $r->property_name ?? '-',
                    ($r->unit_code ? $r->unit_code . ' - ' : '') . ($r->unit_name ?? '-'),
                    $r->tenant_name ?? '-',
                    number_format($r->previous_amount ?? 0, 2),
                    number_format($r->new_amount ?? 0, 2),
                    $r->escalation_type ?? '-',
                    $r->effective_date ? \Carbon\Carbon::parse($r->effective_date)->format('d/m/Y') : '-',
                    $r->status ?? '-',
                    $r->approved_date ? \Carbon\Carbon::parse($r->approved_date)->format('d/m/Y') : '-',
                    $r->applied_date ? \Carbon\Carbon::parse($r->applied_date)->format('d/m/Y') : '-',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Rent Escalation Register',
                    headings:  ['#', 'Escalation Ref', 'Property', 'Unit', 'Tenant', 'Previous Amount (KES)', 'New Amount (KES)', 'Type', 'Effective Date', 'Status', 'Notified Date', 'Applied Date'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 18, 'C' => 26, 'D' => 22, 'E' => 26, 'F' => 20, 'G' => 18, 'H' => 14, 'I' => 14, 'J' => 20, 'K' => 16, 'L' => 14],
                ),
                'rent-escalations-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportRentEscalations: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 11. MAINTENANCE REQUEST REGISTER
    // ──────────────────────────────────────────────────────────────────────────

    public function maintenance(): View
    {
        $properties = Property::orderBy('property_name')->get(['id', 'property_name']);
        return view('reports.property.maintenance', compact('properties'));
    }

    public function getMaintenance(Request $request): JsonResponse
    {
        try {
            $records = DB::table('sys_pm_properties_repairs_requests as r')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'r.property_id')
                ->leftJoin(
                    DB::raw('(SELECT parent_id, COUNT(id) as item_count FROM sys_pm_properties_repairs_requests_49_repeat GROUP BY parent_id) as ri'),
                    'ri.parent_id', '=', 'r.id'
                )
                ->select([
                    'r.id',
                    'r.ref_number',
                    'p.property_name',
                    'r.date_time as request_date',
                    'r.visited_date_time as visited_date',
                    'r.current_status_id as status_id',
                    'r.duration',
                    DB::raw('COALESCE(ri.item_count, 0) as item_count'),
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('r.property_id', $request->property_id))
                ->when($request->filled('status'), fn($q) => $q->where('r.current_status_id', $request->status))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('r.date_time', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('r.date_time', '<=', $request->date_to))
                ->orderBy('r.date_time', 'desc')
                ->get();

            $statusMap = [1 => 'Pending', 2 => 'In Progress', 3 => 'Completed', 4 => 'Cancelled'];

            $data = $records->map(function ($r, $i) use ($statusMap) {
                return [
                    'no'           => $i + 1,
                    'request_ref'  => $r->ref_number ?? $r->id,
                    'property'     => $r->property_name ?? '-',
                    'request_date' => $r->request_date ? \Carbon\Carbon::parse($r->request_date)->format('d/m/Y') : '-',
                    'visited_date' => $r->visited_date ? \Carbon\Carbon::parse($r->visited_date)->format('d/m/Y') : '-',
                    'status'       => $statusMap[$r->status_id] ?? ('Status ' . $r->status_id),
                    'duration'     => $r->duration ?? '-',
                    'item_count'   => $r->item_count,
                ];
            });

            // Chart: open vs closed per property
            $chartRaw = DB::table('sys_pm_properties_repairs_requests as r')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'r.property_id')
                ->select(
                    'p.property_name',
                    DB::raw('SUM(CASE WHEN r.current_status_id IN (1, 2) THEN 1 ELSE 0 END) as open_count'),
                    DB::raw('SUM(CASE WHEN r.current_status_id IN (3, 4) THEN 1 ELSE 0 END) as closed_count')
                )
                ->when($request->filled('property_id'), fn($q) => $q->where('r.property_id', $request->property_id))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('r.date_time', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('r.date_time', '<=', $request->date_to))
                ->groupBy('p.property_name')
                ->orderBy('p.property_name')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
                'chart'   => [
                    'labels' => $chartRaw->pluck('property_name')->toArray(),
                    'open'   => $chartRaw->pluck('open_count')->toArray(),
                    'closed' => $chartRaw->pluck('closed_count')->toArray(),
                ],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getMaintenance: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportMaintenance(Request $request)
    {
        try {
            $records = DB::table('sys_pm_properties_repairs_requests as r')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'r.property_id')
                ->leftJoin(
                    DB::raw('(SELECT parent_id, COUNT(id) as item_count FROM sys_pm_properties_repairs_requests_49_repeat GROUP BY parent_id) as ri'),
                    'ri.parent_id', '=', 'r.id'
                )
                ->select([
                    'r.id', 'r.ref_number', 'p.property_name',
                    'r.date_time as request_date', 'r.visited_date_time as visited_date',
                    'r.current_status_id as status_id', 'r.duration',
                    DB::raw('COALESCE(ri.item_count, 0) as item_count'),
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('r.property_id', $request->property_id))
                ->when($request->filled('status'), fn($q) => $q->where('r.current_status_id', $request->status))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('r.date_time', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('r.date_time', '<=', $request->date_to))
                ->orderBy('r.date_time', 'desc')
                ->get();

            $statusMap = [1 => 'Pending', 2 => 'In Progress', 3 => 'Completed', 4 => 'Cancelled'];

            $rows = $records->map(function ($r, $i) use ($statusMap) {
                return [
                    $i + 1,
                    $r->ref_number ?? $r->id,
                    $r->property_name ?? '-',
                    $r->request_date ? \Carbon\Carbon::parse($r->request_date)->format('d/m/Y') : '-',
                    $r->visited_date ? \Carbon\Carbon::parse($r->visited_date)->format('d/m/Y') : '-',
                    $statusMap[$r->status_id] ?? ('Status ' . $r->status_id),
                    $r->duration ?? '-',
                    $r->item_count,
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Maintenance Request Register',
                    headings:  ['#', 'Request Ref', 'Property', 'Request Date', 'Visited Date', 'Status', 'Duration', 'Items'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 18, 'C' => 28, 'D' => 14, 'E' => 14, 'F' => 16, 'G' => 12, 'H' => 8],
                ),
                'maintenance-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportMaintenance: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 12. INVOICE AGING REPORT
    // ──────────────────────────────────────────────────────────────────────────

    public function invoiceAging(): View
    {
        $properties = Property::orderBy('property_name')->get(['id', 'property_name']);
        return view('reports.property.invoice-aging', compact('properties'));
    }

    public function getInvoiceAging(Request $request): JsonResponse
    {
        try {
            $records = DB::table('sys_pm_debtors_invoices as inv')
                ->leftJoin('sys_pm_clients as tc', 'tc.id', '=', 'inv.tenant_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'inv.property_id')
                ->leftJoin('sys_pm_units as u', 'u.id', '=', 'inv.unit_id')
                ->select([
                    'inv.id',
                    'inv.reference_number as invoice_ref',
                    'inv.date_time as invoice_date',
                    'inv.due_date',
                    'inv.total_amount',
                    'inv.current_balance',
                    'tc.names as tenant_name',
                    'tc.reference_number as tenant_ref',
                    'p.property_name',
                    'u.unit_code',
                    'u.unit_name',
                    DB::raw('DATEDIFF(CURDATE(), inv.due_date) as days_overdue'),
                ])
                ->where('inv.debtor_type_id', 2)
                ->where('inv.current_balance', '>', 0)
                ->when($request->filled('property_id'), fn($q) => $q->where('inv.property_id', $request->property_id))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('inv.date_time', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('inv.date_time', '<=', $request->date_to))
                ->orderBy('days_overdue', 'desc')
                ->get();

            $bucketFilter = $request->input('bucket', 'all');

            $data = $records->filter(function ($r) use ($bucketFilter) {
                $days = (int) ($r->days_overdue ?? 0);
                $bucket = $days <= 0 ? 'current' : ($days <= 30 ? '1-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+')));
                return $bucketFilter === 'all' || $bucketFilter === $bucket;
            })->values()->map(function ($r, $i) {
                $days   = (int) ($r->days_overdue ?? 0);
                $bucket = $days <= 0 ? 'Current' : ($days <= 30 ? '1-30 Days' : ($days <= 60 ? '31-60 Days' : ($days <= 90 ? '61-90 Days' : '90+ Days')));
                return [
                    'no'          => $i + 1,
                    'tenant'      => $r->tenant_name ?? '-',
                    'tenant_ref'  => $r->tenant_ref ?? '-',
                    'property'    => $r->property_name ?? '-',
                    'unit'        => ($r->unit_code ? $r->unit_code . ' - ' : '') . ($r->unit_name ?? '-'),
                    'invoice_ref' => $r->invoice_ref ?? '-',
                    'invoice_date'=> $r->invoice_date ? \Carbon\Carbon::parse($r->invoice_date)->format('d/m/Y') : '-',
                    'due_date'    => $r->due_date ? \Carbon\Carbon::parse($r->due_date)->format('d/m/Y') : '-',
                    'total'       => number_format($r->total_amount ?? 0, 2),
                    'outstanding' => number_format($r->current_balance ?? 0, 2),
                    'days_overdue'=> $days,
                    'bucket'      => $bucket,
                ];
            });

            // Chart: sum outstanding by bucket
            $buckets = ['Current', '1-30 Days', '31-60 Days', '61-90 Days', '90+ Days'];
            $bucketTotals = array_fill_keys($buckets, 0);
            foreach ($records as $r) {
                $days = (int) ($r->days_overdue ?? 0);
                $b    = $days <= 0 ? 'Current' : ($days <= 30 ? '1-30 Days' : ($days <= 60 ? '31-60 Days' : ($days <= 90 ? '61-90 Days' : '90+ Days')));
                $bucketTotals[$b] += (float) ($r->current_balance ?? 0);
            }

            return response()->json([
                'success' => true,
                'data'    => $data->values(),
                'count'   => $data->count(),
                'chart'   => [
                    'labels' => $buckets,
                    'values' => array_values($bucketTotals),
                ],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getInvoiceAging: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportInvoiceAging(Request $request)
    {
        try {
            $records = DB::table('sys_pm_debtors_invoices as inv')
                ->leftJoin('sys_pm_clients as tc', 'tc.id', '=', 'inv.tenant_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'inv.property_id')
                ->leftJoin('sys_pm_units as u', 'u.id', '=', 'inv.unit_id')
                ->select([
                    'inv.id', 'inv.reference_number as invoice_ref', 'inv.date_time as invoice_date',
                    'inv.due_date', 'inv.total_amount', 'inv.current_balance',
                    'tc.names as tenant_name', 'tc.reference_number as tenant_ref',
                    'p.property_name', 'u.unit_code', 'u.unit_name',
                    DB::raw('DATEDIFF(CURDATE(), inv.due_date) as days_overdue'),
                ])
                ->where('inv.debtor_type_id', 2)
                ->where('inv.current_balance', '>', 0)
                ->when($request->filled('property_id'), fn($q) => $q->where('inv.property_id', $request->property_id))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('inv.date_time', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('inv.date_time', '<=', $request->date_to))
                ->orderBy('days_overdue', 'desc')
                ->get();

            $bucketFilter = $request->input('bucket', 'all');

            $rows = $records->filter(function ($r) use ($bucketFilter) {
                $days   = (int) ($r->days_overdue ?? 0);
                $bucket = $days <= 0 ? 'current' : ($days <= 30 ? '1-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+')));
                return $bucketFilter === 'all' || $bucketFilter === $bucket;
            })->values()->map(function ($r, $i) {
                $days   = (int) ($r->days_overdue ?? 0);
                $bucket = $days <= 0 ? 'Current' : ($days <= 30 ? '1-30 Days' : ($days <= 60 ? '31-60 Days' : ($days <= 90 ? '61-90 Days' : '90+ Days')));
                return [
                    $i + 1,
                    $r->tenant_name ?? '-',
                    $r->tenant_ref ?? '-',
                    $r->property_name ?? '-',
                    ($r->unit_code ? $r->unit_code . ' - ' : '') . ($r->unit_name ?? '-'),
                    $r->invoice_ref ?? '-',
                    $r->invoice_date ? \Carbon\Carbon::parse($r->invoice_date)->format('d/m/Y') : '-',
                    $r->due_date ? \Carbon\Carbon::parse($r->due_date)->format('d/m/Y') : '-',
                    number_format($r->total_amount ?? 0, 2),
                    number_format($r->current_balance ?? 0, 2),
                    $days,
                    $bucket,
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Invoice Aging Report',
                    headings:  ['#', 'Tenant', 'Tenant Ref', 'Property', 'Unit', 'Invoice Ref', 'Invoice Date', 'Due Date', 'Invoice Amount (KES)', 'Outstanding (KES)', 'Days Overdue', 'Bucket'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 26, 'C' => 14, 'D' => 22, 'E' => 20, 'F' => 16, 'G' => 14, 'H' => 13, 'I' => 20, 'J' => 18, 'K' => 13, 'L' => 14],
                ),
                'invoice-aging-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportInvoiceAging: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 13. TENANT MOVEMENT HISTORY
    // ──────────────────────────────────────────────────────────────────────────

    public function tenantMovements(): View
    {
        $properties = Property::orderBy('property_name')->get(['id', 'property_name']);
        return view('reports.property.tenant-movements', compact('properties'));
    }

    public function getTenantMovements(Request $request): JsonResponse
    {
        try {
            $records = DB::table('sys_pm_movement_requests as m')
                ->leftJoin('sys_pm_clients as tc', 'tc.id', '=', 'm.tenant_id')
                ->leftJoin('sys_pm_units as u1', 'u1.id', '=', 'm.unit_from_id')
                ->leftJoin('sys_pm_units as u2', 'u2.id', '=', 'm.unit_to_id')
                ->leftJoin('sys_pm_properties as p1', 'p1.id', '=', 'm.property_id')
                ->select([
                    'm.id',
                    'm.reference_number',
                    'tc.names as tenant_name',
                    'tc.reference_number as tenant_ref',
                    'p1.property_name as from_property',
                    'u1.unit_code as from_unit_code',
                    'u1.unit_name as from_unit_name',
                    'u2.unit_code as to_unit_code',
                    'u2.unit_name as to_unit_name',
                    'm.movement_date',
                    'm.current_balance',
                    'm.current_status_id as status_id',
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('m.property_id', $request->property_id))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('m.movement_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('m.movement_date', '<=', $request->date_to))
                ->orderBy('m.movement_date', 'desc')
                ->get();

            $statusMap = [1 => 'Pending', 2 => 'Approved', 3 => 'Completed', 4 => 'Cancelled'];

            $data = $records->map(function ($r, $i) use ($statusMap) {
                return [
                    'no'            => $i + 1,
                    'reference'     => $r->reference_number ?? $r->id,
                    'tenant_ref'    => $r->tenant_ref ?? '-',
                    'tenant_name'   => $r->tenant_name ?? '-',
                    'from_property' => $r->from_property ?? '-',
                    'from_unit'     => ($r->from_unit_code ? $r->from_unit_code . ' - ' : '') . ($r->from_unit_name ?? '-'),
                    'to_unit'       => ($r->to_unit_code ? $r->to_unit_code . ' - ' : '') . ($r->to_unit_name ?? '-'),
                    'movement_date' => $r->movement_date ? \Carbon\Carbon::parse($r->movement_date)->format('d/m/Y') : '-',
                    'balance'       => number_format($r->current_balance ?? 0, 2),
                    'status'        => $statusMap[$r->status_id] ?? ('Status ' . $r->status_id),
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getTenantMovements: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportTenantMovements(Request $request)
    {
        try {
            $records = DB::table('sys_pm_movement_requests as m')
                ->leftJoin('sys_pm_clients as tc', 'tc.id', '=', 'm.tenant_id')
                ->leftJoin('sys_pm_units as u1', 'u1.id', '=', 'm.unit_from_id')
                ->leftJoin('sys_pm_units as u2', 'u2.id', '=', 'm.unit_to_id')
                ->leftJoin('sys_pm_properties as p1', 'p1.id', '=', 'm.property_id')
                ->select([
                    'm.id', 'm.reference_number', 'tc.names as tenant_name', 'tc.reference_number as tenant_ref',
                    'p1.property_name as from_property',
                    'u1.unit_code as from_unit_code', 'u1.unit_name as from_unit_name',
                    'u2.unit_code as to_unit_code', 'u2.unit_name as to_unit_name',
                    'm.movement_date', 'm.current_balance', 'm.current_status_id as status_id',
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('m.property_id', $request->property_id))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('m.movement_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('m.movement_date', '<=', $request->date_to))
                ->orderBy('m.movement_date', 'desc')
                ->get();

            $statusMap = [1 => 'Pending', 2 => 'Approved', 3 => 'Completed', 4 => 'Cancelled'];

            $rows = $records->map(function ($r, $i) use ($statusMap) {
                return [
                    $i + 1,
                    $r->reference_number ?? $r->id,
                    $r->tenant_ref ?? '-',
                    $r->tenant_name ?? '-',
                    $r->from_property ?? '-',
                    ($r->from_unit_code ? $r->from_unit_code . ' - ' : '') . ($r->from_unit_name ?? '-'),
                    ($r->to_unit_code ? $r->to_unit_code . ' - ' : '') . ($r->to_unit_name ?? '-'),
                    $r->movement_date ? \Carbon\Carbon::parse($r->movement_date)->format('d/m/Y') : '-',
                    number_format($r->current_balance ?? 0, 2),
                    $statusMap[$r->status_id] ?? ('Status ' . $r->status_id),
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Tenant Movement History',
                    headings:  ['#', 'Reference', 'Tenant Ref', 'Tenant Name', 'From Property', 'From Unit', 'To Unit', 'Movement Date', 'Balance Carried (KES)', 'Status'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 16, 'C' => 14, 'D' => 26, 'E' => 26, 'F' => 22, 'G' => 22, 'H' => 14, 'I' => 20, 'J' => 14],
                ),
                'tenant-movements-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportTenantMovements: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 14. TENANT BLACKLIST REGISTER
    // ──────────────────────────────────────────────────────────────────────────

    public function blacklist(): View
    {
        return view('reports.property.blacklist');
    }

    public function getBlacklist(Request $request): JsonResponse
    {
        try {
            $records = DB::table('sys_pm_tenants_blacklist as bl')
                ->leftJoin('sys_pm_clients as tc', 'tc.id', '=', 'bl.tenant_id')
                ->leftJoin('sys_pm_tenants_blacklist_reasons as br', 'br.id', '=', 'bl.reason_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'bl.property_id')
                ->select([
                    'bl.id',
                    'tc.reference_number as tenant_ref',
                    'tc.names as tenant_name',
                    'tc.mobile_number',
                    'bl.blacklist_date',
                    'br.reason_name as reason',
                    'bl.current_balance',
                    'bl.current_status_id as status_id',
                    'p.property_name',
                ])
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('bl.blacklist_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('bl.blacklist_date', '<=', $request->date_to))
                ->when($request->filled('status'), fn($q) => $q->where('bl.current_status_id', $request->status))
                ->orderBy('bl.blacklist_date', 'desc')
                ->get();

            $statusMap = [1 => 'Active', 2 => 'Lifted', 3 => 'Under Review'];

            $data = $records->map(function ($r, $i) use ($statusMap) {
                return [
                    'no'          => $i + 1,
                    'tenant_ref'  => $r->tenant_ref ?? '-',
                    'tenant_name' => $r->tenant_name ?? '-',
                    'mobile'      => $r->mobile_number ?? '-',
                    'blacklist_date' => $r->blacklist_date ? \Carbon\Carbon::parse($r->blacklist_date)->format('d/m/Y') : '-',
                    'reason'      => $r->reason ?? '-',
                    'balance'     => number_format($r->current_balance ?? 0, 2),
                    'status'      => $statusMap[$r->status_id] ?? ('Status ' . $r->status_id),
                    'property'    => $r->property_name ?? '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getBlacklist: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportBlacklist(Request $request)
    {
        try {
            $records = DB::table('sys_pm_tenants_blacklist as bl')
                ->leftJoin('sys_pm_clients as tc', 'tc.id', '=', 'bl.tenant_id')
                ->leftJoin('sys_pm_tenants_blacklist_reasons as br', 'br.id', '=', 'bl.reason_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'bl.property_id')
                ->select([
                    'bl.id', 'tc.reference_number as tenant_ref', 'tc.names as tenant_name',
                    'tc.mobile_number', 'bl.blacklist_date', 'br.reason_name as reason',
                    'bl.current_balance', 'bl.current_status_id as status_id', 'p.property_name',
                ])
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('bl.blacklist_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('bl.blacklist_date', '<=', $request->date_to))
                ->when($request->filled('status'), fn($q) => $q->where('bl.current_status_id', $request->status))
                ->orderBy('bl.blacklist_date', 'desc')
                ->get();

            $statusMap = [1 => 'Active', 2 => 'Lifted', 3 => 'Under Review'];

            $rows = $records->map(function ($r, $i) use ($statusMap) {
                return [
                    $i + 1,
                    $r->tenant_ref ?? '-',
                    $r->tenant_name ?? '-',
                    $r->mobile_number ?? '-',
                    $r->blacklist_date ? \Carbon\Carbon::parse($r->blacklist_date)->format('d/m/Y') : '-',
                    $r->reason ?? '-',
                    number_format($r->current_balance ?? 0, 2),
                    $statusMap[$r->status_id] ?? ('Status ' . $r->status_id),
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Tenant Blacklist Register',
                    headings:  ['#', 'Tenant Ref', 'Tenant Name', 'Mobile', 'Blacklist Date', 'Reason', 'Balance at Blacklisting (KES)', 'Status'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 14, 'C' => 28, 'D' => 16, 'E' => 16, 'F' => 30, 'G' => 24, 'H' => 14],
                ),
                'blacklist-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportBlacklist: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 15. LANDLORD REMITTANCE LEDGER
    // ──────────────────────────────────────────────────────────────────────────

    public function landlordRemittance(): View
    {
        $properties = Property::orderBy('property_name')->get(['id', 'property_name']);
        $landlords  = Client::landlords()->select('id', 'names', 'reference_number')->orderBy('names')->get();
        return view('reports.property.landlord-remittance', compact('properties', 'landlords'));
    }

    public function getLandlordRemittance(Request $request): JsonResponse
    {
        try {
            $records = DB::table('cb_landlord_account as la')
                ->leftJoin('sys_pm_clients as c', 'c.id', '=', 'la.client_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'la.property_id')
                ->select([
                    'la.id',
                    'c.reference_number as landlord_ref',
                    'c.names as landlord_name',
                    'p.property_name',
                    'la.entry_date',
                    'la.entry_type_code',
                    'la.entry_description',
                    'la.debit_amount',
                    'la.credit_amount',
                    'la.pv_number',
                    'la.gl_account',
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('la.property_id', $request->property_id))
                ->when($request->filled('client_id'), fn($q) => $q->where('la.client_id', $request->client_id))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('la.entry_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('la.entry_date', '<=', $request->date_to))
                ->orderBy('la.entry_date', 'desc')
                ->orderBy('la.entry_sequence')
                ->get();

            $data = $records->map(function ($r, $i) {
                return [
                    'no'            => $i + 1,
                    'landlord_ref'  => $r->landlord_ref ?? '-',
                    'landlord_name' => $r->landlord_name ?? '-',
                    'property'      => $r->property_name ?? '-',
                    'entry_date'    => $r->entry_date ? \Carbon\Carbon::parse($r->entry_date)->format('d/m/Y') : '-',
                    'entry_type'    => $r->entry_type_code ?? '-',
                    'description'   => $r->entry_description ?? '-',
                    'debit'         => number_format($r->debit_amount ?? 0, 2),
                    'credit'        => number_format($r->credit_amount ?? 0, 2),
                    'pv_number'     => $r->pv_number ?? '-',
                    'gl_account'    => $r->gl_account ?? '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getLandlordRemittance: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportLandlordRemittance(Request $request)
    {
        try {
            $records = DB::table('cb_landlord_account as la')
                ->leftJoin('sys_pm_clients as c', 'c.id', '=', 'la.client_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'la.property_id')
                ->select([
                    'la.id', 'c.reference_number as landlord_ref', 'c.names as landlord_name',
                    'p.property_name', 'la.entry_date', 'la.entry_type_code', 'la.entry_description',
                    'la.debit_amount', 'la.credit_amount', 'la.pv_number', 'la.gl_account',
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('la.property_id', $request->property_id))
                ->when($request->filled('client_id'), fn($q) => $q->where('la.client_id', $request->client_id))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('la.entry_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('la.entry_date', '<=', $request->date_to))
                ->orderBy('la.entry_date', 'desc')
                ->orderBy('la.entry_sequence')
                ->get();

            $rows = $records->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->landlord_ref ?? '-',
                    $r->landlord_name ?? '-',
                    $r->property_name ?? '-',
                    $r->entry_date ? \Carbon\Carbon::parse($r->entry_date)->format('d/m/Y') : '-',
                    $r->entry_type_code ?? '-',
                    $r->entry_description ?? '-',
                    number_format($r->debit_amount ?? 0, 2),
                    number_format($r->credit_amount ?? 0, 2),
                    $r->pv_number ?? '-',
                    $r->gl_account ?? '-',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Landlord Remittance Ledger',
                    headings:  ['#', 'Landlord Ref', 'Landlord Name', 'Property', 'Entry Date', 'Entry Type', 'Description', 'Debit (KES)', 'Credit (KES)', 'PV Number', 'GL Account'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 14, 'C' => 26, 'D' => 26, 'E' => 13, 'F' => 14, 'G' => 32, 'H' => 16, 'I' => 16, 'J' => 16, 'K' => 16],
                ),
                'landlord-remittance-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportLandlordRemittance: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 16. PENDING LEASE DOCUMENTS
    // ──────────────────────────────────────────────────────────────────────────

    public function pendingContracts(): View
    {
        $properties = Property::orderBy('property_name')->get(['id', 'property_name']);
        return view('reports.property.pending-contracts', compact('properties'));
    }

    public function getPendingContracts(Request $request): JsonResponse
    {
        try {
            $records = DB::table('sys_pm_lease_agreements as la')
                ->leftJoin('sys_pm_clients as tc', 'tc.id', '=', 'la.tenant_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'la.property_id')
                ->leftJoin('sys_pm_units as u', 'u.id', '=', 'la.unit_id')
                ->select([
                    'la.id',
                    'la.ref_number',
                    'tc.names as tenant_name',
                    'p.property_name',
                    'u.unit_code',
                    'u.unit_name',
                    'la.date_start',
                    'la.date_end',
                    'la.rent_amount',
                    'la.contract_sent_at',
                    'la.contract_signed_at',
                ])
                ->where(function ($q) {
                    $q->whereNull('la.contract_signed_at')
                      ->orWhereNull('la.contract_sent_at');
                })
                ->when($request->filled('property_id'), fn($q) => $q->where('la.property_id', $request->property_id))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('la.date_start', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('la.date_start', '<=', $request->date_to))
                ->orderBy('la.date_start', 'desc')
                ->get();

            $data = $records->map(function ($r, $i) {
                $daysSinceStart = $r->date_start
                    ? (int) \Carbon\Carbon::parse($r->date_start)->diffInDays(now(), false)
                    : '-';
                return [
                    'no'              => $i + 1,
                    'lease_ref'       => $r->ref_number ?? '-',
                    'tenant_name'     => $r->tenant_name ?? '-',
                    'property'        => $r->property_name ?? '-',
                    'unit'            => ($r->unit_code ? $r->unit_code . ' - ' : '') . ($r->unit_name ?? '-'),
                    'lease_start'     => $r->date_start ? \Carbon\Carbon::parse($r->date_start)->format('d/m/Y') : '-',
                    'lease_end'       => $r->date_end ? \Carbon\Carbon::parse($r->date_end)->format('d/m/Y') : '-',
                    'rent'            => number_format($r->rent_amount ?? 0, 2),
                    'contract_sent'   => $r->contract_sent_at ? \Carbon\Carbon::parse($r->contract_sent_at)->format('d/m/Y') : 'Not Sent',
                    'contract_signed' => $r->contract_signed_at ? \Carbon\Carbon::parse($r->contract_signed_at)->format('d/m/Y') : 'Not Signed',
                    'days_since_start'=> is_int($daysSinceStart) ? max(0, $daysSinceStart) : '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getPendingContracts: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportPendingContracts(Request $request)
    {
        try {
            $records = DB::table('sys_pm_lease_agreements as la')
                ->leftJoin('sys_pm_clients as tc', 'tc.id', '=', 'la.tenant_id')
                ->leftJoin('sys_pm_properties as p', 'p.id', '=', 'la.property_id')
                ->leftJoin('sys_pm_units as u', 'u.id', '=', 'la.unit_id')
                ->select([
                    'la.id', 'la.ref_number', 'tc.names as tenant_name', 'p.property_name',
                    'u.unit_code', 'u.unit_name', 'la.date_start', 'la.date_end',
                    'la.rent_amount', 'la.contract_sent_at', 'la.contract_signed_at',
                ])
                ->where(function ($q) {
                    $q->whereNull('la.contract_signed_at')
                      ->orWhereNull('la.contract_sent_at');
                })
                ->when($request->filled('property_id'), fn($q) => $q->where('la.property_id', $request->property_id))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('la.date_start', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('la.date_start', '<=', $request->date_to))
                ->orderBy('la.date_start', 'desc')
                ->get();

            $rows = $records->map(function ($r, $i) {
                $daysSinceStart = $r->date_start
                    ? max(0, (int) \Carbon\Carbon::parse($r->date_start)->diffInDays(now(), false))
                    : '-';
                return [
                    $i + 1,
                    $r->ref_number ?? '-',
                    $r->tenant_name ?? '-',
                    $r->property_name ?? '-',
                    ($r->unit_code ? $r->unit_code . ' - ' : '') . ($r->unit_name ?? '-'),
                    $r->date_start ? \Carbon\Carbon::parse($r->date_start)->format('d/m/Y') : '-',
                    $r->date_end ? \Carbon\Carbon::parse($r->date_end)->format('d/m/Y') : '-',
                    number_format($r->rent_amount ?? 0, 2),
                    $r->contract_sent_at ? \Carbon\Carbon::parse($r->contract_sent_at)->format('d/m/Y') : 'Not Sent',
                    $r->contract_signed_at ? \Carbon\Carbon::parse($r->contract_signed_at)->format('d/m/Y') : 'Not Signed',
                    $daysSinceStart,
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Pending Lease Documents',
                    headings:  ['#', 'Lease Ref', 'Tenant Name', 'Property', 'Unit', 'Lease Start', 'Lease End', 'Rent (KES)', 'Contract Sent', 'Contract Signed', 'Days Since Start'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 16, 'C' => 26, 'D' => 26, 'E' => 22, 'F' => 13, 'G' => 13, 'H' => 14, 'I' => 14, 'J' => 15, 'K' => 16],
                ),
                'pending-contracts-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportPendingContracts: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. EXIT REPORT
    // ──────────────────────────────────────────────────────────────────────────

    public function exits(): View
    {
        $properties = Property::orderBy('property_name')->get(['id', 'property_name']);
        return view('reports.property.exits', compact('properties'));
    }

    public function getExits(Request $request): JsonResponse
    {
        try {
            $records = TenantExit::completed()
                ->join('sys_pm_clients as tc', 'tc.id', '=', 'cb_tenant_exits.tenant_id')
                ->join('sys_pm_properties as p', 'p.id', '=', 'cb_tenant_exits.property_id')
                ->join('sys_pm_units as u', 'u.id', '=', 'cb_tenant_exits.unit_id')
                ->leftJoin(
                    DB::raw('(SELECT exit_id, SUM(refund_amount) as total_refund FROM cb_tenant_exit_deposit_refunds GROUP BY exit_id) as dr'),
                    'dr.exit_id',
                    '=',
                    'cb_tenant_exits.id'
                )
                ->leftJoin('josya_users as fu', 'fu.id', '=', 'cb_tenant_exits.finalised_by')
                ->select([
                    'cb_tenant_exits.actual_exit_date',
                    'tc.names as tenant_name',
                    'tc.reference_number as tenant_ref',
                    'p.property_name',
                    'u.unit_code',
                    'u.unit_name',
                    'cb_tenant_exits.exit_reason',
                    DB::raw('COALESCE(dr.total_refund, 0) as deposit_refunded'),
                    'fu.name as finalised_by',
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('cb_tenant_exits.property_id', $request->property_id))
                ->when($request->filled('exit_reason'), fn($q) => $q->where('cb_tenant_exits.exit_reason', $request->exit_reason))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('cb_tenant_exits.actual_exit_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('cb_tenant_exits.actual_exit_date', '<=', $request->date_to))
                ->orderBy('cb_tenant_exits.actual_exit_date', 'desc')
                ->get();

            $data = $records->map(function ($r, $i) {
                return [
                    'no'               => $i + 1,
                    'exit_date'        => $r->actual_exit_date ? \Carbon\Carbon::parse($r->actual_exit_date)->format('d/m/Y') : '-',
                    'tenant_name'      => $r->tenant_name,
                    'tenant_ref'       => $r->tenant_ref ?? '-',
                    'property'         => $r->property_name,
                    'unit'             => $r->unit_code . ' - ' . $r->unit_name,
                    'reason'           => $r->exit_reason ?? '-',
                    'deposit_refunded' => number_format($r->deposit_refunded ?? 0, 2),
                    'finalised_by'     => $r->finalised_by ?? '-',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@getExits: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load report'], 500);
        }
    }

    public function exportExits(Request $request)
    {
        try {
            $records = TenantExit::completed()
                ->join('sys_pm_clients as tc', 'tc.id', '=', 'cb_tenant_exits.tenant_id')
                ->join('sys_pm_properties as p', 'p.id', '=', 'cb_tenant_exits.property_id')
                ->join('sys_pm_units as u', 'u.id', '=', 'cb_tenant_exits.unit_id')
                ->leftJoin(
                    DB::raw('(SELECT exit_id, SUM(refund_amount) as total_refund FROM cb_tenant_exit_deposit_refunds GROUP BY exit_id) as dr'),
                    'dr.exit_id',
                    '=',
                    'cb_tenant_exits.id'
                )
                ->leftJoin('josya_users as fu', 'fu.id', '=', 'cb_tenant_exits.finalised_by')
                ->select([
                    'cb_tenant_exits.actual_exit_date',
                    'tc.names as tenant_name',
                    'tc.reference_number as tenant_ref',
                    'p.property_name',
                    'u.unit_code',
                    'u.unit_name',
                    'cb_tenant_exits.exit_reason',
                    DB::raw('COALESCE(dr.total_refund, 0) as deposit_refunded'),
                    'fu.name as finalised_by',
                ])
                ->when($request->filled('property_id'), fn($q) => $q->where('cb_tenant_exits.property_id', $request->property_id))
                ->when($request->filled('exit_reason'), fn($q) => $q->where('cb_tenant_exits.exit_reason', $request->exit_reason))
                ->when($request->filled('date_from'), fn($q) => $q->whereDate('cb_tenant_exits.actual_exit_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn($q) => $q->whereDate('cb_tenant_exits.actual_exit_date', '<=', $request->date_to))
                ->orderBy('cb_tenant_exits.actual_exit_date', 'desc')
                ->get();

            $rows = $records->map(function ($r, $i) {
                return [
                    $i + 1,
                    $r->actual_exit_date ? \Carbon\Carbon::parse($r->actual_exit_date)->format('d/m/Y') : '-',
                    $r->tenant_name,
                    $r->tenant_ref ?? '-',
                    $r->property_name,
                    $r->unit_code . ' - ' . $r->unit_name,
                    $r->exit_reason ?? '-',
                    number_format($r->deposit_refunded ?? 0, 2),
                    $r->finalised_by ?? '-',
                ];
            })->toArray();

            return Excel::download(
                GenericReportExport::build(
                    title:     'Tenant Exit Report',
                    headings:  ['#', 'Exit Date', 'Tenant Name', 'Tenant Ref', 'Property', 'Unit', 'Reason', 'Deposit Refunded (KES)', 'Finalised By'],
                    dataRows:  $rows,
                    meta:      ['Generated: ' . now()->format('d/m/Y H:i:s')],
                    colWidths: ['A' => 5, 'B' => 13, 'C' => 26, 'D' => 14, 'E' => 26, 'F' => 22, 'G' => 22, 'H' => 22, 'I' => 20],
                ),
                'exits-' . now()->format('Ymd') . '.xlsx'
            );
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('PropertyReports@exportExits: ' . $e->getMessage());
            abort(500, 'Export failed.');
        }
    }
}
