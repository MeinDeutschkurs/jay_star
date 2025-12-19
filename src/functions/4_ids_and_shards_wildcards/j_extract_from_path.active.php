<?php
    function j_extract_from_path(string $path, ?string $section = null, $default = null) {
        $parts = explode('/', trim($path, '/'));
        $result = [];

        while (!empty($parts)) {
            $key = array_shift($parts);

            if ($key === 'user') {
                $shardString = j_id('user'); 
                $count = !empty($shardString) ? max(0, count(explode('/', trim($shardString, '/'))) - 1) : 1;
            } else {
                $count = 1;
            }

            $values = array_splice($parts, 0, $count);
            // Wert nur setzen, wenn Segmente vorhanden sind, sonst bleibt er weg
            if (!empty($values)) {
                $result[$key] = implode('/', $values);
            }
        }

        if ($section === null) {
            return $result;
        }

        // Wenn es scheitert (Key existiert nicht), wird das $default zurückgegeben.
        return $result[$section] ?? $default;
    }