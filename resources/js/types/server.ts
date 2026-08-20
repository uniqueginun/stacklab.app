export type ConnectionStatus =
    'unverified' | 'pending_confirmation' | 'connected' | 'failed';

export type ServerProvider = 'digitalocean' | 'custom';

export type ProvisioningProfile = 'php' | 'static';

export type OperationStatus = 'pending' | 'running' | 'succeeded' | 'failed';

export type OperationStepStatus =
    'pending' | 'running' | 'succeeded' | 'failed';

export type ServerIndex = {
    uuid: string;
    name: string;
    provider: ServerProvider;
    provider_label: string;
    host: string;
    connection_status: ConnectionStatus;
    connection_status_label: string;
};

export type ProvisioningProfileOption = {
    key: ProvisioningProfile;
    label: string;
    description: string;
    requires_php: boolean;
    requires_mysql: boolean;
};

export type OperationStep = {
    id: number;
    position: number;
    name: string;
    recipe: string;
    status: OperationStepStatus;
    error_message: string | null;
    output: string | null;
};

export type ServerOperation = {
    uuid: string;
    type: string;
    status: OperationStatus;
    failure_message: string | null;
    started_at: string | null;
    finished_at: string | null;
    steps: OperationStep[];
};

export type ServerShow = ServerIndex & {
    ssh_port: string;
    ssh_user: string;
    ssh_public_key: string | null;
    is_connected: boolean;
    is_provisioned: boolean;
    can_provision: boolean;
    profile: ProvisioningProfile | null;
    os_label: string | null;
    php_versions: string[];
    default_php_version: string;
    php_hint: string | null;
    mysql_versions: string[];
    default_mysql_version: string;
    has_mysql: boolean;
};

export type ServerDatabaseStatus = 'pending' | 'ready' | 'failed';

export type ServerDatabase = {
    uuid: string;
    name: string;
    username: string;
    password: string;
    status: ServerDatabaseStatus;
    failure_message: string | null;
    created_at: string | null;
};

export type ServerShowTab = 'overview' | 'databases';
