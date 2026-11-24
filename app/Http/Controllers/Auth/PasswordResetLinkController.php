<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the password reset link request page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            // Deactivated accounts get no link (they couldn't sign in anyway); the
            // reply stays the same so it doesn't reveal the account's state.
            Password::sendResetLink(
                $request->only('email'),
                fn (User $user, string $token) => $user->isActive()
                    ? $user->sendPasswordResetNotification($token)
                    : null,
            );
        } catch (TransportExceptionInterface $e) {
            report($e);

            throw ValidationException::withMessages([
                'email' => "L'e-mail n'a pas pu être envoyé pour le moment. Réessayez plus tard ou contactez votre administrateur.",
            ]);
        }

        return back()->with('status', __('A reset link will be sent if the account exists.'));
    }
}
