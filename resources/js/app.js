import "./bootstrap";
import hljs from "highlight.js";

// Apply syntax highlighting to all code blocks on page load
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("pre code").forEach((block) => {
        hljs.highlightElement(block);
    });
});
