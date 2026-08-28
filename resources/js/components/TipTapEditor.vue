<script setup lang="ts">
import ImageUploadDialog from '@/components/ImageUploadDialog.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import TipTapImage from '@/extensions/TipTapImageExtension';
import Highlight from '@tiptap/extension-highlight';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Table from '@tiptap/extension-table';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TableRow from '@tiptap/extension-table-row';
import TaskItem from '@tiptap/extension-task-item';
import TaskList from '@tiptap/extension-task-list';
import TextAlign from '@tiptap/extension-text-align';
import Typography from '@tiptap/extension-typography';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Bold,
    CheckSquare,
    ChevronRight,
    Clipboard,
    Code,
    Code2,
    Columns,
    Copy,
    ExternalLink,
    Grid3x3,
    Heading1,
    Heading2,
    Heading3,
    Highlighter,
    ImageIcon,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Minus,
    MoreHorizontal,
    MoveHorizontal,
    MoveVertical,
    Plus,
    Quote,
    Redo,
    Rows,
    Scissors,
    Strikethrough,
    Table as TableIcon,
    Trash2,
    Underline as UnderlineIcon,
    Undo,
    Unlink,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const imageDialogOpen = ref(false);
const showHtmlSource = ref(false);
const htmlDraft = ref('');

const toggleHtmlSource = () => {
    if (!showHtmlSource.value) {
        // entering source view — snapshot current HTML
        htmlDraft.value = editor.value?.getHTML() || '';
        showHtmlSource.value = true;
    } else {
        // leaving source view — push edited HTML back into the editor
        editor.value?.commands.setContent(htmlDraft.value, true);
        emit('update:modelValue', editor.value?.getHTML() || '');
        showHtmlSource.value = false;
    }
};

// Leave source view WITHOUT applying the draft — escape hatch for a bad paste
const discardHtmlSource = () => {
    htmlDraft.value = '';
    showHtmlSource.value = false;
};
const linkDialogOpen = ref(false);
const linkUrl = ref('');
const linkText = ref('');
const linkTarget = ref('_self');
const isEditingLink = ref(false);

const showImageDialog = () => {
    imageDialogOpen.value = true;
};

const handleImageSelected = (url: string, alt?: string) => {
    editor.value
        ?.chain()
        .focus()
        .setImage({
            src: url,
            alt: alt || '',
            title: alt || '',
        })
        .run();
};

const props = withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
        editable?: boolean;
        class?: string;
        noteId?: number | null;
    }>(),
    {
        placeholder: 'Start writing your note...',
        editable: true,
        class: '',
        noteId: null,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    focus: [];
    blur: [];
    save: [];
}>();

// Context menu state
const showContextMenu = ref(false);
const contextMenuPosition = ref({ x: 0, y: 0 });
const contextMenuType = ref<'text' | 'table' | 'link'>('text');

const editor = useEditor({
    content: props.modelValue,
    editable: props.editable,
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [1, 2, 3],
            },
        }),
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
        Typography,
        TaskList,
        TaskItem.configure({
            nested: true,
        }),
        Highlight.configure({
            multicolor: true,
        }),
        Table.configure({
            resizable: true,
            handleWidth: 5,
            cellMinWidth: 50,
            allowTableNodeSelection: true,
        }),
        TableRow,
        TableHeader,
        TableCell,
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        Underline,
        Link.configure({
            HTMLAttributes: {
                class: 'tiptap-link',
            },
            openOnClick: false,
            linkOnPaste: true,
        }),
        TipTapImage.configure({
            HTMLAttributes: {
                class: 'max-w-full',
            },
        }),
    ],
    editorProps: {
        attributes: {
            class: 'tiptap-prose focus:outline-none p-4',
        },
        handleDOMEvents: {
            contextmenu: (view, event) => {
                if (!props.editable) return false;

                event.preventDefault();

                // Determine context menu type based on what was clicked
                const target = event.target as HTMLElement;
                const isInTable = target.closest('table') !== null;
                const isOnLink = target.closest('a') !== null;

                if (isOnLink) {
                    contextMenuType.value = 'link';
                } else if (isInTable) {
                    contextMenuType.value = 'table';
                } else {
                    contextMenuType.value = 'text';
                }

                // Clamp so the menu stays inside the viewport near screen edges
                const menuWidth = 208;
                const menuHeight = 340;
                contextMenuPosition.value = {
                    x: Math.max(8, Math.min(event.clientX, window.innerWidth - menuWidth)),
                    y: Math.max(8, Math.min(event.clientY, window.innerHeight - menuHeight)),
                };
                showContextMenu.value = true;

                return true;
            },
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
    onFocus: () => {
        emit('focus');
    },
    onBlur: () => {
        emit('blur');
    },
});

