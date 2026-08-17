export type ServerStatus = 'connected' | 'provisioning';
export type SiteStatus = 'deployed' | 'deploying';

export type Server = {
    slug: string;
    name: string;
    ip: string;
    provider: string;
    region: string;
    size: string;
    vcpu: string;
    ram: string;
    disk: string;
    sitesCount: number;
    status: ServerStatus;
    os: string;
    php: string;
    database: string;
};

export type Site = {
    slug: string;
    domain: string;
    repository: string;
    branch: string;
    stack: string;
    commit: string;
    status: SiteStatus;
    serverSlug: string;
};

export const servers: Server[] = [
    {
        slug: 'fragrant-forest',
        name: 'fragrant-forest',
        ip: '159.203.44.12',
        provider: 'DigitalOcean',
        region: 'New York 1',
        size: 'Small',
        vcpu: '1 vCPU',
        ram: '1 GB RAM',
        disk: '25 GB Disk',
        sitesCount: 2,
        status: 'connected',
        os: 'Ubuntu 24.04',
        php: 'PHP 8.4',
        database: 'MySQL 8',
    },
    {
        slug: 'quiet-meadow',
        name: 'quiet-meadow',
        ip: '104.248.19.87',
        provider: 'Hetzner',
        region: 'Falkenstein 1',
        size: 'Medium',
        vcpu: '2 vCPU',
        ram: '4 GB RAM',
        disk: '80 GB Disk',
        sitesCount: 0,
        status: 'provisioning',
        os: 'Ubuntu 24.04',
        php: 'PHP 8.4',
        database: 'MySQL 8',
    },
    {
        slug: 'amber-canyon',
        name: 'amber-canyon',
        ip: '51.222.10.4',
        provider: 'AWS',
        region: 'San Francisco 3',
        size: 'Large',
        vcpu: '4 vCPU',
        ram: '8 GB RAM',
        disk: '160 GB Disk',
        sitesCount: 5,
        status: 'connected',
        os: 'Ubuntu 24.04',
        php: 'PHP 8.4',
        database: 'MySQL 8',
    },
];

export const sites: Site[] = [
    {
        slug: 'chirper',
        domain: 'chirper.on-stacklab.app',
        repository: '1005hoon/chirper',
        branch: 'main',
        stack: 'Laravel',
        commit: '48dc5d5',
        status: 'deploying',
        serverSlug: 'fragrant-forest',
    },
    {
        slug: 'zts',
        domain: 'zts-pm62gt8i.on-stacklab.app',
        repository: '1005hoon/zts',
        branch: 'main',
        stack: 'Laravel',
        commit: 'a19f0c3',
        status: 'deployed',
        serverSlug: 'fragrant-forest',
    },
];

export const serverSizes = [
    {
        id: 'small',
        name: 'Small',
        specs: '1 vCPU · 1 GB RAM · 25 GB Disk · Instant',
        price: '$6/mo',
    },
    {
        id: 'medium',
        name: 'Medium',
        specs: '2 vCPU · 4 GB RAM · 80 GB Disk · Instant',
        price: '$25/mo',
    },
    {
        id: 'large',
        name: 'Large',
        specs: '4 vCPU · 8 GB RAM · 160 GB Disk · Instant',
        price: '$50/mo',
    },
    {
        id: 'xlarge',
        name: 'X Large',
        specs: '8 vCPU · 16 GB RAM · 320 GB Disk · Instant',
        price: '$100/mo',
    },
];

export const siteTypes = [
    {
        category: 'PHP',
        items: [
            'Laravel',
            'Symfony',
            'Statamic',
            'WordPress',
            'phpMyAdmin',
            'PHP',
        ],
    },
    { category: 'JavaScript', items: ['Next.js', 'Nuxt.js'] },
    { category: 'Static', items: ['HTML'] },
] as const;

export const currentServer = servers[0];
export const currentSite = sites[0];
