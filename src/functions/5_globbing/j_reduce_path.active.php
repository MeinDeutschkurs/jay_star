<?php
    function j_reduce_path($path, $levels, $separator = '/') {
        $parts = explode($separator, $path);

        if ($levels >= count($parts)) {
            return '';
        }

        $reduced_parts = array_slice($parts, 0, -$levels);

        return implode($separator, $reduced_parts);
    }
