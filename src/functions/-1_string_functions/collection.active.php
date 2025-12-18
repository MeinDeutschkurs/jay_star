<?php
    function slash_to_dash($string) {
        return str_replace('/', '-', $string);
    }

    function dash_to_slash($string) {
        return str_replace('-', '/', $string);
    }

    function wave_to_slash($string) {
        return str_replace('⌇', '/', $string);
    }

    function slash_to_wave($string) {
        return str_replace('/', '⌇', $string);
    }

    function slug_find_problems($string, $optional = []) {
        // Verbotene Zeichen: wave (⌇), underscore (_), tilde (~)
        $forbidden = ['⌇', '_', '~', '@'];
        
        // Spezielle nicht-druckbare Zeichen mit Namen
        $special = [
            "\n" => '\n',
            "\r" => '\r',
            "\t" => '\t',
            ' ' => '[space]',
        ];
        
        // Optionale zusätzliche verbotene Zeichen hinzufügen
        if (!empty($optional)) {
            $forbidden = array_merge($forbidden, $optional);
        }
        
        // Sammle alle gefundenen verbotenen Zeichen
        $found = [];
        
        foreach ($forbidden as $char) {
            if (strpos($string, $char) !== false) {
                $found[] = $char;
            }
        }
        
        foreach ($special as $char => $name) {
            if (strpos($string, $char) !== false) {
                $found[] = $name;
            }
        }
        
        // Prüfe auf andere nicht-druckbare Zeichen
        $test_string = str_replace(array_keys($special), '', $string);
        if (!ctype_print($test_string)) {
            $found[] = '[NON-PRINTABLE]';
        }
        
        return $found;
    }