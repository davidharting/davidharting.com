#!/usr/bin/env node

/**
 * Theme Contrast Checker
 *
 * Validates that theme colors meet WCAG contrast requirements.
 *
 * - DaisyUI themes: WCAG AAA (7:1) for primary UI elements
 * - Highlight.js themes: WCAG AA (4.5:1) for syntax highlighting
 *
 * WCAG 2.1 Requirements:
 * - AA Normal text: 4.5:1
 * - AAA Normal text: 7:1
 */

import { parse, formatHex } from "culori";
import { readFileSync } from "fs";
import { resolve, dirname } from "path";
import { fileURLToPath } from "url";

const __dirname = dirname(fileURLToPath(import.meta.url));

// WCAG contrast thresholds
const WCAG_AA = 4.5;
const WCAG_AAA = 7.0;

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
 * Check a list of color pairs and return results
 * If pair has its own threshold, use that; otherwise use the default
 */
function checkColorPairs(pairs, defaultThreshold = WCAG_AA) {
    const results = [];

    for (const {
        name,
        foreground,
        background,
        threshold: pairThreshold,
    } of pairs) {
        const threshold = pairThreshold ?? defaultThreshold;
        const ratio = getContrastRatio(foreground, background);
        const pass = ratio !== null && ratio >= threshold;

        results.push({
            name,
            foreground,
            background,
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
function formatResults(sectionName, results, levelDescription) {
    const lines = [];
    lines.push(`\n${sectionName}`);
    lines.push(`WCAG Level: ${levelDescription}`);
    lines.push("=".repeat(65));
    lines.push(
        `${"Pair".padEnd(25)} ${"Ratio".padEnd(10)} ${"Required".padEnd(10)} Status`,
    );
    lines.push("-".repeat(65));

    let allPass = true;
    for (const r of results) {
        if (r.error) {
            lines.push(`${r.name.padEnd(25)} ERROR: ${r.error}`);
            allPass = false;
        } else {
            const status = r.pass ? "✓ PASS" : "✗ FAIL";
            if (!r.pass) allPass = false;
            lines.push(
                `${r.name.padEnd(25)} ${r.ratio.padEnd(10)} ${r.threshold.toFixed(1).padEnd(10)} ${status}`,
            );
        }
    }

    lines.push("-".repeat(65));
    lines.push(allPass ? "All checks passed!" : "Some checks failed!");

    return { output: lines.join("\n"), allPass };
}

// =============================================================================
// DaisyUI Theme Parsing
// =============================================================================

/**
 * Parse DaisyUI theme block from app.css
 */
function parseDaisyUITheme(content, themeName) {
    // Find the theme block
    const themeRegex = new RegExp(
        `@plugin\\s+"daisyui/theme"\\s*\\{[^}]*name:\\s*"${themeName}"[^}]*\\}`,
        "s",
    );
    const match = content.match(themeRegex);
    if (!match) return null;

    const block = match[0];
    const colors = {};

    // Extract all color variables
    const varRegex = /--color-([a-z0-9-]+):\s*([^;]+);/g;
    let varMatch;
    while ((varMatch = varRegex.exec(block)) !== null) {
        colors[varMatch[1]] = varMatch[2].trim();
    }

    return colors;
}

/**
 * Get color pairs to check for a DaisyUI theme
 * Returns pairs with their required threshold:
 * - Base content (body text): AAA (7:1)
 * - Colored UI elements: AA (4.5:1)
 */
function getDaisyUIColorPairs(colors) {
    const pairs = [];

    // Base content on base backgrounds - AAA required (body text)
    const baseLevels = ["base-100", "base-200", "base-300"];
    for (const level of baseLevels) {
        if (colors[level] && colors["base-content"]) {
            pairs.push({
                name: `base-content on ${level}`,
                foreground: colors["base-content"],
                background: colors[level],
                threshold: WCAG_AAA,
            });
        }
    }

    // Neutral content - AAA (often used for text-heavy UI)
    if (colors["neutral"] && colors["neutral-content"]) {
        pairs.push({
            name: "neutral-content on neutral",
            foreground: colors["neutral-content"],
            background: colors["neutral"],
            threshold: WCAG_AAA,
        });
    }

    // Primary/secondary/accent - AA (buttons, badges, interactive elements)
    const uiColorTypes = ["primary", "secondary", "accent"];
    for (const type of uiColorTypes) {
        if (colors[type] && colors[`${type}-content`]) {
            pairs.push({
                name: `${type}-content on ${type}`,
                foreground: colors[`${type}-content`],
                background: colors[type],
                threshold: WCAG_AA,
            });
        }
    }

    // Status colors - AA (alerts, badges, feedback)
    const statusTypes = ["info", "success", "warning", "error"];
    for (const type of statusTypes) {
        if (colors[type] && colors[`${type}-content`]) {
            pairs.push({
                name: `${type}-content on ${type}`,
                foreground: colors[`${type}-content`],
                background: colors[type],
                threshold: WCAG_AA,
            });
        }
    }

    return pairs;
}

// =============================================================================
// Highlight.js Theme Parsing
// =============================================================================

/**
 * Parse highlight.js theme file
 */
function parseHljsTheme(filepath) {
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

    // Token selectors to check
    const tokens = [
        ".hljs-comment",
        ".hljs-keyword",
        ".hljs-type",
        ".hljs-string",
        ".hljs-number",
        ".hljs-built_in",
        ".hljs-attr",
        ".hljs-meta",
        ".hljs-punctuation",
        ".hljs-params",
        ".hljs-link",
    ];

    for (const selector of tokens) {
        const escapedSelector = selector.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        const regex = new RegExp(
            `${escapedSelector}[^{]*\\{[^}]*\\bcolor:\\s*([^;]+);`,
        );
        const match = content.match(regex);
        if (match) {
            colors[selector] = match[1].trim();
        }
    }

    return colors;
}

/**
 * Get color pairs to check for an hljs theme
 */
function getHljsColorPairs(colors) {
    const pairs = [];
    const bg = colors.bg;

    if (!bg) return pairs;

    // Check base foreground
    if (colors.fg) {
        pairs.push({
            name: "base text",
            foreground: colors.fg,
            background: bg,
        });
    }

    // Check each token type
    const tokenNames = {
        ".hljs-comment": "comment",
        ".hljs-keyword": "keyword",
        ".hljs-type": "type",
        ".hljs-string": "string",
        ".hljs-number": "number",
        ".hljs-built_in": "built-in",
        ".hljs-attr": "attribute",
        ".hljs-meta": "meta",
        ".hljs-punctuation": "punctuation",
        ".hljs-params": "params",
        ".hljs-link": "link",
    };

    for (const [selector, name] of Object.entries(tokenNames)) {
        if (colors[selector]) {
            pairs.push({
                name,
                foreground: colors[selector],
                background: bg,
            });
        }
    }

    return pairs;
}

// =============================================================================
// Main
// =============================================================================

console.log(
    "\n╔════════════════════════════════════════════════════════════════╗",
);
console.log(
    "║              Theme Contrast Checker                            ║",
);
console.log(
    "╚════════════════════════════════════════════════════════════════╝",
);

let exitCode = 0;

// Check DaisyUI themes (AAA standard)
const appCssPath = resolve(__dirname, "../resources/css/app.css");
const appCss = readFileSync(appCssPath, "utf-8");

const daisyThemes = [
    { name: "shire", label: "Shire (light)" },
    { name: "bagend", label: "Bag End (dark)" },
];

for (const theme of daisyThemes) {
    const colors = parseDaisyUITheme(appCss, theme.name);
    if (!colors) {
        console.log(`\nWARNING: Could not find DaisyUI theme "${theme.name}"`);
        continue;
    }

    const pairs = getDaisyUIColorPairs(colors);
    const results = checkColorPairs(pairs);
    const { output, allPass } = formatResults(
        `DaisyUI: ${theme.label}`,
        results,
        "AAA (7:1) for body text, AA (4.5:1) for UI elements",
    );
    console.log(output);
    if (!allPass) exitCode = 1;
}

// Check highlight.js themes (AA standard)
const hljsThemes = [
    { name: "shire", file: "hljs-shire.css", label: "Shire (light)" },
    { name: "bagend", file: "hljs-bagend.css", label: "Bag End (dark)" },
];

for (const theme of hljsThemes) {
    const themePath = resolve(__dirname, `../resources/css/${theme.file}`);
    const colors = parseHljsTheme(themePath);
    const pairs = getHljsColorPairs(colors);
    const results = checkColorPairs(pairs, WCAG_AA);
    const { output, allPass } = formatResults(
        `Highlight.js: ${theme.label}`,
        results,
        "AA (4.5:1) for syntax highlighting",
    );
    console.log(output);
    if (!allPass) exitCode = 1;
}

// Summary
console.log("\n" + "=".repeat(65));
if (exitCode === 0) {
    console.log("✓ All contrast checks passed!");
} else {
    console.log("✗ Some contrast checks failed!");
}
console.log("=".repeat(65) + "\n");

process.exit(exitCode);
