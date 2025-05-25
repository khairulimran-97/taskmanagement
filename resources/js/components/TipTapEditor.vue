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
import { ref, watch, onBeforeUnmount, createApp } from 'vue'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
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
    FileText
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
}>()

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
                            popup.className = 'z-50 min-w-[16rem] max-w-[20rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-lg'

                            const renderMenu = () => {
                                const items = props.items
                                let selectedIndex = 0

                                const updateMenu = () => {
                                    popup.innerHTML = `
                                        <div class="max-h-[300px] overflow-y-auto">
                                            ${items.map((item, index) => `
                                                <div class="relative flex cursor-pointer select-none items-center rounded-sm px-3 py-2 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground ${index === selectedIndex ? 'bg-accent text-accent-foreground' : ''}" data-index="${index}">
                                                    <div class="mr-3 h-4 w-4 flex-shrink-0">
                                                        ${getIconSvg(item.title)}
                                                    </div>
                                                    <div class="flex flex-col flex-1 min-w-0">
                                                        <span class="font-medium truncate">${item.title}</span>
                                                        <span class="text-xs text-muted-foreground truncate">${item.description}</span>
                                                    </div>
                                                </div>
                                            `).join('')}
                                        </div>
                                        <div class="border-t p-2 text-xs text-muted-foreground">
                                            <div class="flex items-center justify-between">
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
            class: 'prose prose-sm sm:prose lg:prose-lg xl:prose-2xl mx-auto max-w-none focus:outline-none p-4',
        },
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

const insertTable = () => editor.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()

const setTextAlign = (alignment: 'left' | 'center' | 'right') => {
    editor.value?.chain().focus().setTextAlign(alignment).run()
}

const undo = () => editor.value?.chain().focus().undo().run()
const redo = () => editor.value?.chain().focus().redo().run()

// Check if commands are active
const isActive = (command: string, options?: any) => {
    return editor.value?.isActive(command, options) || false
}

const canUndo = () => editor.value?.can().undo() || false
const canRedo = () => editor.value?.can().redo() || false
</script>

<template>
    <div :class="['tiptap-editor border rounded-lg', props.class]">
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
                >
                    <Undo class="h-4 w-4" />
                </Button>
                <Button
                    @click="redo"
                    :disabled="!canRedo()"
                    variant="ghost"
                    size="sm"
                    class="h-8 w-8 p-0"
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
                >
                    <Bold class="h-4 w-4" />
                </Button>
                <Button
                    @click="toggleItalic"
                    :variant="isActive('italic') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                >
                    <Italic class="h-4 w-4" />
                </Button>
                <Button
                    @click="toggleUnderline"
                    :variant="isActive('underline') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                >
                    <UnderlineIcon class="h-4 w-4" />
                </Button>
                <Button
                    @click="toggleStrike"
                    :variant="isActive('strike') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                >
                    <Strikethrough class="h-4 w-4" />
                </Button>
                <Button
                    @click="toggleCode"
                    :variant="isActive('code') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                >
                    <Code class="h-4 w-4" />
                </Button>
                <Button
                    @click="toggleHighlight"
                    :variant="isActive('highlight') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
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
                >
                    <Heading1 class="h-4 w-4" />
                </Button>
                <Button
                    @click="setHeading(2)"
                    :variant="isActive('heading', { level: 2 }) ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                >
                    <Heading2 class="h-4 w-4" />
                </Button>
                <Button
                    @click="setHeading(3)"
                    :variant="isActive('heading', { level: 3 }) ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
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
                >
                    <List class="h-4 w-4" />
                </Button>
                <Button
                    @click="toggleOrderedList"
                    :variant="isActive('orderedList') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                >
                    <ListOrdered class="h-4 w-4" />
                </Button>
                <Button
                    @click="toggleTaskList"
                    :variant="isActive('taskList') ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
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
                >
                    <AlignLeft class="h-4 w-4" />
                </Button>
                <Button
                    @click="setTextAlign('center')"
                    :variant="isActive({ textAlign: 'center' }) ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
                >
                    <AlignCenter class="h-4 w-4" />
                </Button>
                <Button
                    @click="setTextAlign('right')"
                    :variant="isActive({ textAlign: 'right' }) ? 'default' : 'ghost'"
                    size="sm"
                    class="h-8 w-8 p-0"
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
                >
                    <Quote class="h-4 w-4" />
                </Button>
                <Button
                    @click="setHorizontalRule"
                    variant="ghost"
                    size="sm"
                    class="h-8 w-8 p-0"
                >
                    <Minus class="h-4 w-4" />
                </Button>
                <Button
                    @click="insertTable"
                    variant="ghost"
                    size="sm"
                    class="h-8 w-8 p-0"
                >
                    <TableIcon class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <!-- Editor Content -->
        <div class="min-h-[200px]">
            <EditorContent :editor="editor" />
        </div>
    </div>
</template>

<style scoped>
:deep(.ProseMirror) {
    outline: none;
    min-height: 200px;
}

:deep(.ProseMirror p.is-editor-empty:first-child::before) {
    color: #adb5bd;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}

:deep(.ProseMirror h1) {
    font-size: 2em;
    font-weight: bold;
    margin: 1em 0 0.5em 0;
}

:deep(.ProseMirror h2) {
    font-size: 1.5em;
    font-weight: bold;
    margin: 1em 0 0.5em 0;
}

:deep(.ProseMirror h3) {
    font-size: 1.25em;
    font-weight: bold;
    margin: 1em 0 0.5em 0;
}

:deep(.ProseMirror ul) {
    list-style: disc;
    margin-left: 1.5em;
    padding-left: 0;
}

:deep(.ProseMirror ol) {
    list-style: decimal;
    margin-left: 1.5em;
    padding-left: 0;
}

:deep(.ProseMirror blockquote) {
    border-left: 4px solid #e5e7eb;
    padding-left: 1em;
    margin: 1em 0;
    font-style: italic;
}

:deep(.ProseMirror code) {
    background-color: #f3f4f6;
    border-radius: 0.25em;
    padding: 0.125em 0.25em;
    font-family: 'Courier New', Courier, monospace;
}

:deep(.ProseMirror pre) {
    background-color: #f3f4f6;
    border-radius: 0.5em;
    padding: 1em;
    overflow-x: auto;
}

:deep(.ProseMirror mark) {
    background-color: #fef08a;
    padding: 0.125em 0.25em;
    border-radius: 0.25em;
}

:deep(.ProseMirror hr) {
    border: none;
    border-top: 2px solid #e5e7eb;
    margin: 2em 0;
}

:deep(.ProseMirror table) {
    border-collapse: collapse;
    margin: 1em 0;
    overflow: hidden;
    table-layout: fixed;
    width: 100%;
}

:deep(.ProseMirror table td, .ProseMirror table th) {
    border: 1px solid #e5e7eb;
    padding: 0.5em;
    position: relative;
    vertical-align: top;
}

:deep(.ProseMirror table th) {
    background-color: #f3f4f6;
    font-weight: bold;
}

:deep(.ProseMirror .tableWrapper) {
    overflow-x: auto;
}

:deep(.ProseMirror ul[data-type="taskList"]) {
    list-style: none;
    margin-left: 0;
    padding-left: 0;
}

:deep(.ProseMirror ul[data-type="taskList"] li) {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
}

:deep(.ProseMirror ul[data-type="taskList"] li > label) {
    flex: 0 0 auto;
    margin-right: 0.5em;
    user-select: none;
}

:deep(.ProseMirror ul[data-type="taskList"] li > div) {
    flex: 1 1 auto;
}

/* Dark mode styles */
.dark :deep(.ProseMirror blockquote) {
    border-left-color: #374151;
}

.dark :deep(.ProseMirror code) {
    background-color: #1f2937;
}

.dark :deep(.ProseMirror pre) {
    background-color: #1f2937;
}

.dark :deep(.ProseMirror hr) {
    border-top-color: #374151;
}

.dark :deep(.ProseMirror table td, .ProseMirror table th) {
    border-color: #374151;
}

.dark :deep(.ProseMirror table th) {
    background-color: #1f2937;
}
</style>
