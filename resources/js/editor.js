import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

/**
 * Rich text editor for admin description fields.
 *
 * The feature set deliberately mirrors the storefront's rich-text allowlist
 * (Product::ALLOWED_RICH_TEXT_TAGS: p, br, strong, em, ul, ol, li) — anything
 * the editor can produce is guaranteed to render for customers. Extend both
 * together or not at all.
 */
document.addEventListener('alpine:init', () => {
	window.Alpine.data('richEditor', (initialContent = '') => ({
		editor: null,
		tick: 0,

		init() {
			this.editor = new Editor({
				element: this.$refs.editor,
				extensions: [
					StarterKit.configure({
						heading: false,
						blockquote: false,
						code: false,
						codeBlock: false,
						horizontalRule: false,
						strike: false,
						link: false,
						underline: false,
					}),
				],
				content: initialContent,
				editorProps: {
					attributes: {
						class: 'rich-text min-h-40 px-3 py-2 text-sm text-stone-700 focus:outline-none',
					},
				},
				onCreate: () => this.sync(),
				onUpdate: () => this.sync(),
				onSelectionUpdate: () => {
					this.tick++;
				},
				onTransaction: () => {
					this.tick++;
				},
			});
		},

		sync() {
			this.tick++;

			if (this.$refs.input) {
				this.$refs.input.value = this.editor.isEmpty ? '' : this.editor.getHTML();
			}
		},

		command(name) {
			this.editor?.chain().focus()[name]().run();
		},

		active(name) {
			this.tick; // touch for Alpine reactivity

			return this.editor?.isActive(name) ?? false;
		},

		destroy() {
			this.editor?.destroy();
		},
	}));
});
