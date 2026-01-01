<?php

function j_grep($pattern, $subPath = '', $filePattern = '*') {
    return function_exists('shell_exec') && shell_exec('which grep 2>/dev/null')
        ? j_grep_shell($pattern, $subPath, $filePattern)
        : j_grep_manual($pattern, $subPath, $filePattern);
}

function j_grep_multiple($patterns, $subPaths, $filePattern = '*') {
    $patterns = is_array($patterns) ? $patterns : [$patterns];
    $subPaths = is_array($subPaths) ? $subPaths : [$subPaths];
    
    $results = [];
    
    foreach ($patterns as $pattern) {
        foreach ($subPaths as $subPath) {
            $subResults = j_grep($pattern, $subPath, $filePattern);
            $results = array_merge_recursive($results, $subResults);
        }
    }
    
    return $results;
}

function j_grep_build_posix_regex($pattern) {
    $regex = preg_quote($pattern, '/');
    $regex = preg_replace('/\s+/', '[[:space:]]+', $regex);
    $regex = str_replace('\\.', '\\.?', $regex);
    $regex = str_replace(',', ',?', $regex);
    return $regex;
}

function j_grep_build_pcre_regex($pattern) {
    $regex = preg_quote($pattern, '/');
    $regex = preg_replace('/\s+/', '\s+', $regex);
    $regex = str_replace('\\.', '\\.?', $regex);
    $regex = str_replace(',', ',?', $regex);
    return $regex;
}

function j_grep_shell($pattern, $subPath = '', $filePattern = '*') {
    $regexPattern = escapeshellarg(j_grep_build_posix_regex($pattern));
    
    $basePath = rtrim(PATHES_BASE_DIR, '/');
    $subPath = trim($subPath, '/');
    $searchPath = $subPath !== '' ? $basePath . '/' . $subPath : $basePath;
    $escapedPath = escapeshellarg($searchPath);
    
    $includePattern = $filePattern !== '*' ? '--include=' . escapeshellarg($filePattern) . ' ' : '';
    $cmd = "grep -Eirn {$includePattern}{$regexPattern} {$escapedPath}";
    
    $output = shell_exec($cmd);
    
    if ($output === null) {
        return [];
    }
    
    $lines = array_filter(explode("\n", $output));
    $results = [];
    
    foreach ($lines as $line) {
        if (strpos($line, $basePath . '/') === 0) {
            $line = substr($line, strlen($basePath) + 1);
        }
        
        $parts = explode(':', $line, 3);
        if (count($parts) >= 3) {
            $path = $parts[0];
            $lineNum = (int)$parts[1];
            $content = $parts[2];
            
            $results[$path][$lineNum] = $content;
        }
    }
    
    return $results;
}

function j_grep_manual($pattern, $subPath = '', $filePattern = '*') {
    $basePath = rtrim(PATHES_BASE_DIR, '/');
    $subPath = trim($subPath, '/');
    $searchPath = $subPath !== '' ? $basePath . '/' . $subPath : $basePath;
    
    $results = [];
    
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($searchPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );
    } catch (UnexpectedValueException $e) {
        return [];
    }
    
    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;
        
        if ($filePattern !== '*' && !fnmatch($filePattern, $file->getFilename())) continue;
        
        $fp = fopen($file->getPathname(), 'r');
        if (!$fp) continue;
        
        $lineNum = 0;
        while (($line = fgets($fp)) !== false) {
            $lineNum++;
            if (preg_match('/' . j_grep_build_pcre_regex($pattern) . '/i', $line)) {
                $relativePath = substr($file->getPathname(), strlen($basePath) + 1);
                $results[$relativePath][$lineNum] = rtrim($line, "\r\n");
            }
        }
        fclose($fp);
    }
    
    return $results;
}
