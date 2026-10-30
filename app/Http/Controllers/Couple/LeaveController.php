<?php

namespace App\Http\Controllers\Couple;

use App\Exceptions\CoupleException;
use App\Http\Controllers\Controller;
use App\Notifications\CoupleEnded;
use App\Services\Couple\CoupleSpaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function destroy(Request $request, CoupleSpaceService $spaces): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', 'in:KELUAR']], [
            'confirmation.in' => 'Ketik KELUAR (huruf besar) untuk memastikan.',
            'confirmation.required' => 'Ketik KELUAR (huruf besar) untuk memastikan.',
        ]);

        $user = $request->user();
        try {
            $others = $spaces->dissolve($user);
        } catch (CoupleException $e) {
            return back()->withErrors(['confirmation' => $e->getMessage()]);
        }

        foreach ($others as $other) {
            $other->notify(new CoupleEnded($user->firstName()));
        }

        return redirect()->route('onboarding')->with('success', 'Ruang pasangan diakhiri. Data pribadimu tetap ada.');
    }
}
