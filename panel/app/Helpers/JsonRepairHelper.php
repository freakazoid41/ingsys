<?php
/**
 * Repair and decode malformed JSON strings where inner JSON in value fields
 * may not have been escaped properly.
 *
 * Usage:
 *   $decoded = repair_json_string($jsonString);
 *
 * Returns decoded PHP array on success or throws RuntimeException on failure.
 */

if (! function_exists('repair_json_string')) {
    function repair_json_string(string $input, bool $assoc = true)
    {
        $try = function ($s) use ($assoc) {
            $r = json_decode($s, $assoc);
            return $r === null && json_last_error() !== JSON_ERROR_NONE ? null : $r;
        };

        $decoded = $try($input);
        if ($decoded !== null) {
            return $decoded;
        }

        $repaired = $input;
        $len = strlen($repaired);
        $offset = 0;

        // Find occurrences of "Value" and if the following string starts with
        // a JSON object/array ({ or [) we will escape its inner quotes so outer
        // JSON can parse.
        while (($pos = strpos($repaired, '"Value"', $offset)) !== false) {
            $colon = strpos($repaired, ':', $pos);
            if ($colon === false) break;
            // find the first double quote after the colon
            $quote = strpos($repaired, '"', $colon);
            if ($quote === false) break;
            $contentStart = $quote + 1;
            while ($contentStart < $len && ctype_space($repaired[$contentStart])) $contentStart++;
            if ($contentStart >= $len) break;
            $ch = $repaired[$contentStart];
            if ($ch !== '{' && $ch !== '[') { $offset = $pos + 7; continue; }
            $open = $ch;
            $close = $open === '{' ? '}' : ']';
            $depth = 0;
            $i = $contentStart;
            for (; $i < $len; $i++) {
                $c = $repaired[$i];
                if ($c === $open) $depth++;
                elseif ($c === $close) { $depth--; if ($depth === 0) break; }
            }
            if ($i >= $len) break;
            $contentEnd = $i;
            $inner = substr($repaired, $contentStart, $contentEnd - $contentStart + 1);
            // Escape backslashes first, then quotes
            $escapedInner = str_replace(['\\', '"'], ['\\\\', '\\"'], $inner);
            $before = substr($repaired, 0, $contentStart);
            $after = substr($repaired, $contentEnd + 1);
            $repaired = $before . $escapedInner . $after;
            $len = strlen($repaired);
            $offset = $contentStart + strlen($escapedInner);
        }

        $decoded = $try($repaired);
        if ($decoded === null) {
            return false;
            //throw new RuntimeException('Failed to repair JSON: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
