<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Staff;
use App\Services\ErrorDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class SelfServiceController extends Controller
{
    public function myPayslip()
    {
        try {
            $link = auth()->user()->staffLink;

            if (!$link) {
                return view('self-service.my-payslip', [
                    'staffLinked' => false,
                    'staffId'     => null,
                    'staffName'   => null,
                ]);
            }

            return view('self-service.my-payslip', [
                'staffLinked' => true,
                'staffId'     => $link->staff_id,
                'staffName'   => $link->staff->full_names ?? auth()->user()->name,
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return redirect()->back()->with('error', 'Failed to load payslip page.');
        }
    }

    public function myLeave()
    {
        try {
            $link = auth()->user()->staffLink;

            if (!$link) {
                return view('self-service.my-leave', [
                    'staffLinked' => false,
                    'staffId'     => null,
                    'staffName'   => null,
                ]);
            }

            return view('self-service.my-leave', [
                'staffLinked' => true,
                'staffId'     => $link->staff_id,
                'staffName'   => $link->staff->full_names ?? auth()->user()->name,
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            return redirect()->back()->with('error', 'Failed to load leave page.');
        }
    }

     public function showSelf()
    {
        try {
            // Resolve the staff record linked to the authenticated user.
            $user = Auth::user();
    
            $staffLink = \App\Models\UserStaffLink::where('user_id', $user->id)->first();
    
            if (! $staffLink) {
                return redirect()->back()->with('error', 'No staff record is linked to your account.');
            }
    
            $staff = Staff::with([
                'department',
                'position',
                // Discipline cases visible to the employee (step >= 7 is filtered in the view,
                // but we still eager-load all cases so the collection is available).
                'disciplineCases' => function ($query) {
                    $query->with([
                        'incidentReports',
                        'showCauseNotice',
                        'employeeResponse',
                        'deadlineExtensions',
                        'hearings',
                        'outcome',
                    ])->orderBy('date_time', 'desc');
                },
                'incidentReports',
                'investigationsAssigned',
                'witnessStatements',
            ])->findOrFail($staffLink->staff_id);
    
            return view('self-service.my-disciplinary-cases', compact('staff'));
    
        } catch (\Exception $e) {
            Log::error('Error loading self disciplinary page: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load your disciplinary records.');
        }
    }
}
