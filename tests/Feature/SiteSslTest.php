<?php

use App\Enums\SiteCertificateStatus;
use App\Enums\SiteCertificateType;
use App\Enums\SiteStatus;
use App\Jobs\ProcessOperation;
use App\Models\GitHubConnection;
use App\Models\Operation;
use App\Models\Server;
use App\Models\Site;
use App\Models\SiteCertificate;
use App\Models\User;
use App\Ssh\SshResult;
use App\Ssh\SshService;
use App\Support\RecipeRunner;
use App\Support\StepAftermathRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function sslSite(?User $user = null, array $attributes = []): array
{
    $user ??= User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->deployed()->create([
        'domain' => 'stacklab.app',
        'root_path' => '/home/forge/stacklab.app',
        'php_version' => '8.4',
        'web_directory' => '/public',
        ...$attributes,
    ]);

    return [$user, $server, $site];
}

function pemCertificate(): string
{
    return "-----BEGIN CERTIFICATE-----\nMIIBcertificate\n-----END CERTIFICATE-----\n";
}

function pemPrivateKey(): string
{
    return "-----BEGIN PRIVATE KEY-----\nMIIBprivate\n-----END PRIVATE KEY-----\n";
}

test('guests cannot view the ssl tab', function () {
    $site = Site::factory()->create();

    $this->get(route('sites.ssl', $site))
        ->assertRedirect(route('login'));
});

test('a user cannot view another users ssl tab', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->get(route('sites.ssl', $site))
        ->assertForbidden();
});

test('the owner can view the ssl tab', function () {
    [$user, $server, $site] = sslSite();

    $this->actingAs($user)
        ->get(route('sites.ssl', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('tab', 'ssl')
            ->where('site.uuid', $site->uuid)
            ->where('site.can_manage_ssl', true)
            ->where('site.has_active_ssl', false)
            ->where('certificate', null)
            ->where('operation', null)
        );
});

test('the site detail page uses https when ssl is active', function () {
    [$user, $server, $site] = sslSite();
    SiteCertificate::factory()->for($site)->letsEncrypt()->active()->create();

    $this->actingAs($user)
        ->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('site.url', 'https://stacklab.app')
            ->where('site.has_active_ssl', true)
        );
});

test('ssl routes are bound by site uuid', function () {
    [$user, $server, $site] = sslSite();

    $this->actingAs($user)
        ->get('/sites/'.$site->id.'/ssl')
        ->assertNotFound();
});

test('an undeployed site cannot obtain a lets encrypt certificate', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create([
        'status' => SiteStatus::PENDING,
        'domain' => 'stacklab.app',
        'root_path' => '/home/forge/stacklab.app',
    ]);

    $this->actingAs($user)
        ->from(route('sites.ssl', $site))
        ->post(route('sites.ssl.letsencrypt', $site))
        ->assertRedirect(route('sites.ssl', $site))
        ->assertSessionHasErrors(['site']);
});

test('the owner can start a lets encrypt certificate', function () {
    Queue::fake();

    [$user, $server, $site] = sslSite();

    $this->actingAs($user)
        ->post(route('sites.ssl.letsencrypt', $site), [
            'include_www' => true,
        ])
        ->assertRedirect(route('sites.ssl', $site));

    $certificate = SiteCertificate::query()->first();
    $operation = $server->operations()->where('type', 'ssl')->first();

    expect($certificate)->not->toBeNull()
        ->and($certificate->type)->toBe(SiteCertificateType::LETS_ENCRYPT)
        ->and($certificate->status)->toBe(SiteCertificateStatus::PENDING)
        ->and($certificate->domains)->toBe(['stacklab.app', 'www.stacklab.app'])
        ->and($operation)->not->toBeNull()
        ->and($operation->steps->pluck('recipe')->all())->toBe([
            'ssl.certbot.install@v1',
            'ssl.letsencrypt.obtain@v1',
        ])
        ->and($operation->steps->last()->arguments)->not->toHaveKey('ssl_private_key_b64')
        ->and($operation->steps->last()->arguments['certificate_id'])->toBe($certificate->id)
        ->and($operation->steps->last()->arguments['letsencrypt_email'])->toBe($user->email)
        ->and(base64_decode((string) $operation->steps->last()->arguments['nginx_ssl_config_b64']))
        ->toContain('listen 443 ssl http2')
        ->toContain('ssl_certificate /etc/letsencrypt/live/stacklab.app/fullchain.pem');

    Queue::assertPushed(ProcessOperation::class, fn (ProcessOperation $job): bool => $job->operationId === $operation->id);
});

