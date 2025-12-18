<?php
    function j_kv_set($global_key, $key, $value) {
        $GLOBALS[$global_key] ??= [];
        
        if ($key === '') {
            $GLOBALS[$global_key] = $value;
            return $value;
        }
        
        $GLOBALS[$global_key][$key] = $value;
        return $value;
    }