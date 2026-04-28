<?php

namespace App\Http\Controllers;

use Exception;
use App\Services\ErrorDispatcher;
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
}
