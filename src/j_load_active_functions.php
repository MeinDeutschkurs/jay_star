<?php
    function j_load_active_functions($pattern) {
        $files = glob($pattern . '/*.active.php');
        
        foreach ($files as $file) {
            include $file;
        }
        
        return count($files);
    }