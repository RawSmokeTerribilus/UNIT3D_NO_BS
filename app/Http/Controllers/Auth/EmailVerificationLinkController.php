<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\Group;
use App\Models\User;
use App\Services\Unit3dAnnounce;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class EmailVerificationLinkController extends Controller
{
    public function reveal(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return to_route('home.index');
        }

        return back()->with([
            'success'                 => 'Verification link generated below.',
            'verification_link'       => $this->verificationUrl($user),
            'verification_link_email' => $user->getEmailForVerification(),
        ]);
    }

    public function show(Request $request, int $id, string $hash): View|RedirectResponse
    {
        $user = $this->resolveUser($id, $hash);

        if ($user->hasVerifiedEmail()) {
            return to_route('login')
                ->with('success', trans('auth.activation-success'));
        }

        return view('auth.verify-email-link', [
            'confirmUrl' => $request->fullUrl(),
            'user'       => $user,
        ]);
    }

    public function store(int $id, string $hash): RedirectResponse
    {
        $user = $this->resolveUser($id, $hash)->load('group:id,slug');

        if (!$user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($user->group->slug === 'banned') {
            return to_route('login')
                ->withErrors(trans('auth.activation-error'));
        }

        if ($user->group->slug === 'validating') {
            $user->can_download = true;
            $user->group_id = Group::query()->where('slug', '=', 'user')->soleValue('id');
            $user->save();

            cache()->forget('user:'.$user->passkey);

            Unit3dAnnounce::addUser($user);
        }

        return to_route('login')
            ->with('success', trans('auth.activation-success'));
    }

    private function resolveUser(int $id, string $hash): User
    {
        $user = User::query()->findOrFail($id);

        abort_unless(
            hash_equals(sha1($user->getEmailForVerification()), $hash),
            403
        );

        return $user;
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.link.show',
            now()->addMinutes((int) config('auth.verification.expire', 1440)),
            [
                'id'   => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );
    }
}
