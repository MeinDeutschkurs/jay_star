<?php
    function j_kv_get($global_key, $key = "", $default = null, $starts_with = false, $ends_with = false, $contains = false) {
        if (!isset($GLOBALS[$global_key])) {
            return $default;
        }
        
        if ($starts_with !== false || $ends_with !== false || $contains !== false) {
            $result = [];
            foreach ($GLOBALS[$global_key] as $k => $v) {
                $match = true;
                
                if ($starts_with !== false && !str_starts_with($k, $starts_with)) {
                    $match = false;
                }
                
                if ($ends_with !== false && !str_ends_with($k, $ends_with)) {
                    $match = false;
                }
                
                if ($contains !== false && !str_contains($k, $contains)) {
                    $match = false;
                }
                
                if ($match) {
                    $result[$k] = $v;
                }
            }
            return $result ?: $default;
        }
        
        if ($key === '') {
            return $GLOBALS[$global_key];
        }
        
        return $GLOBALS[$global_key][$key] ?? $default;
    }