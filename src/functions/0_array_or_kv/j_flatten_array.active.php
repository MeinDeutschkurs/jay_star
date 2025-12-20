<?php
    function j_flatten_array($array, $separator = '/', $prefix = ''): array {
        $result = [];

        foreach ($array as $key => $value) {
            // Neuen Key-Pfad bilden
            $newKey = $prefix === '' ? $key : $prefix . $separator . $key;

            if (is_array($value) && !empty($value)) {
                // Wenn der Wert ein Array ist, tiefer gehen (Rekursion)
                $result = array_merge($result, j_flatten_array($value, $separator, $newKey));
            } else {
                // Endwert gefunden, in das flache Array schreiben
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
