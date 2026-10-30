<?php

namespace App\Http\Controllers\Couple;

use App\Exceptions\CoupleException;
use App\Http\Controllers\Controller;
use App\Services\Couple\CoupleSpaceService;
use App\Services\Couple\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CoupleSpaceController extends Controller
{
    public function store(Request $request, CoupleSpaceService $spaces, InvitationService $invitations): RedirectResponse
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:60']]);

        try {
            $spaces->create($request->user(), trim((string) ($data['name'] ?? '')));
            $issued = $invitations->issue($request->user()->refresh());
        } catch (CoupleException $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }

        InvitationController::rememberCode($request, $issued);

        return redirect()->route('couple.invite')->with('success', 'Ruang kalian sudah siap.');
    }
}
