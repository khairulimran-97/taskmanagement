/**
 * Event categories — a presentation layer over the existing `color` hex column.
 * No DB change: each category maps to one of the palette colors the backend
 * already returns from CalendarEvent::getAvailableColors(). The stored value is
 * still the hex; categories just give it a name + icon for the UI.
 */
import { Briefcase, User, Users, AlertTriangle, Bell, Circle, type Icon } from 'lucide-vue-next';

export interface EventCategory {
    key: string;
    label: string;
    color: string; // hex stored in DB
    icon: Icon;
}

export const CATEGORIES: EventCategory[] = [
    { key: 'work', label: 'Work', color: '#3B82F6', icon: Briefcase },
    { key: 'personal', label: 'Personal', color: '#10B981', icon: User },
    { key: 'meeting', label: 'Meeting', color: '#8B5CF6', icon: Users },
    { key: 'deadline', label: 'Deadline', color: '#EF4444', icon: AlertTriangle },
    { key: 'reminder', label: 'Reminder', color: '#F59E0B', icon: Bell },
    { key: 'other', label: 'Other', color: '#6B7280', icon: Circle },
];

const norm = (hex: string | null | undefined) => (hex || '').trim().toUpperCase();

/** Find the category for a stored color hex (case-insensitive). Falls back to "Other". */
export function categoryForColor(hex: string | null | undefined): EventCategory {
    const target = norm(hex);
    return CATEGORIES.find((c) => norm(c.color) === target) || CATEGORIES[CATEGORIES.length - 1];
}

/** True when the hex isn't one of the named category colors (i.e. a custom color). */
export function isCustomColor(hex: string | null | undefined): boolean {
    const target = norm(hex);
    return !!target && !CATEGORIES.some((c) => norm(c.color) === target);
}
