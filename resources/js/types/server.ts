export type ConnectionStatus =
    'unverified' | 'pending_confirmation' | 'connected' | 'failed';

export type ServerProvider = 'digitalocean' | 'custom';

export type ServerIndex = {
    uuid: string;
    name: string;
    provider: ServerProvider;
    provider_label: string;
    host: string;
    connection_status: ConnectionStatus;
    connection_status_label: string;
};

export type ServerShow = ServerIndex & {
    ssh_port: string;
    ssh_user: string;
    ssh_public_key: string | null;
    is_connected: boolean;
};
