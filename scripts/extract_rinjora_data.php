<?php

/**
 * One-time extraction of the rinjora prototype data arrays into a PHP data file.
 *
 * Usage:
 *   php scripts/extract_rinjora_data.php docs/rinjora.html app/Support/data/rinjora.php
 *
 * Reads the SOKWE / HERAHEZA / TUJAJURE const arrays from docs/rinjora.html
 * and writes them as a committed PHP data file consumed by App\Support\RinjoraData.
 */

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/extract_rinjora_data.php <rinjora.html> <output.php>\n");
    exit(1);
}

[$script, $source, $target] = $argv;

$html = file_get_contents($source);
if ($html === false) {
    fwrite(STDERR, "Could not read {$source}\n");
    exit(1);
}

/**
 * Extract one `const NAME = [ {key:"value", ...}, ... ];` block.
 */
function extractArray(string $name, string $html): array
{
    if (! preg_match('/const ' . preg_quote($name, '/') . ' = \[(.*?)\];/s', $html, $m)) {
        fwrite(STDERR, "no match for {$name}\n");
        return [];
    }

    $body = $m[1];
    preg_match_all('/\{([^{}]*)\}/s', $body, $objs);
    $out = [];
    foreach ($objs[1] as $o) {
        $row = [];
        foreach (['q', 'a', 't', 'p'] as $key) {
            if (preg_match('/' . $key . ':\s*"((?:[^"\\\\]|\\\\.)*)"/s', $o, $m2)) {
                $row[$key] = stripJsSlashes($m2[1]);
            }
        }
        if (isset($row['q']) && isset($row['a'])) {
            $out[] = $row;
        } elseif (isset($row['t']) && isset($row['p'])) {
            $out[] = $row;
        }
    }

    return $out;
}

function stripJsSlashes(string $s): string
{
    $s = str_replace('\\"', '"', $s);
    $s = str_replace('\\\\', '\\', $s);
    return $s;
}

$sokwe = extractArray('SOKWE', $html);
$hera = extractArray('HERAHEZA', $html);
$tuja = extractArray('TUJAJURE', $html);

echo 'SOKWE=' . count($sokwe) . ' HERAHEZA=' . count($hera) . ' TUJAJURE=' . count($tuja) . PHP_EOL;

$out = "<?php\n\n"
    . "/*\n"
    . " * Auto-extracted from docs/rinjora.html (one-time migration).\n"
    . " * Do not edit by hand - regenerate with scripts/extract_rinjora_data.php.\n"
    . " */\n\n"
    . "return [\n"
    . "    'sokwe' => " . var_export($sokwe, true) . ",\n"
    . "    'heraheza' => " . var_export($hera, true) . ",\n"
    . "    'tujajure' => " . var_export($tuja, true) . ",\n"
    . "];\n";

if (file_put_contents($target, $out) === false) {
    fwrite(STDERR, "Could not write {$target}\n");
    exit(1);
}

echo 'Wrote ' . strlen($out) . " bytes to {$target}\n";
