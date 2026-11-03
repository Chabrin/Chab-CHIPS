<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffPasswordController extends Controller
{
    public function edit()
    {
        // Keep existing reminder links working while using the My Account form.
        return redirect()->route('user.profile', ['tab' => 'security']);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $user = $request->user();
        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }
        $user->password = $data['password'];
        $user->requireReset = false;
        $user->save();
        return redirect()->route('user.profile', ['tab' => 'security'])->with('success', 'Password updated successfully.');
    }
}
