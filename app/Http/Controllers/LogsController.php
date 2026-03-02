<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogsController extends Controller
{

    public function index()
    {
        return view('activity.logs');
    }

    // AJAX endpoint to return filtered logs
    public function list(Request $request)
    {
        try {
            $query = DB::table('cb_audit_logs as a')
                // do not alias users table to avoid mismatch with DB prefixing
                ->leftJoin('users', 'users.id', '=', 'a.user_id')
                ->select(
                    'a.id',
                    'a.operation',
                    'a.table_name',
                    'a.old_data',
                    'a.new_data',
                    'a.user_id',
                    // reference the actual prefixed users table to avoid alias/prefix mismatch in raw SQL
                    DB::raw("COALESCE(\"" . DB::getTablePrefix() . "users\".\"username\", '') as causer_name"),
                    'a.created_at'
                );

            // filters
            if ($request->filled('table')) {
                $query->where('a.table_name', $request->input('table'));
            }

            if ($request->filled('causer')) {
                $query->where('a.user_id', $request->input('causer'));
            }

            if ($request->filled('from')) {
                // interpret from as start of day
                $from = Carbon::parse($request->input('from'))->startOfDay();
                $query->where('a.created_at', '>=', $from);
            }
            if ($request->filled('to')) {
                // interpret to as end of day (even if day not finished)
                $to = Carbon::parse($request->input('to'))->endOfDay();
                $query->where('a.created_at', '<=', $to);
            }

            if ($request->filled('q')) {
                $q = $request->input('q');
                // search in operation, old_data, new_data
                $query->where(function ($sub) use ($q) {
                    $sub->where('a.operation', 'ilike', "%{$q}%")
                        ->orWhereRaw("COALESCE(a.old_data::text, '') ILIKE ?", ["%{$q}%"])
                        ->orWhereRaw("COALESCE(a.new_data::text, '') ILIKE ?", ["%{$q}%"]);
                });
            }

            // return all rows to client-side DataTables (no server-side pagination)
            $results = $query->orderBy('a.created_at', 'desc')->get();
            $total = $results->count();

            // format rows for client
            $rows = $results->map(function ($r) {
                return [
                    'id' => $r->id,
                    'operation' => $r->operation,
                    'table_name' => $r->table_name,
                    'old_data' => $r->old_data,
                    'new_data' => $r->new_data,
                    'causer' => $r->causer_name ?: null,
                    'created_at' => $r->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'total' => $total,
                'page' => 1,
                'per_page' => $total,
                'data' => $rows,
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            // log and return JSON error for AJAX callers
            Log::error('Activity logs list error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'error' => 'Error loading activity logs: ' . $e->getMessage(),
            ], 500);
        }
    }
}
