<?php 
    # j_* (jay star: GATEWAY)
    ob_start();
    
    J_INIT: 
        $startover=false;
        include('src/procedures/j_init.php');
        if ($startover) {$startover=false; goto J_INIT;}
        
    J_FUNCTION_LOADER:
        include('src/j_load_active_functions.php');
        j_load_active_functions('src/functions/*');

    J_CONFIGURATION:
        // register_shutdown_function('j_shutdown');
        j_memo_set('state/output', 'html');
        j_memo_set('state/aes', file_get_contents('secrets/encryption.key'));
        j_memo_set('state/protocol', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? 'https://' : 'http://');
        j_memo_set('data', []);

    J_REGISTRY:
        goto J_REGISTRY_DEMO; # WHICH REGISTRY?

        J_REGISTRY_DEMO:
            // TO-DO: implement real registry. This is "just" a (hardcoded) demo in necessary final format.
            j_memo_set('registry/realhost/localhost', ['web' => 'demo', 'domain' => 'localhost', 'host' => 'localhost', 'owner' => 'jophi']);
            j_memo_set('registry/realhost/127.0.0.1', ['web' => 'demo', 'domain' => 'localhost', 'host' => '127.0.0.1', 'owner' => 'jophi']);
            goto J_RESOLVE_TENANTS;

        J_REGISTRY_PRODUCTION:
            // TO-DO: implement real registry!
            goto J_RESOLVE_TENANTS;

    J_RESOLVE_TENANTS:
        // Associate current "realhost" to the system
        $realhost = strtolower(explode(':', $_SERVER['HTTP_HOST'])[0]);
        j_memo_set('analysis/tenant', j_memo_get('registry/realhost/' . $realhost, default: []));
        
        // Early exit if it failed:
        if (count(j_memo_get('analysis/tenant', default: [])) == 0) goto J_ERROR_TENANT;

    J_RESOLVE_URL_PATH:

        // Analysis of requested path:
        $request_uri = $_SERVER['REQUEST_URI'];
        $original_path = trim(parse_url($request_uri, PHP_URL_PATH), '/');

        // TO-DO:
        // Check if it starts with @
        // Erstes segment des Pfades extrahieren und für den lookup nutzen.
            // User-Profile-Mode (web/$web/$user_ordner_mit_user_shard_wildcard/auth/username=$userNamePhraseWithoutAtSymbol - caseinsensitive!)
            // Bei "MATCH" User Profile anzeigen, sofern der user das erlaubt.
            // Bei "keinem MATCH" 404.
        // Rest des Pfades weiter nutzen, denn analog zum "normalen Inhalt" gibt es auch im user-ordner dieselbe struktur. nur 
            // statt web/$web/domain/$domain/host/$host ist alles eben unter 
            // web/$web/$user_ordner_mit_user_shard/ erreichbar.
        
        // Check if asset path exists (marked by ~/)
        $is_asset_request = strpos($original_path, '~/') !== false;
        $parts = explode('~/', $original_path, 2);
        $structured_part = trim($parts[0], '/');
        $asset_part = isset($parts[1]) ? trim($parts[1], '/') : '';
        
        // Extract SEO term from asset path (everything after last _)
        $seo_term = '';
        if ($asset_part !== '') {
            $asset_segments = explode('/', $asset_part);
            $last_segment = end($asset_segments);
            if (strpos($last_segment, '_') !== false) {
                list($asset_id, $seo) = explode('_', $last_segment, 2);
                $seo_term = $seo;
                // Remove SEO part from asset path
                array_pop($asset_segments);
                $asset_segments[] = $asset_id;
                $asset_part = implode('/', $asset_segments);
            }
        }

    J_SET_SYSTEM_WIDE_PATHES:
        // Get tenant info
        $web = j_memo_get('analysis/tenant/web');
        $domain = j_memo_get('analysis/tenant/domain');
        $host = j_memo_get('analysis/tenant/host');
        
        // Build full structured path with host
        $full_structured = $host . ($structured_part !== '' ? '/' . $structured_part : '');
        
        // Create flat path (replace / with ⌇) - only from structured part!
        $flat_path = slash_to_wave($full_structured);
        
        // State checkers
        j_memo_set('state/is_asset_request', $is_asset_request);
        
        // Analysis
        j_memo_set('analysis/original', $original_path);
        j_memo_set('analysis/flat', $flat_path);
        j_memo_set('analysis/asset', $asset_part);
        j_memo_set('analysis/seo', $seo_term);
        
        // Generate all paths (tenant base + current)
        $base_web = 'web/' . $web;
        $base_domain = $base_web . '/domain/' . $domain;
        $base_host = $base_domain . '/host/' . $host;
        $current_base = $base_host . '/' . $flat_path;
        
        // Normal Mode (so, not user-profile-mode)
        $paths = [
            'web' => $base_web,
            'domain' => $base_domain,
            'host' => $base_host,
            'current-meta' => $current_base . '/meta',
            'current-needs-access' => $current_base . '/needs-access',
            'current-content' => $current_base . '/content',
            'current-media' => $current_base . '/media',
            'current-media-asset' => $current_base . '/media' . ($asset_part !== '' ? '/' . $asset_part : ''),
            'current-media-asset-needs-access' => $current_base . '/media' . ($asset_part !== '' ? '/' . $asset_part : '') . '/needs-access',
        ];
        
        j_memo_set('pathes', $paths);

        // j_memo_set('demo/j_reduce_path', j_reduce_path(j_memo_get('pathes/current-meta'), 2));

    J_INPUT:

        J_INPUT_COOKIES:
            j_cookie_get('__necessary', 
                default: [
                    'device-id' => slash_to_dash(j_id('device')),
                    'user-id' => null,
                    'logged-in' => false,
                ]
            );

        J_INPUT_POST:
            $p=j_kv_get('_POST');
            $pc=count($p);
            j_memo_set('var_post', $p , separator:'_');
            j_memo_set('state/is_post', $pc);
        
        J_INPUT_GET:
            $g=j_kv_get('_GET');
            $gc=count($g);
            j_memo_set('var_get',  $g,  separator:'_');
            j_memo_set('state/is_get',  $gc);

        J_INPUT_IMPLICIT_RUN_MODES:
            j_memo_set('state/is_login',    j_memo_get('var/post/login')  ?? j_memo_get('var/get/login')  ?? false);
            j_memo_set('state/is_logout',   j_memo_get('var/post/logout') ?? j_memo_get('var/get/logout') ?? false);


    J_EVERYTHING_IS_FINE_SO_SKIP_TO_EXIT:
        goto J_EXIT;

    J_ERROR:
        J_ERROR_DEMO:
            // Just a DEMO-Error to demonstrate an error situation
            echo "[INFO]: Just a demo error\n";
            goto J_EXIT;

        J_ERROR_TENANT:
            j_memo_set('analysis/tenant/domain', $realhost); # to have any "domain" for included files
            echo "[TO-DO]: J_ERROR_TENANT\n";
            goto J_EXIT;

    J_EXIT:
        // wird später in j_shutdown() ausgelagert:
        j_memo_set('analysis/unwanted', array_filter(explode("\n",ob_get_clean())));
        // as soon as it is stable enough, this will be implemented into the registered shutdown function.
        $cookies = j_memo_get('var/cookies', default: []);
        foreach(array_keys($cookies) as $cookie) {
            j_cookie_set($cookie);
        }

        switch (j_memo_get('state/output')) {
            case 'json':
                // TO-DO: json-output
                break;
            case 'media':
                // TO-DO: media-output
                break;
            case 'html':
            default:
                // TO-DO: html-output
                echo "<h1>CURRENTLY ONLY DEBUG:</h1>";
                echo "<pre>";
                print_r(j_memo_get());
                break;
        }