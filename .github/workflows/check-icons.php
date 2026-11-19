<?php

/*
 * Fails when an icon folder holds anything but plain SVG images: links, other
 * file types, or SVGs that could run script or load external content. The
 * icons are served from the shop's own domain.
 *
 * Usage: php check-icons.php <dir> [<dir> ...]
 */

const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

const ALLOWED_ELEMENTS = [
    'svg', 'g', 'defs', 'title', 'desc', 'style', 'symbol', 'use',
    'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
    'text', 'tspan', 'image', 'pattern', 'mask', 'clipPath',
    'linearGradient', 'radialGradient', 'stop', 'filter', 'feColorMatrix',
];

// Links may only point inside the file or embed a raster image.
const ALLOWED_HREF = '/^(#|data:image\/(png|jpeg|gif|webp);base64,)/i';

// url() may only point inside the file.
const EXTERNAL_URL = '/url\(\s*(?!#)/i';

// CSS and attribute values (presentation attributes are parsed as CSS) may
// only call these functions.
const ALLOWED_FUNCTIONS = ['url', 'rgb', 'rgba', 'matrix', 'translate', 'scale', 'rotate', 'skewx', 'skewy'];

const ALLOWED_NODE_TYPES = [XML_ELEMENT_NODE, XML_TEXT_NODE, XML_CDATA_SECTION_NODE, XML_COMMENT_NODE];

function cssProblem(string $css): ?string
{
    // Escapes, at-rules and strings are how CSS hides or loads a URL
    // without url(), e.g. image-set("…") or u\72l(…).
    if (preg_match('/[\\\\@"\']|\/\//', $css)) {
        return 'CSS escapes, at-rules, strings and // are not allowed';
    }
    preg_match_all('/([a-z-]+)\s*\(/i', $css, $calls);
    foreach ($calls[1] as $function) {
        if (! in_array(strtolower($function), ALLOWED_FUNCTIONS, true)) {
            return "CSS function $function() is not allowed";
        }
    }
    if (preg_match(EXTERNAL_URL, $css) || stripos($css, 'javascript:') !== false) {
        return 'external url() and javascript: are not allowed';
    }

    return null;
}

function svgProblems(string $xml): array
{
    // UTF-8 only, so the checks below see the same text the browser parses.
    if (strpos($xml, "\0") !== false || ! preg_match('//u', $xml)) {
        return ['only UTF-8 is allowed'];
    }
    if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
        return ['DOCTYPE and entities are not allowed'];
    }

    $document = new DOMDocument();
    if (! @$document->loadXML($xml, LIBXML_NONET) || $document->doctype !== null) {
        return ['not well-formed XML without DOCTYPE'];
    }

    $problems = [];
    $xpath = new DOMXPath($document);

    foreach ($xpath->query('//node()') as $node) {
        if (! in_array($node->nodeType, ALLOWED_NODE_TYPES, true)) {
            $problems[] = "{$node->nodeName} nodes are not allowed";
        }
    }

    foreach ($xpath->query('//*') as $element) {
        if ($element->namespaceURI !== SVG_NAMESPACE || ! in_array($element->localName, ALLOWED_ELEMENTS, true)) {
            $problems[] = "element <{$element->nodeName}> is not allowed";
        }

        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->localName);
            $value = preg_replace('/\s+/', '', $attribute->value);

            if (strpos($name, 'on') === 0) {
                $problems[] = "event attribute {$attribute->nodeName} is not allowed";
            } elseif ($name === 'href') {
                if (! preg_match(ALLOWED_HREF, $value)) {
                    $problems[] = "link {$attribute->nodeName}=\"{$attribute->value}\" is not allowed";
                }
            } else {
                $problem = cssProblem($attribute->value);
                if ($problem !== null) {
                    $problems[] = "attribute {$attribute->nodeName}: $problem";
                }
            }
        }

        if ($element->localName === 'style') {
            $problem = cssProblem($element->textContent);
            if ($problem !== null) {
                $problems[] = "<style>: $problem";
            }
        }
    }

    return array_values(array_unique($problems));
}

$errors = [];
foreach (array_slice($argv, 1) as $directory) {
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($entries as $path => $entry) {
        if ($entry->isLink()) {
            $errors[] = "$path: links are not allowed";
        } elseif ($entry->isDir()) {
            continue;
        } elseif (! $entry->isFile() || strtolower($entry->getExtension()) !== 'svg') {
            $errors[] = "$path: only .svg files are allowed";
        } else {
            foreach (svgProblems((string) file_get_contents($path)) as $problem) {
                $errors[] = "$path: $problem";
            }
        }
    }
}

foreach ($errors as $error) {
    echo "::error::$error\n";
}

exit($errors ? 1 : 0);
