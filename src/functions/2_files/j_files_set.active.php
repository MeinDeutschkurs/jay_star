<?php
    function j_files_set($keypath, $value, $separator = "/", $atomic = false, $lock = true) {
        // Array-Append-Modus: keypath endet mit []
        if (str_ends_with($keypath, '[]')) {
            $base_keypath = substr($keypath, 0, -2); // Entferne []

            // Baue Ordner-Pfad auf
            $parts = $base_keypath === '' ? [] : explode($separator, $base_keypath);
            $encoded_parts = [];

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

            $dir_path = PATHES_BASE_DIR . ($encoded_parts ? implode('/', $encoded_parts) : '');

            // Stelle sicher, dass Ordner existiert
            if (is_file($dir_path)) {
                unlink($dir_path);
            }
            if (!is_dir($dir_path)) {
                mkdir($dir_path, 0777, true);
            }

            // Zähle Dateien im Ordner
            $files = array_filter(scandir($dir_path), function($item) use ($dir_path) {
                return $item !== '.' && $item !== '..' && is_file($dir_path . '/' . $item);
            });
            $next_index = count($files);

            // Ersetze [] durch ermittelten Index
            $keypath = $base_keypath === '' ? (string)$next_index : $base_keypath . $separator . $next_index;
        }

        // Ab hier: originale Funktion unverändert
        $parts = explode($separator, $keypath);
        $encoded_parts = [];

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

        // Arrays/Objekte flach machen
        if (is_array($value) || is_object($value)) {
            // Basis-Pfad EINMALIG aufbauen
            $current_path = PATHES_BASE_DIR;

            foreach ($encoded_parts as $part) {
                $current_path = rtrim($current_path, '/') . '/' . $part;

                if (is_file($current_path)) {
                    unlink($current_path);
                    mkdir($current_path, 0777, true);
                } elseif (!is_dir($current_path)) {
                    mkdir($current_path, 0777, true);
                }
            }

            // Für jeden Sub-Key: Set-Logik INLINE statt rekursiv (für Einzelwerte)
            foreach ((array)$value as $key => $val) {
                // Prüfe ob verschachteltes Array/Objekt
                if (is_array($val) || is_object($val)) {
                    // Nur DANN rekursiv
                    j_files_set($keypath . $separator . $key, $val, $separator, $atomic, $lock);
                } else {
                    // Einzelwert: INLINE ohne erneuten kompletten Pfadaufbau
                    $rel_parts = explode($separator, $key);
                    $rel_encoded = [];

                    foreach ($rel_parts as $part) {
                        $pos = strpos($part, '=');
                        if ($pos !== false) {
                            $k = substr($part, 0, $pos);
                            $v = substr($part, $pos + 1);
                            $rel_encoded[] = rawurlencode($k) . '=' . rawurlencode($v);
                        } else {
                            $rel_encoded[] = rawurlencode($part);
                        }
                    }

                    $file_path = $filesystem_path . '/' . implode('/', $rel_encoded);

                    // Unterverzeichnisse für diesen relativen Pfad erstellen
                    $temp_path = $filesystem_path;

                    foreach ($rel_encoded as $rpart) {
                        if (count($rel_encoded) > 1 && $rpart === end($rel_encoded)) break;
                        $temp_path = rtrim($temp_path, '/') . '/' . $rpart;

                        if (is_file($temp_path)) {
                            unlink($temp_path);
                            mkdir($temp_path, 0777, true);
                        } elseif (!is_dir($temp_path)) {
                            mkdir($temp_path, 0777, true);
                        }
                    }

                    if (is_dir($file_path)) {
                        j_delete_directory_recursive($file_path);
                    }

                    // Meta-Key oder normale Datei schreiben (inline)
                    $basename = basename($file_path);
                    if (strpos($basename, '=') !== false) {
                        if (str_ends_with($file_path, '=')) {
                            if (is_bool($val)) {
                                $val = $val ? '1' : '0';
                            }
                            $file_path .= rawurlencode((string)$val);
                            $basename = basename($file_path);
                        }

                        $dir = dirname($file_path);
                        $key_before_eq = substr($basename, 0, strpos($basename, '='));

                        $existing = [];
                        if (is_dir($dir)) {
                            $handle = opendir($dir);
                            if ($handle) {
                                while (($file = readdir($handle)) !== false) {
                                    if ($file === '.' || $file === '..') continue;
                                    if (strpos($file, $key_before_eq . '=') === 0) {
                                        $existing[] = $dir . '/' . $file;
                                    }
                                }
                                closedir($handle);
                            }
                        }

                        foreach ($existing as $old_file) {
                            if ($old_file !== $file_path) {
                                unlink($old_file);
                            }
                        }

                        if (!file_exists($file_path)) {
                            touch($file_path);
                        }
                    } else {
                        // Normale Datei
                        if (is_bool($val)) {
                            $val = $val ? '1' : '0';
                        }

                        if ($atomic) {
                            $tmp_dir = PATHES_BASE_DIR . 'tmp/';
                            if (!is_dir($tmp_dir)) {
                                mkdir($tmp_dir, 0777, true);
                            }
                            $tmp = $tmp_dir . uniqid('memo_', true);
                            $flags = $lock ? LOCK_EX : 0;
                            file_put_contents($tmp, (string)$val, $flags);
                            rename($tmp, $file_path);
                        } else {
                            $flags = $lock ? LOCK_EX : 0;
                            file_put_contents($file_path, (string)$val, $flags);
                        }
                    }
                }
            }
            return $value;
        }

        // Einzelwert-Logik
        $current_path = PATHES_BASE_DIR;

        foreach ($encoded_parts as $i => $part) {
            if ($i < count($encoded_parts) - 1) {
                $current_path = rtrim($current_path, '/') . '/' . $part;

                if (is_file($current_path)) {
                    unlink($current_path);
                    mkdir($current_path, 0777, true);
                } elseif (!is_dir($current_path)) {
                    mkdir($current_path, 0777, true);
                }
            }
        }

        if (is_dir($filesystem_path)) {
            j_delete_directory_recursive($filesystem_path);
        }

        $basename = basename($filesystem_path);
        if (strpos($basename, '=') !== false) {
            if (str_ends_with($filesystem_path, '=')) {
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }
                $filesystem_path .= rawurlencode((string)$value);
                $basename = basename($filesystem_path);
            }

            $dir = dirname($filesystem_path);
            $key_before_eq = substr($basename, 0, strpos($basename, '='));

            $existing = [];
            if (is_dir($dir)) {
                $handle = opendir($dir);
                if ($handle) {
                    while (($file = readdir($handle)) !== false) {
                        if ($file === '.' || $file === '..') continue;
                        if (strpos($file, $key_before_eq . '=') === 0) {
                            $existing[] = $dir . '/' . $file;
                        }
                    }
                    closedir($handle);
                }
            }

            foreach ($existing as $old_file) {
                if ($old_file !== $filesystem_path) {
                    unlink($old_file);
                }
            }

            if (!file_exists($filesystem_path)) {
                touch($filesystem_path);
            }

            return $value;
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        if ($atomic) {
            $tmp_dir = PATHES_BASE_DIR . 'tmp/';
            if (!is_dir($tmp_dir)) {
                mkdir($tmp_dir, 0777, true);
            }
            $tmp = $tmp_dir . uniqid('memo_', true);
            $flags = $lock ? LOCK_EX : 0;
            file_put_contents($tmp, (string)$value, $flags);
            rename($tmp, $filesystem_path);
        } else {
            $flags = $lock ? LOCK_EX : 0;
            file_put_contents($filesystem_path, (string)$value, $flags);
        }

        return $value;
    }
