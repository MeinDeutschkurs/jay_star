<?php
    function j_file_get_contents($filepath, $lock = true) {
        if (!file_exists($filepath)) {
            return false;
        }
        
        $fp = fopen($filepath, 'r');
        if ($fp === false) {
            return false;
        }
        
        if ($lock) {
            flock($fp, LOCK_SH);
        }
        $content = stream_get_contents($fp);
        fclose($fp);
        
        return $content;
    }