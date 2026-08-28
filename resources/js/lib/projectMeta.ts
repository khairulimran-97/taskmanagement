/**
 * Single source of truth for project/task status & priority styling.
 * All token-based and dark-mode safe — the status language is:
 * completed → success · in progress/active → primary · todo/neutral → muted ·
 * due soon/medium → warning · overdue/urgent → destructive.
 */

export type ProjectStatus = 'active' | 'paused' | 'completed' | 'archived' | string;
export type TaskStatus = 'todo' | 'in_progress' | 'completed' | 'cancelled' | string;
export type Priority = 'urgent' | 'high' | 'medium' | 'low' | string;

const capitalize = (s: string) => (s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : s);

/* ---------- Project status ---------- */
export function projectStatusBadge(status: ProjectStatus): string {
    switch (status) {
        case 'active':
            return 'bg-primary/10 text-primary border-primary/25';
        case 'completed':
            return 'bg-success/10 text-success border-success/25';
        case 'paused':
            return 'bg-warning/10 text-warning border-warning/25';
        case 'archived':
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}

export function projectStatusDot(status: ProjectStatus): string {
    switch (status) {
        case 'active':
            return 'bg-primary';
        case 'completed':
            return 'bg-success';
        case 'paused':
            return 'bg-warning';
        case 'archived':
        default:
            return 'bg-muted-foreground/50';
    }
}

/* ---------- Task status ---------- */
export function taskStatusBadge(status: TaskStatus): string {
    switch (status) {
        case 'in_progress':
            return 'bg-primary/10 text-primary border-primary/25';
        case 'completed':
            return 'bg-success/10 text-success border-success/25';
        case 'cancelled':
            return 'bg-muted text-muted-foreground border-border';
        case 'todo':
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}

export function taskStatusDot(status: TaskStatus): string {
    switch (status) {
        case 'in_progress':
            return 'bg-primary';
        case 'completed':
            return 'bg-success';
        case 'cancelled':
            return 'bg-muted-foreground/40';
        case 'todo':
        default:
            return 'bg-muted-foreground/50';
    }
}

/* ---------- Priority ---------- */
export function priorityBadge(priority: Priority): string {
    switch (priority) {
        case 'urgent':
            return 'bg-destructive/10 text-destructive border-destructive/30';
        case 'high':
            return 'bg-transparent text-destructive border-destructive/40';
        case 'medium':
            return 'bg-warning/10 text-warning border-warning/25';
        case 'low':
            return 'bg-muted text-muted-foreground border-border';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}

export function priorityText(priority: Priority): string {
    switch (priority) {
        case 'urgent':
        case 'high':
            return 'text-destructive';
        case 'medium':
            return 'text-warning';
        case 'low':
        default:
            return 'text-muted-foreground';
    }
}

/* ---------- Progress bar ramp (completion %) ---------- */
export function progressColor(pct: number): string {
    if (pct >= 66) return 'bg-success';
    if (pct >= 33) return 'bg-warning';
    return 'bg-destructive';
}

export const labelize = capitalize;
