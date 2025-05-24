import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';
import { User } from './user';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export type ProjectStatus = 'active' | 'paused' | 'completed' | 'archived';
export type ProjectPriority = 'low' | 'medium' | 'high';

export interface Project {
    id: number;
    name: string;
    description: string | null;
    color: string;
    status: ProjectStatus;
    priority: ProjectPriority;
    due_date: string | null;
    start_date: string | null;
    user_id: number;
    sort_order: number;
    completed_at: string | null;
    created_at: string;
    updated_at: string;
    completion_percentage?: number;
    tasks?: Task[];
}

export interface Task {
    id: number;
    title: string;
    description?: string | null;
    status: 'pending' | 'in_progress' | 'completed';
    project_id: number;
    user_id: number;
    due_date?: string | null;
    completed_at?: string | null;
    created_at: string;
    updated_at: string;
}
