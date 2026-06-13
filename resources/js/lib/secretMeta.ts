/**
 * Single source of truth for secret-vault type options and styling.
 * Token-based and dark-mode safe, matching projectMeta conventions.
 */

export interface SecretTypeOption {
    value: string;
    label: string;
}

export const secretTypes: SecretTypeOption[] = [
    { value: 'db_password', label: 'DB Password' },
    { value: 'api_token', label: 'API Token' },
    { value: 'redis', label: 'Redis' },
    { value: 'ssh_key', label: 'SSH Key' },
    { value: 'env_file', label: 'Env File' },
    { value: 'json', label: 'JSON' },
    { value: 'other', label: 'Other' },
];

export function secretTypeLabel(type: string): string {
    return secretTypes.find((t) => t.value === type)?.label
        ?? (type ? type.charAt(0).toUpperCase() + type.slice(1).replace(/_/g, ' ') : 'Other');
}

export function secretTypeBadge(type: string): string {
    switch (type) {
        case 'db_password':
            return 'bg-primary/12 text-primary border-primary/25';
        case 'api_token':
            return 'bg-violet-500/15 text-violet-700 dark:text-violet-400 border-violet-500/25';
        case 'redis':
            return 'bg-red-500/15 text-red-700 dark:text-red-400 border-red-500/25';
        case 'ssh_key':
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/25';
        case 'env_file':
            return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/25';
        case 'json':
            return 'bg-sky-500/15 text-sky-700 dark:text-sky-400 border-sky-500/25';
        case 'other':
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}

export function secretTypeDot(type: string): string {
    switch (type) {
        case 'db_password':
            return 'bg-primary';
        case 'api_token':
            return 'bg-violet-500';
        case 'redis':
            return 'bg-red-500';
        case 'ssh_key':
            return 'bg-amber-500';
        case 'env_file':
            return 'bg-emerald-500';
        case 'json':
            return 'bg-sky-500';
        case 'other':
        default:
            return 'bg-muted-foreground/50';
    }
}
