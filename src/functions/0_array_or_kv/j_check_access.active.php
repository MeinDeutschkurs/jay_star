<?php
function j_check_access($needs_access_path, $fallback='public::=', $fallback_value=1) {
    $user_has = j_memo_get('state/current-user-has-access', default: []);
    $needs = j_memo_get($needs_access_path, default: []);

    // Sicherheit: Kein has-access definiert
    if (empty($user_has)) {
        echo "[Error]: No \$user_has element defined in state/current-user-has-access.\n";
        return false;
    }

    // Fallback wenn needs leer ist
    if (empty($needs)) {
        if (!empty($fallback) && !empty($fallback_value)) {
            // Fallback verwenden (z.B. 'public::=')
            $needs = [$fallback => $fallback_value];
        } else {
            echo "[Error]: No \$needs element defined in $needs_access_path, even after fallback.\n";
            return false;
        }
    }

    // ODER-Logik: EINER der needs muss matchen
    foreach ($needs as $required_key => $required_value) {
        if (isset($user_has[$required_key]) && $user_has[$required_key]) {
            // ACCESS GRANTED (mindestens ein Match gefunden)
            return true;
        }
    }

    // TODO: Custom Access Nodes prüfen (zeitlich, IP-basiert, device-basiert)

    // ACCESS DENIED (kein einziger Match)
    return false;
}
