<?php
    function j_dirs_get($subpath, $names_only = false) {
        $fullPath = PATHES_BASE_DIR . '/' . ltrim($subpath, '/');

        // Prüfen ob das Verzeichnis existiert
        if (!is_dir($fullPath)) {
            return [];
        }

        $directories = [];
        $items = scandir($fullPath);

        // Sicherstellen dass $subpath mit / endet (außer es ist leer)
        $basePath = rtrim($subpath, '/');
        if ($basePath !== '') {
            $basePath .= '/';
        }

        foreach ($items as $item) {
            // Überspringen von . und .. und versteckten Dateien/Ordnern
            if ($item === '.' || $item === '..' || $item[0] === '.') {
                continue;
            }

            $itemPath = $fullPath . '/' . $item;

            // Nur Verzeichnisse hinzufügen
            if (is_dir($itemPath)) {
                $directories[] = $names_only ? $item : $basePath . $item;
            }
        }

        return $directories;
    }
