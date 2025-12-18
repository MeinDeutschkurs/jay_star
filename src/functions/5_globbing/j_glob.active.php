<?php
    function j_make_case_insensitive_pattern($str) {
        $pattern = '';
        $len = mb_strlen($str, 'UTF-8');
        
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($str, $i, 1, 'UTF-8');
            $lower = mb_strtolower($char, 'UTF-8');
            $upper = mb_strtoupper($char, 'UTF-8');
            
            if ($lower === $upper) {
                // Zeichen hat keine Case-Variante (z.B. Zahlen, Sonderzeichen)
                $pattern .= rawurlencode($char);
            } else {
                // Zeichen hat Case-Varianten
                $enc_lower = rawurlencode($lower);
                $enc_upper = rawurlencode($upper);
                
                if ($enc_lower === $enc_upper) {
                    $pattern .= $enc_lower;
                } elseif (strlen($enc_lower) === strlen($enc_upper) && strlen($enc_lower) === 1) {
                    // ASCII: [aA]
                    $pattern .= '[' . $enc_lower . $enc_upper . ']';
                } else {
                    // Multi-Byte: {alt1,alt2}
                    $pattern .= '{' . $enc_lower . ',' . $enc_upper . '}';
                }
            }
        }
        
        return $pattern;
    }

    function j_glob($pattern, $value = null, $flags = 0, $case_insensitive = false, $lock = true) {
            // Bei case_insensitive MUSS GLOB_BRACE aktiv sein für {alt1,alt2} Patterns
            if ($case_insensitive) {
                $flags = $flags | GLOB_BRACE;
            }
            
            // Encode pattern parts
            $parts = explode('/', $pattern);
            $encoded_parts = [];
            
            foreach ($parts as $part) {
                // Wildcards (* und ?) nicht encoden
                if (strpos($part, '*') !== false || strpos($part, '?') !== false ||
                    strpos($part, '{') !== false || strpos($part, '}') !== false ||
                    strpos($part, '[') !== false || strpos($part, ']') !== false) {
                    $encoded_parts[] = $part;
                } else {
                    $pos = strpos($part, '=');
                    if ($pos !== false) {
                        $key = substr($part, 0, $pos);
                        $val = substr($part, $pos + 1);
                        
                        if ($case_insensitive) {
                            $encoded_parts[] = rawurlencode($key) . '=' . j_make_case_insensitive_pattern($val);
                            // Pattern ist jetzt URL-encodiert!
                        } else {
                            $encoded_parts[] = rawurlencode($key) . '=' . rawurlencode($val);
                        }
                    } else {
                        $encoded_parts[] = rawurlencode($part);
                    }
                }
            }
            
            $filesystem_pattern = PATHES_BASE_DIR . implode('/', $encoded_parts);
            $files = glob($filesystem_pattern, $flags);
            
            if ($files === false) {
                return [];
            }
            
            $results = [];
            $base_len = strlen(PATHES_BASE_DIR);
            
            foreach ($files as $file) {
                // Entferne BASE_DIR vom Pfad
                $relative = substr($file, $base_len);
                $parts = explode('/', $relative);
                $decoded_parts = [];
                
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
                
                $keypath = implode('/', $decoded_parts);
                
                if ($value === null) {
                    $results[] = $keypath;
                    continue;
                }
                
                if (is_file($file)) {
                    $basename = basename($file);
                    if (strpos($basename, '=') !== false) {
                        list($k, $v) = explode('=', $basename, 2);
                        $v = rawurldecode($v);
                        
                        if ($v === (string)$value ||
                            ($value === true && $v === '1') ||
                            ($value === false && $v === '0')) {
                            $results[] = $keypath;
                        }
                    } else {
                        $stored_value = jophi_file_get_contents($file, $lock);
                        
                        if ($stored_value === (string)$value ||
                            ($value === true && $stored_value === '1') ||
                            ($value === false && $stored_value === '0')) {
                            $results[] = $keypath;
                        }
                    }
                }
            }
            
            return $results;
        }