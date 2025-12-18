<?php
    function j_files_get($keypath, $separator = "/", $default = null, $ignore_system_files = true, $lock = true) {
        // Handle Meta-Key queries (path ending with =)
        if (str_ends_with($keypath, '=')) {
            $parts = explode($separator, $keypath);
            $last = array_pop($parts);  // e.g. "status="
            
            if (empty($parts)) {
                return $default;  // Can't query root with =
            }
            
            $parent_path = implode($separator, $parts);
            $parent = j_files_get($parent_path, $separator, $default, $ignore_system_files, $lock);
            
            if (is_array($parent) && isset($parent[$last])) {
                return $parent[$last];
            }
            
            return $default;
        }
        
        $parts = explode($separator, $keypath);
        $encoded_parts = [];
        
        // Encode alle Teile, aber lass = stehen
        foreach ($parts as $part) {
            $pos = strpos($part, '=');
            if ($pos !== false) {
                $key = substr($part, 0, $pos);
                $val = substr($part, $pos + 1);
                $encoded_parts[] = rawurlencode($key) . '=' . rawurlencode($val);
            } else {
                $encoded_parts[] = rawurlencode($part);
            }
        }
        $filesystem_path = PATHES_BASE_DIR . implode('/', $encoded_parts);
        
        // Einzelne Datei
        if (is_file($filesystem_path)) {
            // Meta-Key mit =?
            if (strpos(basename($filesystem_path), '=') !== false) {
                // Value aus dem Dateinamen zurückgeben
                list($key, $val) = explode('=', basename($filesystem_path), 2);
                return rawurldecode($val);
            }
            return j_file_get_contents($filesystem_path, $lock);
        }
        
        // Verzeichnis
        if (is_dir($filesystem_path)) {
            $result = [];
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($filesystem_path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $item) {
                if (!$item->isFile()) continue;
                
                // Optionale Filterung von System-Dateien
                if ($ignore_system_files) {
                    $filename = $item->getFilename();
                    
                    // Ignoriere .DS_Store und ähnliche macOS Dateien
                    if ($filename === '.DS_Store' ||
                        $filename === '._.DS_Store' ||
                        $filename === '.localized' ||
                        $filename === 'Thumbs.db' ||
                        $filename === 'desktop.ini') {
                        continue;
                    }
                    
                    // Ignoriere versteckte Dateien (beginnen mit .)
                    // außer sie haben = im Namen (Meta-Keys)
                    if (str_starts_with($filename, '.') && strpos($filename, '=') === false) {
                        continue;
                    }
                }
                
                $relative = substr($item->getPathname(), strlen($filesystem_path) + 1);
                $parts = explode('/', $relative);
                $decoded_parts = [];
                
                // Dekodiere jeden Teil
                foreach ($parts as $part) {
                    $pos = strpos($part, '=');
                    if ($pos !== false) {
                        $key = rawurldecode(substr($part, 0, $pos));
                        $val = rawurldecode(substr($part, $pos + 1));
                        $decoded_parts[] = $key . '=' . $val;
                    } else {
                        $decoded_parts[] = rawurldecode($part);
                    }
                }
                
                // Baue Array-Struktur auf
                $current = &$result;
                for ($i = 0; $i < count($decoded_parts) - 1; $i++) {
                    if (!isset($current[$decoded_parts[$i]])) {
                        $current[$decoded_parts[$i]] = [];
                    }
                    $current = &$current[$decoded_parts[$i]];
                }
                
                // Letztes Element
                $last = $decoded_parts[count($decoded_parts) - 1];
                if (strpos($last, '=') !== false) {
                    // Meta-Key: Split und speichere mit = im Key
                    list($key, $val) = explode('=', $last, 2);
                    $current[$key . '='] = $val;
                } else {
                    // Normale Datei: Inhalt lesen
                    $current[$last] = j_file_get_contents($item->getPathname(), $lock);
                }
            }
            
            return $result;
        }
        
        return $default;
    }