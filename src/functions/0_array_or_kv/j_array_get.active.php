<?php
    function j_array_get($array, $keypath, $separator = '/', $default = null) {
        if (!is_array($array)) return $default;
        if ($keypath === '') return $array;
        
        $keys = explode($separator, $keypath);
        $current = $array;
        
        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $default;
            }
            $current = $current[$key];
        }
        
        return $current;
    }
    