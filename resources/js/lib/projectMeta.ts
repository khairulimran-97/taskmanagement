/**
 * Single source of truth for project/task status & priority styling.
 * All token-based and dark-mode safe — replaces the divergent color maps that
 * previously lived in ProjectHeader, ProjectStats, TaskList, and Show.vue.
 */

export type ProjectStatus = 'active' | 'paused' | 'completed' | 'archived' | string;
export type TaskStatus = 'todo' | 'in_progress' | 'completed' | 'cancelled' | string;
export type Priority = 'urgent' | 'high' | 'medium' | 'low' | string;

const capitalize = (s: string) => (s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : s);

/* ---------- Project status ---------- */
export function projectStatusBadge(status: ProjectStatus): string {
    switch (status) {
        case 'active':
            return 'bg-primary/12 text-primary border-primary/25';
        case 'completed':
            return 'bg-[hsl(150_24%_42%/0.14)] text-[hsl(150_30%_38%)] dark:text-[hsl(150_30%_55%)] border-[hsl(150_24%_42%/0.25)]';
        case 'paused':
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/25';
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
            return 'bg-[hsl(150_24%_45%)]';
        case 'paused':
            return 'bg-amber-500';
        case 'archived':
        default:
            return 'bg-muted-foreground/50';
    }
}

/* ---------- Task status ---------- */
export function taskStatusBadge(status: TaskStatus): string {
    switch (status) {
        case 'in_progress':
            return 'bg-primary/12 text-primary border-primary/25';
        case 'completed':
            return 'bg-[hsl(150_24%_42%/0.14)] text-[hsl(150_30%_38%)] dark:text-[hsl(150_30%_55%)] border-[hsl(150_24%_42%/0.25)]';
        case 'cancelled':
            return 'bg-destructive/12 text-destructive border-destructive/25';
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
            return 'bg-[hsl(150_24%_45%)]';
        case 'cancelled':
            return 'bg-destructive';
        case 'todo':
        default:
            return 'bg-muted-foreground/50';
    }
}

/* ---------- Priority ---------- */
export function priorityBadge(priority: Priority): string {
    switch (priority) {
        case 'urgent':
            return 'bg-destructive/12 text-destructive border-destructive/25';
        case 'high':
            return 'bg-orange-500/15 text-orange-700 dark:text-orange-400 border-orange-500/25';
        case 'medium':
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/25';
        case 'low':
            return 'bg-[hsl(150_24%_42%/0.14)] text-[hsl(150_30%_38%)] dark:text-[hsl(150_30%_55%)] border-[hsl(150_24%_42%/0.25)]';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}

export function priorityText(priority: Priority): string {
    switch (priority) {
        case 'urgent':
            return 'text-destructive';
        case 'high':
            return 'text-orange-600 dark:text-orange-400';
        case 'medium':
            return 'text-amber-600 dark:text-amber-400';
        case 'low':
            return 'text-[hsl(150_30%_40%)] dark:text-[hsl(150_30%_55%)]';
        default:
            return 'text-muted-foreground';
    }
}

/* ---------- Progress bar ramp (completion %) ---------- */
export function progressColor(pct: number): string {
    if (pct >= 75) return 'bg-[hsl(150_24%_45%)]';
    if (pct >= 50) return 'bg-amber-500';
    if (pct >= 25) return 'bg-orange-500';
    return 'bg-destructive';
}

export const labelize = capitalize;
