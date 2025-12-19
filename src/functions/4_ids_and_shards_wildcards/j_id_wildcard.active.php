<?php

    function j_id_wildcard($section) {
        // Echte ID generieren für korrekte Struktur
        $real_id = j_id($section);
        $parts = explode('/', $real_id);
        
        // Section behalten, Rest durch * ersetzen
        $pattern_parts = [$parts[0]]; // Section
        for ($i = 1; $i < count($parts); $i++) {
            $pattern_parts[] = '*';
        }
        
        return implode('/', $pattern_parts);
    }