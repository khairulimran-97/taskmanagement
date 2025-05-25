import { Node, mergeAttributes } from '@tiptap/core'
import { VueNodeViewRenderer } from '@tiptap/vue-3'
import ImageComponent from '@/components/TipTapImageComponent.vue'

export interface ImageOptions {
    HTMLAttributes: Record<string, any>
}

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        image: {
            /**
             * Add an image
             */
            setImage: (options: { src: string, alt?: string, title?: string, width?: string, height?: string }) => ReturnType,
            /**
             * Update an image at the current position
             */
            updateImage: (options: { src?: string, alt?: string, title?: string, width?: string, height?: string }) => ReturnType,
        }
    }
}

export const TipTapImage = Node.create<ImageOptions>({
    name: 'image',

    group: 'block',

    selectable: true,

    draggable: true,

    addAttributes() {
        return {
            src: {
                default: null,
            },
            alt: {
                default: null,
            },
            title: {
                default: null,
            },
            width: {
                default: null,
            },
            height: {
                default: null,
            },
        }
    },

    parseHTML() {
        return [
            {
                tag: 'img[src]',
            },
        ]
    },

    renderHTML({ HTMLAttributes }) {
        return ['img', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes)]
    },

    addCommands() {
        return {
            setImage: (options) => ({ commands }) => {
                return commands.insertContent({
                    type: this.name,
                    attrs: options,
                })
            },

            updateImage: (options) => ({ commands, editor }) => {
                const { state } = editor
                const { selection } = state
                const node = selection.$from.nodeAfter

                if (!node || node.type.name !== this.name) {
                    return false
                }

                return commands.updateAttributes(this.name, options)
            }
        }
    },

    addNodeView() {
        return VueNodeViewRenderer(ImageComponent)
    },
})

export default TipTapImage
