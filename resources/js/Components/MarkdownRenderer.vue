<template>
  <div class="markdown-content" v-html="sanitizedHtml"></div>
</template>

<script>
import { marked } from "marked";
import DOMPurify from "dompurify";
import { computed } from "vue";

export default {
  name: "MarkdownRenderer",
  props: {
    content: {
      type: String,
      required: true,
      default: "",
    },
    options: {
      type: Object,
      default: () => ({}),
    },
  },
  setup(props) {
    const sanitizedHtml = computed(() => {
      // Convert markdown to HTML
      const html = marked.parse(props.content, {
        gfm: true,
        breaks: true,
        ...props.options,
      });

      // Sanitize HTML to prevent XSS attacks
      return DOMPurify.sanitize(html);
    });

    return {
      sanitizedHtml,
    };
  },
};
</script>

<style scoped>
.markdown-content :deep(pre) {
  background-color: #f4f4f4;
  padding: 1rem;
  border-radius: 4px;
  overflow-x: auto;
}

.markdown-content :deep(code) {
  font-family: monospace;
  background-color: #f4f4f4;
  padding: 0.2rem 0.4rem;
  border-radius: 3px;
}

.markdown-content :deep(h1),
.markdown-content :deep(h2),
.markdown-content :deep(h3),
.markdown-content :deep(h4),
.markdown-content :deep(h5),
.markdown-content :deep(h6) {
  margin-top: 1.5em;
  margin-bottom: 0.5em;
}

.markdown-content :deep(p) {
  margin-bottom: 1em;
}

.markdown-content :deep(ul),
.markdown-content :deep(ol) {
  padding-left: 2em;
  margin-bottom: 1em;
}

.markdown-content :deep(blockquote) {
  border-left: 4px solid #ddd;
  padding-left: 1em;
  color: #666;
  margin-left: 0;
  margin-right: 0;
}

.markdown-content :deep(img) {
  max-width: 100%;
}

.markdown-content :deep(table) {
  border-collapse: collapse;
  width: 100%;
  margin-bottom: 1em;
}

.markdown-content :deep(th),
.markdown-content :deep(td) {
  border: 1px solid #ddd;
  padding: 8px;
}

.markdown-content :deep(tr:nth-child(even)) {
  background-color: #f2f2f2;
}
</style>