test('an existing certificate is stored encrypted and not copied into step arguments', function () {
    Queue::fake();

    [$user, $server, $site] = sslSite();

    $this->actingAs($user)
        ->post(route('sites.ssl.existing', $site), [
            'certificate' => pemCertificate(),
            'private_key' => pemPrivateKey(),
            'chain' => pemCertificate(),
        ])
        ->assertRedirect(route('sites.ssl', $site));

    $certificate = SiteCertificate::query()->first();
    $operation = $server->operations()->where('type', 'ssl')->first();

    expect($certificate)->not->toBeNull()
        ->and($certificate->type)->toBe(SiteCertificateType::EXISTING)
        ->and($certificate->certificate)->toBe(pemCertificate())
        ->and($certificate->private_key)->toBe(pemPrivateKey())
        ->and($operation->steps->pluck('recipe')->all())->toBe(['ssl.existing.install@v1'])
        ->and($operation->steps->first()->arguments)->not->toHaveKey('ssl_private_key_b64')
        ->and($operation->steps->first()->arguments)->not->toHaveKey('ssl_certificate_b64')
        ->and($operation->steps->first()->arguments['certificate_id'])->toBe($certificate->id);
});

test('existing certificate pem must be valid', function () {
    [$user, $server, $site] = sslSite();

    $this->actingAs($user)
        ->from(route('sites.ssl', $site))
        ->post(route('sites.ssl.existing', $site), [
            'certificate' => 'not-a-cert',
            'private_key' => 'not-a-key',
        ])
        ->assertRedirect(route('sites.ssl', $site))
        ->assertSessionHasErrors(['certificate', 'private_key']);
});

test('the owner can generate a csr', function () {
    Queue::fake();

    [$user, $server, $site] = sslSite();

    $this->actingAs($user)
        ->post(route('sites.ssl.csr', $site), [
            'country' => 'us',
            'state' => 'California',
            'locality' => 'San Francisco',
            'organization' => 'Stacklab',
        ])
        ->assertRedirect(route('sites.ssl', $site));

    $certificate = SiteCertificate::query()->first();
    $operation = $server->operations()->where('type', 'ssl')->first();

    expect($certificate->type)->toBe(SiteCertificateType::CSR)
        ->and($operation->steps->pluck('recipe')->all())->toBe(['ssl.csr.generate@v1'])
        ->and($operation->steps->first()->arguments['csr_country'])->toBe('US')
        ->and($operation->steps->first()->arguments['csr_common_name'])->toBe('stacklab.app');
});

test('a csr install does not require a private key', function () {
    Queue::fake();

    [$user, $server, $site] = sslSite();
    $certificate = SiteCertificate::factory()->for($site)->awaitingCertificate()->create([
        'domains' => [$site->domain],
    ]);

    $this->actingAs($user)
        ->post(route('sites.ssl.install', [$site, $certificate]), [
            'certificate' => pemCertificate(),
        ])
        ->assertRedirect(route('sites.ssl', $site));

    $operation = $server->operations()->where('type', 'ssl')->first();

    expect($certificate->fresh()->status)->toBe(SiteCertificateStatus::PENDING)
        ->and($operation->steps->pluck('recipe')->all())->toBe(['ssl.csr.install@v1'])
        ->and($operation->steps->first()->arguments)->not->toHaveKey('ssl_private_key_b64');
});

test('a site cannot start ssl while another server operation is running', function () {
    [$user, $server, $site] = sslSite();
    $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'provision',
        'status' => 'running',
        'plan_snapshot' => [],
    ]);

    $this->actingAs($user)
        ->from(route('sites.ssl', $site))
        ->post(route('sites.ssl.letsencrypt', $site))
        ->assertRedirect(route('sites.ssl', $site))
        ->assertSessionHasErrors(['site']);
});

test('a user cannot obtain ssl for another users site', function () {
    $user = User::factory()->create();
    $site = Site::factory()->deployed()->create();

    $this->actingAs($user)
        ->post(route('sites.ssl.letsencrypt', $site))
        ->assertForbidden();
});

