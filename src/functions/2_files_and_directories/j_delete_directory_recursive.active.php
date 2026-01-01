<?php
    function j_delete_directory_recursive($dir) {
        if (!is_dir($dir)) return false;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? j_delete_directory_recursive($path) : unlink($path);
        }
        return rmdir($dir);
    }