// Watch for external content changes
watch(
    () => props.modelValue,
    (newValue) => {
        if (editor.value && editor.value.getHTML() !== newValue) {
            editor.value.commands.setContent(newValue);
        }
    },
);

// Watch for editable changes
watch(
    () => props.editable,
    (newValue) => {
        if (editor.value) {
            editor.value.setEditable(newValue);
        }
    },
);

onBeforeUnmount(() => {
    if (editor.value) {
        editor.value.destroy();
    }
});

// Hide context menu when clicking elsewhere
onMounted(() => {
    const hideContextMenu = () => {
        showContextMenu.value = false;
    };

    // Handle keyboard shortcuts
    const handleKeydown = (event: KeyboardEvent) => {
        // Escape closes the custom context menu
        if (event.key === 'Escape' && showContextMenu.value) {
            showContextMenu.value = false;
            return;
        }

        // Ctrl+S or Cmd+S to save
        if ((event.ctrlKey || event.metaKey) && event.key === 's') {
            event.preventDefault();
            emit('save');
        }

        // Ctrl+K or Cmd+K to create/edit link
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            openLinkDialog();
        }
    };

    document.addEventListener('click', hideContextMenu);
    document.addEventListener('scroll', hideContextMenu);
    document.addEventListener('keydown', handleKeydown);

    return () => {
        document.removeEventListener('click', hideContextMenu);
        document.removeEventListener('scroll', hideContextMenu);
        document.removeEventListener('keydown', handleKeydown);
    };
});

// Check if cursor is in a table
const isInTable = computed(() => {
    if (!editor.value) return false;
    return editor.value.isActive('table');
});

// Link functionality
const openLinkDialog = () => {
    if (!editor.value) return;

    // Blur the editor to prevent focus conflicts with dialog
    editor.value.commands.blur();

    const { state } = editor.value;
    const { from, to } = state.selection;

    // Check if we're editing an existing link
    const link = editor.value.getAttributes('link');

    if (link.href) {
        // Editing existing link
        isEditingLink.value = true;
        linkUrl.value = link.href;
        linkTarget.value = link.target || '_self';

        // Get the text content of the link
        const selectedText = state.doc.textBetween(from, to, ' ');
        linkText.value = selectedText || 'Link';
    } else {
        // Creating new link
        isEditingLink.value = false;
        linkUrl.value = '';
        linkTarget.value = '_self';

        // Get selected text if any
        const selectedText = state.doc.textBetween(from, to, ' ');
        linkText.value = selectedText || '';
    }

    linkDialogOpen.value = true;
};

const closeLinkDialog = () => {
    linkDialogOpen.value = false;
    linkUrl.value = '';
    linkText.value = '';
    linkTarget.value = '_self';
    isEditingLink.value = false;

    // Refocus the editor after dialog closes
    nextTick(() => {
        editor.value?.commands.focus();
    });
};