test('a successful lets encrypt operation activates the certificate', function () {
    [$user, $server, $site] = sslSite();
    $certificate = SiteCertificate::factory()->for($site)->letsEncrypt()->create([
        'domains' => [$site->domain],
        'status' => SiteCertificateStatus::PENDING,
    ]);
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'ssl',
        'plan_snapshot' => [
            'site_id' => $site->id,
            'certificate_id' => $certificate->id,
        ],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => "Obtain Let's Encrypt certificate",
        'recipe' => 'ssl.letsencrypt.obtain@v1',
        'aftermath' => 'finalize_ssl',
        'status' => 'pending',
        'arguments' => [
            'certificate_id' => $certificate->id,
            'domain' => $site->domain,
        ],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(0, json_encode([
            'step_key' => 'ssl.letsencrypt.obtain',
            'success' => true,
            'changed' => true,
            'data' => ['expires_at' => '2026-11-29T12:00:00Z'],
            'error' => ['code' => null, 'message' => null, 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    $certificate->refresh();

    expect($certificate->status)->toBe(SiteCertificateStatus::ACTIVE)
        ->and($certificate->expires_at?->equalTo(Carbon::parse('2026-11-29T12:00:00Z')))->toBeTrue()
        ->and($operation->fresh()->status)->toBe('succeeded')
        ->and($site->fresh()->hasActiveSsl())->toBeTrue();
});

test('a successful csr generate stores the signing request', function () {
    [$user, $server, $site] = sslSite();
    $certificate = SiteCertificate::factory()->for($site)->csr()->create([
        'domains' => [$site->domain],
        'status' => SiteCertificateStatus::PENDING,
    ]);
    $csr = "-----BEGIN CERTIFICATE REQUEST-----\nMIIBcsr\n-----END CERTIFICATE REQUEST-----";
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'ssl',
        'plan_snapshot' => [
            'site_id' => $site->id,
            'certificate_id' => $certificate->id,
        ],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Generate CSR',
        'recipe' => 'ssl.csr.generate@v1',
        'aftermath' => 'finalize_ssl',
        'status' => 'pending',
        'arguments' => [
            'certificate_id' => $certificate->id,
            'domain' => $site->domain,
        ],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(0, json_encode([
            'step_key' => 'ssl.csr.generate',
            'success' => true,
            'changed' => true,
            'data' => ['csr_b64' => base64_encode($csr)],
            'error' => ['code' => null, 'message' => null, 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    expect($certificate->fresh()->status)->toBe(SiteCertificateStatus::AWAITING_CERTIFICATE)
        ->and($certificate->fresh()->csr)->toBe($csr);
});

test('a csr install normalizes windows certificate pem', function () {
    Queue::fake();

    [$user, $server, $site] = sslSite();
    $certificate = SiteCertificate::factory()->for($site)->awaitingCertificate()->create([
        'domains' => [$site->domain],
    ]);
    $body = str_repeat('A', 80);
    $messy = "-----BEGIN CERTIFICATE-----\r\n{$body}\r\n-----END CERTIFICATE-----";

    $this->actingAs($user)
        ->post(route('sites.ssl.install', [$site, $certificate]), [
            'certificate' => $messy,
        ])
        ->assertRedirect(route('sites.ssl', $site))
        ->assertSessionDoesntHaveErrors();

    $stored = $certificate->fresh()->certificate;

    expect($stored)->not->toContain("\r")
        ->and($stored)->toContain(str_repeat('A', 64))
        ->and($stored)->toEndWith("-----END CERTIFICATE-----\n");
});

test('a failed csr install returns to awaiting the signed certificate', function () {
    [$user, $server, $site] = sslSite();
    $certificate = SiteCertificate::factory()->for($site)->awaitingCertificate()->create([
        'domains' => [$site->domain],
        'status' => SiteCertificateStatus::PENDING,
    ]);
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'ssl',
        'plan_snapshot' => [
            'site_id' => $site->id,
            'certificate_id' => $certificate->id,
        ],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Install signed certificate',
        'recipe' => 'ssl.csr.install@v1',
        'aftermath' => 'finalize_ssl',
        'status' => 'pending',
        'arguments' => [
            'certificate_id' => $certificate->id,
            'domain' => $site->domain,
        ],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(1, json_encode([
            'step_key' => 'ssl.csr.install',
            'success' => false,
            'changed' => false,
            'data' => [],
            'error' => ['code' => 'nginx_test_failed', 'message' => 'Nginx configuration failed validation.', 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    expect($certificate->fresh()->status)->toBe(SiteCertificateStatus::AWAITING_CERTIFICATE)
        ->and($certificate->fresh()->failure_message)->toBe('Nginx configuration failed validation.')
        ->and($operation->fresh()->status)->toBe('failed');
});

test('a failed ssl operation stores the failure message', function () {
    [$user, $server, $site] = sslSite();
    $certificate = SiteCertificate::factory()->for($site)->create([
        'domains' => [$site->domain],
    ]);
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'ssl',
        'plan_snapshot' => [
            'site_id' => $site->id,
            'certificate_id' => $certificate->id,
        ],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => "Obtain Let's Encrypt certificate",
        'recipe' => 'ssl.letsencrypt.obtain@v1',
        'aftermath' => 'finalize_ssl',
        'status' => 'pending',
        'arguments' => [
            'certificate_id' => $certificate->id,
        ],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(1, json_encode([
            'step_key' => 'ssl.letsencrypt.obtain',
            'success' => false,
            'changed' => false,
            'data' => [],
            'error' => ['code' => 'letsencrypt_failed', 'message' => 'DNS does not point at this server.', 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    expect($certificate->fresh()->status)->toBe(SiteCertificateStatus::FAILED)
        ->and($certificate->fresh()->failure_message)->toBe('DNS does not point at this server.')
        ->and($operation->fresh()->status)->toBe('failed');
});

test('existing certificate materials are injected at runtime then wiped after success', function () {
    [$user, $server, $site] = sslSite();
    $certificate = SiteCertificate::factory()->for($site)->existing()->create([
        'domains' => [$site->domain],
        'certificate' => pemCertificate(),
        'private_key' => pemPrivateKey(),
        'chain' => pemCertificate(),
    ]);
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'ssl',
        'plan_snapshot' => [
            'site_id' => $site->id,
            'certificate_id' => $certificate->id,
        ],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Install existing certificate',
        'recipe' => 'ssl.existing.install@v1',
        'aftermath' => 'finalize_ssl',
        'status' => 'pending',
        'arguments' => [
            'certificate_id' => $certificate->id,
            'domain' => $site->domain,
        ],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->withArgs(function ($sshServer, $host, string $script): bool {
            expect($script)->toContain("export MF_SSL_CERTIFICATE_B64='".base64_encode(pemCertificate())."'")
                ->and($script)->toContain("export MF_SSL_PRIVATE_KEY_B64='".base64_encode(pemPrivateKey())."'");

            return true;
        })
        ->andReturn(new SshResult(0, json_encode([
            'step_key' => 'ssl.existing.install',
            'success' => true,
            'changed' => true,
            'data' => ['expires_at' => '2027-01-01T00:00:00Z'],
            'error' => ['code' => null, 'message' => null, 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    $certificate->refresh();

    expect($certificate->status)->toBe(SiteCertificateStatus::ACTIVE)
        ->and($certificate->certificate)->toBeNull()
        ->and($certificate->private_key)->toBeNull()
        ->and($certificate->chain)->toBeNull();
});

test('deleting an active certificate queues nginx rollback', function () {
    Queue::fake();

    [$user, $server, $site] = sslSite();
    $certificate = SiteCertificate::factory()->for($site)->letsEncrypt()->active()->create([
        'domains' => [$site->domain],
    ]);

    $this->actingAs($user)
        ->delete(route('sites.ssl.destroy', [$site, $certificate]))
        ->assertRedirect(route('sites.ssl', $site));

    $operation = $server->operations()->where('type', 'ssl')->first();

    expect($operation)->not->toBeNull()
        ->and($operation->steps->pluck('recipe')->all())->toBe(['ssl.deactivate@v1'])
        ->and($certificate->fresh()->status)->toBe(SiteCertificateStatus::ACTIVE)
        ->and(base64_decode((string) $operation->steps->first()->arguments['nginx_http_config_b64']))
        ->toContain('listen 80')
        ->not->toContain('listen 443');
});

test('a deploy includes the 443 server block when ssl is active', function () {
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/*' => Http::response([
            'sha' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'commit' => ['message' => 'Ship it'],
        ]),
    ]);

    [$user, $server, $site] = sslSite(attributes: [
        'repository_url' => 'octocat/hello',
        'repository_id' => 1,
        'repository_branch' => 'main',
    ]);
    GitHubConnection::factory()->for($user)->create();
    SiteCertificate::factory()->for($site)->letsEncrypt()->active()->create([
        'domains' => [$site->domain],
    ]);

    $this->actingAs($user)
        ->post(route('sites.deployments.store', $site))
        ->assertRedirect(route('sites.deployments', $site));

    $operation = Operation::query()->where('type', 'deploy')->first();
    $nginx = base64_decode((string) $operation->steps->first()->arguments['nginx_config_b64']);

    expect($nginx)
        ->toContain('listen 443 ssl http2')
        ->toContain('return 301 https://$host$request_uri')
        ->toContain('/.well-known/acme-challenge/')
        ->toContain('ssl_certificate /etc/letsencrypt/live/stacklab.app/fullchain.pem');
});
