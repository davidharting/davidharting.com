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
const WCAG_AA_LARGE = 3.0;
const WCAG_AAA = 7.0;

/**
 * Calculate relative luminance per WCAG 2.1
 * https://www.w3.org/WAI/GL/wiki/Relative_luminance
 */
function getLuminance(color) {
    const rgb = parse(color);
    if (!rgb) return null;

    // Convert to sRGB and get components
    const hex = formatHex(rgb);
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;

    // Apply gamma correction
    const toLinear = (c) =>
        c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);

    const R = toLinear(r);
    const G = toLinear(g);
    const B = toLinear(b);

    return 0.2126 * R + 0.7152 * G + 0.0722 * B;
}

/**
 * Calculate contrast ratio between two colors
 * https://www.w3.org/WAI/GL/wiki/Contrast_ratio
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
 * Parse CSS file and extract custom properties from media query blocks
 */
function parseTokensFile(filepath) {
    const content = readFileSync(filepath, "utf-8");

    const themes = {
        light: { name: "shire", tokens: {} },
        dark: { name: "bagend", tokens: {} },
    };

    // Match media query blocks
    const lightMatch = content.match(
        /@media\s*\(prefers-color-scheme:\s*light\)\s*\{[\s\S]*?:root\s*\{([\s\S]*?)\}\s*\}/,
    );
    const darkMatch = content.match(
        /@media\s*\(prefers-color-scheme:\s*dark\)\s*\{[\s\S]*?:root\s*\{([\s\S]*?)\}\s*\}/,
    );

    // Parse custom properties
    const parseProperties = (block) => {
        const props = {};
        const regex = /--([\w-]+):\s*([^;]+);/g;
        let match;
        while ((match = regex.exec(block)) !== null) {
            props[match[1]] = match[2].trim();
        }
        return props;
    };

    if (lightMatch) themes.light.tokens = parseProperties(lightMatch[1]);
    if (darkMatch) themes.dark.tokens = parseProperties(darkMatch[1]);

    return themes;
}

/**
 * Check contrast for a theme and return results
 */
function checkThemeContrast(theme, threshold) {
    const results = [];
    const bg = theme.tokens["hljs-bg"];

    if (!bg) {
        return [{ token: "hljs-bg", error: "Background color not found" }];
    }

    // Foreground tokens to check against background
    const fgTokens = [
        "hljs-fg",
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
        const fg = theme.tokens[token];
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

const tokensPath = resolve(__dirname, "../resources/css/hljs-tokens.css");
const themes = parseTokensFile(tokensPath);

let exitCode = 0;

for (const [scheme, theme] of Object.entries(themes)) {
    const results = checkThemeContrast(theme, threshold);
    const { output, allPass } = formatResults(
        `${theme.name} (${scheme})`,
        results,
    );
    console.log(output);
    if (!allPass) exitCode = 1;
}

process.exit(exitCode);
