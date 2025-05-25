<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Placeholder from '@tiptap/extension-placeholder'
import Typography from '@tiptap/extension-typography'
import TaskList from '@tiptap/extension-task-list'
import TaskItem from '@tiptap/extension-task-item'
import Highlight from '@tiptap/extension-highlight'
import Table from '@tiptap/extension-table'
import TableRow from '@tiptap/extension-table-row'
import TableCell from '@tiptap/extension-table-cell'
import TableHeader from '@tiptap/extension-table-header'
import TextAlign from '@tiptap/extension-text-align'
import Underline from '@tiptap/extension-underline'
import { ref, watch, onBeforeUnmount, computed, nextTick, onMounted } from 'vue'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu'
import {
    Bold,
    Italic,
    Underline as UnderlineIcon,
    Strikethrough,
    Code,
    Heading1,
    Heading2,
    Heading3,
    List,
    ListOrdered,
    Quote,
    Minus,
    Table as TableIcon,
    AlignLeft,
    AlignCenter,
    AlignRight,
    Highlighter,
    CheckSquare,
    Undo,
    Redo,
    Plus,
    Trash2,
    MoreHorizontal,
    Columns,
    Rows,
    Grid3x3,
    ChevronRight,
    Copy,
    MoveVertical,
    MoveHorizontal,
    Scissors,
    Clipboard
} from 'lucide-vue-next'

interface Props {
    modelValue: string
    placeholder?: string
    editable?: boolean
    class?: string
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Start writing your note...',
    editable: true,
    class: ''
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
    'focus': []
    'blur': []
    'save': []
}>()

// Context menu state
const showContextMenu = ref(false)
const contextMenuPosition = ref({ x: 0, y: 0 })
const contextMenuType = ref<'text' | 'table'>('text')

const editor = useEditor({
    content: props.modelValue,
    editable: props.editable,
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [1, 2, 3]
            }
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
    ],
    editorProps: {
        attributes: {
            class: 'tiptap-prose focus:outline-none p-4',
        },
        handleDOMEvents: {
            contextmenu: (view, event) => {
                if (!props.editable) return false

                event.preventDefault()

                // Determine context menu type based on what was clicked
                const target = event.target as HTMLElement
                const isInTable = target.closest('table') !== null

                contextMenuType.value = isInTable ? 'table' : 'text'
                contextMenuPosition.value = { x: event.clientX, y: event.clientY }
                showContextMenu.value = true

                return true
            }
        }
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML())
    },
    onFocus: () => {
        emit('focus')
    },
    onBlur: () => {
        emit('blur')
    },
})

// Watch for external content changes
watch(() => props.modelValue, (newValue) => {
    if (editor.value && editor.value.getHTML() !== newValue) {
        editor.value.commands.setContent(newValue)
    }
})

// Watch for editable changes
watch(() => props.editable, (newValue) => {
    if (editor.value) {
        editor.value.setEditable(newValue)
    }
})

onBeforeUnmount(() => {
    if (editor.value) {
        editor.value.destroy()
    }
})

// Hide context menu when clicking elsewhere
onMounted(() => {
    const hideContextMenu = () => {
        showContextMenu.value = false
    }

    // Handle keyboard shortcuts
    const handleKeydown = (event: KeyboardEvent) => {
        // Ctrl+S or Cmd+S to save
        if ((event.ctrlKey || event.metaKey) && event.key === 's') {
            event.preventDefault()
            emit('save')
        }
    }

    document.addEventListener('click', hideContextMenu)
    document.addEventListener('scroll', hideContextMenu)
    document.addEventListener('keydown', handleKeydown)

    return () => {
        document.removeEventListener('click', hideContextMenu)
        document.removeEventListener('scroll', hideContextMenu)
        document.removeEventListener('keydown', handleKeydown)
    }
})

// Check if cursor is in a table
const isInTable = computed(() => {
    if (!editor.value) return false
    return editor.value.isActive('table')
})

