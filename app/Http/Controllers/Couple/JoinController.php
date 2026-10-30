<?php

namespace App\Http\Controllers\Couple;

use App\Exceptions\CoupleException;
use App\Http\Controllers\Controller;
use App\Services\Couple\InvitationService;
use App\Support\InviteCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JoinController extends Controller
{
    private const CODE_RULES = ['required', 'string', 'max:20'];

    /**
     * Step 1: check the code and ask for confirmation. Shows only the inviter's
     * first name and the space name, never an email address.
     */
    public function preview(Request $request, InvitationService $invitations): Response|RedirectResponse
    {
        $code = $request->validate(['code' => self::CODE_RULES])['code'];

        try {
            $invitation = $invitations->preview($request->user(), $code);
        } catch (CoupleException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return Inertia::render('Onboarding/JoinConfirm', [
            'code' => InviteCode::format(InviteCode::normalize($code)),
            'inviter' => $invitation->creator?->firstName() ?? 'Pasanganmu',
            'spaceName' => $invitation->space->name,
        ]);
    }

    /** Step 2: the user confirmed; link the accounts. */
    public function store(Request $request, InvitationService $invitations): RedirectResponse
    {
        $code = $request->validate(['code' => self::CODE_RULES])['code'];

        try {
            $invitations->accept($request->user(), $code);
        } catch (CoupleException $e) {
            return redirect()->route('onboarding')->withErrors(['code' => $e->getMessage()]);
        }

        return redirect()->route('dashboard')->with('success', 'Kalian sudah terhubung. Selamat datang di Our Space.');
    }
}