const insertOrUpdateLink = () => {
    if (!editor.value || !linkUrl.value.trim()) return;

    const url = linkUrl.value.trim();

    // Add protocol if missing
    const finalUrl = url.match(/^https?:\/\//) ? url : `https://${url}`;

    if (isEditingLink.value) {
        // Update existing link
        editor.value
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({
                href: finalUrl,
                target: linkTarget.value === '_blank' ? '_blank' : undefined,
            })
            .run();
    } else {
        // Create new link
        if (linkText.value.trim()) {
            // Insert content with link mark applied directly
            const linkMark = editor.value.schema.marks.link.create({
                href: finalUrl,
                target: linkTarget.value === '_blank' ? '_blank' : undefined,
            });

            const textNode = editor.value.schema.text(linkText.value, [linkMark]);
            editor.value.chain().focus().insertContent(textNode.toJSON()).run();
        } else {
            // Just add link to selected text
            editor.value
                .chain()
                .focus()
                .setLink({
                    href: finalUrl,
                    target: linkTarget.value === '_blank' ? '_blank' : undefined,
                })
                .run();
        }
    }

    closeLinkDialog();
};

const removeLink = () => {
    if (!editor.value) return;
    editor.value.chain().focus().unsetLink().run();
    showContextMenu.value = false;
};

const openLinkInNewTab = () => {
    if (!editor.value) return;

    const link = editor.value.getAttributes('link');
    if (link.href) {
        window.open(link.href, '_blank');
    }
    showContextMenu.value = false;
};

const copyLinkUrl = async () => {
    if (!editor.value) return;

    const link = editor.value.getAttributes('link');
    if (link.href) {
        try {
            await navigator.clipboard.writeText(link.href);
        } catch {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = link.href;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        }
    }
    showContextMenu.value = false;
};

// Toolbar actions
const toggleBold = () => editor.value?.chain().focus().toggleBold().run();
const toggleItalic = () => editor.value?.chain().focus().toggleItalic().run();
const toggleUnderline = () => editor.value?.chain().focus().toggleUnderline().run();
const toggleStrike = () => editor.value?.chain().focus().toggleStrike().run();
const toggleCode = () => editor.value?.chain().focus().toggleCode().run();
const toggleHighlight = () => editor.value?.chain().focus().toggleHighlight().run();

const setHeading = (level: 1 | 2 | 3) => {
    editor.value?.chain().focus().toggleHeading({ level }).run();
};

const toggleBulletList = () => editor.value?.chain().focus().toggleBulletList().run();
const toggleOrderedList = () => editor.value?.chain().focus().toggleOrderedList().run();
const toggleTaskList = () => editor.value?.chain().focus().toggleTaskList().run();
const toggleBlockquote = () => editor.value?.chain().focus().toggleBlockquote().run();
const setHorizontalRule = () => editor.value?.chain().focus().setHorizontalRule().run();

// Enhanced Table Functions
const insertTable = (rows = 3, cols = 3, withHeaderRow = true) => {
    editor.value?.chain().focus().insertTable({ rows, cols, withHeaderRow }).run();
    showContextMenu.value = false;
};

const addColumnBefore = () => {
    editor.value?.chain().focus().addColumnBefore().run();
    showContextMenu.value = false;
};

const addColumnAfter = () => {
    editor.value?.chain().focus().addColumnAfter().run();
    showContextMenu.value = false;
};

const deleteColumn = () => {
    editor.value?.chain().focus().deleteColumn().run();
    showContextMenu.value = false;
};

const addRowBefore = () => {
    editor.value?.chain().focus().addRowBefore().run();
    showContextMenu.value = false;
};

const addRowAfter = () => {
    editor.value?.chain().focus().addRowAfter().run();
    showContextMenu.value = false;
};

const deleteRow = () => {
    editor.value?.chain().focus().deleteRow().run();
    showContextMenu.value = false;
};

const deleteTable = () => {
    editor.value?.chain().focus().deleteTable().run();
    showContextMenu.value = false;
};

const mergeCells = () => {
    editor.value?.chain().focus().mergeCells().run();
    showContextMenu.value = false;
};

const splitCell = () => {
    editor.value?.chain().focus().splitCell().run();
    showContextMenu.value = false;
};

const toggleHeaderColumn = () => {
    editor.value?.chain().focus().toggleHeaderColumn().run();
    showContextMenu.value = false;
};

const toggleHeaderRow = () => {
    editor.value?.chain().focus().toggleHeaderRow().run();
    showContextMenu.value = false;
};

const mergeOrSplit = () => {
    if (editor.value?.can().mergeCells()) {
        mergeCells();
    } else if (editor.value?.can().splitCell()) {
        splitCell();
    }
};

const setTextAlign = (alignment: 'left' | 'center' | 'right') => {
    editor.value?.chain().focus().setTextAlign(alignment).run();
};

const undo = () => editor.value?.chain().focus().undo().run();
const redo = () => editor.value?.chain().focus().redo().run();

// Copy/Cut/Paste functions for context menu
const copyText = async () => {
    try {
        // Get selected text from editor
        const { from, to } = editor.value.state.selection;
        const selectedText = editor.value.state.doc.textBetween(from, to, ' ');

        if (selectedText) {
            await navigator.clipboard.writeText(selectedText);
        } else {
            // Fallback to document.execCommand for older browsers
            document.execCommand('copy');
        }
    } catch {
        // Fallback to document.execCommand
        document.execCommand('copy');
    }
    showContextMenu.value = false;
};

const cutText = async () => {
    try {
        // Get selected text from editor
        const { from, to } = editor.value.state.selection;
        const selectedText = editor.value.state.doc.textBetween(from, to, ' ');

        if (selectedText) {
            await navigator.clipboard.writeText(selectedText);
            // Delete the selected content
            editor.value.chain().focus().deleteSelection().run();
        } else {
            // Fallback to document.execCommand for older browsers
            document.execCommand('cut');
        }
    } catch {
        // Fallback to document.execCommand
        document.execCommand('cut');
    }
    showContextMenu.value = false;
};

const pasteText = async () => {
    try {
        // Use modern Clipboard API
        if (navigator.clipboard && navigator.clipboard.readText) {
            const text = await navigator.clipboard.readText();
            if (text) {
                // Insert the text at current cursor position
                editor.value.chain().focus().insertContent(text).run();
            }
        } else {
            // Fallback: Focus editor and let browser handle paste
            editor.value.chain().focus().run();
            // Create a temporary textarea to handle paste
            const textarea = document.createElement('textarea');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            textarea.style.top = '-9999px';
            document.body.appendChild(textarea);
            textarea.focus();

            // Listen for paste event
            const handlePaste = (e) => {
                e.preventDefault();
                const pastedText = e.clipboardData?.getData('text/plain');
                if (pastedText) {
                    editor.value.chain().focus().insertContent(pastedText).run();
                }
                document.body.removeChild(textarea);
                textarea.removeEventListener('paste', handlePaste);
            };

            textarea.addEventListener('paste', handlePaste);
            document.execCommand('paste');
        }
    } catch (error) {
        console.warn('Paste failed:', error);
        // Final fallback - just focus the editor
        editor.value.chain().focus().run();
    }
    showContextMenu.value = false;
};

// Check if commands are active
const isActive = (command: string, options?: any) => {
    return editor.value?.isActive(command, options) || false;
};

// Shared styling for toolbar icon buttons — active state reads bg-muted text-foreground per the style guide
const toolbarBtn = (active = false) =>
    active ? 'h-8 w-8 p-0 bg-muted text-foreground hover:bg-muted hover:text-foreground' : 'h-8 w-8 p-0 text-muted-foreground hover:text-foreground';

// Shared styling for custom context-menu items (hover and keyboard-focus parity)
const menuItem =
    'flex w-full cursor-pointer items-center rounded-sm px-3 py-2 text-sm transition-colors duration-150 hover:bg-accent focus-visible:bg-accent focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50';

const canUndo = () => editor.value?.can().undo() || false;
const canRedo = () => editor.value?.can().redo() || false;

// Table state checks
const canMergeCells = () => editor.value?.can().mergeCells() || false;
const canSplitCell = () => editor.value?.can().splitCell() || false;
const canAddColumnBefore = () => editor.value?.can().addColumnBefore() || false;
const canAddColumnAfter = () => editor.value?.can().addColumnAfter() || false;
const canDeleteColumn = () => editor.value?.can().deleteColumn() || false;
const canAddRowBefore = () => editor.value?.can().addRowBefore() || false;
const canAddRowAfter = () => editor.value?.can().addRowAfter() || false;
const canDeleteRow = () => editor.value?.can().deleteRow() || false;
const canDeleteTable = () => editor.value?.can().deleteTable() || false;
</script>

<template>
    <div :class="['tiptap-editor border-border flex flex-col rounded-lg border', props.class]" class="relative">
        <!-- Toolbar -->
        <div v-if="editable" class="border-border bg-muted/20 flex flex-wrap items-center gap-1 border-b p-1.5">
            <!-- History -->
            <div class="flex items-center gap-0.5">
                <Button @click="undo" :disabled="!canUndo()" variant="ghost" size="sm" :class="toolbarBtn()" title="Undo" aria-label="Undo">
                    <Undo class="size-4" />
                </Button>
                <Button @click="redo" :disabled="!canRedo()" variant="ghost" size="sm" :class="toolbarBtn()" title="Redo" aria-label="Redo">
                    <Redo class="size-4" />
                </Button>
            </div>

            <Separator orientation="vertical" class="h-5" />

            <!-- Text Formatting -->
            <div class="flex items-center gap-0.5">
                <Button
                    @click="toggleBold"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive('bold'))"
                    :aria-pressed="isActive('bold')"
                    title="Bold"
                    aria-label="Bold"
                >
                    <Bold class="size-4" />
                </Button>
                <Button
                    @click="toggleItalic"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive('italic'))"
                    :aria-pressed="isActive('italic')"
                    title="Italic"
                    aria-label="Italic"
                >
                    <Italic class="size-4" />
                </Button>
                <Button
                    @click="toggleUnderline"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive('underline'))"
                    :aria-pressed="isActive('underline')"
                    title="Underline"
                    aria-label="Underline"
                >
                    <UnderlineIcon class="size-4" />
                </Button>
                <Button
                    @click="toggleStrike"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive('strike'))"
                    :aria-pressed="isActive('strike')"
                    title="Strikethrough"
                    aria-label="Strikethrough"
                >
                    <Strikethrough class="size-4" />
                </Button>
                <Button
                    @click="toggleCode"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive('code'))"
                    :aria-pressed="isActive('code')"
                    title="Inline code"
                    aria-label="Inline code"
                >
                    <Code class="size-4" />
                </Button>
                <Button
                    @click="toggleHighlight"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive('highlight'))"
                    :aria-pressed="isActive('highlight')"
                    title="Highlight"
                    aria-label="Highlight"
                >
                    <Highlighter class="size-4" />
                </Button>
            </div>

            <Separator orientation="vertical" class="h-5" />

            <!-- Headings -->
            <div class="flex items-center gap-0.5">
                <Button
                    @click="setHeading(1)"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive('heading', { level: 1 }))"
                    :aria-pressed="isActive('heading', { level: 1 })"
                    title="Heading 1"
                    aria-label="Heading 1"
                >
                    <Heading1 class="size-4" />
                </Button>
                <Button
                    @click="setHeading(2)"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive('heading', { level: 2 }))"
                    :aria-pressed="isActive('heading', { level: 2 })"
                    title="Heading 2"
                    aria-label="Heading 2"
                >
                    <Heading2 class="size-4" />
                </Button>
                <Button
                    @click="setHeading(3)"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive('heading', { level: 3 }))"
                    :aria-pressed="isActive('heading', { level: 3 })"
                    title="Heading 3"
                    aria-label="Heading 3"
                >
                    <Heading3 class="size-4" />
                </Button>
            </div>

            <Separator orientation="vertical" class="h-5" />

            <!-- Lists -->
            <div class="flex items-center gap-0.5">
                <Button
                    @click="toggleBulletList"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive('bulletList'))"
                    :aria-pressed="isActive('bulletList')"
                    title="Bullet list"
                    aria-label="Bullet list"
                >
                    <List class="size-4" />
                </Button>
                <Button
                    @click="toggleOrderedList"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive('orderedList'))"
                    :aria-pressed="isActive('orderedList')"
                    title="Numbered list"
                    aria-label="Numbered list"
                >
                    <ListOrdered class="size-4" />
                </Button>
                <Button
                    @click="toggleTaskList"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive('taskList'))"
                    :aria-pressed="isActive('taskList')"
                    title="Task list"
                    aria-label="Task list"
                >
                    <CheckSquare class="size-4" />
                </Button>
            </div>

            <Separator orientation="vertical" class="h-5" />

            <!-- Alignment -->
            <div class="flex items-center gap-0.5">
                <Button
                    @click="setTextAlign('left')"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive({ textAlign: 'left' }))"
                    :aria-pressed="isActive({ textAlign: 'left' })"
                    title="Align left"
                    aria-label="Align left"
                >
                    <AlignLeft class="size-4" />
                </Button>
                <Button
                    @click="setTextAlign('center')"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive({ textAlign: 'center' }))"
                    :aria-pressed="isActive({ textAlign: 'center' })"
                    title="Align center"
                    aria-label="Align center"
                >
                    <AlignCenter class="size-4" />
                </Button>
                <Button
                    @click="setTextAlign('right')"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive({ textAlign: 'right' }))"
                    :aria-pressed="isActive({ textAlign: 'right' })"
                    title="Align right"
                    aria-label="Align right"
                >
                    <AlignRight class="size-4" />
                </Button>
            </div>

            <Separator orientation="vertical" class="h-5" />

            <!-- Link -->
            <div class="flex items-center gap-0.5">
                <Button
                    @click="openLinkDialog"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive('link'))"
                    :aria-pressed="isActive('link')"
                    title="Insert link (Ctrl+K)"
                    aria-label="Insert link"
                >
                    <LinkIcon class="size-4" />
                </Button>
                <Button
                    v-if="isActive('link')"
                    @click="removeLink"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn()"
                    title="Remove link"
                    aria-label="Remove link"
                >
                    <Unlink class="size-4" />
                </Button>
            </div>

            <Separator orientation="vertical" class="h-5" />

            <!-- Other Elements -->
            <div class="flex items-center gap-0.5">
                <Button
                    @click="toggleBlockquote"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn(isActive('blockquote'))"
                    :aria-pressed="isActive('blockquote')"
                    title="Quote"
                    aria-label="Quote"
                >
                    <Quote class="size-4" />
                </Button>
                <Button
                    @click="setHorizontalRule"
                    variant="ghost"
                    size="sm"
                    :class="toolbarBtn()"
                    title="Horizontal rule"
                    aria-label="Horizontal rule"
                >
                    <Minus class="size-4" />
                </Button>
            </div>

            <Separator orientation="vertical" class="h-5" />

            <!-- Table Controls -->
            <div class="flex items-center gap-0.5">
                <!-- Table Insert Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="sm" :class="toolbarBtn()" title="Insert table" aria-label="Insert table">
                            <TableIcon class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48">
                        <DropdownMenuItem @click="insertTable(2, 2, false)">
                            <Grid3x3 class="mr-2 size-4" />
                            2×2 table
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="insertTable(3, 3, true)">
                            <Grid3x3 class="mr-2 size-4" />
                            3×3 table (with header)
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="insertTable(4, 4, true)">
                            <Grid3x3 class="mr-2 size-4" />
                            4×4 table (with header)
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="insertTable(5, 3, true)">
                            <Grid3x3 class="mr-2 size-4" />
                            5×3 table (with header)
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- Table Edit Dropdown (only show when in table) -->
                <DropdownMenu v-if="isInTable">
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="sm" :class="toolbarBtn()" title="Table options" aria-label="Table options">
                            <MoreHorizontal class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <!-- Row Operations -->
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>
                                <Rows class="mr-2 size-4" />
                                Rows
                                <ChevronRight class="ml-auto size-4" />
                            </DropdownMenuSubTrigger>
                            <DropdownMenuSubContent>
                                <DropdownMenuItem @click="addRowBefore" :disabled="!canAddRowBefore()">
                                    <Plus class="mr-2 size-4" />
                                    Add row above
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="addRowAfter" :disabled="!canAddRowAfter()">
                                    <Plus class="mr-2 size-4" />
                                    Add row below
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem @click="deleteRow" :disabled="!canDeleteRow()" class="text-destructive">
                                    <Trash2 class="mr-2 size-4" />
                                    Delete row
                                </DropdownMenuItem>
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>

                        <!-- Column Operations -->
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>
                                <Columns class="mr-2 size-4" />
                                Columns
                                <ChevronRight class="ml-auto size-4" />
                            </DropdownMenuSubTrigger>
                            <DropdownMenuSubContent>
                                <DropdownMenuItem @click="addColumnBefore" :disabled="!canAddColumnBefore()">
                                    <Plus class="mr-2 size-4" />
                                    Add column left
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="addColumnAfter" :disabled="!canAddColumnAfter()">
                                    <Plus class="mr-2 size-4" />
                                    Add column right
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem @click="deleteColumn" :disabled="!canDeleteColumn()" class="text-destructive">
                                    <Trash2 class="mr-2 size-4" />
                                    Delete column
                                </DropdownMenuItem>
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>

                        <DropdownMenuSeparator />

                        <!-- Cell Operations -->
                        <DropdownMenuItem @click="mergeOrSplit" :disabled="!canMergeCells() && !canSplitCell()">
                            <Grid3x3 class="mr-2 size-4" />
                            {{ canMergeCells() ? 'Merge cells' : 'Split cell' }}
                        </DropdownMenuItem>

                        <!-- Header Operations -->
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="toggleHeaderRow">
                            <MoveHorizontal class="mr-2 size-4" />
                            Toggle header row
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="toggleHeaderColumn">
                            <MoveVertical class="mr-2 size-4" />
                            Toggle header column
                        </DropdownMenuItem>

                        <!-- Delete Table -->
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="deleteTable" :disabled="!canDeleteTable()" class="text-destructive">
                            <Trash2 class="mr-2 size-4" />
                            Delete table
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <Button @click="showImageDialog" variant="ghost" size="sm" :class="toolbarBtn()" title="Insert image" aria-label="Insert image">
                <ImageIcon class="size-4" />
            </Button>

            <Separator orientation="vertical" class="mx-1 h-5" />

            <Button
                @click="toggleHtmlSource"
                variant="ghost"
                size="sm"
                :class="toolbarBtn(showHtmlSource)"
                :aria-pressed="showHtmlSource"
                :title="showHtmlSource ? 'Back to rich text' : 'View HTML source'"
                :aria-label="showHtmlSource ? 'Back to rich text' : 'View HTML source'"
            >
                <Code2 class="size-4" />
            </Button>
        </div>

        <!-- Editor Content with Context Menu (fills the remaining pane height instead of a hardcoded 100vh) -->
        <div class="relative min-h-0 flex-1">
            <!-- Raw HTML source view -->
            <div v-if="showHtmlSource" class="html-source flex h-full flex-col">
                <div class="border-border bg-muted/50 flex items-center gap-2 border-b px-3 py-1.5">
                    <span class="text-muted-foreground text-xs font-medium">HTML source</span>
                    <span class="ml-auto flex items-center gap-1">
                        <Button
                            @click="discardHtmlSource"
                            variant="ghost"
                            size="sm"
                            class="text-muted-foreground hover:text-foreground h-7 px-2 text-xs"
                            title="Discard changes and go back to rich text"
                        >
                            Discard
                        </Button>
                        <Button
                            @click="toggleHtmlSource"
                            variant="outline"
                            size="sm"
                            class="h-7 px-2 text-xs"
                            title="Apply changes and go back to rich text"
                        >
                            Apply
                        </Button>
                    </span>
                </div>
                <textarea
                    v-model="htmlDraft"
                    spellcheck="false"
                    class="bg-background text-foreground w-full flex-1 resize-none p-4 font-mono text-sm leading-relaxed outline-none"
                    style="font-family: ui-monospace, SFMono-Regular, 'SF Mono', Consolas, 'Liberation Mono', Menlo, monospace"
                    placeholder="<p>HTML source…</p>"
                    aria-label="HTML source"
                ></textarea>
            </div>

            <EditorContent v-show="!showHtmlSource" :editor="editor" class="h-full overflow-auto" />

            <!-- Custom Context Menu -->
            <Teleport to="body">
                <div
                    v-if="showContextMenu"
                    class="border-border bg-popover fixed z-50 min-w-48 rounded-md border shadow-md"
                    :style="{
                        left: `${contextMenuPosition.x}px`,
                        top: `${contextMenuPosition.y}px`,
                    }"
                    @click.stop
                >
                    <!-- Link Context Menu -->
                    <div v-if="contextMenuType === 'link'" class="p-1">
                        <div class="border-border text-muted-foreground mb-1 border-b px-3 py-1.5 text-xs font-medium">Link</div>

                        <button type="button" @click="openLinkInNewTab" :class="menuItem">
                            <ExternalLink class="mr-2 size-4" />
                            Open link
                        </button>

                        <button type="button" @click="copyLinkUrl" :class="menuItem">
                            <Copy class="mr-2 size-4" />
                            Copy link
                        </button>

                        <button type="button" @click="openLinkDialog" :class="menuItem">
                            <LinkIcon class="mr-2 size-4" />
                            Edit link
                        </button>

                        <div class="border-border my-1 border-t"></div>

                        <button type="button" @click="removeLink" :class="[menuItem, 'text-destructive']">
                            <Unlink class="mr-2 size-4" />
                            Remove link
                        </button>
                    </div>

                    <!-- Text Context Menu -->
                    <div v-if="contextMenuType === 'text'" class="p-1">
                        <div class="border-border text-muted-foreground mb-1 border-b px-3 py-1.5 text-xs font-medium">Text</div>

                        <button type="button" @click="copyText" :class="menuItem">
                            <Copy class="mr-2 size-4" />
                            Copy
                        </button>

                        <button type="button" @click="cutText" :class="menuItem">
                            <Scissors class="mr-2 size-4" />
                            Cut
                        </button>

                        <button type="button" @click="pasteText" :class="menuItem">
                            <Clipboard class="mr-2 size-4" />
                            Paste
                        </button>

                        <div class="border-border my-1 border-t"></div>

                        <!-- Formatting Options -->
                        <button
                            type="button"
                            @click="
                                toggleBold();
                                showContextMenu = false;
                            "
                            :class="[menuItem, { 'bg-muted text-foreground': isActive('bold') }]"
                        >
                            <Bold class="mr-2 size-4" />
                            Bold
                        </button>

                        <button
                            type="button"
                            @click="
                                toggleItalic();
                                showContextMenu = false;
                            "
                            :class="[menuItem, { 'bg-muted text-foreground': isActive('italic') }]"
                        >
                            <Italic class="mr-2 size-4" />
                            Italic
                        </button>

                        <div class="border-border my-1 border-t"></div>

                        <!-- Insert Options -->
                        <button type="button" @click="openLinkDialog" :class="menuItem">
                            <LinkIcon class="mr-2 size-4" />
                            Insert link
                        </button>

                        <button type="button" @click="insertTable(3, 3, true)" :class="menuItem">
                            <TableIcon class="mr-2 size-4" />
                            Insert table
                        </button>

                        <button
                            type="button"
                            @click="
                                setHorizontalRule();
                                showContextMenu = false;
                            "
                            :class="menuItem"
                        >
                            <Minus class="mr-2 size-4" />
                            Insert divider
                        </button>
                    </div>

                    <!-- Table Context Menu -->
                    <div v-if="contextMenuType === 'table'" class="p-1">
                        <div class="border-border text-muted-foreground mb-1 border-b px-3 py-1.5 text-xs font-medium">Table</div>

                        <!-- Row Operations -->
                        <button type="button" @click="addRowBefore" :disabled="!canAddRowBefore()" :class="menuItem">
                            <Plus class="mr-2 size-4" />
                            Add row above
                        </button>

                        <button type="button" @click="addRowAfter" :disabled="!canAddRowAfter()" :class="menuItem">
                            <Plus class="mr-2 size-4" />
                            Add row below
                        </button>

                        <button type="button" @click="deleteRow" :disabled="!canDeleteRow()" :class="[menuItem, 'text-destructive']">
                            <Trash2 class="mr-2 size-4" />
                            Delete row
                        </button>

                        <div class="border-border my-1 border-t"></div>

                        <!-- Column Operations -->
                        <button type="button" @click="addColumnBefore" :disabled="!canAddColumnBefore()" :class="menuItem">
                            <Plus class="mr-2 size-4" />
                            Add column left
                        </button>

                        <button type="button" @click="addColumnAfter" :disabled="!canAddColumnAfter()" :class="menuItem">
                            <Plus class="mr-2 size-4" />
                            Add column right
                        </button>

                        <button type="button" @click="deleteColumn" :disabled="!canDeleteColumn()" :class="[menuItem, 'text-destructive']">
                            <Trash2 class="mr-2 size-4" />
                            Delete column
                        </button>

                        <div class="border-border my-1 border-t"></div>

                        <!-- Cell Operations -->
                        <button type="button" @click="mergeOrSplit" :disabled="!canMergeCells() && !canSplitCell()" :class="menuItem">
                            <Grid3x3 class="mr-2 size-4" />
                            {{ canMergeCells() ? 'Merge cells' : 'Split cell' }}
                        </button>

                        <div class="border-border my-1 border-t"></div>

                        <!-- Header Operations -->
                        <button type="button" @click="toggleHeaderRow" :class="menuItem">
                            <MoveHorizontal class="mr-2 size-4" />
                            Toggle header row
                        </button>

                        <button type="button" @click="toggleHeaderColumn" :class="menuItem">
                            <MoveVertical class="mr-2 size-4" />
                            Toggle header column
                        </button>

                        <div class="border-border my-1 border-t"></div>

                        <!-- Delete Table -->
                        <button type="button" @click="deleteTable" :disabled="!canDeleteTable()" :class="[menuItem, 'text-destructive']">
                            <Trash2 class="mr-2 size-4" />
                            Delete table
                        </button>
                    </div>
                </div>
            </Teleport>
        </div>

        <!-- Link Dialog -->
        <Dialog
            :open="linkDialogOpen"
            @update:open="
                (value) => {
                    if (!value) closeLinkDialog();
                }
            "
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ isEditingLink ? 'Edit link' : 'Insert link' }}</DialogTitle>
                    <DialogDescription>
                        {{
                            isEditingLink
                                ? 'Update the URL and settings for this link.'
                                : 'Add a hyperlink to your text. Enter the URL and choose how it opens.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div v-if="!isEditingLink" class="space-y-2">
                        <Label for="linkText">Link text</Label>
                        <Input id="linkText" v-model="linkText" placeholder="Enter link text" autocomplete="off" />
                    </div>

                    <div class="space-y-2">
                        <Label for="linkUrl">URL</Label>
                        <Input id="linkUrl" v-model="linkUrl" type="url" placeholder="https://example.com" autocomplete="off" required />
                    </div>

                    <div class="space-y-2">
                        <Label for="linkTarget">Open in</Label>
                        <select
                            id="linkTarget"
                            v-model="linkTarget"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring/50 flex h-10 w-full cursor-pointer rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="_self">Same tab</option>
                            <option value="_blank">New tab</option>
                        </select>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="closeLinkDialog"> Cancel </Button>
                    <Button @click="insertOrUpdateLink" :disabled="!linkUrl.trim()">
                        {{ isEditingLink ? 'Update link' : 'Insert link' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ImageUploadDialog v-model:open="imageDialogOpen" :noteId="props.noteId" @image-selected="handleImageSelected" />
    </div>
</template>