// Toolbar actions
const toggleBold = () => editor.value?.chain().focus().toggleBold().run()
const toggleItalic = () => editor.value?.chain().focus().toggleItalic().run()
const toggleUnderline = () => editor.value?.chain().focus().toggleUnderline().run()
const toggleStrike = () => editor.value?.chain().focus().toggleStrike().run()
const toggleCode = () => editor.value?.chain().focus().toggleCode().run()
const toggleHighlight = () => editor.value?.chain().focus().toggleHighlight().run()

const setHeading = (level: 1 | 2 | 3) => {
    editor.value?.chain().focus().toggleHeading({ level }).run()
}

const toggleBulletList = () => editor.value?.chain().focus().toggleBulletList().run()
const toggleOrderedList = () => editor.value?.chain().focus().toggleOrderedList().run()
const toggleTaskList = () => editor.value?.chain().focus().toggleTaskList().run()
const toggleBlockquote = () => editor.value?.chain().focus().toggleBlockquote().run()
const setHorizontalRule = () => editor.value?.chain().focus().setHorizontalRule().run()

// Enhanced Table Functions
const insertTable = (rows = 3, cols = 3, withHeaderRow = true) => {
    editor.value?.chain().focus().insertTable({ rows, cols, withHeaderRow }).run()
    showContextMenu.value = false
}

const addColumnBefore = () => {
    editor.value?.chain().focus().addColumnBefore().run()
    showContextMenu.value = false
}

const addColumnAfter = () => {
    editor.value?.chain().focus().addColumnAfter().run()
    showContextMenu.value = false
}

const deleteColumn = () => {
    editor.value?.chain().focus().deleteColumn().run()
    showContextMenu.value = false
}

const addRowBefore = () => {
    editor.value?.chain().focus().addRowBefore().run()
    showContextMenu.value = false
}

const addRowAfter = () => {
    editor.value?.chain().focus().addRowAfter().run()
    showContextMenu.value = false
}

const deleteRow = () => {
    editor.value?.chain().focus().deleteRow().run()
    showContextMenu.value = false
}

const deleteTable = () => {
    editor.value?.chain().focus().deleteTable().run()
    showContextMenu.value = false
}

const mergeCells = () => {
    editor.value?.chain().focus().mergeCells().run()
    showContextMenu.value = false
}

const splitCell = () => {
    editor.value?.chain().focus().splitCell().run()
    showContextMenu.value = false
}

const toggleHeaderColumn = () => {
    editor.value?.chain().focus().toggleHeaderColumn().run()
    showContextMenu.value = false
}

const toggleHeaderRow = () => {
    editor.value?.chain().focus().toggleHeaderRow().run()
    showContextMenu.value = false
}

const mergeOrSplit = () => {
    if (editor.value?.can().mergeCells()) {
        mergeCells()
    } else if (editor.value?.can().splitCell()) {
        splitCell()
    }
}

const setTextAlign = (alignment: 'left' | 'center' | 'right') => {
    editor.value?.chain().focus().setTextAlign(alignment).run()
}

const undo = () => editor.value?.chain().focus().undo().run()
const redo = () => editor.value?.chain().focus().redo().run()

// Copy/Cut/Paste functions for context menu
const copyText = async () => {
    try {
        // Get selected text from editor
        const { from, to } = editor.value.state.selection
        const selectedText = editor.value.state.doc.textBetween(from, to, ' ')

        if (selectedText) {
            await navigator.clipboard.writeText(selectedText)
        } else {
            // Fallback to document.execCommand for older browsers
            document.execCommand('copy')
        }
    } catch (error) {
        // Fallback to document.execCommand
        document.execCommand('copy')
    }
    showContextMenu.value = false
}

const cutText = async () => {
    try {
        // Get selected text from editor
        const { from, to } = editor.value.state.selection
        const selectedText = editor.value.state.doc.textBetween(from, to, ' ')

        if (selectedText) {
            await navigator.clipboard.writeText(selectedText)
            // Delete the selected content
            editor.value.chain().focus().deleteSelection().run()
        } else {
            // Fallback to document.execCommand for older browsers
            document.execCommand('cut')
        }
    } catch (error) {
        // Fallback to document.execCommand
        document.execCommand('cut')
    }
    showContextMenu.value = false
}

