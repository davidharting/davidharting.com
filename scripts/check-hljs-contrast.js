#!/usr/bin/env node

/**
 * Highlight.js Theme Contrast Checker
 *
 * Validates that syntax highlighting colors meet WCAG contrast requirements.
 * Checks all foreground colors against the theme background.
 *
 * WCAG 2.1 Requirements:
 * - AA Normal text: 4.5:1
 * - AA Large text: 3:1
 * - AAA Normal text: 7:1
 * - AAA Large text: 4.5:1
 *
 * Usage: node scripts/check-hljs-contrast.js [--strict]
 *   --strict: Require AAA compliance (default is AA)
 */

import { parse, formatHex } from "culori";
import { readFileSync } from "fs";
import { resolve, dirname } from "path";
import { fileURLToPath } from "url";

const __dirname = dirname(fileURLToPath(import.meta.url));

// WCAG contrast thresholds
const WCAG_AA = 4.5;
const WCAG_AAA = 7.0;

// Theme files to check
const THEMES = [
    { name: "shire", file: "hljs-shire.css", scheme: "light" },
    { name: "bagend", file: "hljs-bagend.css", scheme: "dark" },
];

// Map of CSS selectors to token names for reporting
const SELECTOR_TO_TOKEN = {
    ".hljs": "hljs-bg",
    ".hljs-comment": "hljs-comment",
    ".hljs-keyword": "hljs-keyword",
    ".hljs-type": "hljs-type",
    ".hljs-string": "hljs-string",
    ".hljs-number": "hljs-number",
    ".hljs-built_in": "hljs-builtin",
    ".hljs-attr": "hljs-attribute",
    ".hljs-meta": "hljs-meta",
    ".hljs-punctuation": "hljs-punctuation",
    ".hljs-params": "hljs-params",
    ".hljs-link": "hljs-link",
    ".hljs-addition": "hljs-addition-fg",
    ".hljs-deletion": "hljs-deletion-fg",
};

/**
 * Calculate relative luminance per WCAG 2.1
 */
function getLuminance(color) {
    const rgb = parse(color);
    if (!rgb) return null;

    const hex = formatHex(rgb);
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;

    const toLinear = (c) =>
        c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);

    return 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b);
}

/**
 * Calculate contrast ratio between two colors
 */
function getContrastRatio(color1, color2) {
    const L1 = getLuminance(color1);
    const L2 = getLuminance(color2);

    if (L1 === null || L2 === null) return null;

    const lighter = Math.max(L1, L2);
    const darker = Math.min(L1, L2);

    return (lighter + 0.05) / (darker + 0.05);
}

/**
 * Parse a CSS theme file and extract colors
 */
function parseThemeFile(filepath) {
    const content = readFileSync(filepath, "utf-8");
    const colors = {};

    // Extract background color from .hljs
    const bgMatch = content.match(/\.hljs\s*\{[^}]*background:\s*([^;]+);/);
    if (bgMatch) {
        colors.bg = bgMatch[1].trim();
    }

    // Extract foreground color from .hljs
    const fgMatch = content.match(/\.hljs\s*\{[^}]*\bcolor:\s*([^;]+);/);
    if (fgMatch) {
        colors.fg = fgMatch[1].trim();
    }

    // Extract colors for each token type
    for (const [selector, token] of Object.entries(SELECTOR_TO_TOKEN)) {
        if (token === "hljs-bg") continue;

        // Escape special regex characters in selector
        const escapedSelector = selector.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

        // Match the selector (possibly with other selectors) and extract color
        const regex = new RegExp(
            `${escapedSelector}[^{]*\\{[^}]*\\bcolor:\\s*([^;]+);`,
        );
        const match = content.match(regex);
        if (match) {
            colors[token] = match[1].trim();
        }
    }

    return colors;
}

/**
 * Check contrast for a theme and return results
 */
function checkThemeContrast(colors, threshold) {
    const results = [];
    const bg = colors.bg;

    if (!bg) {
        return [{ token: "hljs-bg", error: "Background color not found" }];
    }

    // Check foreground against background
    const fgTokens = [
        "fg",
        "hljs-comment",
        "hljs-keyword",
        "hljs-type",
        "hljs-string",
        "hljs-number",
        "hljs-builtin",
        "hljs-attribute",
        "hljs-meta",
        "hljs-punctuation",
        "hljs-params",
        "hljs-link",
        "hljs-addition-fg",
        "hljs-deletion-fg",
    ];

    for (const token of fgTokens) {
        const fg = colors[token];
        if (!fg) continue;

        const ratio = getContrastRatio(fg, bg);
        const pass = ratio !== null && ratio >= threshold;

        results.push({
            token,
            fg,
            bg,
            ratio: ratio ? ratio.toFixed(2) : "N/A",
            pass,
            threshold,
        });
    }

    return results;
}

/**
 * Format results as a table
 */
function formatResults(themeName, results) {
    const lines = [];
    lines.push(`\n${themeName} Theme Contrast Report`);
    lines.push("=".repeat(60));
    lines.push(
        `${"Token".padEnd(20)} ${"Ratio".padEnd(10)} ${"Required".padEnd(10)} Status`,
    );
    lines.push("-".repeat(60));

    let allPass = true;
    for (const r of results) {
        if (r.error) {
            lines.push(`${r.token.padEnd(20)} ERROR: ${r.error}`);
            allPass = false;
        } else {
            const status = r.pass ? "✓ PASS" : "✗ FAIL";
            if (!r.pass) allPass = false;
            lines.push(
                `${r.token.padEnd(20)} ${r.ratio.padEnd(10)} ${r.threshold.toFixed(1).padEnd(10)} ${status}`,
            );
        }
    }

    lines.push("-".repeat(60));
    lines.push(allPass ? "All checks passed!" : "Some checks failed!");

    return { output: lines.join("\n"), allPass };
}

// Main execution
const args = process.argv.slice(2);
const strict = args.includes("--strict");
const threshold = strict ? WCAG_AAA : WCAG_AA;

console.log(`\nHighlight.js Theme Contrast Checker`);
console.log(`WCAG Level: ${strict ? "AAA" : "AA"} (${threshold}:1 minimum)\n`);

let exitCode = 0;

for (const theme of THEMES) {
    const themePath = resolve(__dirname, `../resources/css/${theme.file}`);
    const colors = parseThemeFile(themePath);
    const results = checkThemeContrast(colors, threshold);
    const { output, allPass } = formatResults(
        `${theme.name} (${theme.scheme})`,
        results,
    );
    console.log(output);
    if (!allPass) exitCode = 1;
}

process.exit(exitCode);
