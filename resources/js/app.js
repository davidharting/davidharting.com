import "./bootstrap";

// Import only hljs core + specific languages to reduce bundle size
import hljs from "highlight.js/lib/core";
import bash from "highlight.js/lib/languages/bash";
import json from "highlight.js/lib/languages/json";
import yaml from "highlight.js/lib/languages/yaml";
import sql from "highlight.js/lib/languages/sql";
import python from "highlight.js/lib/languages/python";

hljs.registerLanguage("bash", bash);
hljs.registerLanguage("json", json);
hljs.registerLanguage("yaml", yaml);
hljs.registerLanguage("sql", sql);
hljs.registerLanguage("python", python);

// Apply syntax highlighting to all code blocks on page load
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("pre code").forEach((block) => {
        hljs.highlightElement(block);
    });
});