const pasteText = async () => {
    try {
        // Use modern Clipboard API
        if (navigator.clipboard && navigator.clipboard.readText) {
            const text = await navigator.clipboard.readText()
            if (text) {
                // Insert the text at current cursor position
                editor.value.chain().focus().insertContent(text).run()
            }
        } else {
            // Fallback: Focus editor and let browser handle paste
            editor.value.chain().focus().run()
            // Create a temporary textarea to handle paste
            const textarea = document.createElement('textarea')
            textarea.style.position = 'fixed'
            textarea.style.left = '-9999px'
            textarea.style.top = '-9999px'
            document.body.appendChild(textarea)
            textarea.focus()

            // Listen for paste event
            const handlePaste = (e) => {
                e.preventDefault()
                const pastedText = e.clipboardData?.getData('text/plain')
                if (pastedText) {
                    editor.value.chain().focus().insertContent(pastedText).run()
                }
                document.body.removeChild(textarea)
                textarea.removeEventListener('paste', handlePaste)
            }

            textarea.addEventListener('paste', handlePaste)
            document.execCommand('paste')
        }
    } catch (error) {
        console.warn('Paste failed:', error)
        // Final fallback - just focus the editor
        editor.value.chain().focus().run()
    }
    showContextMenu.value = false
}

// Check if commands are active
const isActive = (command: string, options?: any) => {
    return editor.value?.isActive(command, options) || false
}

const canUndo = () => editor.value?.can().undo() || false
const canRedo = () => editor.value?.can().redo() || false

// Table state checks
const canMergeCells = () => editor.value?.can().mergeCells() || false
const canSplitCell = () => editor.value?.can().splitCell() || false
const canAddColumnBefore = () => editor.value?.can().addColumnBefore() || false
const canAddColumnAfter = () => editor.value?.can().addColumnAfter() || false
const canDeleteColumn = () => editor.value?.can().deleteColumn() || false
const canAddRowBefore = () => editor.value?.can().addRowBefore() || false
const canAddRowAfter = () => editor.value?.can().addRowAfter() || false
const canDeleteRow = () => editor.value?.can().deleteRow() || false
const canDeleteTable = () => editor.value?.can().deleteTable() || false
</script>

