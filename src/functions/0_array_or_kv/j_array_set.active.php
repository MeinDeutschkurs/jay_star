<?php
    function j_array_set(&$array, $keypath, $value, $separator = '/') {
        if (!is_array($array)) $array = [];
        if ($keypath === '') {
            $array = $value;
            return $value;
        }

        // Array-Append-Modus: keypath endet mit []
        if (str_ends_with($keypath, '[]')) {
            $base_path = substr($keypath, 0, -2); // Entferne []

            // Navigiere zum Ziel-Array
            if ($base_path === '') {
                $target = &$array;
            } else {
                $keys = explode($separator, $base_path);
                $target = &$array;
                foreach ($keys as $key) {
                    if (!isset($target[$key]) || !is_array($target[$key])) {
                        $target[$key] = [];
                    }
                    $target = &$target[$key];
                }
            }

            // Finde nächsten freien numerischen Index
            $target[] = $value;
            return $value;
        }

        // Prüfe ob $value ein Array/Object ist UND ob es Batch-Modus sein soll
        if (is_array($value) || is_object($value)) {
            $value_array = (array)$value;
            
            // Prüfe ob IRGENDEIN Key einen Separator enthält
            $is_batch_mode = false;
            foreach ($value_array as $key => $val) {
                if (strpos((string)$key, $separator) !== false) {
                    $is_batch_mode = true;
                    break;
                }
            }
            
            // Kein Separator in Keys gefunden → Array ist ein WERT
            if (!$is_batch_mode) {
                $keys = explode($separator, $keypath);
                $last_key = array_pop($keys);
                $current = &$array;
                foreach ($keys as $key) {
                    if (!isset($current[$key]) || !is_array($current[$key])) {
                        $current[$key] = [];
                    }
                    $current = &$current[$key];
                }
                $current[$last_key] = $value;
                return $value;
            }
            
            // BATCH-MODUS: Navigation zu $keypath EINMALIG
            $keys = explode($separator, $keypath);
            $current = &$array;
            foreach ($keys as $k) {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
            
            // ← AB HIER haben wir $current als Reference zu a/b/c/d
            // Jetzt für jeden Key: NUR den relativen Pfad nutzen
            foreach ($value_array as $key => $val) {
                // Prüfe ob $val wieder ein Array/Object ist
                if (is_array($val) || is_object($val)) {
                    // Rekursiv - ABER: $current ist bereits die Base!
                    j_array_set($current, $key, $val, $separator);
                } else {
                    // Einzelwert: Explode NUR auf dem aktuellen Key
                    $rel_keys = explode($separator, (string)$key);
                    $last_key = array_pop($rel_keys);
                    $temp = &$current;  // ← Startet bei a/b/c/d, nicht bei root!
                    foreach ($rel_keys as $k) {
                        if (!isset($temp[$k]) || !is_array($temp[$k])) {
                            $temp[$k] = [];
                        }
                        $temp = &$temp[$k];
                    }
                    $temp[$last_key] = $val;
                }
            }
            return $value;
        }
        
        // Einzelwert-Logik (wenn direkt aufgerufen)
        $keys = explode($separator, $keypath);
        $last_key = array_pop($keys);
        $current = &$array;
        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
        $current[$last_key] = $value;
        return $value;
    }
