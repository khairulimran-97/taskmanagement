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

// Project types
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

// Task types
export type TaskStatus = 'todo' | 'in_progress' | 'completed' | 'cancelled';
export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent';

export interface Task {
    id: number;
    title: string;
    description?: string | null;
    status: TaskStatus;
    priority: TaskPriority;
    due_date?: string | null;
    start_date?: string | null;
    project_id: number;
    user_id: number;
    assigned_to?: number | null;
    parent_task_id?: number | null;
    sort_order: number;
    completed_at?: string | null;
    created_at: string;
    updated_at: string;
    // Relationships
    tags?: Tag[];
    subtasks?: Task[];
    parentTask?: Task;
    assignedUser?: User;
}

// Tag types
export interface Tag {
    id: number;
    name: string;
    slug: string;
    color: string;
    description?: string | null;
    user_id: number;
    created_at: string;
    updated_at: string;
    // Relationships
    tasks?: Task[];
}

// Extended interfaces for complex components
export interface ExtendedTask extends Task {
    tags?: Tag[];
    subtasks?: ExtendedTask[];
    parent_task_id?: number;
}

export interface ExtendedProject extends Project {
    tasks?: ExtendedTask[];
}

export interface CalendarEvent {
    id: number;
    title: string;
    description?: string | null;
    start_date: string;
    end_date?: string | null;
    color: string;
    all_day: boolean;
    user_id: number;
    created_at: string;
    updated_at: string;
}

export interface CalendarEventForm {
    title: string;
    description?: string;
    start_date: string;
    end_date?: string;
    color: string;
    all_day: boolean;
}

export interface FullCalendarEvent {
    id: string | number;
    title: string;
    start: string;
    end?: string;
    allDay: boolean;
    backgroundColor: string;
    borderColor?: string;
    textColor?: string;
    extendedProps?: {
        description?: string;
        user_id?: number;
        [key: string]: any;
    };
}
