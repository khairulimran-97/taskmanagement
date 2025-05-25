<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3'
import { Extension } from '@tiptap/core'
import Suggestion from '@tiptap/suggestion'
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

// Create slash commands extension
const SlashCommands = Extension.create({
    name: 'slashCommands',

    addOptions() {
        return {
            suggestion: {
                char: '/',
                allowSpaces: false,
                startOfLine: false,
                command: ({ editor, range, props }) => {
                    props.command({ editor, range })
                },
            },
        }
    },

    addProseMirrorPlugins() {
        return [
            Suggestion({
                editor: this.editor,
                ...this.options.suggestion,
            }),
        ]
    },
})

// Command items for the slash menu
const commandItems = [
    {
        title: 'Heading 1',
        description: 'Big section heading',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).setHeading({ level: 1 }).run()
        },
    },
    {
        title: 'Heading 2',
        description: 'Medium section heading',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).setHeading({ level: 2 }).run()
        },
    },
    {
        title: 'Heading 3',
        description: 'Small section heading',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).setHeading({ level: 3 }).run()
        },
    },
    {
        title: 'Bullet List',
        description: 'Create a simple bullet list',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).toggleBulletList().run()
        },
    },
    {
        title: 'Numbered List',
        description: 'Create a list with numbering',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).toggleOrderedList().run()
        },
    },
    {
        title: 'Task List',
        description: 'Track tasks with a checklist',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).toggleTaskList().run()
        },
    },
    {
        title: 'Quote',
        description: 'Capture a quote',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).toggleBlockquote().run()
        },
    },
    {
        title: 'Code Block',
        description: 'Capture a code snippet',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).toggleCodeBlock().run()
        },
    },
    {
        title: 'Table',
        description: 'Insert a table',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
        },
    },
    {
        title: 'Divider',
        description: 'Visually divide blocks',
        command: ({ editor, range }) => {
            editor.chain().focus().deleteRange(range).setHorizontalRule().run()
        },
    },
]

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
        SlashCommands.configure({
            suggestion: {
                items: ({ query }) => {
                    return commandItems.filter(item =>
                        item.title.toLowerCase().includes(query.toLowerCase()) ||
                        item.description.toLowerCase().includes(query.toLowerCase())
                    ).slice(0, 10)
                },
                render: () => {
                    let component
                    let popup

                    return {
                        onStart: (props) => {
                            popup = document.createElement('div')
                            popup.className = 'tiptap-slash-menu'

                            const renderMenu = () => {
                                const items = props.items
                                let selectedIndex = 0

                                const updateMenu = () => {
                                    popup.innerHTML = `
                                        <div class="menu-content">
                                            ${items.map((item, index) => `
                                                <div class="menu-item ${index === selectedIndex ? 'selected' : ''}" data-index="${index}">
                                                    <div class="menu-item-icon">
                                                        ${getIconSvg(item.title)}
                                                    </div>
                                                    <div class="menu-item-content">
                                                        <span class="menu-item-title">${item.title}</span>
                                                        <span class="menu-item-description">${item.description}</span>
                                                    </div>
                                                </div>
                                            `).join('')}
                                        </div>
                                        <div class="menu-footer">
                                            <div class="menu-footer-content">
                                                <span>↑↓ to navigate</span>
                                                <span>↵ to select</span>
                                            </div>
                                        </div>
                                    `

                                    // Add click listeners
                                    popup.querySelectorAll('[data-index]').forEach((el, index) => {
                                        el.addEventListener('click', () => {
                                            props.command(items[index])
                                        })
                                        el.addEventListener('mouseenter', () => {
                                            selectedIndex = index
                                            updateMenu()
                                        })
                                    })
                                }

                                const getIconSvg = (title) => {
                                    const icons = {
                                        'Heading 1': '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12h12"/><path d="M6 20V4"/><path d="M18 20V4"/><path d="M21 4h-3"/><path d="M21 20h-3"/><path d="M3 4h3"/><path d="M3 20h3"/></svg>',
                                        'Heading 2': '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12h12"/><path d="M6 20V4"/><path d="M18 20V4"/><path d="M21 4h-3"/><path d="M21 20h-3"/><path d="M3 4h3"/><path d="M3 20h3"/></svg>',
                                        'Heading 3': '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12h12"/><path d="M6 20V4"/><path d="M18 20V4"/><path d="M21 4h-3"/><path d="M21 20h-3"/><path d="M3 4h3"/><path d="M3 20h3"/></svg>',
                                        'Bullet List': '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></svg>',
                                        'Numbered List': '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" x2="21" y1="6" y2="6"/><line x1="10" x2="21" y1="12" y2="12"/><line x1="10" x2="21" y1="18" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>',
                                        'Task List': '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>',
                                        'Quote': '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>',
                                        'Code Block': '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16,18 22,12 16,6"/><polyline points="8,6 2,12 8,18"/></svg>',
                                        'Table': '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"/><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/></svg>',
                                        'Divider': '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>',
                                    }
                                    return icons[title] || '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14,2 14,8 20,8"/></svg>'
                                }

                                updateMenu()

                                return {
                                    selectedIndex,
                                    upHandler: () => {
                                        selectedIndex = ((selectedIndex + items.length - 1) % items.length)
                                        updateMenu()
                                    },
                                    downHandler: () => {
                                        selectedIndex = ((selectedIndex + 1) % items.length)
                                        updateMenu()
                                    },
                                    enterHandler: () => {
                                        props.command(items[selectedIndex])
                                    }
                                }
                            }

                            component = renderMenu()
                            document.body.appendChild(popup)

                            // Position the popup
                            const { clientRect } = props
                            if (clientRect) {
                                popup.style.position = 'absolute'
                                popup.style.top = `${clientRect().bottom + window.scrollY}px`
                                popup.style.left = `${clientRect().left + window.scrollX}px`
                                popup.style.zIndex = '50'
                            }
                        },

                        onUpdate: (props) => {
                            // Update position
                            const { clientRect } = props
                            if (clientRect && popup) {
                                popup.style.top = `${clientRect().bottom + window.scrollY}px`
                                popup.style.left = `${clientRect().left + window.scrollX}px`
                            }
                        },

                        onKeyDown: (props) => {
                            if (props.event.key === 'Escape') {
                                return true
                            }

                            if (props.event.key === 'ArrowUp') {
                                component.upHandler()
                                return true
                            }

                            if (props.event.key === 'ArrowDown') {
                                component.downHandler()
                                return true
                            }

                            if (props.event.key === 'Enter') {
                                component.enterHandler()
                                return true
                            }

                            return false
                        },

                        onExit: () => {
                            if (popup && popup.parentNode) {
                                popup.parentNode.removeChild(popup)
                            }
                        },
                    }
                },
            },
        }),
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
        <div class="min-h-[200px] relative">
            <EditorContent :editor="editor" />

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
