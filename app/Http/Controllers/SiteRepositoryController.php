<?php

namespace App\Http\Controllers;

use App\Actions\Sites\AttachSiteRepository;
use App\Http\Requests\AttachSiteRepositoryRequest;
use App\Models\Site;
use App\Support\GitHubApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Throwable;

class SiteRepositoryController extends Controller
{
    public function edit(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site);

        $connection = $request->user()->githubConnection;
        $repositories = [];
        $branches = [];

        if ($connection !== null) {
            $api = new GitHubApi($connection);

            try {
                $repositories = $api->repositories();
            } catch (Throwable) {
                $repositories = [];
            }

            $selected = $request->query('repository');

            if (is_string($selected) && preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $selected) === 1) {
                try {
                    [$owner, $repo] = explode('/', $selected, 2);
                    $branches = $api->branches($owner, $repo);
                } catch (Throwable) {
                    $branches = [];
                }
            }
        }

        return response()->json([
            'githubConnected' => $connection !== null,
            'repository' => $site->repository_url,
            'branch' => $site->repository_branch,
            'repositories' => $repositories,
            'branches' => $branches,
        ]);
    }

    public function update(
        AttachSiteRepositoryRequest $request,
        Site $site,
        AttachSiteRepository $attachSiteRepository,
    ): RedirectResponse {
        $attachSiteRepository->handle($request->user(), $site, $request->repositoryAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Repository attached.',
        ]);

        return to_route('sites.source', $site);
    }
}
