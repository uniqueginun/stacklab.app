export type SiteCreateServer = {
    uuid: string;
    name: string;
    host: string;
    os_label: string | null;
};

export type SiteStatus = 'pending' | 'deploying' | 'deployed' | 'failed';

export type DeploymentOptions = {
    run_composer: boolean;
    run_npm: boolean;
    run_migrations: boolean;
    run_caches: boolean;
    run_queue_restart: boolean;
    run_hook: boolean;
};

export type SiteIndex = {
    uuid: string;
    domain: string;
    type: string;
    status: SiteStatus;
    status_label: string;
    web_directory: string | null;
    repository_url: string | null;
    repository_branch: string | null;
    server: {
        uuid: string;
        name: string;
    };
};

export type SiteShow = SiteIndex & {
    server: {
        uuid: string;
        name: string;
        host: string;
    };
    is_laravel: boolean;
    is_php: boolean;
    deployment_options: DeploymentOptions | null;
    php_version: string | null;
    root_path: string | null;
    last_deployed_at: string | null;
    created_at: string | null;
    can_manage_ssl: boolean;
    can_manage_queues: boolean;
    has_active_ssl: boolean;
    url: string;
    can_include_www: boolean;
    current_release: {
        uuid: string;
        commit_sha: string;
        short_sha: string;
        commit_message: string | null;
    } | null;
};

export type QueueWorkerStatus =
    | 'pending'
    | 'installing'
    | 'installed'
    | 'updating'
    | 'restarting'
    | 'deleting'
    | 'failed';

export type QueueWorker = {
    uuid: string;
    name: string;
    connection: string;
    queue: string;
    php_version: string;
    processes: number;
    sleep: number;
    timeout: number;
    tries: number;
    backoff: number;
    max_jobs: number;
    max_time: number;
    stopwaitsecs: number;
    restart_on_deploy: boolean;
    status: QueueWorkerStatus;
    status_label: string;
    failure_message: string | null;
    installed_at: string | null;
    created_at: string | null;
};

export type QueueWorkerDefaults = {
    connection: string;
    queue: string;
    processes: number;
    sleep: number;
    timeout: number;
    tries: number;
    backoff: number;
    max_jobs: number;
    max_time: number;
    stopwaitsecs: number;
    restart_on_deploy: boolean;
};

export type QueueWorkerRuntime = {
    configured_processes: number;
    running_processes: number;
    states: Record<string, number>;
    healthy: boolean;
    checked_at: string;
    missing: boolean;
};

export type QueueWorkerStatusResponse = {
    workers: Record<string, QueueWorkerRuntime>;
    error: string | null;
};

export type QueueWorkerLogsResponse = {
    output: string;
    truncated: boolean;
};

export type SiteCertificateType = 'letsencrypt' | 'existing' | 'csr';

export type SiteCertificateStatus =
    | 'pending'
    | 'awaiting_certificate'
    | 'active'
    | 'failed';

export type SiteCertificate = {
    uuid: string;
    type: SiteCertificateType;
    type_label: string;
    status: SiteCertificateStatus;
    status_label: string;
    domains: string[];
    csr: string | null;
    expires_at: string | null;
    failure_message: string | null;
    activated_at: string | null;
    created_at: string | null;
};

export type SiteRelease = {
    uuid: string;
    commit_sha: string;
    short_sha: string;
    commit_message: string | null;
    status: string;
    status_label: string;
    activated_at: string | null;
    created_at: string | null;
    is_current: boolean;
    can_rollback: boolean;
};

export type GitHubAccount = {
    connected: boolean;
    username: string | null;
};

export type SiteEnvironmentFile = {
    contents: string | null;
    path: string;
};

export type SiteCommandResult = {
    command: string;
    working_directory: string;
    exit_code: number;
    output: string;
};

export type GitHubRepository = {
    id: number;
    full_name: string;
    private: boolean;
    default_branch: string;
};

export type GitHubBranch = {
    name: string;
};

export type SiteRepositoryCatalog = {
    githubConnected: boolean;
    repository: string | null;
    branch: string | null;
    repositories: GitHubRepository[];
    branches: GitHubBranch[];
};
