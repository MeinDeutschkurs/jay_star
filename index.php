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

        // PHASE 1: User Profile Mode (@username)
        if (str_starts_with($original_path, '@')) {
            $segments = explode('/', $original_path, 2);
            $username = substr($segments[0], 1); // Remove @ symbol
            $rest = $segments[1] ?? '';

            // Lookup user by username (case-insensitive)
            $web = j_memo_get('analysis/tenant/web');
            $matches = j_glob(
                'web/' . $web . '/' . j_id_wildcard('user') . '/auth/username=' . $username,
                case_insensitive: true
            );

            if (count($matches) > 0) {
                // User found - extract user shard path
                $user_shard = j_reduce_path($matches[0], 2); // Remove /auth/username=...
                j_memo_set('state/is_user_profile_mode', true);
                j_memo_set('analysis/user_shard', $user_shard);
                $original_path = trim($rest, '/');
            } else {
                // User not found - 404
                // ATTENTION, AS SOON AS WE HAVE THE HOST-BASED LAYOUT/DESIGN, THIS ERROR MUST BE COVERED BY A DIFFERENT HANDLER, WHICH
                // INCLUDES ALL THE WEB/DOMAIN AND HOST BASED FEATURES!
                goto J_ERROR_USER_NOT_FOUND;
            }
        }

        // PHASE 2: API Endpoint Detection (jophi/v1)
        $api_prefix = 'jophi/v1';
        if (str_starts_with($original_path, $api_prefix)) {
            j_memo_set('state/output', 'json');
            $original_path = trim(substr($original_path, strlen($api_prefix)), '/');
        }

        // PHASE 3: maybe further path-parts, if necessary, that change the basic behaviour.
        // TO-DO: As soon as in need.

        // FINAL PHASE: General splittings:
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
        $web    = j_memo_get('analysis/tenant/web');
        $domain = j_memo_get('analysis/tenant/domain');
        $host   = j_memo_get('analysis/tenant/host');
        
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

    J_READ:    

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
                j_memo_set('state/is_login',            j_memo_get('var/post/login')  ?? j_memo_get('var/get/login')  ?? false);
                j_memo_set('state/is_logout',           j_memo_get('var/post/logout') ?? j_memo_get('var/get/logout') ?? false);
                j_memo_set('state/is_backend_web',      j_memo_get('var/post/web')    ?? j_memo_get('var/get/web')    ?? false);
                j_memo_set('state/is_backend_domain',   j_memo_get('var/post/dom')    ?? j_memo_get('var/get/dom')    ?? false);
                j_memo_set('state/is_backend_host',     j_memo_get('var/post/host')   ?? j_memo_get('var/get/host')   ?? false);

            J_DEMO_OF_ALL_USERS_OF_WEB_DEMO:
                j_memo_set('demo/j_glob/find_all_users', j_glob(
                    'web/demo/' . j_id_wildcard('user') . '/auth/username=*',
                    trim_prefix: 'web/demo/',
                    trim_suffix: 'auth/username',
                    meta_split: true,
                    keys_slash_to_dash: true
                ));

                j_memo_set('demo/j_extract_from_path/user',     j_extract_from_path('web/demo/user/1/013/ac1/000/000/001/882/9e1/b8b/95e/f54/auth/username', 'user', default:'error'));
                j_memo_set('demo/j_extract_from_path/web',      j_extract_from_path('web/demo/domain/localhost/host/127.0.0.1/content/whatever', 'web', default:'error'));
                j_memo_set('demo/j_extract_from_path/domain',   j_extract_from_path('web/demo/domain/localhost/host/127.0.0.1/content/whatever', 'domain', default:'error'));
                j_memo_set('demo/j_extract_from_path/host',     j_extract_from_path('web/demo/domain/localhost/host/127.0.0.1/content/whatever', 'host', default:'error'));

        J_CURRENT:

            J_CURRENT_SETTINGS:
                // Read available overrides (structure with only the values, flattened)
                $web_settings_path='system/'.j_memo_get('pathes/web').'/settings';
                $domain_settings_path='system/'.j_memo_get('pathes/domain').'/settings';
                $host_settings_path='system/'.j_memo_get('pathes/host').'/settings';
                $user_settings_path=null;

                // set a demo value: j_files_set($web_settings_path.'/localization/language', 'de');

                j_memo_set($web_settings_path, j_flatten_array(j_files_get($web_settings_path, default: [])), separator:'.');
                j_memo_set($domain_settings_path, j_flatten_array(j_files_get($domain_settings_path, default: [])), separator:'.');
                j_memo_set($host_settings_path, j_flatten_array(j_files_get($host_settings_path, default: [])), separator: '.');

                // override chain:
                j_memo_set('system/override-chain', [
                    1 => 'web',
                    2 => 'domain',
                    3 => 'host',
                    4 => 'user',
                ]); 

                // system settings:
                j_memo_set('system/settings', [
                    'type' => 'settings',
                    'icon' => '⚙️',
                ]);

                j_memo_set('system/settings/localization', [
                    'type' => 'settings_category',
                    'icon' => '🌍',
                ]);

                // settings/localization/language
                j_memo_set('system/settings/localization/language', [
                    'type' => 'settings_category_select',
                    'icon' => '🗣️',
                    'value' => 'de',
                    'can_be_changed_by' => ['web', 'domain', 'host']
                ]);
                                

                // LOGIC, WITHOUT USER-OVERRIDES CURRENTLY, BECAUSE WE DO NOT HAVE A LOGIN-LOGOUT-SYSTEM NOW!         
                $chain = j_memo_get('system/override-chain');

                foreach ($chain as $level) {
                    $path = match($level) {
                        'web' => $web_settings_path,
                        'domain' => $domain_settings_path,
                        'host' => $host_settings_path,
                        'user' => $user_settings_path,
                    };
                    
                    $overrides = j_memo_get($path) ?? [];
                    
                    foreach ($overrides as $setting_key => $override_value) {
                        $can_change = j_memo_get("system/settings/{$setting_key}/can_be_changed_by") ?? [];
                        
                        if (in_array($level, $can_change)) {
                            j_memo_set("system/settings/{$setting_key}/value", $override_value);
                        }
                    }
                } 

    J_CREATE:

        // goto J_SKIP_CREATE_DEMO_USER;
        J_CREATE_DEMO_USER:
        /*
            $web = j_memo_get('analysis/tenant/web');
            $domain = j_memo_get('analysis/tenant/domain');
            $host = j_memo_get('analysis/tenant/host');
            $newUserShard = j_id('user');
            $newUserDash = slash_to_dash($newUserShard);
            $username = 'jophi';
            $useremail = 'jophi@zucki.guru';
            $passwort = 'geheim1234';
            $has_access = [
                'web-admin::' . $web . '=' => 1,
                'domain-admin::' . $domain . '=' => 1,
                'host-admin::' . $host . '=' => 1,
                'registered::=' => 1,
                'private::' . $newUserDash . '=' => 1,
            ];

            j_files_set("web/$web/" . $newUserShard, [
                'type=' => 'user_account',
                'has_access' => $has_access,
                'auth/type=' => 'user_account_auth',
                'auth/user_id=' => $newUserDash,
                'auth/username=' => $username,
                'auth/useremail=' => $useremail,
                'auth/userpassword' => password_hash($passwort, PASSWORD_DEFAULT),
                'meta/type=' => 'user_account_meta',
                'meta/creation-date=' => time(),
                'profile/type=' => 'user_account_profile',
                'profile/title=' => $username,
                'profile/description=' => 'Hello World!',
                'profile/needs_access' => [],
                'profile/blocks' => [],
            ]);
        */
        J_SKIP_CREATE_DEMO_USER:

    J_UPDATE:

    J_DELETE:
   
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

        J_ERROR_USER_NOT_FOUND:
            http_response_code(404);
            echo "[ERROR]: User profile not found\n";
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
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(j_memo_get('data', default: []));
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