<template>
    <div :class="['tiptap-editor border rounded-lg', props.class]" class="relative">
        <!-- Toolbar -->
        <div v-if="editable" class="border-b p-2 flex flex-wrap gap-1 bg-muted/20">
            <!-- History -->
            <div class="flex items-center space-x-1">
                <Button
                    @click="undo"
                    :disabled="!canUndo()"
                    variant="ghost"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Undo"
                >
                    <Undo class="h-4 w-4" />
                </Button>
                <Button
                    @click="redo"
                    :disabled="!canRedo()"
                    variant="ghost"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Redo"
                >
                    <Redo class="h-4 w-4" />
                </Button>
            </div>

            <Separator orientation="vertical" class="h-8" />

            <!-- Text Formatting -->
            <div class="flex items-center space-x-1">
                <Button
                    @click="toggleBold"
                    :variant="isActive('bold') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Bold"
                >
                    <Bold class="h-4 w-4" />
                </Button>
                <Button
                    @click="toggleItalic"
                    :variant="isActive('italic') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Italic"
                >
                    <Italic class="h-4 w-4" />
                </Button>
                <Button
                    @click="toggleUnderline"
                    :variant="isActive('underline') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Underline"
                >
                    <UnderlineIcon class="h-4 w-4" />
                </Button>
                <Button
                    @click="toggleStrike"
                    :variant="isActive('strike') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Strikethrough"
                >
                    <Strikethrough class="h-4 w-4" />
                </Button>
                <Button
                    @click="toggleCode"
                    :variant="isActive('code') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Code"
                >
                    <Code class="h-4 w-4" />
                </Button>
                <Button
                    @click="toggleHighlight"
                    :variant="isActive('highlight') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Highlight"
                >
                    <Highlighter class="h-4 w-4" />
                </Button>
            </div>

            <Separator orientation="vertical" class="h-8" />

            <!-- Headings -->
            <div class="flex items-center space-x-1">
                <Button
                    @click="setHeading(1)"
                    :variant="isActive('heading', { level: 1 }) ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Heading 1"
                >
                    <Heading1 class="h-4 w-4" />
                </Button>
                <Button
                    @click="setHeading(2)"
                    :variant="isActive('heading', { level: 2 }) ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Heading 2"
                >
                    <Heading2 class="h-4 w-4" />
                </Button>
                <Button
                    @click="setHeading(3)"
                    :variant="isActive('heading', { level: 3 }) ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Heading 3"
                >
                    <Heading3 class="h-4 w-4" />
                </Button>
            </div>

            <Separator orientation="vertical" class="h-8" />

            <!-- Lists -->
            <div class="flex items-center space-x-1">
                <Button
                    @click="toggleBulletList"
                    :variant="isActive('bulletList') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Bullet List"
                >
                    <List class="h-4 w-4" />
                </Button>
                <Button
                    @click="toggleOrderedList"
                    :variant="isActive('orderedList') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Numbered List"
                >
                    <ListOrdered class="h-4 w-4" />
                </Button>
                <Button
                    @click="toggleTaskList"
                    :variant="isActive('taskList') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Task List"
                >
                    <CheckSquare class="h-4 w-4" />
                </Button>
            </div>

            <Separator orientation="vertical" class="h-8" />

            <!-- Alignment -->
            <div class="flex items-center space-x-1">
                <Button
                    @click="setTextAlign('left')"
                    :variant="isActive({ textAlign: 'left' }) ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Align Left"
                >
                    <AlignLeft class="h-4 w-4" />
                </Button>
                <Button
                    @click="setTextAlign('center')"
                    :variant="isActive({ textAlign: 'center' }) ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Align Center"
                >
                    <AlignCenter class="h-4 w-4" />
                </Button>
                <Button
                    @click="setTextAlign('right')"
                    :variant="isActive({ textAlign: 'right' }) ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Align Right"
                >
                    <AlignRight class="h-4 w-4" />
                </Button>
            </div>

            <Separator orientation="vertical" class="h-8" />

            <!-- Other Elements -->
            <div class="flex items-center space-x-1">
                <Button
                    @click="toggleBlockquote"
                    :variant="isActive('blockquote') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Quote"
                >
                    <Quote class="h-4 w-4" />
                </Button>
                <Button
                    @click="setHorizontalRule"
                    variant="ghost"
                    size="sm"
                    class="h-8 w-8 p-0"
                    title="Horizontal Rule"
                >
                    <Minus class="h-4 w-4" />
                </Button>
            </div>

            <Separator orientation="vertical" class="h-8" />

            <!-- Table Controls -->
            <div class="flex items-center space-x-1">
                <!-- Table Insert Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 w-8 p-0"
                            title="Insert Table"
                        >
                            <TableIcon class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48">
                        <DropdownMenuItem @click="insertTable(2, 2, false)">
                            <Grid3x3 class="h-4 w-4 mr-2" />
                            2×2 Table
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="insertTable(3, 3, true)">
                            <Grid3x3 class="h-4 w-4 mr-2" />
                            3×3 Table (with header)
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="insertTable(4, 4, true)">
                            <Grid3x3 class="h-4 w-4 mr-2" />
                            4×4 Table (with header)
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="insertTable(5, 3, true)">
                            <Grid3x3 class="h-4 w-4 mr-2" />
                            5×3 Table (with header)
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- Table Edit Dropdown (only show when in table) -->
                <DropdownMenu v-if="isInTable">
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 w-8 p-0"
                            title="Table Options"
                        >
                            <MoreHorizontal class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <!-- Row Operations -->
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>
                                <Rows class="h-4 w-4 mr-2" />
                                Row Operations
                                <ChevronRight class="h-4 w-4 ml-auto" />
                            </DropdownMenuSubTrigger>
                            <DropdownMenuSubContent>
                                <DropdownMenuItem
                                    @click="addRowBefore"
                                    :disabled="!canAddRowBefore()"
                                >
                                    <Plus class="h-4 w-4 mr-2" />
                                    Add Row Above
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    @click="addRowAfter"
                                    :disabled="!canAddRowAfter()"
                                >
                                    <Plus class="h-4 w-4 mr-2" />
                                    Add Row Below
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    @click="deleteRow"
                                    :disabled="!canDeleteRow()"
                                    class="text-destructive"
                                >
                                    <Trash2 class="h-4 w-4 mr-2" />
                                    Delete Row
                                </DropdownMenuItem>
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>

                        <!-- Column Operations -->
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>
                                <Columns class="h-4 w-4 mr-2" />
                                Column Operations
                                <ChevronRight class="h-4 w-4 ml-auto" />
                            </DropdownMenuSubTrigger>
                            <DropdownMenuSubContent>
                                <DropdownMenuItem
                                    @click="addColumnBefore"
                                    :disabled="!canAddColumnBefore()"
                                >
                                    <Plus class="h-4 w-4 mr-2" />
                                    Add Column Left
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    @click="addColumnAfter"
                                    :disabled="!canAddColumnAfter()"
                                >
                                    <Plus class="h-4 w-4 mr-2" />
                                    Add Column Right
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    @click="deleteColumn"
                                    :disabled="!canDeleteColumn()"
                                    class="text-destructive"
                                >
                                    <Trash2 class="h-4 w-4 mr-2" />
                                    Delete Column
                                </DropdownMenuItem>
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>

                        <DropdownMenuSeparator />

                        <!-- Cell Operations -->
                        <DropdownMenuItem
                            @click="mergeOrSplit"
                            :disabled="!canMergeCells() && !canSplitCell()"
                        >
                            <Grid3x3 class="h-4 w-4 mr-2" />
                            {{ canMergeCells() ? 'Merge Cells' : 'Split Cell' }}
                        </DropdownMenuItem>

                        <!-- Header Operations -->
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="toggleHeaderRow">
                            <MoveHorizontal class="h-4 w-4 mr-2" />
                            Toggle Header Row
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="toggleHeaderColumn">
                            <MoveVertical class="h-4 w-4 mr-2" />
                            Toggle Header Column
                        </DropdownMenuItem>

                        <!-- Delete Table -->
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            @click="deleteTable"
                            :disabled="!canDeleteTable()"
                            class="text-destructive"
                        >
                            <Trash2 class="h-4 w-4 mr-2" />
                            Delete Table
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Editor Content with Context Menu -->
        <div class="relative h-screen">
            <EditorContent
                :editor="editor"
                class="overflow-auto h-full"/>

            <!-- Custom Context Menu -->
            <Teleport to="body">
                <div
                    v-if="showContextMenu"
                    class="fixed bg-popover border rounded-md shadow-lg z-50 min-w-48"
                    :style="{
                        left: `${contextMenuPosition.x}px`,
                        top: `${contextMenuPosition.y}px`
                    }"
                    @click.stop
                >
                    <!-- Text Context Menu -->
                    <div v-if="contextMenuType === 'text'" class="p-1">
                        <div class="px-3 py-2 text-sm text-muted-foreground border-b mb-1">
                            Text Actions
                        </div>

                        <button @click="copyText" class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm">
                            <Copy class="h-4 w-4 mr-2" />
                            Copy
                        </button>

                        <button @click="cutText" class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm">
                            <Scissors class="h-4 w-4 mr-2" />
                            Cut
                        </button>

                        <button @click="pasteText" class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm">
                            <Clipboard class="h-4 w-4 mr-2" />
                            Paste
                        </button>

                        <div class="border-t my-1"></div>

                        <!-- Formatting Options -->
                        <button @click="toggleBold(); showContextMenu = false"
                                class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm"
                                :class="{ 'bg-accent': isActive('bold') }">
                            <Bold class="h-4 w-4 mr-2" />
                            Bold
                        </button>

                        <button @click="toggleItalic(); showContextMenu = false"
                                class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm"
                                :class="{ 'bg-accent': isActive('italic') }">
                            <Italic class="h-4 w-4 mr-2" />
                            Italic
                        </button>

                        <div class="border-t my-1"></div>

                        <!-- Insert Options -->
                        <button @click="insertTable(3, 3, true)" class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm">
                            <TableIcon class="h-4 w-4 mr-2" />
                            Insert Table
                        </button>

                        <button @click="setHorizontalRule(); showContextMenu = false" class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm">
                            <Minus class="h-4 w-4 mr-2" />
                            Insert Divider
                        </button>
                    </div>

                    <!-- Table Context Menu -->
                    <div v-if="contextMenuType === 'table'" class="p-1">
                        <div class="px-3 py-2 text-sm text-muted-foreground border-b mb-1">
                            Table Actions
                        </div>

                        <!-- Row Operations -->
                        <button @click="addRowBefore"
                                :disabled="!canAddRowBefore()"
                                class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm disabled:opacity-50">
                            <Plus class="h-4 w-4 mr-2" />
                            Add Row Above
                        </button>

                        <button @click="addRowAfter"
                                :disabled="!canAddRowAfter()"
                                class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm disabled:opacity-50">
                            <Plus class="h-4 w-4 mr-2" />
                            Add Row Below
                        </button>

                        <button @click="deleteRow"
                                :disabled="!canDeleteRow()"
                                class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm text-destructive disabled:opacity-50">
                            <Trash2 class="h-4 w-4 mr-2" />
                            Delete Row
                        </button>

                        <div class="border-t my-1"></div>

                        <!-- Column Operations -->
                        <button @click="addColumnBefore"
                                :disabled="!canAddColumnBefore()"
                                class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm disabled:opacity-50">
                            <Plus class="h-4 w-4 mr-2" />
                            Add Column Left
                        </button>

                        <button @click="addColumnAfter"
                                :disabled="!canAddColumnAfter()"
                                class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm disabled:opacity-50">
                            <Plus class="h-4 w-4 mr-2" />
                            Add Column Right
                        </button>

                        <button @click="deleteColumn"
                                :disabled="!canDeleteColumn()"
                                class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm text-destructive disabled:opacity-50">
                            <Trash2 class="h-4 w-4 mr-2" />
                            Delete Column
                        </button>

                        <div class="border-t my-1"></div>

                        <!-- Cell Operations -->
                        <button @click="mergeOrSplit"
                                :disabled="!canMergeCells() && !canSplitCell()"
                                class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm disabled:opacity-50">
                            <Grid3x3 class="h-4 w-4 mr-2" />
                            {{ canMergeCells() ? 'Merge Cells' : 'Split Cell' }}
                        </button>

                        <div class="border-t my-1"></div>

                        <!-- Header Operations -->
                        <button @click="toggleHeaderRow" class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm">
                            <MoveHorizontal class="h-4 w-4 mr-2" />
                            Toggle Header Row
                        </button>

                        <button @click="toggleHeaderColumn" class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm">
                            <MoveVertical class="h-4 w-4 mr-2" />
                            Toggle Header Column
                        </button>

                        <div class="border-t my-1"></div>

                        <!-- Delete Table -->
                        <button @click="deleteTable"
                                :disabled="!canDeleteTable()"
                                class="w-full flex items-center px-3 py-2 text-sm hover:bg-accent rounded-sm text-destructive disabled:opacity-50">
                            <Trash2 class="h-4 w-4 mr-2" />
                            Delete Table
                        </button>
                    </div>
                </div>
            </Teleport>
        </div>
    </div>
</template>
