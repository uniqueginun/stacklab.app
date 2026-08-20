<?php

namespace App\Http\Controllers;

use App\Actions\GitHub\ConnectGitHub;
use App\Actions\GitHub\DisconnectGitHub;
use App\Models\GitHubConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VersionControlProviderController extends Controller
{
    public function redirect(Request $request, string $provider): Response
    {
        $this->ensureGitHub($provider);

        Gate::authorize('create', GitHubConnection::class);

        $this->rememberIntendedUrl($request);

        $driver = Socialite::driver('github');

        if ($driver instanceof AbstractProvider) {
            $driver->scopes(['repo', 'admin:public_key', 'read:user']);
        }

        $redirect = $driver->redirect();

        if ($request->header('X-Inertia')) {
            return Inertia::location($redirect->getTargetUrl());
        }

        return $redirect;
    }

    public function callback(Request $request, string $provider, ConnectGitHub $connectGitHub): RedirectResponse
    {
        $this->ensureGitHub($provider);

        Gate::authorize('create', GitHubConnection::class);

        try {
            $githubUser = Socialite::driver('github')->user();

            if (! $githubUser instanceof SocialiteUser) {
                throw new \RuntimeException('GitHub did not return an OAuth user.');
            }

            $connectGitHub->handle($request->user(), $githubUser);
        } catch (Throwable) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Unable to connect GitHub. Please try again.',
            ]);

            return redirect()->intended(route('dashboard'));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'GitHub connected.',
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, string $provider, DisconnectGitHub $disconnectGitHub): RedirectResponse
    {
        $this->ensureGitHub($provider);

        $connection = $request->user()->githubConnection;

        if ($connection !== null) {
            Gate::authorize('delete', $connection);
        }

        $disconnectGitHub->handle($request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'GitHub disconnected.',
        ]);

        return back();
    }

    private function ensureGitHub(string $provider): void
    {
        abort_unless($provider === 'github', 404);
    }

    private function rememberIntendedUrl(Request $request): void
    {
        $candidate = $this->safeInternalUrl($request->query('return'))
            ?? $this->safeInternalUrl(url()->previous());

        if ($candidate === null) {
            return;
        }

        $request->session()->put('url.intended', $candidate);
    }

    private function safeInternalUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && (str_starts_with($url, $appUrl.'/') || $url === $appUrl)) {
            return $url;
        }

        return null;
    }
}
