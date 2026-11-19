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

// CSS url() may only point inside the file.
const EXTERNAL_URL = 'url\(\s*(?![\'"]?\s*#)';

function svgProblems(string $xml): array
{
    if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
        return ['DOCTYPE and entities are not allowed'];
    }

    $document = new DOMDocument();
    if (! @$document->loadXML($xml, LIBXML_NONET)) {
        return ['not well-formed XML'];
    }

    $problems = [];
    foreach ((new DOMXPath($document))->query('//*') as $element) {
        if ($element->namespaceURI !== SVG_NAMESPACE || ! in_array($element->localName, ALLOWED_ELEMENTS, true)) {
            $problems[] = "element <{$element->nodeName}> is not allowed";
        }

        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->localName);
            $value = preg_replace('/\s+/', '', $attribute->value);

            if (strpos($name, 'on') === 0) {
                $problems[] = "event attribute {$attribute->nodeName} is not allowed";
            }
            if ($name === 'href' && ! preg_match(ALLOWED_HREF, $value)) {
                $problems[] = "link {$attribute->nodeName}=\"{$attribute->value}\" is not allowed";
            }
            if (stripos($value, 'javascript:') !== false || preg_match('/' . EXTERNAL_URL . '/i', $value)) {
                $problems[] = "value of {$attribute->nodeName} is not allowed";
            }
        }

        if ($element->localName === 'style'
            && preg_match('/@import|javascript:|' . EXTERNAL_URL . '/i', $element->textContent)) {
            $problems[] = '<style> loads external content';
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
