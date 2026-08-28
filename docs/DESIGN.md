# Taskflow revamp — style guide (single source of truth)

Direction: **quiet precision**. Evergreen, professional, calm. The Linear/Things/Stripe-dashboard school.
Nothing may read as "AI-generated": no gradients, no glassmorphism, no oversized rounded blobs, no emoji-as-icons, no serif display type, no decorative saffron/cream warmth.

## Typography — ONE font
- **Inter only**, loaded from bunny.net in `app.blade.php`. Weights 400 / 500 / 600 / 700.
- `font-display`, `font-serif`, Fraunces, Hanken Grotesk: **must not appear anywhere**. Remove the class entirely (the sans is the default); when a heading needs presence use `font-semibold tracking-tight`.
- Readability: `--text-sm` is raised to 15px and `--text-xs` to 13px in app.css — don't shrink text below these.
- Scale (use these, nothing arbitrary):
  - Page title: `text-xl font-semibold tracking-tight` (PageHeader handles this — pages must use PageHeader)
  - Section/card title: `text-sm font-semibold` or CardTitle default (`text-base`)
  - Body: `text-sm`
  - Meta/labels/hints: `text-xs text-muted-foreground`
  - Big stat numbers: `text-2xl font-semibold tabular-nums` (StatCard handles it)
- All numeric data (counts, dates in tables, times): add `tabular-nums`.
- No uppercase-tracking eyebrow labels except tiny group labels in sidebars/menus where shadcn already does it.

## Color — semantic tokens ONLY
Tokens are defined in `resources/css/app.css`. Cool neutrals (blue-gray canvas hsl(220 30% 95%) — deliberately NOT white, so white cards float with depth; the workspace additionally gets a soft ambient cobalt wash at the top via main[data-slot='sidebar-inset'] in app.css — never patterns/dot grids, which were explicitly rejected) + **one cobalt-blue accent** (`--primary`), with an **ink-navy sidebar** (dark in both themes — sidebar-* tokens; inside the sidebar use `sidebar-primary`/`sidebar-foreground`, never the main-surface tokens). Status colors exist as tokens; green appears ONLY as the success/completed status, never as theme chrome.

Rules:
- **Never** use raw Tailwind palette utilities for chrome/brand: no `text-orange-*`, `bg-amber-*`, `text-blue-*`, `bg-purple-*`, etc. and no hex/hsl inline styles.
- Use: `bg-background`, `bg-card`, `text-foreground`, `text-muted-foreground`, `border-border`, `bg-muted`, `text-primary`, `bg-primary`, `text-destructive`, `text-success`, `text-warning`, `bg-success/10`, `bg-warning/10`, `bg-destructive/10`, chart colors `text-chart-1..5` / `bg-chart-1..5`.
- Status language (identical on every page):
  - done/completed/active-good → `success`
  - in progress → `primary`
  - todo/neutral/paused → `muted-foreground`
  - due soon / warning / medium priority → `warning`
  - overdue / urgent / destructive → `destructive`
  - Render status as a 1.5-size dot + sentence-case label: `<span class="size-1.5 rounded-full bg-success" /> Completed`, or a soft badge: `bg-success/10 text-success` (same pattern for warning/destructive/primary). No solid loud badges for statuses.
- Dark mode: never hand-roll `dark:` colors when a token already adapts. Only use `dark:` for opacity tweaks (e.g. `bg-success/10 dark:bg-success/15`).

## Shape & depth
- Radius: cards/containers `rounded-lg`, inputs/buttons keep shadcn defaults (`rounded-md`), never `rounded-2xl`/`rounded-3xl`. Avatars/dots `rounded-full`.
- Depth: **borders over shadows**. Cards: `border` + `shadow-xs` max. Popovers/dialogs/sheets: `shadow-md` (shadcn default ok). No `shadow-lg`+ on static content, no colored shadows, **no gradients anywhere**.
- Dividers: `border-border`; use `divide-y divide-border` for lists instead of gaps between boxed items.
- Buttons: `outline` = `bg-card` + `border-input` (never `bg-background` — it melts into the canvas/modal); `secondary` sits one step deeper than muted so it reads on white surfaces.

## Density & layout
- Page container: consistent `PageContainer` + `PageHeader` on EVERY page (title, description, actions slot). No page invents its own header.
- Card padding: `p-4` to `p-5`. List rows: `py-2.5 px-3`, min touch height 40px (44px on mobile targets).
- Vertical rhythm: sections separated by `space-y-6`; within a card `space-y-3`/`space-y-4`.
- Tables/lists: `text-sm`; header row `text-xs font-medium text-muted-foreground`.

## Interaction (this is half the brief — "better user interaction")
Every interactive element MUST have all of:
1. `cursor-pointer` (links/buttons get it free; add to custom clickable divs — better: make them real `<button>`/`<Link>`).
2. Hover state: `hover:bg-muted/60` for rows, `hover:border-ring/40` NOT allowed — for cards that link somewhere use `hover:border-muted-foreground/30 hover:shadow-xs transition-colors`.
3. `focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none` on custom interactive elements (shadcn components already have this — don't strip it).
4. Transition: `transition-colors duration-150` (150–200ms). Nothing above 300ms except the RingStat sweep. No entrance animations on app pages; `prefers-reduced-motion` must not break anything (no motion that conveys required info).
5. Async actions: disable the trigger + show progress (`:disabled="form.processing"` + spinner or "Saving…" label). Every destructive action confirms via AlertDialog. Every mutation gets a sonner toast, phrased as the completed action ("Project archived"), matching the button verb.
6. Empty states: use `EmptyState` with one clear primary action.
7. Icon-only buttons: `aria-label` + Tooltip.

## Copy
Sentence case everywhere (buttons, titles, labels). Buttons say what they do: "New project", "Save changes", "Add task". Errors say what happened and how to fix. No exclamation marks, no filler ("Manage all your…").

## Icons
Lucide only, `size-4` in buttons/rows, `size-5` in page-header contexts. Never emoji. Icon chips: `bg-muted text-muted-foreground` or `bg-primary/10 text-primary` — one accent chip per view max.

## Sidebar navigation
- Groups: Dashboard standalone, then labeled groups (Workspace: Projects/Notes/Calendar; Tools: Vault, Settings); only the user menu lives in the footer.
- Active item = a SOLID cobalt pill (bg-sidebar-primary + white text) so it never blends into the navy sidebar; hover = the quiet navy accent. No left rails, bars, or indicator strips — explicitly rejected as "vibe code".

## What to actively remove while sweeping pages
- `font-display` / serif classes → delete class
- `rounded-xl/2xl` on cards → `rounded-lg`
- gradients (`bg-gradient-*`, `from-*`), glass (`backdrop-blur` on cards), colored glows
- raw palette colors → semantic tokens per status language above
- ad-hoc page headers → `PageHeader`
- ad-hoc empty divs saying "No items" → `EmptyState`
- oversized hero paddings (`py-16`+ inside app pages) → normal rhythm
- redundant decorative icon chips (keep max one per header)

Keep all functionality identical: props, events, routes, business logic untouched. This is a re-skin + interaction-polish pass, not a refactor.
