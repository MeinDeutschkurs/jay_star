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

    J_REGISTRY: # WHICH REGISTRY?
        goto J_REGISTRY_DEMO; 

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
        j_memo_set('state/is_user_profile_mode', false);
        if (str_starts_with($original_path, '@')) {
            j_memo_set('state/is_user_profile_mode', true);
            j_memo_set('state/is_user_profile_mode_user_is_found', false);
            $segments = explode('/', $original_path, 2);
            $username = substr($segments[0], 1); // Remove @ symbol
            $rest = $segments[1] ?? '';

            // Lookup user by username (case-insensitive)
            $web = j_memo_get('analysis/tenant/web');
            $matches = j_glob(
                'web/' . $web . '/' . j_id_wildcard('user') . '/auth/username=' . $username,
                case_insensitive: true
            );

            $s_result = count($matches);

            // Exact match:
            if ($s_result === 1) {
                // Exact match: User found - extract user sharded id
                $user_shard_only = j_reduce_path($matches[0], 2); // Remove /auth/username=...
                $user_sharded_id_slashes = 'user/' . $user_shard_only;
                j_memo_set('state/is_user_profile_mode_user_is_found', true);
                j_memo_set('analysis/user_sharded_id_slashes', $user_sharded_id_slashes);
                $original_path = trim($rest, '/');
            } // no error jump here, because we need more data.
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
            'current-needs-access' => $current_base . '/needs_access',
            'current-content' => $current_base . '/content',
            'current-media' => $current_base . '/media',
            'current-media-asset' => $current_base . '/media' . ($asset_part !== '' ? '/' . $asset_part : ''),
            'current-media-asset-needs-access' => $current_base . '/media' . ($asset_part !== '' ? '/' . $asset_part : '') . '/needs_access',
        ];
        
        // set all already known base pathes:
        j_memo_set('pathes', $paths);

    J_INPUT:

        J_INPUT_COOKIES:
            // ACHTUNG: das "__necessary-cookie" heißt jetht PHPSESSID! 
            // Das soll dafür sorgen, dass das Cookie eher angenommen als abgelehnt wird.
            j_cookie_get('PHPSESSID', 
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
            // reminder: we already have state/is_post
            // reminder: we already have state/is_get
            // reminder: we already have state/is_user_profile_mode with its state/is_user_profile_mode_user_is_found
            // reminder: we already have state/is_asset_request
            j_memo_set('state/is_login',            j_memo_get('var/post/login')  ?? j_memo_get('var/get/login')  ?? false);
            j_memo_set('state/is_logout',           j_memo_get('var/post/logout') ?? j_memo_get('var/get/logout') ?? false);
            j_memo_set('state/is_backend_web',      j_memo_get('var/post/web')    ?? j_memo_get('var/get/web')    ?? false);
            j_memo_set('state/is_backend_domain',   j_memo_get('var/post/dom')    ?? j_memo_get('var/get/dom')    ?? false);
            j_memo_set('state/is_backend_host',     j_memo_get('var/post/host')   ?? j_memo_get('var/get/host')   ?? false);


        goto J_AUTH; 
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

    J_AUTH:
        $web = j_memo_get('analysis/tenant/web');
        $device_id_dash = j_from_cookie('PHPSESSID', 'device-id');

        // LOGOUT-HANDLER
        if (j_memo_get('state/is_logout')) {
            $user_sharded_id_dashes = j_from_cookie('PHPSESSID', 'user-id');
            if ($user_sharded_id_dashes) {
                $user_sharded_id_slashes = dash_to_slash($user_sharded_id_dashes);
                j_files_set("web/$web/$user_sharded_id_slashes/devices/$device_id_dash=", 'false');
            }
            j_update_cookie('PHPSESSID', 'user-id', null);
            j_update_cookie('PHPSESSID', 'logged-in', false);
            j_memo_set('state/user_sharded_id_slashes', null);
            j_memo_set('state/logged_in', false);
            goto J_CURRENT;
        }

        // LOGIN-HANDLER
        if (j_memo_get('state/is_login')) {
            $username = j_memo_get('var/post/username') ?? j_memo_get('var/get/username');
            $password = j_memo_get('var/post/password') ?? j_memo_get('var/get/password');

            if ($username && $password) {
                $matches = j_glob(
                    "web/$web/" . j_id_wildcard('user') . "/auth/username=$username",
                    case_insensitive: true
                );

                if (count($matches) > 0) {
                    $match_path = $matches[0];
                    $user_shard_only = j_extract_from_path($match_path, 'user');
                    $user_sharded_id_slashes = 'user/' . $user_shard_only;
                    $stored_hash = j_files_get("web/$web/$user_sharded_id_slashes/auth/userpassword");

                    if (password_verify($password, $stored_hash)) {
                        // Login erfolgreich
                        $user_sharded_id_dashes = slash_to_dash($user_sharded_id_slashes);
                        j_files_set("web/$web/$user_sharded_id_slashes/devices/$device_id_dash=", time());
                        j_update_cookie('PHPSESSID', 'user-id', $user_sharded_id_dashes);
                        j_update_cookie('PHPSESSID', 'logged-in', true);
                        j_memo_set('state/user_sharded_id_slashes', $user_sharded_id_slashes);
                        j_memo_set('state/logged_in', true);
                    } else {
                        // Falsches Passwort
                        j_memo_set('data/messages/error[]', 'Invalid username or password');
                        j_memo_set('state/login_failed', true);
                        j_memo_set('state/logged_in', false);
                    }
                } else {
                    // User nicht gefunden
                    j_memo_set('data/messages/error[]', 'Invalid username or password');
                    j_memo_set('state/login_failed', true);
                    j_memo_set('state/logged_in', false);
                }
            } else {
                j_memo_set('data/messages/error[]', 'Username and password required');
            }
        }

        // SESSION-VALIDIERUNG (für bereits eingeloggte User)
        if (!j_memo_get('state/is_login') && !j_memo_get('state/is_logout')) {
            $user_sharded_id_dashes = j_from_cookie('PHPSESSID', 'user-id');
            $logged_in = j_from_cookie('PHPSESSID', 'logged-in');

            if ($user_sharded_id_dashes && $logged_in) {
                $user_sharded_id_slashes = dash_to_slash($user_sharded_id_dashes);
                $device_status = j_files_get("web/$web/$user_sharded_id_slashes/devices/$device_id_dash=", default: 'false');

                if ($device_status === 'false' || $device_status === false) {
                    // Device wurde revoked oder existiert nicht - Auto-Logout
                    j_update_cookie('PHPSESSID', 'user-id', null);
                    j_update_cookie('PHPSESSID', 'logged-in', false);
                    j_memo_set('state/user_sharded_id_slashes', null);
                    j_memo_set('state/logged_in', false);
                } else {
                    // Session gültig
                    j_memo_set('state/user_sharded_id_slashes', $user_sharded_id_slashes);
                    j_memo_set('state/logged_in', true);
                }
            } else {
                j_memo_set('state/logged_in', false);
            }
        }

        // ACCESS-CONTROL: has-access laden
        j_memo_set('state/current-user-has-access', []);

        if (j_memo_get('state/logged_in') === true) {
            $user_sharded_id_slashes = j_memo_get('state/user_sharded_id_slashes');
            $has_access = j_files_get("web/$web/$user_sharded_id_slashes/has_access", default: []);

            foreach ($has_access as $key => $value) {
                j_memo_set("state/current-user-has-access/$key", $value);
            }
        }

        // PUBLIC immer hinzufügen (für ALLE - eingeloggt oder nicht!)
        j_memo_set('state/current-user-has-access/public::=', 1);

    J_CURRENT:

        J_CURRENT_SETTINGS:
            // reminder: a value will be set like this: j_files_set($web_settings_path.'/localization/language', 'de');
            // Read available overrides (structure with only the values, flattened)
            $web_settings_path='system/'.j_memo_get('pathes/web').'/settings';
            $domain_settings_path='system/'.j_memo_get('pathes/domain').'/settings';
            $host_settings_path='system/'.j_memo_get('pathes/host').'/settings';
            $user_settings_path=null;

            if (j_memo_get('state/logged_in') === true) {
                $user_settings_path=j_memo_get('pathes/web').'/'.j_memo_get('state/user_sharded_id_slashes').'/settings';
            }

            j_memo_set($web_settings_path, j_flatten_array(j_files_get($web_settings_path, default: [])), separator:'.');
            j_memo_set($domain_settings_path, j_flatten_array(j_files_get($domain_settings_path, default: [])), separator:'.');
            j_memo_set($host_settings_path, j_flatten_array(j_files_get($host_settings_path, default: [])), separator: '.');

            // configure override chain:
            j_memo_set('system/override-chain', [
                1 => 'web',
                2 => 'domain',
                3 => 'host'
            ]); 

            // append user to override chain, if a user is logged in:
            if (j_memo_get('state/logged_in') === true) {
                j_memo_set('system/override-chain[]', 'user');
            }

            J_SYSTEM_SETTINGS:
                j_memo_set('system/settings', [
                    'type' => 'settings',
                    'icon' => '⚙️',
                ]);

                // settings/localization
                j_memo_set('system/settings/localization', [
                    'type' => 'settings_category',
                    'icon' => '🌍',
                ]);

                j_memo_set('system/settings/localization/language', [
                    'type' => 'settings_category_selectosdlanguage',
                    'icon' => '🗣️',
                    'value' => 'de',
                    'can_be_changed_by' => ['web', 'domain', 'host'],
                    'log-history' => false
                ]);

                j_memo_set('system/settings/localization/time-zone', [
                    'type' => 'settings_category_selecttimezone',
                    'icon' => '🕗',
                    'value' => '',
                    'can_be_changed_by' => ['user'],
                    'log-history' => true
                ]);

                // settings/appearance
                j_memo_set('system/settings/appearance', [
                    'type' => 'settings_category',
                    'icon' => '👨‍🎨',
                ]);


                j_memo_set('system/settings/appearance/theme', [
                    'type' => 'settings_category_selecttheme',
                    'icon' => '🌓',
                    'value' => 'Dark',
                    'can_be_changed_by' => ['web', 'domain', 'host', 'user'],
                    'log-history' => false,
                ]);

                j_memo_set('system/settings/appearance/scheme', [
                    'type' => 'settings_category_selectscheme',
                    'icon' => '🌈',
                    'value' => 'default',
                    'can_be_changed_by' => ['web', 'domain', 'host', 'user'],
                    'log-history' => false,
                ]);


                j_memo_set('system/settings/appearance/base-layout', [
                    'type' => 'settings_category_selectbaselayout',
                    'icon' => '⿲',
                    'value' => 'default',
                    'can_be_changed_by' => ['web', 'domain', 'host'],
                    'log-history' => false,
                ]);

                // settings/project
                j_memo_set('system/settings/project', [
                    'type' => 'settings_category',
                    'icon' => '🏗️',
                ]);

                j_memo_set('system/settings/project/title', [
                    'type' => 'settings_category_singleline',
                    'icon' => '#️⃣',
                    'value' => 'Jay Star',
                    'can_be_changed_by' => ['web', 'domain', 'host'],
                    'log-history' => false,
                ]);

                j_memo_set('system/settings/project/description', [
                    'type' => 'settings_category_singleline',
                    'icon' => 'ℹ️',
                    'value' => 'J_*',
                    'can_be_changed_by' => ['web', 'domain', 'host'],
                    'log-history' => false
                ]);

                j_memo_set('system/settings/project/bulk-email', [
                    'type' => 'settings_category_singleline',
                    'icon' => '📧',
                    'value' => 'no-reply@'.j_memo_get('analysis/tenant/host'),
                    'can_be_changed_by' => ['web', 'domain', 'host'],
                    'log-history' => false
                ]);

                // settings/dsgvo
                j_memo_set('system/settings/dsgvo', [
                    'type' => 'settings_category',
                    'icon' => '§',
                ]);

                j_memo_set('system/settings/dsgvo/allow-youtube-cookies', [
                    'type' => 'settings_category_timestamporfalse',
                    'icon' => '🎬',
                    'value' => '0',
                    'can_be_changed_by' => ['user'],
                    'log-history' => true
                ]);

                j_memo_set('system/settings/dsgvo/allow-vimeo-cookies', [
                    'type' => 'settings_category_timestamporfalse',
                    'icon' => '🎬',
                    'value' => '0',
                    'can_be_changed_by' => ['user'],
                    'log-history' => true
                ]);

                j_memo_set('system/settings/dsgvo/allow-facebook-cookies', [
                    'type' => 'settings_category_timestamporfalse',
                    'icon' => '🕸️',
                    'value' => '0',
                    'can_be_changed_by' => ['user'],
                    'log-history' => true
                ]);
                            
            J_OVERRIDE_SYSTEM_SETTINGS_BY_CHAIN_LEVELS:
                // LOGIC, INCLUDING USER OVERRIDES
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

J_CURRENT_FEATURE_CHECK:
    // Edit-Mode aus GET/POST holen
    $edit_mode = j_memo_get('var/get/edit') ?? j_memo_get('var/post/edit');

    if (!$edit_mode) {
        // Kein Edit-Mode aktiv
        goto J_SKIP_FEATURE_CHECK;
    }

    // Feature-Name generieren
    $current_feature = 'edit_' . $edit_mode;
    $bp = "state/current-feature-$current_feature-needs-access";

    $web = j_memo_get('analysis/tenant/web');
    $domain = j_memo_get('analysis/tenant/domain');
    $host = j_memo_get('analysis/tenant/host');

    // needs-access je nach Edit-Mode setzen
    switch($edit_mode) {
        case 'page':
            j_memo_set("$bp/web-admin::$web=", 1);
            j_memo_set("$bp/web-editor::$web=", 1);
            j_memo_set("$bp/domain-admin::$domain=", 1);
            j_memo_set("$bp/domain-editor::$domain=", 1);
            j_memo_set("$bp/host-admin::$host=", 1);
            j_memo_set("$bp/host-editor::$host=", 1);
            break;

        case 'profile':
            j_memo_set("$bp/registered::=", 1);  // Jeder registrierte User
            break;

        case 'web_settings':
            j_memo_set("$bp/web-admin::$web=", 1);
            // NUR Admin, NICHT Editor!
            break;

        case 'domain_settings':
            j_memo_set("$bp/domain-admin::$domain=", 1);
            break;

        case 'host_settings':
            j_memo_set("$bp/host-admin::$host=", 1);
            break;

        case 'user_settings':
            j_memo_set("$bp/registered::=", 1);  // Eigene Settings bearbeiten
            break;

        case 'url_structure':
            j_memo_set("$bp/web-admin::$web=", 1);
            break;

        default:
            // Unbekannter Edit-Mode
            $current_feature = false;
    }

    // Access-Check nur wenn Feature erkannt wurde
    if ($current_feature) {
        $feature_ok = j_check_access($bp);
        j_memo_set("state/feature-$current_feature-enabled", $feature_ok);
    }

    J_SKIP_FEATURE_CHECK:

    J_CRUD:
        J_READ:

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

        J_ERROR_LOGIN_FIRST:
            http_response_code(511);
            echo "TO-DO: Data: 511 Network Authentication Required - Please log in to access this content\n";
            goto J_EXIT;

        J_ERROR_FORBIDDEN:
            http_response_code(403);
            echo "TO-DO: Data: 403 Forbidden - You do not have permission to access this resource\n";
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