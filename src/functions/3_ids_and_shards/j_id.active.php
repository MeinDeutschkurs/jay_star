<?php

function j_id($section) {
        $section = trim($section, '/');
        
        $timestamp_nanos = time() * 1000000000;
        $hrtime = hrtime();
        $sum = $timestamp_nanos + $hrtime[1];
        $hex = base_convert($sum, 10, 16);
        
        $hex = str_pad($hex, 24, '0', STR_PAD_LEFT);
        $groups = str_split($hex, 3);
        
        // Shard-Rand, momentan 0,1
        $rand = rand(0, 1);
        
        // PID normiert auf 6 Stellen Hex, dann in 2×3 splitten
        $pid = getmypid();
        $pid_hex = str_pad(dechex($pid), 6, '0', STR_PAD_LEFT);
        $pid_parts = str_split($pid_hex, 3); // ['003', '039']
        
        return $section . '/' . $rand . '/' . implode('/', $pid_parts) . '/' . implode('/', $groups);
    }