<?php
    $database_name='data';
    @define('PATHES_BASE_DIR', getcwd() . '/'.$database_name.'/');


    if (!file_exists('./.htaccess')) {
        echo '<!-- HOLD ON! I have to complete the setup process... ';
            file_put_contents('./.htaccess',
                'RewriteEngine On' . PHP_EOL .
                '# Exception for special folders:' . PHP_EOL .
                'RewriteCond %{REQUEST_URI} !^/(css|js|assets|\.well-known)/' . PHP_EOL .
                PHP_EOL .
                '# index.php is the gateway:' . PHP_EOL .
                'RewriteCond %{REQUEST_URI} !^/index\.php$ [NC]' . PHP_EOL .
                'RewriteRule ^(.*)$ /index.php [QSA,L]'
            );
        echo 'DONE. -->'. PHP_EOL;
        $startover=true;
    }

    if (!file_exists('./secrets/encryption.key')) {
        echo '<!-- Creating encryption key... ';
        
        // Erstelle secrets-Ordner falls nicht vorhanden
        if (!is_dir('./secrets')) {
            mkdir('./secrets', 0700);
        }
        
        // Generiere einen sicheren 32-Byte-Schlüssel für AES-256
        $encryption_key = bin2hex(random_bytes(32));
        
        file_put_contents('./secrets/encryption.key', $encryption_key);
        
        echo 'DONE. -->'. PHP_EOL;
        $startover = true;
    }

    if (!file_exists('./.gitignore')) {
        echo '<!-- Creating .gitignore... ';
        file_put_contents('./.gitignore',
            '# Auto-generated' . PHP_EOL .
            '.htaccess' . PHP_EOL .
            PHP_EOL .
            '# macOS' . PHP_EOL .
            '.DS_Store' . PHP_EOL .
            '.AppleDouble' . PHP_EOL .
            '.LSOverride' . PHP_EOL .
            '._*' . PHP_EOL .
            PHP_EOL .
            '# IDE' . PHP_EOL .
            '.vscode/' . PHP_EOL .
            '.idea/' . PHP_EOL .
            '.claude/' . PHP_EOL .
            'claude.md' . PHP_EOL .
            '*.sublime-project' . PHP_EOL .
            '*.sublime-workspace' . PHP_EOL .
            PHP_EOL .
            '# Logs' . PHP_EOL .
            '*.log' . PHP_EOL .
            PHP_EOL .
            '# Temp' . PHP_EOL .
            '*.tmp' . PHP_EOL .
            '*.bak' . PHP_EOL .
            PHP_EOL .
            '# Secret & Data' . PHP_EOL .
            'secrets/' . PHP_EOL .
            $database_name.'/' . PHP_EOL .
            PHP_EOL .
            '# SSL Certificates (managed by AA-Panel)' . PHP_EOL .
            '.well-known/' . PHP_EOL
        );
        echo 'DONE. -->'. PHP_EOL;
        $startover=true;
    }