<?php
// Utility helpers to keep ticket-related theme classes consistent across pages.
if (!function_exists('ticket_normalize_theme')) {
    function ticket_normalize_theme($rawTheme, $fallback = 'primary') {
        $allowed = [
            'primary','secondary','success','danger','warning','info','light','dark',
            'indigo','navy','purple','pink','teal','orange','olive','lime','fuchsia','maroon'
        ];
        $value = strtolower(trim((string)$rawTheme));
        if ($value === '' || !in_array($value, $allowed, true)) {
            $safeFallback = strtolower(trim((string)$fallback));
            return in_array($safeFallback, $allowed, true) ? $safeFallback : 'primary';
        }
        return $value;
    }
}
