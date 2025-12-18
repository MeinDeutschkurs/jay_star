<?php

function j_cookie_get($name, $default = []) {
    if (isset($_COOKIE[$name])) {
        $decrypted_data = @j_decrypt_aes($_COOKIE[$name]);
        if ($decrypted_data !== null) {
            j_memo_set('var/cookies/'.$name, $decrypted_data);
            return;
        } else {
            // TO-DO: IP-Terror - Logging, if necessary
        }
    }
    j_memo_set('var/cookies/'.$name, $default);
}

function j_cookie_set($name) {
    $domain = j_memo_get('analysis/tenant/domain');
    $cookie_data = j_memo_get('var/cookies/'.$name);
    if ($cookie_data) {
        $encrypted_data = j_encrypt_aes($cookie_data);
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        
        $is_localhost = ($domain === 'localhost' || $domain === '127.0.0.1');
        $is_ip = filter_var($domain, FILTER_VALIDATE_IP);
        
        $cookie_options = [
            'expires' => time() + (10 * 365 * 24 * 3600),
            'path' => '/',
            'secure' => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        
        if (!$is_localhost && !$is_ip) {
            $cookie_options['domain'] = '.' . $domain;
        }
        
        setcookie($name, $encrypted_data, $cookie_options);
    }
}

function j_update_cookie($name, $key, $value) {
    j_memo_set('var/cookies/'.$name.'/'.$key, $value);
}

function j_decrypt_aes($encrypted) {
    $KEY = j_memo_get('state/aes');
    
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    
    $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', hex2bin($KEY), OPENSSL_RAW_DATA, $iv);
    
    return json_decode($decrypted, true);
}

function j_encrypt_aes($mixed) {
    $KEY = j_memo_get('state/aes');
    
    $data = json_encode($mixed);
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', hex2bin($KEY), OPENSSL_RAW_DATA, $iv);
    
    return base64_encode($iv . $encrypted);
}