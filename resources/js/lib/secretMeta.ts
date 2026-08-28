/**
 * Single source of truth for secret-vault type options and labels.
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
    return secretTypes.find((t) => t.value === type)?.label ?? (type ? type.charAt(0).toUpperCase() + type.slice(1).replace(/_/g, ' ') : 'Other');
}
