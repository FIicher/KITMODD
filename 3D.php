<?php

// ============================================

// PROPAINT 3D - APPLICATION SÉCURISÉE

// ============================================



// === HEADERS DE SÉCURITÉ HTTP ===

// Permet l'intégration via iframe depuis d'autres domaines

// header('X-Frame-Options: SAMEORIGIN');

// Protection XSS intégrée au navigateur

header('X-XSS-Protection: 1; mode=block');

// Empêche le sniffing de type MIME

header('X-Content-Type-Options: nosniff');

// Politique de référent

header('Referrer-Policy: strict-origin-when-cross-origin');

// Permissions-Policy (limite les APIs sensibles)

header('Permissions-Policy: geolocation=(), microphone=(), camera=()');



// === CONFIGURATION SESSION SÉCURISÉE ===

if (session_status() !== PHP_SESSION_ACTIVE) {

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 

               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||

               (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    

    @session_start([

        'cookie_httponly' => true,

        'use_strict_mode' => true,

        'cookie_secure' => $isHttps,

        'cookie_samesite' => 'Lax',

        'use_only_cookies' => true,

        'cookie_lifetime' => 0, // Session cookie (expire à la fermeture du navigateur)

    ]);

    

    // Régénérer l'ID de session périodiquement pour éviter le fixation

    if (!isset($_SESSION['pp3_session_created'])) {

        $_SESSION['pp3_session_created'] = time();

    } elseif (time() - $_SESSION['pp3_session_created'] > 1800) {

        // Régénérer toutes les 30 minutes

        session_regenerate_id(true);

        $_SESSION['pp3_session_created'] = time();

    }

}



// === FONCTION DE GÉNÉRATION DE TOKEN CSRF ===

function pp3_csrf_token(): string {

    if (empty($_SESSION['pp3_csrf_token'])) {

        $_SESSION['pp3_csrf_token'] = bin2hex(random_bytes(32));

    }

    return $_SESSION['pp3_csrf_token'];

}



function pp3_csrf_verify(): bool {

    $token = $_POST['pp3_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (empty($_SESSION['pp3_csrf_token']) || empty($token)) {

        return false;

    }

    return hash_equals($_SESSION['pp3_csrf_token'], $token);

}



// === FONCTION DE SANITIZATION ===

function pp3_sanitize_string(string $str, int $maxLen = 1000): string {

    $str = trim($str);

    if (strlen($str) > $maxLen) {

        $str = substr($str, 0, $maxLen);

    }

    return $str;

}



function pp3_sanitize_html(string $str): string {

    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');

}



function pp3_sanitize_filename(string $name): string {

    // Supprime les caractères dangereux des noms de fichiers

    $name = preg_replace('/[\/\\\\:*?"<>|]/', '', $name);

    $name = preg_replace('/\.{2,}/', '.', $name);

    return trim($name);

}



// === MINIFICATION CSS/JS POUR PRODUCTION ===

function pp3_minify_css(string $css): string {

    // Supprime les commentaires

    $css = preg_replace('/\/\*.*?\*\//s', '', $css);

    // Supprime les espaces multiples

    $css = preg_replace('/\s+/', ' ', $css);

    // Supprime les espaces autour des caractères spéciaux

    $css = preg_replace('/\s*([{};:,>+~])\s*/', '$1', $css);

    // Supprime les espaces au début et à la fin

    $css = trim($css);

    return $css;

}



function pp3_minify_js(string $js): string {

    // Minification basique (ne casse pas le code)

    // Supprime les commentaires // sur une ligne

    $js = preg_replace('/\/\/[^\n]*/', '', $js);

    // Supprime les espaces multiples (sauf dans les strings)

    $js = preg_replace('/[ \t]+/', ' ', $js);

    // Supprime les nouvelles lignes multiples

    $js = preg_replace('/\n+/', "\n", $js);

    return trim($js);

}



// Mode production (mettre à true pour minifier)

define('PP3_PRODUCTION', false);



// Dossier de textures (créé automatiquement à côté de ce fichier)

$textureDir = __DIR__ . DIRECTORY_SEPARATOR . 'textureplan';

if (!is_dir($textureDir)) {

    @mkdir($textureDir, 0775, true);

}



// Dossier IA génération auto (sauvegardes feedback + cfg Groq)

$iagenDir = __DIR__ . DIRECTORY_SEPARATOR . 'iagenauto';

if (!is_dir($iagenDir)) {

    @mkdir($iagenDir, 0775, true);

}



// Dossier IA: imports GLB + JSON d'apprentissage

$iagenGlbDir = $iagenDir . DIRECTORY_SEPARATOR . 'glbiagen';

if (!is_dir($iagenGlbDir)) {

    @mkdir($iagenGlbDir, 0775, true);

}



// Génère quelques textures SVG par défaut (libres / procédurales)

$defaultTextures = [

    'soft-circle.svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128"><defs><radialGradient id="g" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#ffffff" stop-opacity="1"/><stop offset="70%" stop-color="#ffffff" stop-opacity="0.6"/><stop offset="100%" stop-color="#000000" stop-opacity="0"/></radialGradient></defs><rect width="128" height="128" fill="black"/><circle cx="64" cy="64" r="60" fill="url(#g)"/></svg>',

    'hard-circle.svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128"><rect width="128" height="128" fill="black"/><circle cx="64" cy="64" r="52" fill="white"/></svg>',

    'stripes.svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128"><defs><pattern id="p" width="12" height="12" patternUnits="userSpaceOnUse" patternTransform="rotate(25)"><rect width="12" height="12" fill="black"/><rect x="0" y="0" width="6" height="12" fill="white"/></pattern></defs><rect width="128" height="128" fill="url(#p)"/></svg>',

    'noise.svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128"><filter id="n"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" stitchTiles="stitch"/></filter><rect width="128" height="128" filter="url(#n)"/></svg>',

];



if (is_dir($textureDir)) {

    foreach ($defaultTextures as $file => $content) {

        $path = $textureDir . DIRECTORY_SEPARATOR . $file;

        if (!is_file($path)) {

            @file_put_contents($path, $content);

        }

    }

}



function list_textures(string $dir): array {

    if (!is_dir($dir)) return [];

    $items = @scandir($dir);

    if (!is_array($items)) return [];

    $out = [];

    foreach ($items as $name) {

        if ($name === '.' || $name === '..') continue;

        $path = $dir . DIRECTORY_SEPARATOR . $name;

        if (!is_file($path)) continue;

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) continue;

        $out[] = $name;

    }

    sort($out);

    return $out;

}



function pp3_load_theme_config(): string {

    $path = __DIR__ . DIRECTORY_SEPARATOR . 'theme_config.json';

    if (!file_exists($path)) return '';

    $json = @file_get_contents($path);

    $data = json_decode($json, true);

    if (!is_array($data)) return '';



    // Injection de la config JS pour le ThemeManager

    $jsConfig = "<script>window.pp3ThemeConfig = " . json_encode($data) . ";</script>";



    // Script pour appliquer le menu position au chargement

    $menuPosition = $data['desktop']['vars']['--menu-position'] ?? 'left';

    $loaderType = $data['desktop']['vars']['--loader-type'] ?? '1';

    $jsMenuPosition = "<script>document.body.setAttribute('data-menu-position', '" . addslashes($menuPosition) . "');</script>";



    // Helper pour générer le bloc CSS d'un device

    $buildBlock = function($vars, $mode) {

        $s = ":root {\n";

        foreach ($vars ?? [] as $k => $v) {

            $s .= "  {$k}: {$v} !important;\n";

        }

        $s .= "}\n";



        // Mode specific rules (mirrors JS logic)

        if ($mode === 'text') {

            $s .= "

              .icon-btn svg { display: none !important; }

              .icon-btn { width: auto !important; height: auto !important; padding: 8px 12px !important; aspect-ratio: auto !important; }

              .icon-btn .sr-only { position: static !important; width: auto !important; height: auto !important; clip: auto !important; color: var(--app-text) !important; }

            ";

        } else if ($mode === 'both') {

            $s .= "

              .icon-btn { width: auto !important; height: auto !important; padding: 8px 12px !important; display: inline-flex !important; gap: 8px; aspect-ratio: auto !important; }

              .icon-btn .sr-only { position: static !important; width: auto !important; height: auto !important; clip: auto !important; color: var(--app-text) !important; }

            ";

        } else {

             // icons default (reset to be sure)

             $s .= "

               .icon-btn svg { display: block !important; }

               .icon-btn .sr-only { position: absolute !important; width: 1px !important; height: 1px !important; clip: rect(0,0,0,0) !important; }

             ";

        }

        return $s;

    };



    $css = '';

    // Desktop

    $css .= $buildBlock($data['desktop']['vars'] ?? [], $data['desktop']['mode'] ?? 'icons');



    // Tablet

    $css .= "@media (max-width: 1024px) {\n" . $buildBlock($data['tablet']['vars'] ?? [], $data['tablet']['mode'] ?? 'icons') . "\n}\n";



    // Mobile

    $css .= "@media (max-width: 768px) {\n" . $buildBlock($data['mobile']['vars'] ?? [], $data['mobile']['mode'] ?? 'icons') . "\n}\n";



    // On ferme la balise style et on ajoute les scripts JS

    return $css . "</style>" . $jsConfig . $jsMenuPosition . "<style>";

    // Astuce: pp3_load_theme_config est appelé DANS <style id="..."> via echo.

    // Donc on ferme ce style, on insère le script, et on rouvre un style vide (qui sera fermé par le template </style>)

    // Wait, let's fix the template call site instead. it's simpler.

}



function pp3_get_loader_html(): string {

    $path = __DIR__ . DIRECTORY_SEPARATOR . 'theme_config.json';

    $loaderType = 1;

    $loaderSpans = '';



    if (file_exists($path)) {

        $json = @file_get_contents($path);

        $data = json_decode($json, true);

        if (is_array($data) && isset($data['desktop']['vars']['--loader-type'])) {

            $loaderType = intval($data['desktop']['vars']['--loader-type']);

        }

    }



    // Générer les spans nécessaires pour certains loaders

    if ($loaderType === 3) {

        $loaderSpans = '<span></span><span></span><span></span>';

    } else if ($loaderType === 7) {

        $loaderSpans = '<span></span><span></span><span></span><span></span><span></span>';

    } else if ($loaderType === 10) {

        $loaderSpans = '<span></span><span></span>';

    }



    if ($loaderType === 0) {

        return '<div id="pp3-loader" style="display:none;"><div id="pp3-loader-content"></div></div>';

    }



    return '<div id="pp3-loader"><div id="pp3-loader-content" class="pp3-loader-' . $loaderType . '">' . $loaderSpans . '</div></div>';

}



function pp3_save_theme_config(string $json): void {

    $path = __DIR__ . DIRECTORY_SEPARATOR . 'theme_config.json';

    @file_put_contents($path, $json);

}



// =========================

// Comptes / Premium / Admin

// =========================



function pp3_json(array $payload): void {

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload);

    exit;

}



function pp3_now_sql(): string {

    return gmdate('Y-m-d H:i:s');

}



function pp3_random_prefix(int $len = 13): string {

    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    $max = strlen($alphabet) - 1;

    $bytes = random_bytes($len);

    $out = '';

    for ($i = 0; $i < $len; $i++) {

        $out .= $alphabet[ord($bytes[$i]) % ($max + 1)];

    }

    return $out;

}



function pp3_get_prefix(): ?string {

    $p = $_SESSION['pp3_prefix'] ?? null;

    if (!is_string($p) || !preg_match('/^[a-zA-Z0-9]{13}$/', $p)) return null;

    return $p;

}



function pp3_set_prefix(string $prefix): void {

    $_SESSION['pp3_prefix'] = $prefix;

}



function pp3_get_db(): ?array {

    $cfg = $_SESSION['pp3_db'] ?? null;

    if (!is_array($cfg)) return null;

    $need = ['host', 'dbname', 'user', 'pass', 'charset'];

    foreach ($need as $k) {

        if (!array_key_exists($k, $cfg)) return null;

    }

    return $cfg;

}



function pp3_set_db(array $cfg): void {

    $_SESSION['pp3_db'] = $cfg;

}



function pp3_persist_path(): string {

    // Persistance serveur (même config pour tous les navigateurs)

    // NB: contient le mot de passe DB en clair si fourni.

    return __DIR__ . DIRECTORY_SEPARATOR . '.pp3_db_config.json';

}



function pp3_load_persisted_config_into_session(): void {

    // Si la session n'a pas la config, essayer de la charger depuis un fichier local.

    if (pp3_get_db() && pp3_get_prefix()) return;



    $path = pp3_persist_path();

    if (!is_file($path)) return;

    $raw = @file_get_contents($path);

    if (!is_string($raw) || $raw === '') return;

    $data = json_decode($raw, true);

    if (!is_array($data)) return;



    $prefix = $data['prefix'] ?? null;

    $db = $data['db'] ?? null;

    if (!is_string($prefix) || !preg_match('/^[a-zA-Z0-9]{13}$/', $prefix)) return;

    if (!is_array($db)) return;

    $need = ['host', 'dbname', 'user', 'pass', 'charset'];

    foreach ($need as $k) {

        if (!array_key_exists($k, $db)) return;

    }



    // Injecte dans la session uniquement si absent

    if (!pp3_get_prefix()) {

        pp3_set_prefix($prefix);

    }

    if (!pp3_get_db()) {

        pp3_set_db([

            'host' => (string)$db['host'],

            'dbname' => (string)$db['dbname'],

            'user' => (string)$db['user'],

            'pass' => (string)$db['pass'],

            'charset' => (string)($db['charset'] ?: 'utf8mb4'),

        ]);

    }

}



function pp3_save_persisted_config_from_session(): void {

    $prefix = pp3_get_prefix();

    $db = pp3_get_db();

    if (!$prefix || !$db) return;



    $path = pp3_persist_path();

    $payload = [

        'prefix' => $prefix,

        'db' => [

            'host' => (string)$db['host'],

            'dbname' => (string)$db['dbname'],

            'user' => (string)$db['user'],

            'pass' => (string)$db['pass'],

            'charset' => (string)$db['charset'],

        ],

        'saved_at' => pp3_now_sql(),

    ];



    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if (!is_string($json) || $json === '') return;



    // Écriture atomique

    $tmp = $path . '.tmp';

    @file_put_contents($tmp, $json, LOCK_EX);

    @rename($tmp, $path);

    @chmod($path, 0600);

}



function pp3_pdo(): PDO {

    $cfg = pp3_get_db();

    if (!$cfg) {

        throw new RuntimeException('DB non configurée.');

    }

    $host = (string)$cfg['host'];

    $dbname = (string)$cfg['dbname'];

    $charset = (string)$cfg['charset'];

    $user = (string)$cfg['user'];

    $pass = (string)$cfg['pass'];

    $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

    return new PDO($dsn, $user, $pass, [

        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        PDO::ATTR_EMULATE_PREPARES => false,

    ]);

}



function pp3_tbl_membre(string $p): string { return $p . 'propainttroidmembre'; }

function pp3_tbl_admin(string $p): string { return $p . 'propainttroidad'; }



function pp3_col(string $p, string $suffix): string {

    // suffix doit être alphanum/underscore pour éviter injection via noms de colonnes

    $suffix = preg_replace('/[^a-zA-Z0-9_]+/', '', $suffix);

    return $p . $suffix;

}



function pp3_create_tables(PDO $pdo, string $p): void {

    $membre = pp3_tbl_membre($p);

    $admin = pp3_tbl_admin($p);



    $colId = pp3_col($p, 'propainttroidid');

    $colMail = pp3_col($p, 'propainttroidmail');

    $colPwd = pp3_col($p, 'propainttroidpd');

    $colCreated = pp3_col($p, 'datecreationtroid');

    $colLast = pp3_col($p, 'derniereconnexiontroid');

    $colPrem = pp3_col($p, 'premtroid');

    // Champ interne (non demandé explicitement) pour marquer l\'admin

    $colIsAdmin = pp3_col($p, 'isadmintroid');



    $adminId = pp3_col($p, 'propainttroidadid');

    $adminTitle = pp3_col($p, 'propainttroidadtitle');

    $adminOpt = pp3_col($p, 'propainttroidaintoption');

    $adminSk = pp3_col($p, 'propainttroidstripesklive');

    $adminPk = pp3_col($p, 'propainttroidstripepklive');



    // Table membres

    $sqlM = "CREATE TABLE IF NOT EXISTS `{$membre}` (

        `{$colId}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

        `{$colMail}` VARCHAR(190) NOT NULL,

        `{$colPwd}` VARCHAR(255) NOT NULL,

        `{$colCreated}` DATETIME NULL,

        `{$colLast}` DATETIME NULL,

        `{$colPrem}` DATETIME NULL,

        `{$colIsAdmin}` TINYINT(1) NOT NULL DEFAULT 0,

        PRIMARY KEY (`{$colId}`),

        UNIQUE KEY `uniq_mail` (`{$colMail}`)

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sqlM);



    // Table admin (1 ligne)

    $sqlA = "CREATE TABLE IF NOT EXISTS `{$admin}` (

        `{$adminId}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

        `{$adminTitle}` VARCHAR(190) NOT NULL DEFAULT 'Propaint 3D Admin',

        `{$adminOpt}` LONGTEXT NULL,

        `{$adminSk}` VARCHAR(255) NULL,

        `{$adminPk}` VARCHAR(255) NULL,

        PRIMARY KEY (`{$adminId}`)

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sqlA);



    // Init admin row si vide

    $cnt = (int)$pdo->query("SELECT COUNT(*) AS c FROM `{$admin}`")->fetchColumn();

    if ($cnt <= 0) {

        $defaultOpt = json_encode([

            'premiumActive' => false,

            'exportRequiresPremium' => false,

            'ads' => [

                'enabled' => false,

                'zones' => [],

            ],

            'plans' => [

                // prix en centimes (0 = désactivé / gratuit)

                'unique' => 0,

                'day' => 0,

                'week' => 0,

                'month' => 0,

                'month3' => 0,

                'month6' => 0,

                'year' => 0,

                'lifetime' => 0,

            ],

            'enabledPlans' => [],

            'featurePremium' => [],

        ], JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare("INSERT INTO `{$admin}` (`{$adminTitle}`, `{$adminOpt}`) VALUES (:t, :o)");

        $stmt->execute([':t' => 'Propaint 3D Admin', ':o' => $defaultOpt]);

    }

}



function pp3_admin_get(PDO $pdo, string $p): array {

    $admin = pp3_tbl_admin($p);

    $adminId = pp3_col($p, 'propainttroidadid');

    $adminTitle = pp3_col($p, 'propainttroidadtitle');

    $adminOpt = pp3_col($p, 'propainttroidaintoption');

    $adminSk = pp3_col($p, 'propainttroidstripesklive');

    $adminPk = pp3_col($p, 'propainttroidstripepklive');

    $row = $pdo->query("SELECT `{$adminId}` AS id, `{$adminTitle}` AS title, `{$adminOpt}` AS opt, `{$adminSk}` AS sk, `{$adminPk}` AS pk FROM `{$admin}` ORDER BY `{$adminId}` ASC LIMIT 1")->fetch();

    $opt = [];

    if ($row && isset($row['opt']) && is_string($row['opt']) && $row['opt'] !== '') {

        $opt = json_decode($row['opt'], true);

        if (!is_array($opt)) $opt = [];

    }

    return [

        'id' => $row['id'] ?? null,

        'title' => $row['title'] ?? 'Admin',

        'opt' => $opt,

        'stripe' => [

            'sk' => $row['sk'] ?? null,

            'pk' => $row['pk'] ?? null,

        ],

    ];

}



function pp3_is_logged(): bool {

    return isset($_SESSION['pp3_user']) && is_array($_SESSION['pp3_user']) && isset($_SESSION['pp3_user']['id']);

}



function pp3_user(): ?array {

    $u = $_SESSION['pp3_user'] ?? null;

    return is_array($u) ? $u : null;

}



function pp3_refresh_user(PDO $pdo, string $p, int $id): array {

    $m = pp3_tbl_membre($p);

    $colId = pp3_col($p, 'propainttroidid');

    $colMail = pp3_col($p, 'propainttroidmail');

    $colLast = pp3_col($p, 'derniereconnexiontroid');

    $colPrem = pp3_col($p, 'premtroid');

    $colIsAdmin = pp3_col($p, 'isadmintroid');

    $stmt = $pdo->prepare("SELECT `{$colId}` AS id, `{$colMail}` AS mail, `{$colLast}` AS last_login, `{$colPrem}` AS premium_until, `{$colIsAdmin}` AS is_admin FROM `{$m}` WHERE `{$colId}`=:id LIMIT 1");

    $stmt->execute([':id' => $id]);

    $row = $stmt->fetch();

    if (!$row) throw new RuntimeException('Utilisateur introuvable.');

    $prem = $row['premium_until'] ?? null;

    $isPremium = false;

    if (is_string($prem) && $prem !== '') {

        $isPremium = (strtotime($prem . ' UTC') !== false) && (strtotime($prem . ' UTC') > time());

    }

    return [

        'id' => (int)$row['id'],

        'mail' => $row['mail'],

        'is_admin' => ((int)($row['is_admin'] ?? 0) === 1),

        'premium_until' => $prem,

        'is_premium' => $isPremium,

    ];

}



function pp3_set_user(array $u): void {

    $_SESSION['pp3_user'] = $u;

}



function pp3_clear_user(): void {

    unset($_SESSION['pp3_user']);

}



function pp3_stripe_request(string $method, string $path, string $sk, array $params = []): array {

    $url = 'https://api.stripe.com' . $path;

    $ch = curl_init();

    $headers = [

        'Authorization: Bearer ' . $sk,

    ];



    $method = strtoupper($method);

    if ($method === 'GET' && $params) {

        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);

    }



    curl_setopt_array($ch, [

        CURLOPT_URL => $url,

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_CONNECTTIMEOUT => 8,

        CURLOPT_TIMEOUT => 18,

        CURLOPT_HTTPHEADER => $headers,

    ]);



    if ($method === 'POST') {

        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

    }



    $raw = curl_exec($ch);

    $err = curl_error($ch);

    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);



    if ($raw === false) {

        throw new RuntimeException('Stripe error: ' . ($err ?: 'unknown'));

    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {

        throw new RuntimeException('Stripe error: réponse invalide');

    }

    if ($code >= 400) {

        $msg = $data['error']['message'] ?? ('HTTP ' . $code);

        throw new RuntimeException('Stripe error: ' . $msg);

    }

    return $data;

}



function pp3_plan_to_seconds(string $plan): int {

    switch ($plan) {

        case 'day': return 86400;

        case 'week': return 7 * 86400;

        case 'month': return 30 * 86400;

        case 'month3': return 90 * 86400;

        case 'month6': return 180 * 86400;

        case 'year': return 365 * 86400;

        case 'unique': return 30 * 86400; // achat unique = 1 mois par défaut

        case 'lifetime': return 50 * 365 * 86400;

        default: return 0;

    }

}



// === SYSTÈME DE TRADUCTION ===

function pp3_tbl_traduction(string $p): string { return $p . 'propainttroidtraduction'; }



function pp3_groq_translate_batch(string $apiKey, array $items, string $targetLang): array {

    $texts = [];

    foreach ($items as $it) {

        $txt = is_array($it) ? ($it['text'] ?? '') : (string)$it;

        $txt = trim($txt);

        if ($txt === '') continue;

        $texts[] = $txt;

    }

    if (!$texts) throw new Exception('Aucun texte à traduire');



    $system = 'Tu es un moteur de traduction. Tu traduis du français vers la langue cible indiquée. '

        . 'Ne rajoute pas de texte, ne change pas les noms de variables, le HTML ou les codes spéciaux. '

        . 'Réponds UNIQUEMENT par un JSON: un tableau de chaînes, dans le même ordre que les entrées.';



    $payload = [

        'model' => 'llama-3.3-70b-versatile',

        'temperature' => 0.2,

        'messages' => [

            ['role' => 'system', 'content' => $system],

            ['role' => 'user', 'content' => 'Langue cible: ' . $targetLang . "\n\nTextes à traduire (JSON array):\n" . json_encode($texts, JSON_UNESCAPED_UNICODE)],

        ],

    ];



    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_POST => true,

        CURLOPT_HTTPHEADER => [

            'Authorization: Bearer ' . $apiKey,

            'Content-Type: application/json',

        ],

        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),

        CURLOPT_TIMEOUT => 120,

    ]);

    $resp = curl_exec($ch);

    $err = curl_error($ch);

    curl_close($ch);



    if ($resp === false) throw new Exception('Erreur cURL Groq: ' . $err);

    $decoded = json_decode($resp, true);

    if (!is_array($decoded)) throw new Exception('Réponse Groq invalide');

    if (isset($decoded['error'])) {

        $msg = is_array($decoded['error']) ? ($decoded['error']['message'] ?? 'Erreur Groq') : (string)$decoded['error'];

        throw new Exception($msg);

    }



    $text = $decoded['choices'][0]['message']['content'] ?? '';

    if (!is_string($text) || $text === '') throw new Exception('Réponse IA vide');



    $jsonStr = $text;

    if (preg_match('/```(?:json)?\s*(.+?)```/s', $text, $m)) {

        $jsonStr = $m[1];

    }

    $parsed = json_decode(trim($jsonStr), true);

    if (!is_array($parsed)) throw new Exception('Impossible de parser la réponse IA');



    if (array_keys($parsed) === range(0, count($parsed) - 1)) {

        $out = [];

        foreach ($parsed as $v) $out[] = is_string($v) ? $v : (string)$v;

        return $out;

    }



    if (isset($parsed['translations']) && is_array($parsed['translations'])) {

        $out = [];

        foreach ($parsed['translations'] as $v) $out[] = is_string($v) ? $v : (string)$v;

        return $out;

    }



    throw new Exception('Format de réponse IA inattendu');

}



// === IA Génératrice 3D: config Groq + feedback (fichiers locaux) ===

function pp3_iagen_dir(): string {

    return __DIR__ . DIRECTORY_SEPARATOR . 'iagenauto';

}



function pp3_iagen_ensure_dir(): string {

    $dir = pp3_iagen_dir();

    if (!is_dir($dir)) {

        @mkdir($dir, 0775, true);

    }

    return $dir;

}



function pp3_iagen_glb_dir(): string {

    return pp3_iagen_ensure_dir() . DIRECTORY_SEPARATOR . 'glbiagen';

}



function pp3_iagen_ensure_glb_dir(): string {

    $dir = pp3_iagen_glb_dir();

    if (!is_dir($dir)) {

        @mkdir($dir, 0775, true);

    }

    return $dir;

}



function pp3_iagen_safe_slug(string $s, int $maxLen = 60): string {

    $s = trim($s);

    if ($s === '') return 'x';

    $s = mb_strtolower($s);

    $s = preg_replace('/[^a-z0-9]+/u', '-', $s);

    $s = trim($s, '-');

    if ($s === '') $s = 'x';

    if (strlen($s) > $maxLen) $s = substr($s, 0, $maxLen);

    return $s;

}



function pp3_iagen_load_training_examples(int $maxFiles = 200): array {

    $dir = pp3_iagen_ensure_glb_dir();

    $files = glob($dir . DIRECTORY_SEPARATOR . '*.json');

    if (!is_array($files)) $files = [];

    rsort($files);

    $files = array_slice($files, 0, $maxFiles);

    $out = [];

    foreach ($files as $f) {

        $raw = @file_get_contents($f);

        if (!is_string($raw) || $raw === '') continue;

        $d = json_decode($raw, true);

        if (!is_array($d)) continue;

        $out[] = $d;

    }

    return $out;

}



function pp3_iagen_pick_relevant_examples(array $all, array $selections, int $limit = 8): array {

    $cat = (string)($selections['ai-sel-1'] ?? '');

    $target = (string)($selections['ai-sel-2'] ?? '');

    $catN = pp3_iagen_safe_slug($cat);

    $targetN = pp3_iagen_safe_slug($target);



    $scored = [];

    foreach ($all as $ex) {

        if (!is_array($ex)) continue;

        $score = 0;

        $exCat = pp3_iagen_safe_slug((string)($ex['category'] ?? ''));

        $exTarget = pp3_iagen_safe_slug((string)($ex['target'] ?? ''));

        if ($cat !== '' && $cat !== 'Auto' && $exCat === $catN) $score += 3;

        if ($target !== '' && $target !== 'Auto' && $exTarget === $targetN) $score += 4;

        $scored[] = ['score' => $score, 'ex' => $ex];

    }

    usort($scored, function($a, $b) {

        return ($b['score'] <=> $a['score']);

    });



    $picked = [];

    foreach ($scored as $row) {

        $picked[] = $row['ex'];

        if (count($picked) >= $limit) break;

    }



    // Résume pour ne pas exploser le prompt

    $summary = [];

    foreach ($picked as $ex) {

        $f = is_array($ex['features'] ?? null) ? $ex['features'] : [];



        // Échantillon compact des parties (si fourni) pour permettre des modifications ciblées.

        $partsSample = null;

        if (isset($f['parts']) && is_array($f['parts'])) {

            $partsSample = [];

            $n = 0;

            foreach ($f['parts'] as $p) {

                if (!is_array($p)) continue;

                $partsSample[] = [

                    'part_id' => (string)($p['part_id'] ?? ''),

                    'name' => (string)($p['name'] ?? ''),

                    'path' => (string)($p['path'] ?? ''),

                    'material_name' => (string)($p['material_name'] ?? ''),

                    'colors' => $p['colors'] ?? null,

                    'maps' => $p['maps'] ?? null,

                ];

                $n++;

                if ($n >= 8) break;

            }

        }



        $summary[] = [

            'source' => (string)($ex['source'] ?? 'glb'),

            'category' => (string)($ex['category'] ?? ''),

            'target' => (string)($ex['target'] ?? ''),

            'name' => (string)($ex['name'] ?? ''),

            'glb_file' => (string)($ex['glb_file'] ?? ''),

            'bbox' => $f['bbox'] ?? null,

            'bbox_ratio' => $f['bbox_ratio'] ?? null,

            'mesh_count' => $f['mesh_count'] ?? null,

            'vertex_count' => $f['vertex_count'] ?? null,

            'triangle_count' => $f['triangle_count'] ?? null,

            'material_count' => $f['material_count'] ?? null,

            'texture_count' => $f['texture_count'] ?? null,

            'dominant_colors' => $f['dominant_colors'] ?? null,

            'lights' => $f['lights'] ?? null,

            'parts_sample' => $partsSample,

        ];

    }

    return $summary;

}



function pp3_iagen_cfg_json_path(): string {

    return pp3_iagen_ensure_dir() . DIRECTORY_SEPARATOR . 'cfgrq.json';

}



function pp3_iagen_cfg_js_path(): string {

    return pp3_iagen_ensure_dir() . DIRECTORY_SEPARATOR . 'cfgrq.js';

}



function pp3_iagen_load_cfg(): array {

    $cfg = [

        'groq_api_key' => '',

        'groq_model' => 'llama-3.3-70b-versatile',

        'saved_at' => null,

    ];

    $path = pp3_iagen_cfg_json_path();

    if (is_file($path)) {

        $raw = @file_get_contents($path);

        if (is_string($raw) && $raw !== '') {

            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {

                $cfg['groq_api_key'] = (string)($decoded['groq_api_key'] ?? $cfg['groq_api_key']);

                $cfg['groq_model'] = (string)($decoded['groq_model'] ?? $cfg['groq_model']);

                $cfg['saved_at'] = $decoded['saved_at'] ?? $cfg['saved_at'];

            }

        }

    }

    return $cfg;

}



function pp3_iagen_save_cfg(string $apiKey, string $model): void {

    pp3_iagen_ensure_dir();

    $payload = [

        'groq_api_key' => $apiKey,

        'groq_model' => $model,

        'saved_at' => pp3_now_sql(),

    ];



    $jsonPath = pp3_iagen_cfg_json_path();

    @file_put_contents($jsonPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);



    // cfgrq.js demandé: expose volontairement la clé côté client (admin doit savoir que c'est sensible).

    $jsPath = pp3_iagen_cfg_js_path();

    $js = 'window.__IAGEN_GROQ_CFG__ = ' . json_encode([

        'apiKey' => $apiKey,

        'model' => $model,

        'savedAt' => $payload['saved_at'],

    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';';

    @file_put_contents($jsPath, $js, LOCK_EX);

}



function pp3_groq_chat(string $apiKey, string $model, array $messages, float $temperature = 0.2, int $maxTokens = 1200): string {

    $payload = [

        'model' => $model,

        'temperature' => $temperature,

        'max_tokens' => $maxTokens,

        'messages' => $messages,

    ];



    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_POST => true,

        CURLOPT_HTTPHEADER => [

            'Authorization: Bearer ' . $apiKey,

            'Content-Type: application/json',

        ],

        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),

        CURLOPT_TIMEOUT => 120,

    ]);

    $resp = curl_exec($ch);

    $err = curl_error($ch);

    curl_close($ch);



    if ($resp === false) {

        throw new Exception('Erreur cURL Groq: ' . $err);

    }

    $decoded = json_decode($resp, true);

    if (!is_array($decoded)) {

        throw new Exception('Réponse Groq invalide');

    }

    if (isset($decoded['error'])) {

        $msg = is_array($decoded['error']) ? ($decoded['error']['message'] ?? 'Erreur Groq') : (string)$decoded['error'];

        throw new Exception($msg);

    }

    $text = $decoded['choices'][0]['message']['content'] ?? '';

    if (!is_string($text) || $text === '') {

        throw new Exception('Réponse IA vide');

    }

    return $text;

}



function pp3_parse_json_from_llm(string $text): array {

    $jsonStr = $text;

    if (preg_match('/```(?:json)?\s*(.+?)```/s', $text, $m)) {

        $jsonStr = $m[1];

    }

    $parsed = json_decode(trim($jsonStr), true);

    if (!is_array($parsed)) {

        throw new Exception('Impossible de parser la réponse IA (JSON attendu).');

    }

    return $parsed;

}



function pp3_iagen_feedback_stats(int $maxFiles = 400): array {

    $dir = pp3_iagen_ensure_dir();

    $files = glob($dir . DIRECTORY_SEPARATOR . 'feedback_*.json');

    if (!is_array($files)) $files = [];

    rsort($files);

    $files = array_slice($files, 0, $maxFiles);



    $bySelect = []; // selectKey => ['like'=>[opt=>count], 'dislike'=>[opt=>count]]

    $total = 0;

    $recentLikedNames = [];

    $recentDislikedNames = [];

    $recentDislikedPrompts = [];



    // Apprentissage additionnel (sans casser l'existant): stats par parties GLB.

    // Clé: part_id ou fallback (nameIncludes/materialNameIncludes)

    $byPart = ['like' => [], 'dislike' => []];



    foreach ($files as $f) {

        $raw = @file_get_contents($f);

        if (!is_string($raw) || $raw === '') continue;

        $d = json_decode($raw, true);

        if (!is_array($d)) continue;

        $liked = !!($d['liked'] ?? false);

        $sel = $d['selections'] ?? null;

        if (!is_array($sel)) continue;

        $total++;



        // Anti-répétition (facultatif): noms/prompt récents

        $obj = $d['object'] ?? null;

        if (is_array($obj)) {

            $name = $obj['name'] ?? null;

            if (is_string($name) && trim($name) !== '') {

                if ($liked) $recentLikedNames[] = trim($name);

                else $recentDislikedNames[] = trim($name);

            }

            $iagen = $obj['iagen'] ?? null;

            if (!$iagen && isset($obj['userData']['iagen']) && is_array($obj['userData']['iagen'])) {

                $iagen = $obj['userData']['iagen'];

            }

            if (is_array($iagen)) {

                $plan = $iagen['plan'] ?? null;

                if (is_array($plan)) {

                    if (!$liked) {

                        $p = $plan['prompt'] ?? null;

                        if (is_string($p) && trim($p) !== '') {

                            $recentDislikedPrompts[] = trim($p);

                        }

                    }



                    // Capture part_ops (si présent) pour guider les futures modifications ciblées

                    $partOps = $plan['part_ops'] ?? null;

                    if (is_array($partOps)) {

                        foreach ($partOps as $op) {

                            if (!is_array($op)) continue;

                            $match = $op['match'] ?? null;

                            if (!is_array($match)) $match = [];

                            $key = '';

                            if (isset($match['part_id']) && is_string($match['part_id']) && trim($match['part_id']) !== '') {

                                $key = 'part_id:' . trim($match['part_id']);

                            } else {

                                $ni = isset($match['nameIncludes']) && is_string($match['nameIncludes']) ? trim($match['nameIncludes']) : '';

                                $mi = isset($match['materialNameIncludes']) && is_string($match['materialNameIncludes']) ? trim($match['materialNameIncludes']) : '';

                                if ($ni !== '' || $mi !== '') {

                                    $key = 'match:' . $ni . '|' . $mi;

                                }

                            }

                            if ($key === '') continue;

                            $bucket = $liked ? 'like' : 'dislike';

                            if (!isset($byPart[$bucket][$key])) $byPart[$bucket][$key] = 0;

                            $byPart[$bucket][$key]++;

                        }

                    }

                }

            }

        }



        foreach ($sel as $k => $v) {

            $k = (string)$k;

            $v = (string)$v;

            if ($k === '' || $v === '') continue;

            if (!isset($bySelect[$k])) {

                $bySelect[$k] = ['like' => [], 'dislike' => []];

            }

            $bucket = $liked ? 'like' : 'dislike';

            if (!isset($bySelect[$k][$bucket][$v])) $bySelect[$k][$bucket][$v] = 0;

            $bySelect[$k][$bucket][$v]++;

        }

    }



    // Top 3 par select

    $summary = [];

    foreach ($bySelect as $k => $b) {

        arsort($b['like']);

        arsort($b['dislike']);

        $summary[$k] = [

            'top_like' => array_slice(array_keys($b['like']), 0, 3),

            'top_dislike' => array_slice(array_keys($b['dislike']), 0, 3),

        ];

    }



    // Top par parties

    $byPartSummary = null;

    if (!empty($byPart['like']) || !empty($byPart['dislike'])) {

        arsort($byPart['like']);

        arsort($byPart['dislike']);

        $byPartSummary = [

            'top_like' => array_slice(array_keys($byPart['like']), 0, 8),

            'top_dislike' => array_slice(array_keys($byPart['dislike']), 0, 8),

        ];

    }



    return [

        'total_feedback_files' => count($files),

        'total_used' => $total,

        'by_select' => $summary,

        'by_part' => $byPartSummary,

        'avoid' => [

            'recent_disliked_names' => array_values(array_slice(array_unique($recentDislikedNames), 0, 40)),

            'recent_disliked_prompts' => array_values(array_slice(array_unique($recentDislikedPrompts), 0, 40)),

        ],

        'prefer' => [

            'recent_liked_names' => array_values(array_slice(array_unique($recentLikedNames), 0, 40)),

        ],

    ];

}



function pp3_groq_test_key(string $apiKey): array {

    $ch = curl_init('https://api.groq.com/openai/v1/models');

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPGET => true,

        CURLOPT_HTTPHEADER => [

            'Authorization: Bearer ' . $apiKey,

            'Content-Type: application/json',

        ],

        CURLOPT_TIMEOUT => 30,

    ]);

    $resp = curl_exec($ch);

    $err = curl_error($ch);

    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);



    if ($resp === false) {

        return ['ok' => false, 'error' => 'Erreur cURL: ' . $err];

    }

    $decoded = json_decode($resp, true);

    if ($code >= 400) {

        $msg = is_array($decoded) ? ($decoded['error']['message'] ?? ('HTTP ' . $code)) : ('HTTP ' . $code);

        return ['ok' => false, 'error' => $msg];

    }

    return ['ok' => true];

}



function pp3_get_translation_file_path(string $lang): string {

    $langDir = __DIR__ . DIRECTORY_SEPARATOR . strtolower($lang);

    if (!is_dir($langDir)) {

        @mkdir($langDir, 0755, true);

    }

    return $langDir . DIRECTORY_SEPARATOR . 'translations.json';

}



function pp3_load_translations(string $lang): array {

    $file = pp3_get_translation_file_path($lang);

    if (!file_exists($file)) {

        return [];

    }

    $content = @file_get_contents($file);

    if ($content === false) {

        return [];

    }

    $data = json_decode($content, true);

    return is_array($data) ? $data : [];

}



function pp3_save_translations(string $lang, array $translations): bool {

    $file = pp3_get_translation_file_path($lang);

    $json = json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if ($json === false) {

        return false;

    }

    return @file_put_contents($file, $json, LOCK_EX) !== false;

}



function pp3_get_available_languages(): array {

    return [

        'en' => 'English',

        'es' => 'Español',

        'de' => 'Deutsch',

        'it' => 'Italiano',

        'pt' => 'Português',

        'ru' => 'Русский',

        'ja' => '日本語',

        'zh' => '中文',

        'ar' => 'العربية',

        'hi' => 'हिन्दी',

        'nl' => 'Nederlands',

        'pl' => 'Polski',

        'tr' => 'Türkçe',

        'ko' => '한국어',

        'sv' => 'Svenska',

        'da' => 'Dansk',

        'fi' => 'Suomi',

        'no' => 'Norsk',

        'cs' => 'Čeština',

        'ro' => 'Română',

        'el' => 'Ελληνικά',

        'hu' => 'Magyar',

        'th' => 'ไทย',

        'id' => 'Indonesia',

        'vi' => 'Tiếng Việt',

        'uk' => 'Українська',

        'he' => 'עברית',

        'fa' => 'فارسی',

        'bg' => 'Български',

        'hr' => 'Hrvatski',

        'sk' => 'Slovenčina',

        'sl' => 'Slovenščina',

        'lt' => 'Lietuvių',

        'lv' => 'Latviešu',

        'et' => 'Eesti',

        'ms' => 'Melayu',

        'bn' => 'বাংলা',

        'ta' => 'தமிழ்',

        'te' => 'తెలుగు',

        'mr' => 'मराठी',

    ];

}



function pp3_generate_lang_folder(string $lang, array $translations): void {

    $langDir = __DIR__ . DIRECTORY_SEPARATOR . strtolower($lang);

    if (!is_dir($langDir)) @mkdir($langDir, 0755, true);



    $sourceFile = __FILE__;

    $content = @file_get_contents($sourceFile);

    if ($content === false) return;



    // Trier les traductions par longueur décroissante

    $sortedKeys = array_keys($translations);

    usort($sortedKeys, function($a, $b) {

        return strlen($b) - strlen($a);

    });



    $replacements = 0;



    // Approche sécurisée : ne remplacer QUE dans des contextes de texte visible

    foreach ($sortedKeys as $key) {

        $value = $translations[$key];

        if (empty($value) || empty($key) || $key === $value) continue;



        // Échapper pour regex

        $keyEsc = preg_quote($key, '/');

        $valueEsc = str_replace('$', '\$', $value);



        // 1. Meta description

        $content = preg_replace(

            '/(name="description"\s+content=")(' . $keyEsc . ')(")/',

            '$1' . $valueEsc . '$3',

            $content

        );



        // 2. Meta keywords

        $content = preg_replace(

            '/(name="keywords"\s+content=")(' . $keyEsc . ')(")/',

            '$1' . $valueEsc . '$3',

            $content

        );



        // 3. Contenu entre balises HTML (>texte<)

        $before = $content;

        $content = preg_replace(

            '/(>)(\s*)(' . $keyEsc . ')(\s*)(<)/u',

            '$1$2' . $valueEsc . '$4$5',

            $content

        );



        // 2. Attributs placeholder="texte"

        $content = preg_replace(

            '/(placeholder\s*=\s*")(' . $keyEsc . ')(")/iu',

            '$1' . $valueEsc . '$3',

            $content

        );



        // 3. Attributs title="texte"

        $content = preg_replace(

            '/(title\s*=\s*")(' . $keyEsc . ')(")/iu',

            '$1' . $valueEsc . '$3',

            $content

        );



        // 4. Attributs aria-label="texte"

        $content = preg_replace(

            '/(aria-label\s*=\s*")(' . $keyEsc . ')(")/iu',

            '$1' . $valueEsc . '$3',

            $content

        );



        // 5. alert("texte")

        $content = preg_replace(

            '/(alert\s*\(\s*["\'])(' . $keyEsc . ')(["\'])/iu',

            '$1' . $valueEsc . '$3',

            $content

        );



        // 6. confirm("texte")

        $content = preg_replace(

            '/(confirm\s*\(\s*["\'])(' . $keyEsc . ')(["\'])/iu',

            '$1' . $valueEsc . '$3',

            $content

        );



        // 7. textContent = "texte" ou .textContent="texte"

        $content = preg_replace(

            '/(textContent\s*=\s*["\'])(' . $keyEsc . ')(["\'])/iu',

            '$1' . $valueEsc . '$3',

            $content

        );



        // 8. innerHTML = "texte"

        $content = preg_replace(

            '/(innerHTML\s*=\s*["\'])(' . $keyEsc . ')(["\'])/iu',

            '$1' . $valueEsc . '$3',

            $content

        );



        if ($content !== $before) {

            $replacements++;

        }

    }



    $indexFile = $langDir . DIRECTORY_SEPARATOR . 'index.php';

    @file_put_contents($indexFile, $content);



    error_log("Traduction $lang: $replacements textes traduits");

}



// Endpoint JSON (ne touche qu'à ce fichier)

if (isset($_POST['pp3_action']) || isset($_GET['pp3_action'])) {

    // Permet de réutiliser la même config DB/prefix dans un autre navigateur.

    pp3_load_persisted_config_into_session();



    $action = pp3_sanitize_string((string)($_POST['pp3_action'] ?? $_GET['pp3_action'] ?? ''), 50);

    

    // === RATE LIMITING SIMPLE ===

    $rateLimitKey = 'pp3_rate_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    $rateLimitWindow = 60; // 1 minute

    $rateLimitMax = 100; // Max requêtes par minute

    

    if (!isset($_SESSION[$rateLimitKey])) {

        $_SESSION[$rateLimitKey] = ['count' => 0, 'start' => time()];

    }

    if (time() - $_SESSION[$rateLimitKey]['start'] > $rateLimitWindow) {

        $_SESSION[$rateLimitKey] = ['count' => 0, 'start' => time()];

    }

    $_SESSION[$rateLimitKey]['count']++;

    

    if ($_SESSION[$rateLimitKey]['count'] > $rateLimitMax) {

        header('HTTP/1.1 429 Too Many Requests');

        pp3_json(['ok' => false, 'error' => 'Trop de requêtes. Réessayez dans une minute.']);

    }

    

    // === VÉRIFICATION CSRF POUR LES ACTIONS SENSIBLES ===

    $csrfRequiredActions = [

        'setup_db', 'register', 'login', 'logout', 'admin_save', 

        'traduction_save', 'traduction_delete', 'save_groq_key',

        'iagen_save_cfg', 'iagen_save_feedback', 'theme_save',

        'create_checkout', 'confirm_checkout'

    ];

    

    if (in_array($action, $csrfRequiredActions, true) && $_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!pp3_csrf_verify()) {

            pp3_json(['ok' => false, 'error' => 'Token de sécurité invalide. Rechargez la page.']);

        }

    }

    

    try {

        if ($action === 'status') {

            $prefix = pp3_get_prefix();

            $dbOk = pp3_get_db() ? true : false;

            $user = null;

            $adminSettings = null;

            if ($dbOk && $prefix) {

                $pdo = pp3_pdo();

                $adminSettings = pp3_admin_get($pdo, $prefix);

                if (pp3_is_logged()) {

                    $u = pp3_user();

                    $user = pp3_refresh_user($pdo, $prefix, (int)$u['id']);

                    pp3_set_user($user);

                }

            }

            pp3_json([

                'ok' => true,

                'configured' => ($dbOk && !!$prefix),

                'prefix' => $prefix,

                'logged' => $user ? true : false,

                'user' => $user,

                'admin' => $adminSettings,

                'csrf' => pp3_csrf_token(), // Token CSRF pour les formulaires

            ]);

        }



        if ($action === 'setup_db') {

            $host = trim((string)($_POST['host'] ?? ''));

            $dbname = trim((string)($_POST['dbname'] ?? ''));

            $user = trim((string)($_POST['user'] ?? ''));

            $pass = (string)($_POST['pass'] ?? '');

            $charset = trim((string)($_POST['charset'] ?? 'utf8mb4'));

            if ($host === '' || $dbname === '' || $user === '') {

                pp3_json(['ok' => false, 'error' => 'Champs DB manquants.']);

            }

            pp3_set_db([

                'host' => $host,

                'dbname' => $dbname,

                'user' => $user,

                'pass' => $pass,

                'charset' => $charset ?: 'utf8mb4',

            ]);

            $prefix = pp3_get_prefix();

            if (!$prefix) {

                $prefix = pp3_random_prefix(13);

                pp3_set_prefix($prefix);

            }

            $pdo = pp3_pdo();

            pp3_create_tables($pdo, $prefix);

            // Persiste pour les autres navigateurs (config globale serveur)

            pp3_save_persisted_config_from_session();

            pp3_json(['ok' => true, 'prefix' => $prefix]);

        }



        // Les actions suivantes nécessitent DB + prefix

        $prefix = pp3_get_prefix();

        $pdo = null;



        // === ACTIONS DE TRADUCTION ET IA (sans PDO requis) ===

        if (in_array($action, ['traduction_init', 'traduction_list', 'traduction_get', 'traduction_save', 'traduction_ia', 'save_groq_key', 'get_groq_key', 'traduction_list_existing', 'traduction_delete'], true)) {

            // Vérifier que l'utilisateur est admin (nécessite session mais pas PDO)

            if (!isset($_SESSION['pp3_user']) || empty($_SESSION['pp3_user']['is_admin'])) {

                pp3_json(['ok' => false, 'error' => 'Accès admin requis.']);

            }



            if ($action === 'traduction_init') {

                // Créer le dossier racine pour les traductions si nécessaire

                $langs = pp3_get_available_languages();

                $created = 0;

                foreach (array_keys($langs) as $lang) {

                    $dir = __DIR__ . DIRECTORY_SEPARATOR . strtolower($lang);

                    if (!is_dir($dir)) {

                        if (@mkdir($dir, 0755, true)) $created++;

                    }

                }

                pp3_json(['ok' => true, 'created' => $created]);

            }



            if ($action === 'traduction_list') {

                pp3_json(['ok' => true, 'languages' => pp3_get_available_languages()]);

            }



            if ($action === 'traduction_get') {

                $lang = trim((string)($_POST['lang'] ?? ''));

                if ($lang === '') pp3_json(['ok' => false, 'error' => 'Langue manquante.']);



                $translations = pp3_load_translations($lang);

                pp3_json(['ok' => true, 'translations' => $translations]);

            }



            if ($action === 'traduction_save') {

                $lang = trim((string)($_POST['lang'] ?? ''));

                $translationsRaw = (string)($_POST['translations'] ?? '');

                if ($lang === '') pp3_json(['ok' => false, 'error' => 'Langue manquante.']);



                $translations = json_decode($translationsRaw, true);

                if (!is_array($translations)) pp3_json(['ok' => false, 'error' => 'Format invalide.']);



                // Sauvegarder dans le fichier JSON

                if (!pp3_save_translations($lang, $translations)) {

                    pp3_json(['ok' => false, 'error' => 'Impossible de sauvegarder les traductions.']);

                }



                // Générer le dossier de langue avec index.php

                pp3_generate_lang_folder($lang, $translations);



                pp3_json(['ok' => true, 'file' => pp3_get_translation_file_path($lang)]);

            }



            if ($action === 'traduction_ia') {

                // Récupérer la clé Groq depuis un fichier de config local

                $configFile = __DIR__ . DIRECTORY_SEPARATOR . '.groq_config.json';

                $groqKey = '';

                if (file_exists($configFile)) {

                    $configData = json_decode(file_get_contents($configFile), true);

                    $groqKey = is_array($configData) ? ($configData['groq_api_key'] ?? '') : '';

                }



                if ($groqKey === '') {

                    pp3_json(['ok' => false, 'error' => 'Clé Groq manquante. Configurez-la dans l\'onglet IA.']);

                }



                $lang = trim((string)($_POST['lang'] ?? ''));

                $itemsRaw = (string)($_POST['items'] ?? '');

                if ($lang === '') pp3_json(['ok' => false, 'error' => 'Langue manquante.']);



                $items = json_decode($itemsRaw, true);

                if (!is_array($items)) pp3_json(['ok' => false, 'error' => 'Format invalide.']);



                $langs = pp3_get_available_languages();

                $targetLang = $langs[$lang] ?? $lang;



                try {

                    $translated = pp3_groq_translate_batch($groqKey, $items, $targetLang);

                    pp3_json(['ok' => true, 'translations' => $translated]);

                } catch (Exception $e) {

                    pp3_json(['ok' => false, 'error' => 'Erreur traduction IA: ' . $e->getMessage()]);

                }

            }



            if ($action === 'get_groq_key') {

                $configFile = __DIR__ . DIRECTORY_SEPARATOR . '.groq_config.json';

                $groqKey = '';

                if (file_exists($configFile)) {

                    $configData = json_decode(file_get_contents($configFile), true);

                    $groqKey = is_array($configData) ? ($configData['groq_api_key'] ?? '') : '';

                }

                pp3_json(['ok' => true, 'groq_key' => $groqKey]);

            }



            if ($action === 'save_groq_key') {

                $groqKey = trim((string)($_POST['groq_key'] ?? ''));

                $configFile = __DIR__ . DIRECTORY_SEPARATOR . '.groq_config.json';

                $config = ['groq_api_key' => $groqKey];

                @file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

                pp3_json(['ok' => true]);

            }



            if ($action === 'traduction_list_existing') {

                // Lister les dossiers de langue qui existent

                $langs = pp3_get_available_languages();

                $existing = [];

                foreach (array_keys($langs) as $code) {

                    $dir = __DIR__ . DIRECTORY_SEPARATOR . strtolower($code);

                    $jsonFile = $dir . DIRECTORY_SEPARATOR . 'translations.json';

                    $indexFile = $dir . DIRECTORY_SEPARATOR . 'index.php';

                    if (file_exists($jsonFile)) {

                        $translations = pp3_load_translations($code);

                        $existing[] = [

                            'code' => $code,

                            'name' => $langs[$code],

                            'count' => count($translations),

                            'has_index' => file_exists($indexFile),

                            'date' => date('Y-m-d H:i:s', filemtime($jsonFile))

                        ];

                    }

                }

                pp3_json(['ok' => true, 'existing' => $existing]);

            }



            if ($action === 'traduction_delete') {

                $lang = trim((string)($_POST['lang'] ?? ''));

                if ($lang === '') pp3_json(['ok' => false, 'error' => 'Langue manquante.']);



                $dir = __DIR__ . DIRECTORY_SEPARATOR . strtolower($lang);

                if (is_dir($dir)) {

                    // Supprimer les fichiers

                    $jsonFile = $dir . DIRECTORY_SEPARATOR . 'translations.json';

                    $indexFile = $dir . DIRECTORY_SEPARATOR . 'index.php';

                    @unlink($jsonFile);

                    @unlink($indexFile);

                    @rmdir($dir);

                }

                pp3_json(['ok' => true]);

            }

        }



        // === IA Génératrice 3D (popup Ctrl+Shift+G): Groq + feedback (sans PDO requis) ===

        if (in_array($action, ['iagen_get_cfg', 'iagen_save_cfg', 'iagen_test_cfg', 'iagen_groq_plan', 'iagen_save_feedback', 'iagen_stats', 'iagen_glbiagen_import', 'iagen_glbiagen_list', 'iagen_glbiagen_download', 'theme_save'], true)) {

            $isAdmin = (isset($_SESSION['pp3_user']) && !empty($_SESSION['pp3_user']['is_admin']));



            if ($action === 'iagen_glbiagen_list') {

                // Public: liste des exemples disponibles (sans contenu sensible)

                $all = pp3_iagen_load_training_examples(500);

                $items = [];

                foreach ($all as $ex) {

                    if (!is_array($ex)) continue;

                    $items[] = [

                        'source' => (string)($ex['source'] ?? 'glb'),

                        'category' => (string)($ex['category'] ?? ''),

                        'target' => (string)($ex['target'] ?? ''),

                        'name' => (string)($ex['name'] ?? ''),

                        'glb_file' => (string)($ex['glb_file'] ?? ''),

                        'saved_at' => $ex['saved_at'] ?? null,

                        'features' => (is_array($ex['features'] ?? null) ? [

                            'bbox' => $ex['features']['bbox'] ?? null,

                            'bbox_ratio' => $ex['features']['bbox_ratio'] ?? null,

                            'mesh_count' => $ex['features']['mesh_count'] ?? null,

                            'triangle_count' => $ex['features']['triangle_count'] ?? null,

                            'material_count' => $ex['features']['material_count'] ?? null,

                            'texture_count' => $ex['features']['texture_count'] ?? null,

                            'dominant_colors' => $ex['features']['dominant_colors'] ?? null,

                        ] : null),

                    ];

                }

                pp3_json(['ok' => true, 'items' => $items]);

            }



            if ($action === 'iagen_glbiagen_download') {

                // Public: download d'un .glb importé (pour reproduction identique)

                $file = trim((string)($_POST['glb_file'] ?? ''));

                if ($file === '' || !preg_match('/^[a-zA-Z0-9_\-\.]+\.glb$/', $file)) {

                    pp3_json(['ok' => false, 'error' => 'Nom de fichier invalide']);

                }

                $path = pp3_iagen_ensure_glb_dir() . DIRECTORY_SEPARATOR . $file;

                if (!is_file($path)) {

                    pp3_json(['ok' => false, 'error' => 'GLB introuvable']);

                }

                header('Content-Type: model/gltf-binary');

                header('Content-Disposition: inline; filename="' . basename($file) . '"');

                header('Content-Length: ' . filesize($path));

                readfile($path);

                exit;

            }



            if ($action === 'iagen_glbiagen_import') {

                if (!$isAdmin) pp3_json(['ok' => false, 'error' => 'Accès admin requis.']);



                $category = trim((string)($_POST['category'] ?? ''));

                $target = trim((string)($_POST['target'] ?? ''));

                if ($category === '' || $target === '') {

                    pp3_json(['ok' => false, 'error' => 'Champs manquants (catégorie + objet cible).']);

                }



                if (!isset($_FILES['glb']) || !is_array($_FILES['glb'])) {

                    pp3_json(['ok' => false, 'error' => 'Fichier GLB manquant.']);

                }



                $f = $_FILES['glb'];

                if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {

                    pp3_json(['ok' => false, 'error' => 'Upload échoué (code ' . (int)$f['error'] . ').']);

                }



                $origName = (string)($f['name'] ?? 'model.glb');

                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                if ($ext !== 'glb') {

                    pp3_json(['ok' => false, 'error' => 'Format invalide: .glb uniquement.']);

                }



                $size = (int)($f['size'] ?? 0);

                if ($size <= 0 || $size > 60 * 1024 * 1024) {

                    pp3_json(['ok' => false, 'error' => 'Taille GLB invalide (max 60MB).']);

                }



                $featuresRaw = (string)($_POST['features_json'] ?? '');

                $features = json_decode($featuresRaw, true);

                if (!is_array($features)) {

                    pp3_json(['ok' => false, 'error' => 'features_json invalide (JSON attendu).']);

                }



                $dir = pp3_iagen_ensure_glb_dir();

                $stamp = gmdate('Ymd_His');

                $rand = pp3_random_prefix(8);

                $catSlug = pp3_iagen_safe_slug($category);

                $tgtSlug = pp3_iagen_safe_slug($target);

                $base = 'glb_' . $catSlug . '_' . $tgtSlug . '_' . $stamp . '_' . $rand;



                $glbPath = $dir . DIRECTORY_SEPARATOR . $base . '.glb';

                $jsonPath = $dir . DIRECTORY_SEPARATOR . $base . '.json';



                $tmp = (string)($f['tmp_name'] ?? '');

                if ($tmp === '' || !is_uploaded_file($tmp)) {

                    pp3_json(['ok' => false, 'error' => 'Upload invalide (tmp).']);

                }

                if (!@move_uploaded_file($tmp, $glbPath)) {

                    pp3_json(['ok' => false, 'error' => 'Impossible de sauvegarder le GLB.']);

                }



                $payload = [

                    'source' => 'glb',

                    'category' => $category,

                    'target' => $target,

                    'name' => (string)($features['name'] ?? ''),

                    'features' => $features,

                    'glb_file' => basename($glbPath),

                    'saved_at' => pp3_now_sql(),

                ];

                @file_put_contents($jsonPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);



                // Ajoute un "feedback" positif d'import dans iagenauto/ pour accélérer l'apprentissage.

                // (L'import est considéré comme un exemple de référence.)

                $fbDir = pp3_iagen_ensure_dir();

                $fbPath = $fbDir . DIRECTORY_SEPARATOR . 'feedback_import_' . $stamp . '_' . $rand . '.json';

                $fb = [

                    'liked' => true,

                    'selections' => [

                        'ai-sel-1' => $category,

                        'ai-sel-2' => $target,

                    ],

                    'object' => [

                        'name' => (string)($features['name'] ?? $origName),

                        'uuid' => $base,

                        'iagen' => [

                            'selections' => [

                                'ai-sel-1' => $category,

                                'ai-sel-2' => $target,

                            ],

                            'plan' => [

                                'name' => (string)($features['name'] ?? ''),

                                'category' => $category,

                                'prompt' => 'BASE IMPORT GLB (référence)',

                                'post_ops' => [

                                    'glb_file' => basename($glbPath),

                                ],

                            ],

                        ],

                        'glb_file' => basename($glbPath),

                        'training_json' => basename($jsonPath),

                    ],

                    'groq_model' => null,

                    'saved_at' => pp3_now_sql(),

                ];

                @file_put_contents($fbPath, json_encode($fb, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);



                pp3_json(['ok' => true, 'glb' => basename($glbPath), 'json' => basename($jsonPath)]);

            }



            if ($action === 'iagen_get_cfg') {

                $cfg = pp3_iagen_load_cfg();

                // Pour les non-admin, ne jamais renvoyer la clé.

                $resp = [

                    'ok' => true,

                    'has_key' => (trim((string)$cfg['groq_api_key']) !== ''),

                    'model' => (string)$cfg['groq_model'],

                    'saved_at' => $cfg['saved_at'],

                    'is_admin' => $isAdmin,

                ];

                if ($isAdmin) {

                    $resp['groq_key'] = (string)$cfg['groq_api_key'];

                }

                pp3_json($resp);

            }



            if ($action === 'iagen_test_cfg') {

                if (!$isAdmin) pp3_json(['ok' => false, 'error' => 'Accès admin requis.']);

                $key = trim((string)($_POST['groq_key'] ?? ''));

                if ($key === '' || !preg_match('/^gsk_/i', $key)) {

                    pp3_json(['ok' => false, 'error' => 'Clé Groq invalide (doit commencer par gsk_).']);

                }

                $res = pp3_groq_test_key($key);

                pp3_json($res);

            }



            if ($action === 'iagen_save_cfg') {

                if (!$isAdmin) pp3_json(['ok' => false, 'error' => 'Accès admin requis.']);

                $key = trim((string)($_POST['groq_key'] ?? ''));

                $model = trim((string)($_POST['groq_model'] ?? ''));



                if ($key === '' || !preg_match('/^gsk_/i', $key)) {

                    pp3_json(['ok' => false, 'error' => 'Clé Groq invalide (doit commencer par gsk_).']);

                }

                if ($model === '') {

                    $model = 'llama-3.3-70b-versatile';

                }



                // Test immédiat avant de sauver

                $test = pp3_groq_test_key($key);

                if (empty($test['ok'])) {

                    pp3_json(['ok' => false, 'error' => (string)($test['error'] ?? 'Test clé impossible')]);

                }



                pp3_iagen_save_cfg($key, $model);

                pp3_json(['ok' => true, 'model' => $model]);

            }



            // === THEME MANAGER (AJOUT) ===

            if ($action === 'theme_save') {

                if (!$isAdmin) pp3_json(['ok' => false, 'error' => 'Accès admin requis.']);

                $json = (string)($_POST['theme_json'] ?? '');

                 // Validation basique JSON

                if (!$json || !json_decode($json)) {

                    pp3_json(['ok' => false, 'error' => 'Format JSON invalide.']);

                }

                pp3_save_theme_config($json);

                pp3_json(['ok' => true]);

            }



            if ($action === 'iagen_stats') {

                pp3_json(['ok' => true, 'stats' => pp3_iagen_feedback_stats()]);

            }



            if ($action === 'iagen_save_feedback') {

                $likedRaw = (string)($_POST['liked'] ?? '');

                $liked = ($likedRaw === '1' || strtolower($likedRaw) === 'true');

                $selectionsRaw = (string)($_POST['selections_json'] ?? '');

                $objectRaw = (string)($_POST['object_json'] ?? '');

                $selections = json_decode($selectionsRaw, true);

                $object = json_decode($objectRaw, true);

                if (!is_array($selections)) $selections = [];

                if (!is_array($object)) $object = [];



                $cfg = pp3_iagen_load_cfg();

                $model = (string)($cfg['groq_model'] ?? '');



                $dir = pp3_iagen_ensure_dir();

                $stamp = gmdate('Ymd_His');

                $rand = pp3_random_prefix(10);

                $path = $dir . DIRECTORY_SEPARATOR . 'feedback_' . $stamp . '_' . $rand . '.json';



                $payload = [

                    'liked' => $liked,

                    'selections' => $selections,

                    'object' => $object,

                    'groq_model' => $model,

                    'saved_at' => pp3_now_sql(),

                ];

                @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

                pp3_json(['ok' => true]);

            }



            if ($action === 'iagen_groq_plan') {

                // Pas admin requis: utilise la config serveur déjà enregistrée.

                $cfg = pp3_iagen_load_cfg();

                $apiKey = trim((string)($cfg['groq_api_key'] ?? ''));

                $model = trim((string)($cfg['groq_model'] ?? 'llama-3.3-70b-versatile'));

                if ($apiKey === '') {

                    pp3_json(['ok' => false, 'error' => 'Groq non configuré (clé manquante).']);

                }



                $count = (int)($_POST['count'] ?? 1);

                if ($count < 1) $count = 1;

                // 200 propositions => batch côté client ; serveur accepte un lot raisonnable.

                if ($count > 20) $count = 20;



                $mode = trim((string)($_POST['mode'] ?? 'plan'));

                if (!in_array($mode, ['plan', 'catalog'], true)) $mode = 'plan';



                $selectionsRaw = (string)($_POST['selections_json'] ?? '');

                $selections = json_decode($selectionsRaw, true);

                if (!is_array($selections)) $selections = [];



                $animate = ((string)($_POST['animate'] ?? '') === '1');

                $textures = ((string)($_POST['textures'] ?? '') === '1');



                $cdnRaw = (string)($_POST['cdn_json'] ?? '');

                $cdn = json_decode($cdnRaw, true);

                if (!is_array($cdn)) $cdn = [];



                $stats = pp3_iagen_feedback_stats();

                $examplesAll = pp3_iagen_load_training_examples(200);

                $examples = pp3_iagen_pick_relevant_examples($examplesAll, $selections, 8);



                $system = "Tu es un planificateur d'objets 3D pour un éditeur Three.js.\n"

                    . "Tu DOIS répondre UNIQUEMENT en JSON strict (pas de texte autour).\n"

                    . "But: produire des objets cohérents et exploitables (végétal, minéral, mobilier, outils, architecture, environnement, props, vivant/animaux, créatures, eau).\n"

                    . "INTERDIT: structures abstraites génériques répétitives (structures aléatoires), objets sans usage, charabia.\n"

                    . "Les 'selections' sont des CONTRAINTES FORTES: tu dois t'y conformer.\n"

                    . "Retour attendu: soit un objet soit un tableau si count>1. Schéma EXACT:\n"

                    . "{name:string, category:string, prompt:string, constraints:{complexity:1..5, styleHint:string, animate:boolean, proceduralTextures:boolean}, post_ops:{glb_file?:string, colorHex?:string, materialPreset?:string, rotateDeg?:[number,number,number], deform?:{type:string,strength:number}, textureHint?:string}, part_ops?:[{match:{part_id?:string,nameIncludes?:string,materialNameIncludes?:string,limit?:number}, ops:{colorHex?:string, materialPreset?:string, textureHint?:string, textureFile?:string}}], tags:string[]}\n"

                    . "- name: nom concret (ex: 'Chaise pliante', 'Cactus en pot', 'Lampe de bureau').\n"

                    . "- category: une catégorie simple (ex: 'vegetal','environment','furniture','tool','architecture','prop','mineral').\n"

                    . "- prompt: court, concret, décrit forme + parties + matière (ex: 'chaise pliante métal, assise tissu, pieds fins, proportions réalistes').\n"

                    . "- post_ops: sert à appliquer des opérations d'éditeur (couleur/texture/rotation/déformation) automatiquement.\n"

                    . "Apprentissage: évite learning_stats.avoid.recent_disliked_* et favorise learning_stats.prefer.* ; évite aussi top_dislike, favorise top_like.\n"

                    . "Apprentissage++: PRIORITÉ à l'assimilation identique des imports GLB.\n"

                    . "- Si training_examples contient glb_file et que ça correspond à la demande, tu peux définir post_ops.glb_file pour indiquer l'exemple de base EXACT à réutiliser.\n"

                    . "- Ensuite seulement, tu proposes des variations via post_ops (couleur/preset/rotation/déformation/textureHint) et via prompt.\n"

                    . "- Pour modifier des PARTIES précises d'un GLB importé, utilise part_ops avec match (part_id/nameIncludes/materialNameIncludes) et ops (couleur/preset/texture).\n"

                    . "- Apprentissage parties: si learning_stats.by_part existe, évite top_dislike et favorise top_like dans tes prochains part_ops.\n"

                    . "Variété: chaque item doit être différent (name, silhouette, catégorie, matériau) ; ne répète pas un même thème.\n"

                    . "Si mode='catalog': fais une liste variée d'idées PROPOSABLES, sans ajouter de blabla.";



                $user = [

                    'mode' => $mode,

                    'count' => $count,

                    'selections' => $selections,

                    'toggles' => ['animate' => $animate, 'textures' => $textures],

                    'cdn' => $cdn,

                    'learning_stats' => $stats,

                    'training_examples' => $examples,

                ];



                $messages = [

                    ['role' => 'system', 'content' => $system],

                    ['role' => 'user', 'content' => json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],

                ];



                try {

                    $text = pp3_groq_chat($apiKey, $model, $messages, 0.25, 1600);

                    $parsed = pp3_parse_json_from_llm($text);

                    pp3_json(['ok' => true, 'plan' => $parsed, 'model' => $model]);

                } catch (Exception $e) {

                    pp3_json(['ok' => false, 'error' => 'Erreur Groq: ' . $e->getMessage()]);

                }

            }

        }



        // Actions qui nécessitent PDO

        if ($action !== 'logout') {

            if (!$prefix || !pp3_get_db()) {

                pp3_json(['ok' => false, 'error' => 'DB non configurée.']);

            }

            $pdo = pp3_pdo();

        }



        if ($action === 'logout') {

            pp3_clear_user();

            pp3_json(['ok' => true]);

        }



        if ($action === 'register') {

            $mail = strtolower(trim((string)($_POST['mail'] ?? '')));

            $pwd = (string)($_POST['pwd'] ?? '');

            if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {

                pp3_json(['ok' => false, 'error' => 'Email invalide.']);

            }

            if (strlen($pwd) < 6) {

                pp3_json(['ok' => false, 'error' => 'Mot de passe trop court (min 6).']);

            }

            $m = pp3_tbl_membre($prefix);

            $colId = pp3_col($prefix, 'propainttroidid');

            $colMail = pp3_col($prefix, 'propainttroidmail');

            $colPwd = pp3_col($prefix, 'propainttroidpd');

            $colCreated = pp3_col($prefix, 'datecreationtroid');

            $colLast = pp3_col($prefix, 'derniereconnexiontroid');

            $colIsAdmin = pp3_col($prefix, 'isadmintroid');



            $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$m}`")->fetchColumn();

            $isAdmin = ($count === 0) ? 1 : 0;



            $hash = password_hash($pwd, PASSWORD_DEFAULT);

            $now = pp3_now_sql();

            $stmt = $pdo->prepare("INSERT INTO `{$m}` (`{$colMail}`, `{$colPwd}`, `{$colCreated}`, `{$colLast}`, `{$colIsAdmin}`) VALUES (:m,:p,:c,:l,:a)");

            $stmt->execute([':m' => $mail, ':p' => $hash, ':c' => $now, ':l' => $now, ':a' => $isAdmin]);



            $id = (int)$pdo->lastInsertId();

            $u = pp3_refresh_user($pdo, $prefix, $id);

            pp3_set_user($u);

            pp3_json(['ok' => true, 'user' => $u]);

        }



        if ($action === 'login') {

            $mail = strtolower(trim((string)($_POST['mail'] ?? '')));

            $pwd = (string)($_POST['pwd'] ?? '');

            if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {

                pp3_json(['ok' => false, 'error' => 'Email invalide.']);

            }

            $m = pp3_tbl_membre($prefix);

            $colId = pp3_col($prefix, 'propainttroidid');

            $colMail = pp3_col($prefix, 'propainttroidmail');

            $colPwd = pp3_col($prefix, 'propainttroidpd');

            $colLast = pp3_col($prefix, 'derniereconnexiontroid');

            $stmt = $pdo->prepare("SELECT `{$colId}` AS id, `{$colPwd}` AS pwd FROM `{$m}` WHERE `{$colMail}`=:m LIMIT 1");

            $stmt->execute([':m' => $mail]);

            $row = $stmt->fetch();

            if (!$row || !isset($row['pwd']) || !password_verify($pwd, $row['pwd'])) {

                pp3_json(['ok' => false, 'error' => 'Identifiants invalides.']);

            }

            $id = (int)$row['id'];

            $now = pp3_now_sql();

            $pdo->prepare("UPDATE `{$m}` SET `{$colLast}`=:l WHERE `{$colId}`=:id")->execute([':l' => $now, ':id' => $id]);

            $u = pp3_refresh_user($pdo, $prefix, $id);

            pp3_set_user($u);

            pp3_json(['ok' => true, 'user' => $u]);

        }



        // Admin-only

        if (in_array($action, ['admin_get','admin_save','create_checkout','confirm_checkout'], true)) {

            if (!pp3_is_logged()) pp3_json(['ok' => false, 'error' => 'Non connecté.']);

            $u = pp3_user();

            $u = pp3_refresh_user($pdo, $prefix, (int)$u['id']);

            pp3_set_user($u);

        }



        if ($action === 'admin_get') {

            $u = pp3_user();

            if (empty($u['is_admin'])) pp3_json(['ok' => false, 'error' => 'Accès admin requis.']);

            $admin = pp3_admin_get($pdo, $prefix);

            pp3_json(['ok' => true, 'admin' => $admin]);

        }



        if ($action === 'admin_save') {

            $u = pp3_user();

            if (empty($u['is_admin'])) pp3_json(['ok' => false, 'error' => 'Accès admin requis.']);

            $admin = pp3_tbl_admin($prefix);

            $adminId = pp3_col($prefix, 'propainttroidadid');

            $adminOpt = pp3_col($prefix, 'propainttroidaintoption');

            $adminSk = pp3_col($prefix, 'propainttroidstripesklive');

            $adminPk = pp3_col($prefix, 'propainttroidstripepklive');



            $optRaw = (string)($_POST['opt_json'] ?? '');

            $opt = json_decode($optRaw, true);

            if (!is_array($opt)) $opt = [];



            $sk = trim((string)($_POST['sk'] ?? ''));

            $pk = trim((string)($_POST['pk'] ?? ''));

            $stmt = $pdo->prepare("UPDATE `{$admin}` SET `{$adminOpt}`=:o, `{$adminSk}`=:sk, `{$adminPk}`=:pk WHERE `{$adminId}`=(SELECT MIN(`{$adminId}`) FROM `{$admin}`)");

            $stmt->execute([

                ':o' => json_encode($opt, JSON_UNESCAPED_UNICODE),

                ':sk' => ($sk !== '' ? $sk : null),

                ':pk' => ($pk !== '' ? $pk : null),

            ]);

            $adminRow = pp3_admin_get($pdo, $prefix);

            pp3_json(['ok' => true, 'admin' => $adminRow]);

        }



        if ($action === 'create_checkout') {

            $u = pp3_user();

            if (!$u) pp3_json(['ok' => false, 'error' => 'Non connecté.']);



            $adminRow = pp3_admin_get($pdo, $prefix);

            $opt = $adminRow['opt'] ?? [];

            $premiumActive = !empty($opt['premiumActive']);

            if (!$premiumActive) pp3_json(['ok' => false, 'error' => 'Premium non actif.']);



            $sk = (string)($adminRow['stripe']['sk'] ?? '');

            if ($sk === '') pp3_json(['ok' => false, 'error' => 'Stripe SK manquant.']);



            $plan = (string)($_POST['plan'] ?? '');

            $enabled = $opt['enabledPlans'] ?? [];

            if (!is_array($enabled) || !in_array($plan, $enabled, true)) {

                pp3_json(['ok' => false, 'error' => 'Plan non disponible.']);

            }

            $plans = $opt['plans'] ?? [];

            $amount = (int)($plans[$plan] ?? 0);

            if ($amount <= 0) {

                pp3_json(['ok' => false, 'error' => 'Prix non configuré.' ]);

            }



            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

            $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');

            $baseUrl = $scheme . '://' . $host . $path;

            $successUrl = $baseUrl . '?pp3_success=1&plan=' . rawurlencode($plan) . '&session_id={CHECKOUT_SESSION_ID}';

            $cancelUrl = $baseUrl . '?pp3_cancel=1';



            $session = pp3_stripe_request('POST', '/v1/checkout/sessions', $sk, [

                'mode' => 'payment',

                'success_url' => $successUrl,

                'cancel_url' => $cancelUrl,

                'client_reference_id' => (string)$u['id'],

                'metadata[user_id]' => (string)$u['id'],

                'metadata[plan]' => $plan,

                'line_items[0][quantity]' => 1,

                'line_items[0][price_data][currency]' => 'eur',

                'line_items[0][price_data][unit_amount]' => $amount,

                'line_items[0][price_data][product_data][name]' => 'Propaint 3D Premium - ' . $plan,

            ]);

            pp3_json(['ok' => true, 'url' => $session['url'] ?? null]);

        }



        if ($action === 'confirm_checkout') {

            $u = pp3_user();

            if (!$u) pp3_json(['ok' => false, 'error' => 'Non connecté.']);

            $sessionId = trim((string)($_POST['session_id'] ?? ''));

            $plan = (string)($_POST['plan'] ?? '');

            if ($sessionId === '' || $plan === '') pp3_json(['ok' => false, 'error' => 'Paramètres manquants.']);



            $adminRow = pp3_admin_get($pdo, $prefix);

            $opt = $adminRow['opt'] ?? [];

            $sk = (string)($adminRow['stripe']['sk'] ?? '');

            if ($sk === '') pp3_json(['ok' => false, 'error' => 'Stripe SK manquant.']);



            $sess = pp3_stripe_request('GET', '/v1/checkout/sessions/' . rawurlencode($sessionId), $sk);

            $paid = (($sess['payment_status'] ?? '') === 'paid');

            if (!$paid) pp3_json(['ok' => false, 'error' => 'Paiement non confirmé.']);



            $seconds = pp3_plan_to_seconds($plan);

            if ($seconds <= 0) pp3_json(['ok' => false, 'error' => 'Plan invalide.']);



            $m = pp3_tbl_membre($prefix);

            $colId = pp3_col($prefix, 'propainttroidid');

            $colPrem = pp3_col($prefix, 'premtroid');



            $until = gmdate('Y-m-d H:i:s', time() + $seconds);

            $stmt = $pdo->prepare("UPDATE `{$m}` SET `{$colPrem}`=:u WHERE `{$colId}`=:id");

            $stmt->execute([':u' => $until, ':id' => (int)$u['id']]);



            $u2 = pp3_refresh_user($pdo, $prefix, (int)$u['id']);

            pp3_set_user($u2);

            pp3_json(['ok' => true, 'user' => $u2]);

        }



        pp3_json(['ok' => false, 'error' => 'Action inconnue.']);

    } catch (Throwable $e) {

        pp3_json(['ok' => false, 'error' => $e->getMessage()]);

    }

}



// Upload texture (AJAX)

if (isset($_POST['upload_texture'])) {

    header('Content-Type: application/json; charset=utf-8');

    $ok = false;

    $error = null;



    if (!is_dir($textureDir)) {

        $error = 'Dossier textureplan introuvable.';

    } elseif (!isset($_FILES['texture']) || !is_array($_FILES['texture'])) {

        $error = 'Fichier manquant.';

    } else {

        $f = $_FILES['texture'];

        if (!isset($f['error']) || $f['error'] !== UPLOAD_ERR_OK) {

            $error = 'Erreur upload.';

        } else {

            $name = $f['name'] ?? 'texture';

            $name = basename($name);

            $name = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $name);

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) {

                $error = 'Format non supporté.';

            } else {

                $dest = $textureDir . DIRECTORY_SEPARATOR . $name;

                $tmp = $f['tmp_name'] ?? '';

                if (!is_string($tmp) || $tmp === '' || !is_uploaded_file($tmp)) {

                    $error = 'Fichier temporaire invalide.';

                } else {

                    $ok = @move_uploaded_file($tmp, $dest);

                    if (!$ok) $error = 'Impossible d\'enregistrer le fichier.';

                }

            }

        }

    }



    echo json_encode([

        'ok' => $ok,

        'error' => $error,

        'textures' => list_textures($textureDir),

    ]);

    exit;

}



// Vérifier si une exportation GLB est demandée

if (isset($_POST['export_glb']) && isset($_POST['scene_data'])) {

    // IMPORTANT:

    // Un fichier .glb est un binaire (GLTF 2.0) et ne peut PAS être produit ici

    // sans une vraie chaîne d'export côté serveur.

    // L\'export GLB correct est désormais fait côté navigateur via THREE.GLTFExporter.

    // On garde ce fallback uniquement pour ne pas casser un POST éventuel.

    header('Content-Type: application/json; charset=utf-8');

    header('Content-Disposition: attachment; filename="scene.json"');

    echo $_POST['scene_data'];

    exit;

}

?><!DOCTYPE html>

<html lang="fr">

<head>
<!-- VISUAL_EDITOR_CSS_START -->
<style id="visual-editor-generated-css">
/* Styles générés par l'éditeur visuel */
/* Desktop (par défaut) */
svg svg {
    color: #b62f2f
}
#openAdminPanelBtn {
    color: #11f20d
}
#openAccountPanelBtn {
    background-color: #3ec33c
}
#select-btn {
    color: #4c1010
}

/* Tablette (max-width: 1024px) */
@media (max-width: 1024px) {
  #select-btn {
    color: #b93c3c
  }
  body > div.container > div.content > div.sidebar > div#toolbar-panel > div.icon-grid > button#move-btn > svg svg {
    color: #e01f1f;
    
    
     background-color: #a72f2f
  }
  #move-btn {
    color: #e21d1d;
    
    
     background-color: #7dc238
  }
}

/* Mobile (max-width: 768px) */
@media (max-width: 768px) {
  #openAccountPanelBtn {
    color: #26b314;
    
    
    
     background-color: #209d2f
  }
}

</style>
<!-- VISUAL_EDITOR_CSS_END -->


    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="Éditeur 3D professionnel en ligne pour créer, modéliser et exporter vos projets 3D. Outils avancés de modélisation, textures et rendu en temps réel.">

    <meta name="keywords" content="éditeur 3D, modélisation 3D, création 3D en ligne, éditeur Three.js, outil 3D gratuit, design 3D, modèle 3D, rendu 3D, export 3D, WebGL">

    <title>Éditeur 3D</title>

    <!-- Dynamic Theme Injection -->

    <style id="dynamic-theme-style">

<?php echo pp3_load_theme_config(); ?>

    </style>

    <style>

    /* Styles générés par l'éditeur visuel */

/* Desktop (par défaut) */



/* Tablette (max-width: 1024px) */



/* Mobile (max-width: 768px) */

@media (max-width: 768px) {

  #openAccountPanelBtn {

    height: 98px

  }

  #openAdminPanelBtn {

    height: 98PX

  }

  .icon-grid {

    height: 100px

  }

}

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

        }



        html, body {

            height: 100%;

        }



        #pp3-loader {

            position: fixed;

            top: 0;

            left: 0;

            width: 100%;

            height: 100%;

            background: rgba(0, 0, 0, 0.5);

            backdrop-filter: blur(10px);

            -webkit-backdrop-filter: blur(10px);

            display: flex;

            align-items: center;

            justify-content: center;

            z-index: 99999;

            transition: opacity 0.5s ease;

        }

        #pp3-loader.hidden {

            opacity: 0;

            pointer-events: none;

        }

        #pp3-loader {

            background: var(--loader-bg, rgba(0, 0, 0, 0.5));

            opacity: var(--loader-opacity, 1);

        }

        /* Loader Type 1: Spinner (default) */

        .pp3-loader-1 {

            width: 60px;

            height: 60px;

            border: 4px solid var(--loader-color-secondary, rgba(255, 0, 0, 0.2));

            border-top-color: var(--loader-color, #ff0000);

            border-radius: 50%;

            animation: pp3-spin 0.8s linear infinite;

        }

        /* Loader Type 2: Pulse */

        .pp3-loader-2 {

            width: 60px;

            height: 60px;

            background: var(--loader-color, #ff0000);

            border-radius: 50%;

            animation: pp3-pulse 1s ease-in-out infinite;

        }

        /* Loader Type 3: Dots */

        .pp3-loader-3 {

            display: flex;

            gap: 8px;

        }

        .pp3-loader-3 span {

            width: 16px;

            height: 16px;

            background: var(--loader-color, #ff0000);

            border-radius: 50%;

            animation: pp3-bounce 0.6s ease-in-out infinite;

        }

        .pp3-loader-3 span:nth-child(2) { animation-delay: 0.1s; }

        .pp3-loader-3 span:nth-child(3) { animation-delay: 0.2s; }

        /* Loader Type 4: Bar */

        .pp3-loader-4 {

            width: 120px;

            height: 8px;

            background: var(--loader-color-secondary, rgba(255,255,255,0.2));

            border-radius: 4px;

            overflow: hidden;

        }

        .pp3-loader-4::after {

            content: '';

            display: block;

            width: 40%;

            height: 100%;

            background: var(--loader-color, #ff0000);

            border-radius: 4px;

            animation: pp3-bar 1s ease-in-out infinite;

        }

        /* Loader Type 5: Ring */

        .pp3-loader-5 {

            width: 60px;

            height: 60px;

            border: 4px solid transparent;

            border-top-color: var(--loader-color, #ff0000);

            border-bottom-color: var(--loader-color, #ff0000);

            border-radius: 50%;

            animation: pp3-spin 1s linear infinite;

        }

        /* Loader Type 6: Cube */

        .pp3-loader-6 {

            width: 40px;

            height: 40px;

            background: var(--loader-color, #ff0000);

            animation: pp3-cube 1.2s ease-in-out infinite;

        }

        /* Loader Type 7: Wave */

        .pp3-loader-7 {

            display: flex;

            gap: 4px;

            align-items: flex-end;

            height: 40px;

        }

        .pp3-loader-7 span {

            width: 8px;

            background: var(--loader-color, #ff0000);

            animation: pp3-wave 0.8s ease-in-out infinite;

        }

        .pp3-loader-7 span:nth-child(1) { animation-delay: 0s; }

        .pp3-loader-7 span:nth-child(2) { animation-delay: 0.1s; }

        .pp3-loader-7 span:nth-child(3) { animation-delay: 0.2s; }

        .pp3-loader-7 span:nth-child(4) { animation-delay: 0.3s; }

        .pp3-loader-7 span:nth-child(5) { animation-delay: 0.4s; }

        /* Loader Type 8: Circle */

        .pp3-loader-8 {

            width: 60px;

            height: 60px;

            border: 4px solid var(--loader-color-secondary, rgba(255,255,255,0.2));

            border-radius: 50%;

            position: relative;

        }

        .pp3-loader-8::after {

            content: '';

            position: absolute;

            top: -4px;

            left: -4px;

            width: 60px;

            height: 60px;

            border: 4px solid transparent;

            border-top-color: var(--loader-color, #ff0000);

            border-radius: 50%;

            animation: pp3-spin 0.8s linear infinite;

        }

        /* Loader Type 9: Flip */

        .pp3-loader-9 {

            width: 50px;

            height: 50px;

            background: var(--loader-color, #ff0000);

            animation: pp3-flip 1.2s ease-in-out infinite;

        }

        /* Loader Type 10: Orbit */

        .pp3-loader-10 {

            width: 60px;

            height: 60px;

            position: relative;

        }

        .pp3-loader-10 span {

            position: absolute;

            width: 16px;

            height: 16px;

            background: var(--loader-color, #ff0000);

            border-radius: 50%;

            animation: pp3-orbit 1.2s linear infinite;

        }

        .pp3-loader-10 span:nth-child(2) { animation-delay: -0.6s; }

        @keyframes pp3-spin {

            to { transform: rotate(360deg); }

        }

        @keyframes pp3-pulse {

            0%, 100% { transform: scale(1); opacity: 1; }

            50% { transform: scale(1.3); opacity: 0.5; }

        }

        @keyframes pp3-bounce {

            0%, 100% { transform: translateY(0); }

            50% { transform: translateY(-20px); }

        }

        @keyframes pp3-bar {

            0% { transform: translateX(-100%); }

            100% { transform: translateX(350%); }

        }

        @keyframes pp3-cube {

            0%, 100% { transform: rotate(0deg); }

            25% { transform: rotate(90deg) scale(1.1); }

            50% { transform: rotate(180deg); }

            75% { transform: rotate(270deg) scale(0.9); }

        }

        @keyframes pp3-wave {

            0%, 100% { height: 10px; }

            50% { height: 40px; }

        }

        @keyframes pp3-flip {

            0% { transform: perspective(120px) rotateX(0deg) rotateY(0deg); }

            50% { transform: perspective(120px) rotateX(180deg) rotateY(0deg); }

            100% { transform: perspective(120px) rotateX(180deg) rotateY(180deg); }

        }

        @keyframes pp3-orbit {

            0% { transform: rotate(0deg) translateX(22px) rotate(0deg); }

            100% { transform: rotate(360deg) translateX(22px) rotate(-360deg); }

        }



        body {

            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;

            background: #0b0b0b;

            height: 100vh;

            width: 100vw;

            overflow: hidden;

            padding: 0;

        }



        #cameraStatusBar {

            position: fixed;

            left: 0;

            right: 0;

            bottom: 0;

            height: 15px;

            background: rgba(128, 128, 128, 0.7);

            color: rgba(255, 255, 255, 0.92);

            font-size: 11px;

            line-height: 15px;

            padding: 0 8px;

            z-index: 9999;

            pointer-events: none;

            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

        }



        .container {

            display: flex;

            flex-direction: column;

            height: 100vh;

            width: 100vw;

            background: transparent;

            border-radius: 0;

            box-shadow: none;

            overflow: hidden;

        }



        .content {

            display: flex;

            flex: 1;

            overflow: hidden;

        }



        .sidebar {

            width: 300px;

            background: #000;

            border: 1px inset rgba(255, 255, 255, 0.75);

            border-radius: 6px;

            padding: 12px;

            overflow-y: auto;

            color: #fff;

            margin: 10px;

        }



        /* Scrollbars: fines + rouge + track noir (toute l'app) */

        * {

            scrollbar-width: thin;

            scrollbar-color: #ff0000 #000;

        }

        *::-webkit-scrollbar {

            width: 6px;

            height: 6px;

        }

        *::-webkit-scrollbar-track {

            background: #000;

        }

        *::-webkit-scrollbar-thumb {

            background: #ff0000;

            border-radius: 4px;

            border: 1px solid rgba(0, 0, 0, 0.6);

        }

        *::-webkit-scrollbar-thumb:hover {

            background: #ff2a2a;

        }



        .viewer-container {

            flex: 1;

            position: relative;

            background: var(--viewer-bg, #1a1a1a);

        }



        #viewer {

            width: 100%;

            height: 100%;

            display: block;

        }



        canvas {

            touch-action: none;

        }



        .panel {

            margin-bottom: 25px;

            background: rgba(0, 0, 0, 0.4);

            border-radius: 6px;

            padding: 15px;

            box-shadow: none;

            border: 1px solid rgba(255, 255, 255, 0.18);

        }



        .panel h3 {

            color: #fff;

            margin-bottom: 15px;

            padding-bottom: 10px;

            border-bottom: 1px solid rgba(255, 255, 255, 0.18);

            font-size: 16px;

        }



        .btn-group {

            display: flex;

            flex-wrap: wrap;

            gap: 8px;

            margin-bottom: 15px;

        }



        button {

            background: rgba(0, 0, 0, 0.6);

            color: #fff;

            border: 1px solid rgba(255, 255, 255, 0.22);

            padding: 10px 12px;

            border-radius: 6px;

            cursor: pointer;

            font-size: 14px;

            transition: all 0.3s ease;

            flex: 1;

            min-width: 120px;

        }



        button:hover {

            background: rgba(0, 0, 0, 0.75);

            transform: translateY(-1px);

        }



        button:disabled {

            opacity: 0.5;

            cursor: not-allowed;

            transform: none;

        }



        button.active {

            background: rgba(255, 255, 255, 0.12);

            box-shadow: none;

            border-color: rgba(255, 255, 255, 0.45);

        }



        .form-group {

            margin-bottom: 15px;

        }



        label {

            display: block;

            margin-bottom: 5px;

            color: rgba(255, 255, 255, 0.85);

            font-size: 14px;

        }



        input[type="color"],

        input[type="range"],

        input[type="number"],

        select {

            width: 100%;

            padding: 8px;

            border: 1px solid rgba(255, 255, 255, 0.22);

            border-radius: 6px;

            font-size: 14px;

            background: rgba(0, 0, 0, 0.6);

            color: #fff;

        }



        input[type="range"] {

            padding: 0;

        }



        /* Range: track blanc élargi + remplissage gris-bleuté */

        input[type="range"] {

            --range-pct: 0%;

            accent-color: #788CA4;

            -webkit-appearance: none;

            appearance: none;

            height: 20px;

            background: transparent;

        }

        input[type="range"]::-webkit-slider-runnable-track {

            height: 8px;

            border-radius: 4px;

            background:

                linear-gradient(#788CA4, #788CA4) 0/var(--range-pct) 100% no-repeat,

                rgba(255, 255, 255, 0.92);

        }

        input[type="range"]::-webkit-slider-thumb {

            -webkit-appearance: none;

            appearance: none;

            width: 14px;

            height: 14px;

            border-radius: 9999px;

            background: #788CA4;

            border: 2px solid rgba(255, 255, 255, 0.85);

            margin-top: -3px;

        }

        input[type="range"]::-moz-range-track {

            height: 8px;

            border-radius: 4px;

            background: rgba(255, 255, 255, 0.92);

        }

        input[type="range"]::-moz-range-progress {

            height: 8px;

            border-radius: 4px;

            background: #788CA4;

        }

        input[type="range"]::-moz-range-thumb {

            width: 14px;

            height: 14px;

            border-radius: 9999px;

            background: #788CA4;

            border: 2px solid rgba(255, 255, 255, 0.85);

        }



        /* Checkboxes: rondes + check vert (#66DF00) */

        input[type="checkbox"] {

            -webkit-appearance: none;

            appearance: none;

            width: 16px;

            height: 16px;

            border-radius: 9999px;

            border: 1px solid rgba(255, 255, 255, 0.35);

            background: rgba(255, 255, 255, 0.08);

            display: inline-grid;

            place-content: center;

            vertical-align: middle;

        }

        input[type="checkbox"]::before {

            content: '';

            width: 8px;

            height: 8px;

            border-radius: 9999px;

            transform: scale(0);

            transition: transform 120ms ease;

            background: #0b0b0b;

        }

        input[type="checkbox"]:checked {

            background: #66DF00;

            border-color: #66DF00;

        }

        input[type="checkbox"]:checked::before {

            transform: scale(1);

        }



        /* File inputs: on remplace le bouton natif par un label + icône */

        .file-input {

            position: absolute;

            width: 1px;

            height: 1px;

            padding: 0;

            margin: -1px;

            overflow: hidden;

            clip: rect(0, 0, 0, 0);

            white-space: nowrap;

            border: 0;

        }

        .file-picker {

            display: inline-flex;

            align-items: center;

            gap: 10px;

            width: 100%;

            padding: 10px 12px;

            border-radius: 6px;

            border: 1px solid rgba(255, 255, 255, 0.22);

            background: rgba(0, 0, 0, 0.6);

            color: #fff;

            cursor: pointer;

            user-select: none;

        }

        .file-picker:hover {

            background: rgba(0, 0, 0, 0.75);

        }

        .file-picker svg {

            width: 18px;

            height: 18px;

            stroke: currentColor;

            fill: none;

            stroke-width: 2;

            stroke-linecap: round;

            stroke-linejoin: round;

            flex: 0 0 auto;

        }

        .file-picker span {

            font-size: 14px;

            font-weight: 600;

        }



        .slider-container {

            display: flex;

            align-items: center;

            gap: 10px;

        }



        .slider-value {

            min-width: 40px;

            text-align: center;

            background: rgba(0, 0, 0, 0.4);

            color: #fff;

            padding: 5px;

            border-radius: 3px;

            border: 1px solid rgba(255, 255, 255, 0.18);

        }



        .object-list {

            list-style: none;

            max-height: 200px;

            overflow-y: auto;

        }



        .object-item {

            padding: 10px;

            margin-bottom: 5px;

            background: rgba(0, 0, 0, 0.35);

            border: 1px solid rgba(255, 255, 255, 0.14);

            border-radius: 5px;

            cursor: pointer;

            transition: all 0.3s ease;

            color: rgba(255, 255, 255, 0.9);

        }



        .object-item:hover {

            background: rgba(255, 255, 255, 0.08);

        }



        .object-item.selected {

            background: rgba(255, 255, 255, 0.14);

            color: #fff;

            border-color: rgba(255, 255, 255, 0.4);

        }



        /* Animations: sélection de points (rose clair) */

        .object-item.point-selected {

            background: rgba(255, 105, 180, 0.22);

            border-color: rgba(255, 105, 180, 0.55);

            color: rgba(255, 255, 255, 0.95);

        }



        .object-item.group-header {

            background: rgba(0, 0, 0, 0.55);

            border-color: rgba(255, 255, 255, 0.22);

            cursor: pointer;

            font-weight: 700;

        }



        .info {

            font-size: 12px;

            color: rgba(255, 255, 255, 0.75);

            margin-top: 20px;

            padding: 10px;

            background: rgba(0, 0, 0, 0.4);

            border-radius: 6px;

            border: 1px solid rgba(255, 255, 255, 0.18);

        }



        .export-btn {

            background: rgba(0, 0, 0, 0.6);

            margin-top: 20px;

        }



        .export-btn:hover {

            background: rgba(0, 0, 0, 0.75);

        }



        /* Toolbar (icônes) */

        .panel-toolbar {

            margin-bottom: 12px;

            padding: 10px;

        }

        .icon-grid {

            display: grid;

            grid-template-columns: repeat(auto-fit, minmax(44px, 1fr));

            gap: 8px;

        }

        .icon-divider {

            height: 1px;

            background: rgba(255, 255, 255, 0.18);

            margin: 10px 0;

        }

        .icon-btn {

            min-width: 0;

            padding: 10px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 0;

            aspect-ratio: 1 / 1;

        }

        .icon-btn svg {

            width: 18px;

            height: 18px;

            stroke: currentColor;

            fill: none;

            stroke-width: 2;

            stroke-linecap: round;

            stroke-linejoin: round;

        }

        .sr-only {

            position: absolute;

            width: 1px;

            height: 1px;

            padding: 0;

            margin: -1px;

            overflow: hidden;

            clip: rect(0, 0, 0, 0);

            white-space: nowrap;

            border: 0;

        }



        /* Popup panel */

        .sidebar-popup {

            position: sticky;

            top: 0;

            z-index: 50;

            margin-top: 10px;

            background: rgba(0, 0, 0, 0.4);

            border: 1px solid rgba(255, 255, 255, 0.22);

            border-radius: 6px;

            overflow: hidden;

        }

        .sidebar-popup.hidden {

            display: none;

        }

        .sidebar-popup-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;

            padding: 10px 10px;

            border-bottom: 1px solid rgba(255, 255, 255, 0.18);

        }

        .sidebar-popup-title {

            font-size: 13px;

            font-weight: 600;

            color: #fff;

        }

        .sidebar-popup-close {

            flex: 0 0 auto;

            min-width: 0;

            padding: 8px;

            width: 38px;

            height: 38px;

        }

        .sidebar-popup-body {

            padding: 10px;

        }

        .panel-store {

            display: none;

        }



        .popup-panel {

            margin: 0;

        }



        /* Compte / Admin / Premium */

        .pp3-hidden {

            display: none !important;

        }

        .pp3-msg {

            margin-top: 0;

        }

        .pp3-tabs {

            display: flex;

            gap: 8px;

            flex-wrap: wrap;

            margin-bottom: 10px;

        }

        .pp3-tab-btn {

            background: rgba(0, 0, 0, 0.4);

            border: 1px solid rgba(255, 255, 255, 0.18);

            padding: 8px 10px;

            border-radius: 6px;

            cursor: pointer;

            color: rgba(255, 255, 255, 0.9);

        }

        .pp3-tab-btn.active {

            background: rgba(255, 255, 255, 0.12);

            border-color: rgba(255, 255, 255, 0.35);

            color: #fff;

        }

        .icon-btn.pp3-admin {

            background: rgba(239, 68, 68, 0.22);

            border-color: rgba(239, 68, 68, 0.25);

        }



        /* Modales centrées */

        .pp3-modal-overlay {

            position: fixed;

            inset: 0;

            background: var(--popup-overlay-bg, rgba(0, 0, 0, 0.85));

            z-index: 9999;

            display: none;

            align-items: center;

            justify-content: center;

            padding: 20px;

        }

        .pp3-modal-overlay.active {

            display: flex;

        }

        .pp3-modal-content {

            position: relative;

            background: var(--popup-bg, #000);

            border: var(--popup-border-width, 1px) solid var(--popup-border-color, rgba(255, 255, 255, 0.35));

            border-radius: var(--popup-radius, 12px);

            padding: 25px;

            max-width: var(--popup-max-width, 95vw);

            width: var(--popup-width, 95vw);

            max-height: var(--popup-max-height, 95vh);

            height: auto;

            overflow-y: auto;

            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8);

        }

        /* Stop propagation of clicks inside modal content */

        .pp3-modal-content {

            pointer-events: auto;

        }

        .panel-close-btn {

            position: absolute;

            top: 10px;

            right: 10px;

            background: rgba(239, 68, 68, 0.55);

            color: #fff;

            border: none;

            width: 32px;

            height: 32px;

            border-radius: 50%;

            cursor: pointer;

            font-size: 22px;

            line-height: 1;

            padding: 0;

            display: flex;

            align-items: center;

            justify-content: center;

            transition: all 0.3s;

            min-width: 32px;

        }

        .panel-close-btn:hover {

            background: rgba(239, 68, 68, 0.8);

            transform: scale(1.1);

        }



        /* Système de traduction */

        .pp3-trad-item {

            background: rgba(0, 0, 0, 0.4);

            border: 1px solid rgba(255, 255, 255, 0.18);

            border-radius: 6px;

            padding: 10px;

            margin-bottom: 8px;

        }

        .pp3-trad-item label {

            font-size: 11px;

            color: rgba(255, 255, 255, 0.6);

            margin-bottom: 4px;

        }

        .pp3-trad-item input,

        .pp3-trad-item textarea {

            width: 100%;

            background: rgba(0, 0, 0, 0.6);

            border: 1px solid rgba(255, 255, 255, 0.22);

            border-radius: 4px;

            padding: 6px 8px;

            color: #fff;

            font-size: 13px;

            font-family: inherit;

        }

        .pp3-trad-item textarea {

            min-height: 60px;

            resize: vertical;

        }

        .pp3-trad-original {

            color: rgba(255, 255, 255, 0.75);

            font-size: 12px;

            margin-top: 4px;

            font-style: italic;

        }

        .icon-btn.pp3-admin:hover {

            background: rgba(239, 68, 68, 0.28);

        }

        .pp3-premium-crown {

            position: absolute;

            top: 4px;

            right: 4px;

            width: 14px;

            height: 14px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            font-size: 12px;

            line-height: 1;

            pointer-events: none;

        }

        .icon-btn {

            position: relative;

        }



        .controls-info {

            position: absolute;

            bottom: 20px;

            left: 20px;

            background: rgba(0,0,0,0.7);

            color: white;

            padding: 10px;

            border-radius: 5px;

            font-size: 12px;

        }



        #groupSelectRect {

            position: absolute;

            border: 2px dashed rgba(102, 126, 234, 0.95);

            background: rgba(102, 126, 234, 0.12);

            pointer-events: none;

            display: none;

            z-index: 50;

        }



        /* =============================================

           MOBILE REFONTE COMPLETE - max-width: 900px

           ============================================= */

        @media (max-width: 900px) {

            /* Container principal */

            .container {

                height: 100vh;

                width: 100vw;

                border-radius: 0;

                display: flex;

                flex-direction: column;

                overflow: hidden;

            }



            /* Zone content en colonne */

            .content {

                flex-direction: column;

                flex: 1;

                overflow: hidden;

            }



            /* ===== BARRE DE NAVIGATION MOBILE EN HAUT ===== */

            .sidebar {

                position: fixed !important;

                top: 0 !important;

                left: 0 !important;

                right: 0 !important;

                width: 100vw !important;

                max-width: 100vw !important;

                height: auto !important;

                min-height: 60px !important;

                max-height: 300px !important;

                border: none !important;

                border-bottom: 2px solid rgba(255, 255, 255, 0.15) !important;

                border-radius: 0 !important;

                padding: 10px 8px !important;

                margin: 0 !important;

                overflow-y: auto !important;

                overflow-x: hidden !important;

                -webkit-overflow-scrolling: touch;

                z-index: 5000 !important;

                background: var(--navbar-bg, #1a1a1a) !important;

                box-sizing: border-box !important;

            }



            /* Zone viewer décalée sous la barre */

            .viewer-container {

                position: fixed !important;

                top: 0 !important;

                left: 0 !important;

                right: 0 !important;

                bottom: 0 !important;

                width: 100vw !important;

                height: 100vh !important;

                margin: 0 !important;

                padding-top: 80px !important;

                z-index: 1 !important;

            }



            /* ===== TOOLBAR PANEL ===== */

            #toolbar-panel {

                padding: 5px 8px !important;

                margin: 0 !important;

                background: transparent !important;

                border: none !important;

            }



            /* ===== GRILLE D'ICONES RESPONSIVE ===== */

            .icon-grid {

                display: flex !important;

                flex-wrap: wrap !important;

                justify-content: flex-start !important;

                align-items: center !important;

                gap: 6px !important;

                width: 100% !important;

            }



            /* Diviseur d'icônes compact */

            .icon-divider {

                display: none !important;

            }



            /* ===== BOUTONS ICONES MOBILE ===== */

            .icon-btn {

                width: 44px !important;

                height: 44px !important;

                min-width: 44px !important;

                min-height: 44px !important;

                max-width: 44px !important;

                max-height: 44px !important;

                padding: 8px !important;

                margin: 0 !important;

                display: inline-flex !important;

                align-items: center !important;

                justify-content: center !important;

                flex-shrink: 0 !important;

                flex-grow: 0 !important;

                aspect-ratio: 1 / 1 !important;

                border-radius: 8px !important;

                background: rgba(255, 255, 255, 0.08) !important;

                border: 1px solid rgba(255, 255, 255, 0.2) !important;

                box-sizing: border-box !important;

                overflow: visible !important;

            }



            /* ===== SVG DANS LES ICONES - CORRECTION CRITIQUE ===== */

            .icon-btn svg {

                width: 24px !important;

                height: 24px !important;

                min-width: 24px !important;

                min-height: 24px !important;

                max-width: 24px !important;

                max-height: 24px !important;

                stroke: currentColor !important;

                fill: none !important;

                stroke-width: 2 !important;

                display: block !important;

                flex-shrink: 0 !important;

                overflow: visible !important;

            }



            /* Boutons génériques */

            button {

                min-width: 0;

            }



            .btn-group button {

                flex: 1 1 calc(50% - 8px);

            }



            /* Sidebar popup masqué sur mobile (on utilise fullscreen) */

            #sidebarPopupHost {

                display: none !important;

            }



            /* Panel store invisible */

            #panelStore {

                display: none !important;

            }



            /* Paint panel */

            #paint-panel {

                position: sticky;

                top: 0;

                z-index: 5;

            }

        }



        /* =============================================

           ECRANS TACTILES - pointer: coarse

           ============================================= */

        @media (pointer: coarse) {

            button {

                padding: 12px 14px;

                font-size: 16px;

            }

            label {

                font-size: 14px;

            }

            input[type="range"] {

                height: 44px;

            }

            input[type="number"],

            select {

                height: 44px;

                font-size: 16px;

            }

            .panel {

                padding: 12px;

            }



            /* Icônes tactiles optimisées */

            .icon-btn {

                min-width: 44px !important;

                min-height: 44px !important;

                touch-action: manipulation !important;

            }



            .icon-btn svg {

                width: 22px !important;

                height: 22px !important;

                pointer-events: none !important;

            }

        }



/* Variables CSS pour le Theme Editor */

:root {

    --app-bg: #0b0b0b;

    --app-text: #ffffff;

    --panel-bg: #1a1a1a;

    --panel-border: #333333;

    --viewer-bg: #1a1a1a;

    --grid-color: #444444;

    --glob-radius: 8px;

    --glob-border-width: 1px;

    --btn-bg: rgba(255,255,255,0.05);

    --btn-active: #4a9eff;

    --navbar-bg: #1a1a1a;

    --menu-position: left;

    --menu-width: 220px;

    --icon-size: 40px;

    --icon-gap: 8px;

    --viewer-size: 100%;

    --popup-bg: #000;

    --popup-border-color: rgba(255,255,255,0.35);

    --popup-border-width: 1px;

    --popup-radius: 12px;

    --popup-max-width: 95vw;

    --popup-max-height: 95vh;

    --popup-width: 95vw;

    --loader-type: 1;

    --loader-color: #ff0000;

    --loader-color-secondary: rgba(255,0,0,0.2);

    --loader-bg: rgba(0,0,0,0.5);

    --loader-opacity: 1;

}



/* Application des variables */

body {

    background: var(--app-bg) !important;

    color: var(--app-text) !important;

}

.panel, .pp3-full-modal-content, .sidebar-popup, #toolbar-panel {

    background: var(--panel-bg) !important;

    border-color: var(--panel-border) !important;

}

.icon-btn, button {

    border-radius: var(--glob-radius) !important;

    border-width: var(--glob-border-width) !important;

}

.sidebar {

    background: var(--navbar-bg) !important;

}

/* Desktop only: apply menu-width and icon-size from theme */

@media (min-width: 901px) {

    .sidebar {

        width: var(--menu-width) !important;

    }

    .icon-btn {

        width: var(--icon-size) !important;

        height: var(--icon-size) !important;

    }

}

.icon-grid {

    gap: var(--icon-gap) !important;

}

.viewer-container {

    flex: var(--viewer-size) !important;

}



/* =============================================

   MOBILE/TABLET: Popups Fullscreen - REFONTE

   ============================================= */

@media (max-width: 1024px) {

    /* ===== MODAL PLEIN ECRAN - DIRECTEMENT DANS BODY ===== */

    .pp3-full-modal,

    #pp3FullPageModal {

        position: fixed !important;

        top: 0 !important;

        left: 0 !important;

        right: 0 !important;

        bottom: 0 !important;

        width: 100vw !important;

        height: 100vh !important;

        z-index: 100000 !important;

        background: rgba(0, 0, 0, 0.98) !important;

        display: flex !important;

        align-items: flex-start !important;

        justify-content: center !important;

        padding: 0 !important;

        margin: 0 !important;

        overflow: hidden !important;

    }



    .pp3-full-modal.hidden,

    #pp3FullPageModal.hidden {

        display: none !important;

    }



    /* Contenu modal fullscreen */

    .pp3-full-modal-content,

    #pp3FullModalBody {

        position: relative !important;

        width: 100vw !important;

        height: 100vh !important;

        max-width: 100vw !important;

        max-height: 100vh !important;

        border-radius: 0 !important;

        margin: 0 !important;

        padding: 20px !important;

        padding-top: 60px !important;

        overflow-y: auto !important;

        overflow-x: hidden !important;

        background: var(--panel-bg, #1a1a1a) !important;

        -webkit-overflow-scrolling: touch;

        box-sizing: border-box !important;

    }



    /* Bouton fermer modal */

    .pp3-full-modal-close {

        position: fixed !important;

        top: 10px !important;

        right: 15px !important;

        width: 44px !important;

        height: 44px !important;

        font-size: 28px !important;

        z-index: 100001 !important;

        background: rgba(255, 0, 0, 0.8) !important;

        border: none !important;

        border-radius: 50% !important;

        color: white !important;

        display: flex !important;

        align-items: center !important;

        justify-content: center !important;

        cursor: pointer !important;

    }



    /* ===== PANELS ACCOUNT & ADMIN FULLSCREEN ===== */

    #account-panel,

    #admin-panel,

    #account-panel.popup-panel,

    #admin-panel.popup-panel,

    .popup-panel {

        position: relative !important;

        width: 100% !important;

        height: auto !important;

        min-height: 100% !important;

        max-width: 100% !important;

        max-height: none !important;

        overflow-y: visible !important;

        border-radius: 0 !important;

        border: none !important;

        margin: 0 !important;

        padding: 15px !important;

        background: transparent !important;

        box-sizing: border-box !important;

    }



    /* Titres des panels */

    #account-panel h3,

    #admin-panel h3,

    .popup-panel h3 {

        font-size: 20px !important;

        margin-bottom: 20px !important;

        padding-bottom: 15px !important;

        border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;

    }



    /* Bouton close dans les panels */

    .panel-close-btn {

        display: none !important;

    }



    /* Tabs dans admin panel */

    #admin-panel .pp3-tabs {

        display: flex !important;

        flex-wrap: wrap !important;

        gap: 5px !important;

        margin-bottom: 15px !important;

    }



    #admin-panel .pp3-tab-btn {

        flex: 1 1 auto !important;

        min-width: 80px !important;

        padding: 10px 8px !important;

        font-size: 12px !important;

    }



    /* Form groups responsive */

    .popup-panel .form-group,

    #account-panel .form-group,

    #admin-panel .form-group {

        margin-bottom: 15px !important;

    }



    .popup-panel input,

    .popup-panel select,

    #account-panel input,

    #account-panel select,

    #admin-panel input,

    #admin-panel select {

        width: 100% !important;

        padding: 12px !important;

        font-size: 16px !important;

        border-radius: 8px !important;

    }



    .popup-panel button,

    #account-panel button,

    #admin-panel button {

        width: 100% !important;

        padding: 14px !important;

        font-size: 16px !important;

        margin-top: 5px !important;

    }



    /* Messages info */

    .popup-panel .info,

    #account-panel .info,

    #admin-panel .info {

        padding: 12px !important;

        font-size: 14px !important;

        border-radius: 8px !important;

    }



    /* ===== OVERLAY PUB MOBILE ===== */

    [id^="pp3Ads"],

    #pp3AdsOverlay {

        -webkit-overflow-scrolling: touch !important;

    }

}



/* =============================================

   MENU POSITION - DESKTOP SEULEMENT

   ============================================= */

@media (min-width: 901px) {

    /* Position: Droite */

    body[data-menu-position="right"] .content {

        flex-direction: row-reverse !important;

    }

    body[data-menu-position="right"] .sidebar {

        border-left: 1px solid rgba(255, 255, 255, 0.12) !important;

        border-right: none !important;

    }



    /* Position: Haut */

    body[data-menu-position="top"] .content {

        flex-direction: column !important;

    }

    body[data-menu-position="top"] .sidebar {

        width: 100% !important;

        max-width: 100% !important;

        height: auto !important;

        max-height: 200px !important;

        border-bottom: 1px solid rgba(255, 255, 255, 0.12) !important;

        border-right: none !important;

        overflow-y: auto !important;

    }

    body[data-menu-position="top"] .icon-grid {

        display: flex !important;

        flex-wrap: wrap !important;

        justify-content: flex-start !important;

    }



    /* Position: Bas */

    body[data-menu-position="bottom"] .content {

        flex-direction: column-reverse !important;

    }

    body[data-menu-position="bottom"] .sidebar {

        width: 100% !important;

        max-width: 100% !important;

        height: auto !important;

        max-height: 200px !important;

        border-top: 1px solid rgba(255, 255, 255, 0.12) !important;

        border-right: none !important;

        overflow-y: auto !important;

    }

    body[data-menu-position="bottom"] .icon-grid {

        display: flex !important;

        flex-wrap: wrap !important;

        justify-content: flex-start !important;

    }

}



/* Mode: Texte Uniquement */

body.mode-text .icon-btn svg {

    display: none !important;

}

body.mode-text .icon-btn {

    width: auto !important;

    height: auto !important;

    aspect-ratio: auto !important;

    padding: 8px 12px !important;

    font-size: 14px !important;

}

body.mode-text .icon-btn .sr-only {

    position: static !important;

    width: auto !important;

    height: auto !important;

    clip: auto !important;

    color: var(--app-text) !important;

}



/* Mode: Icône + Texte */

body.mode-both .icon-btn {

    width: auto !important;

    height: auto !important;

    aspect-ratio: auto !important;

    padding: 8px 12px !important;

    display: inline-flex !important;

    gap: 8px;

}

body.mode-both .icon-btn .sr-only {

    position: static !important;

    width: auto !important;

    height: auto !important;

    clip: auto !important;

    color: var(--app-text) !important;

}



/* Administration Appearance UI */

.app-theme-editor {

    padding: 10px;

}

.app-theme-controls {

    margin-bottom: 15px;

    background: rgba(0,0,0,0.2);

    padding: 10px;

    border-radius: 8px;

}

.app-device-tabs {

    display: grid;

    grid-template-columns: 1fr 1fr 1fr;

    gap: 5px;

    margin-bottom: 10px;

}

.theme-grid {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 10px;

    padding: 10px 0;

}

.theme-input {

    width: 100%;

    height: 30px;

    border-radius: 4px;

    border: 1px solid #555;

    background: #222;

    color: white;

}

input[type="color"].theme-input {

    padding: 0 2px;

}



/* Full Page Modal */

.pp3-full-modal {

    position: fixed;

    top: 0;

    left: 0;

    width: 100%;

    height: 100%;

    background: rgba(0, 0, 0, 0.95);

    z-index: 10000;

    display: flex;

    flex-direction: column;

    justify-content: center;

    align-items: center;

    padding: 20px;

    opacity: 1;

    transition: opacity 0.3s ease;

}

.pp3-full-modal.hidden {

    display: none;

    opacity: 0;

    pointer-events: none;

}

.pp3-full-modal-content {

    background: #1a1a1a;

    border: 1px solid #333;

    border-radius: 12px;

    padding: 30px;

    width: 100%;

    max-width: 600px;

    max-height: 90vh;

    overflow-y: auto;

    position: relative;

    box-shadow: 0 10px 30px rgba(0,0,0,0.5);

}

.pp3-full-modal-close {

    position: absolute;

    top: 15px;

    right: 15px;

    background: none;

    border: none;

    color: #999;

    font-size: 24px;

    cursor: pointer;

}

.pp3-full-modal-close:hover {

    color: white;

}



/* Form Styles */

.form-group input,

.form-group select,

.form-group textarea {

    border-radius: 8px !important;

    border: 1px solid white !important;

    background: rgba(0, 0, 0, 0.4) !important;

    color: white !important;

}

.form-group input::placeholder,

.form-group textarea::placeholder {

    color: white !important;

}

/* Focus state */

.form-group input:focus,

.form-group select:focus,

.form-group textarea:focus {

    border-color: white !important;

    box-shadow: 0 0 0 2px rgba(255,255,255,0.1);

    outline: none;

}



/* Select Options */

select option,

.form-group select option {

    background-color: #000 !important;

    color: #fff !important;

}



/* Fix Icon Sizes: Account, Admin, and AI */

#openAccountPanelBtn,

#ai-launcher-btn {

    width: 44px !important;   /* Agrandis de ~5px par rapport au standard */

    height: 44px !important;

    min-width: 44px !important;

    padding: 0 !important;

    display: inline-flex !important;

    align-items: center;

    justify-content: center;

}



/* Admin button: similar size but NO forced display (controlled by JS) */

#openAdminPanelBtn {

    width: 44px;

    height: 44px;

    min-width: 44px;

    padding: 0;

    align-items: center;

    justify-content: center;

}



/* Ensure inner SVGs are properly sized within the button square */

#openAccountPanelBtn svg,

#openAdminPanelBtn svg,

#ai-launcher-btn svg {

     width: 24px !important;

     height: 24px !important;

     font-size: 24px !important;

}

    </style>

</head>

<body>

    <?php echo pp3_get_loader_html(); ?>

    <script>

        window.addEventListener('load', function() {

            setTimeout(function() {

                var loader = document.getElementById('pp3-loader');

                if (loader) {

                    loader.classList.add('hidden');

                    // NE PAS supprimer le loader pour pouvoir le tester/réafficher

                }

            }, 100);

        });

    </script>



    <!-- MODAL FULLPAGE POUR POPUPS (dans body directement, pas dans sidebar) -->

    <div id="pp3FullPageModal" class="pp3-full-modal hidden" role="dialog" aria-label="Modal Plein Écran">

        <div class="pp3-full-modal-content">

            <button class="pp3-full-modal-close" onclick="pp3_closeFullModal()">&times;</button>

            <div id="pp3FullModalBody"></div>

        </div>

    </div>



    <div class="container">

        <div class="content">

            <div class="sidebar">

                <div class="panel panel-toolbar" id="toolbar-panel">

                    <div class="icon-grid" aria-label="Compte">

                        <button id="openAccountPanelBtn" class="icon-btn" type="button" title="Compte" data-open-panel="account-panel" data-feature-id="panel_account">

                            <svg viewBox="0 0 24 24"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="8" r="4"/></svg>

                            <span class="sr-only">Compte</span>

                        </button>

                        <button id="openAdminPanelBtn" class="icon-btn pp3-admin" type="button" title="Admin" data-open-panel="admin-panel" data-feature-id="panel_admin" style="display:none;">

                            <svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 9.5-8 10-4.5-.5-8-5-8-10V6l8-4z"/><path d="M9 12l2 2 4-4"/></svg>

                            <span class="sr-only">Admin</span>

                        </button>

                    </div>

                    <div class="icon-divider"></div>



                    <div class="icon-grid" aria-label="Outils">

                        <button id="select-btn" class="icon-btn active" type="button" data-popup-title="Sélectionner" data-popup-mode="info" data-feature-id="tool_select">

                            <svg viewBox="0 0 24 24"><path d="M3 3l7 19 2-7 7-2L3 3z"/></svg>

                            <span class="sr-only">Sélectionner</span>

                        </button>

                        <button id="move-btn" class="icon-btn" type="button" data-popup-title="Déplacer" data-popup-mode="info" data-feature-id="tool_move">

                            <svg viewBox="0 0 24 24"><path d="M12 2v20M2 12h20"/><path d="M12 2l3 3M12 2l-3 3M12 22l3-3M12 22l-3-3M2 12l3 3M2 12l3-3M22 12l-3 3M22 12l-3-3"/></svg>

                            <span class="sr-only">Déplacer</span>

                        </button>

                        <button id="scale-btn" class="icon-btn" type="button" data-popup-title="Redimensionner" data-popup-mode="info" data-feature-id="tool_scale">

                            <svg viewBox="0 0 24 24"><path d="M3 7V3h4"/><path d="M21 17v4h-4"/><path d="M7 21H3v-4"/><path d="M17 3h4v4"/><path d="M7 3l14 14"/></svg>

                            <span class="sr-only">Redimensionner</span>

                        </button>

                        <button id="deform-btn" class="icon-btn" type="button" data-popup-title="Déformer" data-popup-mode="info" data-feature-id="tool_deform">

                            <svg viewBox="0 0 24 24"><path d="M4 7c3-4 6 4 8 0s5-4 8 0"/><path d="M4 17c3 4 6-4 8 0s5 4 8 0"/></svg>

                            <span class="sr-only">Déformer</span>

                        </button>

                        <button id="rotate-btn" class="icon-btn" type="button" data-popup-title="Rotation" data-popup-mode="info" data-feature-id="tool_rotate">

                            <svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>

                            <span class="sr-only">Rotation</span>

                        </button>

                        <button id="group-btn" class="icon-btn" type="button" data-popup-title="Select.Grouper" data-popup-mode="info" data-feature-id="tool_group">

                            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h7v7h-7z"/></svg>

                            <span class="sr-only">Select.Grouper</span>

                        </button>

                        <button id="brush-btn" class="icon-btn" type="button" data-popup-title="Brush (peindre)" data-popup-panel="paint-panel" data-feature-id="tool_brush">

                            <svg viewBox="0 0 24 24"><path d="M4 20c2 0 4-1 5-3 1-2 1-4 3-5l8-8 2 2-8 8c-1 2-3 2-5 3-2 1-3 3-3 5z"/></svg>

                            <span class="sr-only">Brush (peindre)</span>

                        </button>

                        <button id="sculpt-btn" class="icon-btn" type="button" data-popup-title="Sculpter" data-popup-panel="sculpt-panel" data-feature-id="tool_sculpt">

                            <svg viewBox="0 0 24 24"><path d="M4 15c4-6 6 6 10 0s6 0 6 0"/><path d="M5 19h14"/></svg>

                            <span class="sr-only">Sculpter</span>

                        </button>

                        <button id="rig-btn" class="icon-btn" type="button" data-popup-title="Mouvement (squelette)" data-popup-panel="rig-panel" data-feature-id="tool_rig" disabled>

                            <svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><path d="M12 7v5"/><circle cx="7" cy="14" r="2"/><circle cx="17" cy="14" r="2"/><path d="M12 12l-5 2M12 12l5 2"/><circle cx="10" cy="21" r="2"/><circle cx="14" cy="21" r="2"/><path d="M7 16l3 3M17 16l-3 3"/></svg>

                            <span class="sr-only">Mouvement (squelette)</span>

                        </button>

                    </div>



                    <div class="icon-divider"></div>



                    <div class="icon-grid" aria-label="Panneaux">

                        <button id="openAnimationsPanelBtn" class="icon-btn" type="button" title="Animations" data-open-panel="animations-panel" data-feature-id="panel_animations">

                            <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>

                            <span class="sr-only">Animations</span>

                        </button>

                        <button id="openObjectsPanelBtn" class="icon-btn" type="button" title="Objets scène" data-open-panel="objects-panel" data-feature-id="panel_objects">

                            <svg viewBox="0 0 24 24"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/><circle cx="7" cy="6" r="1.5"/><circle cx="7" cy="12" r="1.5"/><circle cx="7" cy="18" r="1.5"/></svg>

                            <span class="sr-only">Objets scène</span>

                        </button>

                        <button id="openExportPanelBtn" class="icon-btn" type="button" title="Export GLB" data-open-panel="export-panel" data-feature-id="panel_export">

                            <svg viewBox="0 0 24 24"><path d="M12 3v12"/><path d="M8 7l4-4 4 4"/><path d="M4 21h16"/></svg>

                            <span class="sr-only">Export GLB</span>

                        </button>

                        <button id="openLightPanelBtn" class="icon-btn" type="button" title="Lumières" data-open-panel="light-panel" data-feature-id="panel_lights">

                            <svg viewBox="0 0 24 24"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12c.7.6 1 1.4 1 2h6c0-.6.3-1.4 1-2A7 7 0 0 0 12 2z"/></svg>

                            <span class="sr-only">Lumières</span>

                        </button>

                        <button id="openPropertiesPanelBtn" class="icon-btn" type="button" title="Propriétés" data-open-panel="properties-panel" data-feature-id="panel_properties">

                            <svg viewBox="0 0 24 24"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/><path d="M19.4 15a7.9 7.9 0 0 0 .1-2l2-1.5-2-3.5-2.3.6a8 8 0 0 0-1.7-1l-.3-2.4H10l-.3 2.4a8 8 0 0 0-1.7 1L5.7 8 3.7 11.5l2 1.5a7.9 7.9 0 0 0 .1 2l-2 1.5 2 3.5 2.3-.6a8 8 0 0 0 1.7 1l.3 2.4h4.6l.3-2.4a8 8 0 0 0 1.7-1l2.3.6 2-3.5-2-1.5z"/></svg>

                            <span class="sr-only">Propriétés</span>

                        </button>

                        <button id="openImportPanelBtn" class="icon-btn" type="button" title="Importer" data-open-panel="import-panel" data-feature-id="panel_import">

                            <svg viewBox="0 0 24 24"><path d="M12 21V9"/><path d="M8 13l4-4 4 4"/><path d="M4 3h16v4H4z"/></svg>

                            <span class="sr-only">Importer</span>

                        </button>

                        <button id="openShapePanelBtn" class="icon-btn" type="button" title="Ajouter forme" data-open-panel="shape-panel" data-feature-id="panel_shape">

                            <svg viewBox="0 0 24 24"><rect x="4" y="4" width="8" height="8" rx="1"/><path d="M14 14h6v6h-6z"/><path d="M14 5h6"/><path d="M17 2v6"/></svg>

                            <span class="sr-only">Ajouter forme</span>

                        </button>

                    </div>



                </div>



                <div id="sidebarPopupHost" class="sidebar-popup hidden" role="dialog" aria-label="ParamÃ¨tres">

                    <div class="sidebar-popup-header">

                        <div id="sidebarPopupTitle" class="sidebar-popup-title">ParamÃ¨tres</div>

                        <button id="sidebarPopupCloseBtn" class="sidebar-popup-close icon-btn" type="button" aria-label="Fermer">

                            <svg viewBox="0 0 24 24"><path d="M6 6l12 12"/><path d="M18 6L6 18"/></svg>

                        </button>

                    </div>

                    <div id="sidebarPopupBody" class="sidebar-popup-body"></div>

                </div>



                <div id="panelStore" class="panel-store" aria-hidden="true">



                <div class="panel" id="account-panel" style="display:none;">

                    <h3>Compte</h3>

                    <button type="button" class="panel-close-btn" onclick="pp3_closeModal('account');">&times;</button>



                    <div id="pp3AccountMsg" class="info pp3-msg pp3-hidden"></div>



                    <div id="pp3DbSetupBox">

                        <div class="info" style="margin-top:0;">Première utilisation: configure la connexion à ta base MySQL pour créer les tables.</div>

                        <div class="form-group">

                            <label>Host</label>

                            <input type="text" id="pp3DbHost" value="localhost" />

                        </div>

                        <div class="form-group">

                            <label>Database</label>

                            <input type="text" id="pp3DbName" value="" />

                        </div>

                        <div class="form-group">

                            <label>User</label>

                            <input type="text" id="pp3DbUser" value="" />

                        </div>

                        <div class="form-group">

                            <label>Password</label>

                            <input type="password" id="pp3DbPass" value="" />

                        </div>

                        <div class="form-group">

                            <label>Charset</label>

                            <input type="text" id="pp3DbCharset" value="utf8mb4" />

                        </div>

                        <div class="btn-group">

                            <button id="pp3SetupDbBtn" type="button">Initialiser la base</button>

                        </div>

                        <div class="info">Après initialisation: la page se recharge automatiquement.</div>

                    </div>



                    <div id="pp3AuthBox" class="pp3-hidden">

                        <div id="pp3AuthToggle" class="pp3-tabs">

                            <button id="pp3ShowLoginBtn" type="button" class="pp3-tab-btn active">Connexion</button>

                            <button id="pp3ShowRegisterBtn" type="button" class="pp3-tab-btn">Inscription</button>

                        </div>



                        <div id="pp3LoginBox">

                            <div class="form-group">

                                <label>Email</label>

                                <input type="email" id="pp3LoginMail" value="" />

                            </div>

                            <div class="form-group">

                                <label>Mot de passe</label>

                                <input type="password" id="pp3LoginPwd" value="" />

                            </div>

                            <div class="btn-group">

                                <button id="pp3LoginBtn" type="button">Se connecter</button>

                            </div>

                        </div>



                        <div id="pp3RegisterBox" class="pp3-hidden">

                            <div class="form-group">

                                <label>Email</label>

                                <input type="email" id="pp3RegisterMail" value="" />

                            </div>

                            <div class="form-group">

                                <label>Mot de passe</label>

                                <input type="password" id="pp3RegisterPwd" value="" />

                            </div>

                            <div class="btn-group">

                                <button id="pp3RegisterBtn" type="button">Créer le compte</button>

                            </div>

                            <div class="info">Le premier compte créé devient admin.</div>

                        </div>



                        <div id="pp3LoggedBox" class="pp3-hidden">

                            <div class="info" style="margin-top:0;">

                                Connecté: <span id="pp3UserMail">-</span><br>

                                Premium: <span id="pp3UserPremium">-</span>

                            </div>

                            <div class="btn-group" style="margin-top:10px;">

                                <button id="pp3LogoutBtn" type="button" style="background: rgba(239,68,68,0.55);">Se déconnecter</button>

                            </div>

                        </div>



                        <div id="pp3SubscribeBox" class="pp3-hidden" style="margin-top:12px;">

                            <h3 style="margin-top:0;">Abonnement Premium</h3>

                            <div id="pp3PlansContainer" class="btn-group"></div>

                            <button id="pp3SubscribeCtaBtn" type="button" onclick="pp3SubscribeCtaClick();" style="margin-top: 10px;">Télécharger avec pub</button>

                            <div class="info">Après paiement, tu reviens ici et le premium est activé.</div>

                        </div>

                    </div>

                </div>



                <div class="panel" id="admin-panel" style="display:none;">

                    <h3>Admin</h3>

                    <div id="pp3AdminMsg" class="info pp3-msg pp3-hidden"></div>

                    <div class="pp3-tabs">

                        <button type="button" class="pp3-tab-btn active" data-pp3-tab="stripe">Stripe</button>

                        <button type="button" class="pp3-tab-btn" data-pp3-tab="premium">Premium</button>

                        <button type="button" class="pp3-tab-btn" data-pp3-tab="auth">Autorisation</button>

                        <button type="button" class="pp3-tab-btn" data-pp3-tab="traduction">Traduction</button>

                        <button type="button" class="pp3-tab-btn" data-pp3-tab="ia">IA</button>

                        <button type="button" class="pp3-tab-btn" data-pp3-tab="pub">Pub</button>

                        <button type="button" class="pp3-tab-btn" data-pp3-tab="appearance">Apparence</button>

                    </div>



                    <div id="pp3AdminTab_stripe" data-pp3-tabpanel="stripe">

                        <div class="form-group">

                            <label>Stripe Secret Key (live)</label>

                            <input type="password" id="pp3StripeSk" value="" />

                        </div>

                        <div class="form-group">

                            <label>Stripe Publishable Key (live)</label>

                            <input type="text" id="pp3StripePk" value="" />

                        </div>

                    </div>



                    <div id="pp3AdminTab_premium" class="pp3-hidden" data-pp3-tabpanel="premium">

                        <label style="display:flex; align-items:center; gap:8px; margin:0 0 10px 0;">

                            <input type="checkbox" id="pp3PremiumActive" />

                            <span>Activer Premium</span>

                        </label>

                        <label style="display:flex; align-items:center; gap:8px; margin:0 0 10px 0;">

                            <input type="checkbox" id="pp3ExportRequiresPremium" />

                            <span>Bloquer l’export si non premium</span>

                        </label>

                        <div class="info" style="margin-top:0;">Prix en euros (ex: 4.99). Les plans cochés sont disponibles.</div>

                        <div style="display:grid; grid-template-columns: 1fr 120px; gap:8px; align-items:center;">

                            <label style="display:flex; align-items:center; gap:8px; margin:0;"><input type="checkbox" class="pp3PlanEnabled" data-plan="unique" /> Unique</label>

                            <input type="number" class="pp3PlanPrice" data-plan="unique" step="0.01" min="0" value="0" />



                            <label style="display:flex; align-items:center; gap:8px; margin:0;"><input type="checkbox" class="pp3PlanEnabled" data-plan="day" /> Jour</label>

                            <input type="number" class="pp3PlanPrice" data-plan="day" step="0.01" min="0" value="0" />



                            <label style="display:flex; align-items:center; gap:8px; margin:0;"><input type="checkbox" class="pp3PlanEnabled" data-plan="week" /> Semaine</label>

                            <input type="number" class="pp3PlanPrice" data-plan="week" step="0.01" min="0" value="0" />



                            <label style="display:flex; align-items:center; gap:8px; margin:0;"><input type="checkbox" class="pp3PlanEnabled" data-plan="month" /> Mois</label>

                            <input type="number" class="pp3PlanPrice" data-plan="month" step="0.01" min="0" value="0" />



                            <label style="display:flex; align-items:center; gap:8px; margin:0;"><input type="checkbox" class="pp3PlanEnabled" data-plan="month3" /> 3 mois</label>

                            <input type="number" class="pp3PlanPrice" data-plan="month3" step="0.01" min="0" value="0" />



                            <label style="display:flex; align-items:center; gap:8px; margin:0;"><input type="checkbox" class="pp3PlanEnabled" data-plan="month6" /> 6 mois</label>

                            <input type="number" class="pp3PlanPrice" data-plan="month6" step="0.01" min="0" value="0" />



                            <label style="display:flex; align-items:center; gap:8px; margin:0;"><input type="checkbox" class="pp3PlanEnabled" data-plan="year" /> Année</label>

                            <input type="number" class="pp3PlanPrice" data-plan="year" step="0.01" min="0" value="0" />



                            <label style="display:flex; align-items:center; gap:8px; margin:0;"><input type="checkbox" class="pp3PlanEnabled" data-plan="lifetime" /> À vie</label>

                            <input type="number" class="pp3PlanPrice" data-plan="lifetime" step="0.01" min="0" value="0" />

                        </div>

                    </div>



                    <div id="pp3AdminTab_auth" class="pp3-hidden" data-pp3-tabpanel="auth">

                        <div class="info" style="margin-top:0;">Configurez l'accès à chaque fonctionnalité : pour tout le monde, premium uniquement, ou personne (désactivé).</div>



                        <h4 style="margin: 15px 0 10px 0; color: #4a9eff; font-size: 14px;">🤖 IA Génératrice 3D</h4>

                        <div style="display:grid; grid-template-columns: 1fr auto; gap:8px; align-items:center; margin-bottom: 15px; padding: 10px; background: rgba(74,158,255,0.1); border-radius: 8px;">

                            <span>IA Génératrice 3D</span>

                            <select class="pp3FeatureAccess" data-feature="ai_generator" style="padding: 6px; border-radius: 6px; background: #222; color: #fff; border: 1px solid #444;">

                                <option value="all">Pour tout le monde</option>

                                <option value="premium">Premium uniquement</option>

                                <option value="none">Désactivé</option>

                            </select>

                        </div>



                        <h4 style="margin: 15px 0 10px 0; color: #fff; font-size: 14px;">🛠️ Outils</h4>

                        <div style="display:grid; grid-template-columns: 1fr auto; gap:8px; align-items:center;">

                            <span>Brush (peindre)</span>

                            <select class="pp3FeatureAccess" data-feature="tool_brush" style="padding: 6px; border-radius: 6px; background: #222; color: #fff; border: 1px solid #444;">

                                <option value="all">Pour tout le monde</option>

                                <option value="premium">Premium uniquement</option>

                                <option value="none">Désactivé</option>

                            </select>



                            <span>Sculpter</span>

                            <select class="pp3FeatureAccess" data-feature="tool_sculpt" style="padding: 6px; border-radius: 6px; background: #222; color: #fff; border: 1px solid #444;">

                                <option value="all">Pour tout le monde</option>

                                <option value="premium">Premium uniquement</option>

                                <option value="none">Désactivé</option>

                            </select>



                            <span>Mouvement (squelette)</span>

                            <select class="pp3FeatureAccess" data-feature="tool_rig" style="padding: 6px; border-radius: 6px; background: #222; color: #fff; border: 1px solid #444;">

                                <option value="all">Pour tout le monde</option>

                                <option value="premium">Premium uniquement</option>

                                <option value="none">Désactivé</option>

                            </select>

                        </div>



                        <h4 style="margin: 15px 0 10px 0; color: #fff; font-size: 14px;">📦 Panneaux</h4>

                        <div style="display:grid; grid-template-columns: 1fr auto; gap:8px; align-items:center;">

                            <span>Ajouter forme</span>

                            <select class="pp3FeatureAccess" data-feature="panel_shape" style="padding: 6px; border-radius: 6px; background: #222; color: #fff; border: 1px solid #444;">

                                <option value="all">Pour tout le monde</option>

                                <option value="premium">Premium uniquement</option>

                                <option value="none">Désactivé</option>

                            </select>



                            <span>Animations</span>

                            <select class="pp3FeatureAccess" data-feature="panel_animations" style="padding: 6px; border-radius: 6px; background: #222; color: #fff; border: 1px solid #444;">

                                <option value="all">Pour tout le monde</option>

                                <option value="premium">Premium uniquement</option>

                                <option value="none">Désactivé</option>

                            </select>



                            <span>Lumières</span>

                            <select class="pp3FeatureAccess" data-feature="panel_lights" style="padding: 6px; border-radius: 6px; background: #222; color: #fff; border: 1px solid #444;">

                                <option value="all">Pour tout le monde</option>

                                <option value="premium">Premium uniquement</option>

                                <option value="none">Désactivé</option>

                            </select>



                            <span>Propriétés</span>

                            <select class="pp3FeatureAccess" data-feature="panel_properties" style="padding: 6px; border-radius: 6px; background: #222; color: #fff; border: 1px solid #444;">

                                <option value="all">Pour tout le monde</option>

                                <option value="premium">Premium uniquement</option>

                                <option value="none">Désactivé</option>

                            </select>



                            <span>Importer</span>

                            <select class="pp3FeatureAccess" data-feature="panel_import" style="padding: 6px; border-radius: 6px; background: #222; color: #fff; border: 1px solid #444;">

                                <option value="all">Pour tout le monde</option>

                                <option value="premium">Premium uniquement</option>

                                <option value="none">Désactivé</option>

                            </select>

                        </div>



                        <h4 style="margin: 15px 0 10px 0; color: #fff; font-size: 14px;">💾 Export</h4>

                        <div style="display:grid; grid-template-columns: 1fr auto; gap:8px; align-items:center;">

                            <span>Export GLB</span>

                            <select class="pp3FeatureAccess" data-feature="export_glb" style="padding: 6px; border-radius: 6px; background: #222; color: #fff; border: 1px solid #444;">

                                <option value="all">Pour tout le monde</option>

                                <option value="premium">Premium uniquement</option>

                                <option value="none">Désactivé</option>

                            </select>

                        </div>

                    </div>



                    <div id="pp3AdminTab_traduction" class="pp3-hidden" data-pp3-tabpanel="traduction">

                        <div class="form-group">

                            <label>Traductions existantes</label>

                            <div id="pp3ExistingTranslations" style="margin-bottom: 15px;"></div>

                            <button id="pp3RefreshExistingBtn" type="button" style="background: rgba(59,130,246,0.55); margin-bottom: 15px;">Actualiser la liste</button>

                        </div>

                        <div style="border-top: 1px solid rgba(255,255,255,0.18); padding-top: 15px; margin-top: 15px;">

                            <h4 style="margin-bottom: 10px; color: #fff;">Nouvelle traduction</h4>

                            <div class="form-group">

                                <label>Langue à traduire</label>

                                <select id="pp3TradLangSelect"></select>

                            </div>

                        <div class="btn-group">

                            <button id="pp3TradLoadBtn" type="button">Charger traductions</button>

                            <button id="pp3TradIaBtn" type="button" style="background: rgba(139,92,246,0.55);">Traduire avec IA</button>

                        </div>

                        <div class="form-group">

                            <label>Textes à traduire (<span id="pp3TradCount">0</span> éléments)</label>

                            <div id="pp3TradContainer" style="max-height: 400px; overflow-y: auto;"></div>

                        </div>

                        <div class="btn-group">

                            <button id="pp3TradSaveBtn" type="button" style="background: rgba(34,197,94,0.55);">Sauvegarder et générer dossier</button>

                        </div>

                        <div class="info">Les traductions sont sauvegardées en base et génèrent automatiquement un dossier /XX/index.php avec la version traduite.</div>

                        </div>

                    </div>



                    <div id="pp3AdminTab_ia" class="pp3-hidden" data-pp3-tabpanel="ia">

                        <div class="form-group">

                            <label>Clé API Groq (gsk_...)</label>

                            <input type="password" id="pp3GroqApiKey" placeholder="gsk_..." />

                        </div>

                        <div class="btn-group">

                            <button id="pp3SaveGroqKeyBtn" type="button">Enregistrer clé</button>

                        </div>

                        <div class="info">La clé Groq est utilisée pour la traduction automatique via IA. Elle est sauvegardée en temps réel.</div>

                    </div>



                    <div id="pp3AdminTab_pub" class="pp3-hidden" data-pp3-tabpanel="pub">

                        <div class="info" style="margin-top:0;">Monetag (pub): permet d'activer un téléchargement gratuit avec pub (cooldown 15s) quand l'export est bloqué aux non-premium.</div>

                        <label style="display:flex; align-items:center; gap:8px; margin:0 0 10px 0;">

                            <input type="checkbox" id="pp3AdsEnabled" />

                            <span>Activer la pub (Monetag)</span>

                        </label>



                        <div class="btn-group" style="margin-top: 8px;">

                            <button id="pp3AdsAddZoneBtn" type="button" style="background: rgba(59,130,246,0.55);">Ajouter zone monetag</button>

                        </div>



                        <div id="pp3AdsAddZoneBox" class="pp3-hidden" style="margin-top: 10px; padding: 10px; border: 1px solid rgba(255,255,255,0.18); border-radius: 10px;">

                            <div class="form-group">

                                <label>URL du script (src)</label>

                                <input type="text" id="pp3AdsZoneUrl" placeholder="https://.../script.js" />

                            </div>

                            <div class="form-group">

                                <label>data-zone</label>

                                <input type="text" id="pp3AdsZoneCode" placeholder="123456" />

                            </div>

                            <div class="btn-group">

                                <button id="pp3AdsZoneValidateBtn" type="button" style="background: rgba(34,197,94,0.55);">Valider</button>

                            </div>

                            <div class="info" style="margin-top: 8px;">Maximum 5 zones au total.</div>

                        </div>



                        <div class="form-group" style="margin-top: 12px;">

                            <label>Zones configurées</label>

                            <div id="pp3AdsZonesList" style="display:grid; gap:8px;"></div>

                        </div>

                    </div>



                    <div id="pp3AdminTab_appearance" class="pp3-hidden" data-pp3-tabpanel="appearance">

                        <div class="app-theme-editor">

                            <!-- Sélecteurs globaux -->

                            <div class="app-theme-controls">

                                <div class="form-group">

                                    <label>Appareil cible</label>

                                    <div class="pp3-tabs app-device-tabs">

                                        <button type="button" class="pp3-tab-btn active" data-device="desktop">🖥️ PC</button>

                                        <button type="button" class="pp3-tab-btn" data-device="tablet">📱 Tablette</button>

                                        <button type="button" class="pp3-tab-btn" data-device="mobile">📱 Mobile</button>

                                    </div>

                                </div>

                                <div class="form-group">

                                    <label>Style du Menu</label>

                                    <select id="appThemeMenuMode">

                                        <option value="icons">Icônes uniquement</option>

                                        <option value="text">Texte uniquement</option>

                                        <option value="both">Icônes + Texte</option>

                                    </select>

                                </div>

                            </div>



                            <!-- Accordéon des catégories -->

                            <div class="app-theme-accordion">

                                <!-- 1. Couleurs Globales -->

                                <details open>

                                    <summary>Couleurs & Fond</summary>

                                    <div class="theme-grid">

                                        <div class="form-group">

                                            <label>Couleur de fond (App)</label>

                                            <input type="color" class="theme-input" data-var="--app-bg" value="#0b0b0b">

                                        </div>

                                        <div class="form-group">

                                            <label>Couleur texte principal</label>

                                            <input type="color" class="theme-input" data-var="--app-text" value="#ffffff">

                                        </div>

                                        <div class="form-group">

                                            <label>Couleur panel/popup</label>

                                            <input type="color" class="theme-input" data-var="--panel-bg" value="#1a1a1a">

                                        </div>

                                        <div class="form-group">

                                            <label>Bordure panels</label>

                                            <input type="color" class="theme-input" data-var="--panel-border" value="#333333">

                                        </div>

                                        <div class="form-group">

                                            <label>Couleur navbar</label>

                                            <input type="color" class="theme-input" data-var="--navbar-bg" value="#1a1a1a">

                                        </div>

                                    </div>

                                </details>



                                <!-- 2. Visualisateur -->

                                <details>

                                    <summary>Visualisateur 3D</summary>

                                    <div class="theme-grid">

                                        <div class="form-group">

                                            <label>Couleur fond 3D</label>

                                            <input type="color" class="theme-input" data-var="--viewer-bg" value="#222222" data-3d="background">

                                        </div>

                                        <div class="form-group">

                                            <label>Couleur Grille</label>

                                            <input type="color" class="theme-input" data-var="--grid-color" value="#444444" data-3d="grid">

                                        </div>

                                        <div class="form-group">

                                            <label>Taille visualisateur (%)</label>

                                            <input type="range" class="theme-input" data-var="--viewer-size" min="50" max="100" value="100" data-unit="%">

                                        </div>

                                    </div>

                                </details>



                                <!-- 3. Menu & Layout -->

                                <details>

                                    <summary>Menu & Layout</summary>

                                    <div class="theme-grid">

                                        <div class="form-group">

                                            <label>Position du menu</label>

                                            <select class="theme-input" data-var="--menu-position" id="appThemeMenuPosition">

                                                <option value="left">Gauche</option>

                                                <option value="right">Droite</option>

                                                <option value="top">Haut</option>

                                                <option value="bottom">Bas</option>

                                            </select>

                                        </div>

                                        <div class="form-group">

                                            <label>Largeur menu (px)</label>

                                            <input type="range" class="theme-input" data-var="--menu-width" min="60" max="400" value="220" data-unit="px">

                                        </div>

                                        <div class="form-group">

                                            <label>Taille icônes (px)</label>

                                            <input type="range" class="theme-input" data-var="--icon-size" min="24" max="64" value="40" data-unit="px">

                                        </div>

                                        <div class="form-group">

                                            <label>Espacement icônes (px)</label>

                                            <input type="range" class="theme-input" data-var="--icon-gap" min="2" max="20" value="8" data-unit="px">

                                        </div>

                                    </div>

                                </details>



                                <!-- 4. Boutons & Inputs -->

                                <details>

                                    <summary>Boutons & Inputs</summary>

                                    <div class="theme-grid">

                                        <div class="form-group">

                                            <label>Radius (arrondi)</label>

                                            <input type="range" class="theme-input" data-var="--glob-radius" min="0" max="25" value="8" data-unit="px">

                                        </div>

                                        <div class="form-group">

                                            <label>Bordure taille</label>

                                            <input type="range" class="theme-input" data-var="--glob-border-width" min="0" max="5" value="1" data-unit="px">

                                        </div>

                                        <div class="form-group">

                                            <label>Couleur boutons</label>

                                            <input type="color" class="theme-input" data-var="--btn-bg" value="#333333">

                                        </div>

                                        <div class="form-group">

                                            <label>Couleur boutons (actif)</label>

                                            <input type="color" class="theme-input" data-var="--btn-active" value="#4a9eff">

                                        </div>

                                    </div>

                                </details>



                                <!-- 5. Popups -->

                                <details>

                                    <summary>Popups & Modales</summary>

                                    <div class="theme-grid">

                                        <div class="form-group">

                                            <label>Fond popup</label>

                                            <input type="color" class="theme-input" data-var="--popup-bg" value="#000000">

                                        </div>

                                        <div class="form-group">

                                            <label>Couleur bordure popup</label>

                                            <input type="color" class="theme-input" data-var="--popup-border-color" value="#555555">

                                        </div>

                                        <div class="form-group">

                                            <label>Épaisseur bordure (px)</label>

                                            <input type="range" class="theme-input" data-var="--popup-border-width" min="0" max="5" value="1" data-unit="px">

                                        </div>

                                        <div class="form-group">

                                            <label>Radius popup (px)</label>

                                            <input type="range" class="theme-input" data-var="--popup-radius" min="0" max="30" value="12" data-unit="px">

                                        </div>

                                        <div class="form-group">

                                            <label>Largeur max popup (%)</label>

                                            <input type="range" class="theme-input" data-var="--popup-max-width" min="50" max="100" value="95" data-unit="vw">

                                        </div>

                                        <div class="form-group">

                                            <label>Hauteur max popup (%)</label>

                                            <input type="range" class="theme-input" data-var="--popup-max-height" min="50" max="100" value="95" data-unit="vh">

                                        </div>

                                    </div>

                                </details>



                                <!-- 6. Loader -->

                                <details>

                                    <summary>Loader (chargement)</summary>

                                    <div class="theme-grid">

                                        <div class="form-group">

                                            <label>Type de loader</label>

                                            <select class="theme-input" data-var="--loader-type" id="appThemeLoaderType">

                                                <option value="0">Désactivé</option>

                                                <option value="1" selected>Spinner</option>

                                                <option value="2">Pulse</option>

                                                <option value="3">Points</option>

                                                <option value="4">Barre</option>

                                                <option value="5">Anneau</option>

                                                <option value="6">Cube</option>

                                                <option value="7">Vague</option>

                                                <option value="8">Cercle</option>

                                                <option value="9">Flip</option>

                                                <option value="10">Orbite</option>

                                            </select>

                                        </div>

                                        <div class="form-group">

                                            <label>Couleur loader</label>

                                            <input type="color" class="theme-input" data-var="--loader-color" value="#ff0000">

                                        </div>

                                        <div class="form-group">

                                            <label>Couleur secondaire</label>

                                            <input type="color" class="theme-input" data-var="--loader-color-secondary" value="#330000">

                                        </div>

                                        <div class="form-group">

                                            <label>Fond du loader</label>

                                            <input type="color" class="theme-input" data-var="--loader-bg" value="#000000">

                                        </div>

                                        <div class="form-group">

                                            <label>Opacité fond (%)</label>

                                            <input type="range" class="theme-input" data-var="--loader-opacity" min="0" max="100" value="50" data-unit="%" data-divide="100">

                                        </div>

                                    </div>

                                    <button type="button" id="pp3PreviewLoaderBtn" style="margin-top:10px; width:100%;">👁️ Prévisualiser le Loader (3s)</button>

                                </details>



                                <div class="info" style="margin-top:10px;">Les modifications s'appliquent en temps réel. Sauvegardez pour conserver.</div>

                            </div>

                        </div>

                    </div>



                    <div class="btn-group" style="margin-top:12px;">

                        <button id="pp3AdminSaveBtn" type="button">Enregistrer</button>

                    </div>

                </div>



                <div class="panel" id="animations-panel" style="display:none;">

                    <h3>Animations</h3>



                    <div class="btn-group" style="margin-bottom:10px;">

                        <button id="createSceneAnimBtn" type="button">Créer une animation (scène)</button>

                        <button id="createSelectionAnimBtn" type="button">Créer une animation sur l’élément sélectionné</button>

                        <button id="myAnimationsBtn" type="button">Mes animations</button>

                    </div>



                    <div id="animListView" style="display:block;">

                        <div class="form-group">

                            <label>Liste</label>

                            <ul id="myAnimationsList" class="object-list"></ul>

                        </div>

                        <div class="info" style="margin-top:8px;">

                            • Les clips importés (.glb) apparaissent ici<br>

                            • Les animations créées sont sauvegardées localement (navigateur)

                        </div>

                    </div>



                    <div id="animDetailView" style="display:none;">

                        <div class="form-group">

                            <label>Nom</label>

                            <input type="text" id="animNameInput" value="Animation" />

                        </div>



                        <div class="form-group">

                            <label>Portée</label>

                            <select id="animScopeSelect">

                                <option value="scene">Scène (multi-objets)</option>

                                <option value="selection">Sélection</option>

                            </select>

                        </div>



                        <div class="btn-group" style="margin-bottom:10px;">

                            <button id="animAddSelectedTargetBtn" type="button">Ajouter l’élément sélectionné</button>

                            <button id="animAddCameraTargetBtn" type="button">Ajouter la caméra</button>

                            <button id="animAddLightTargetBtn" type="button">Ajouter une light</button>

                            <button id="animAddBoneTargetBtn" type="button">Ajouter un bone</button>

                            <button id="animRemoveTargetBtn" type="button" style="background: rgba(239,68,68,0.55);">Supprimer l’élément</button>

                        </div>



                        <div class="form-group">

                            <label>Élément animé</label>

                            <select id="animTargetSelect"></select>

                        </div>



                        <div class="form-group">

                            <label>Durée (s)</label>

                            <input type="number" id="animDurationInput" min="0.000001" step="0.000001" value="3" />

                        </div>



                        <div class="grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:8px; margin-bottom:10px;">

                            <label style="display:flex; align-items:center; gap:8px; margin:0;"><input type="checkbox" id="animLoopInfinite" checked /> Boucle infini</label>

                            <label style="display:flex; align-items:center; gap:8px; margin:0;"><input type="checkbox" id="animBoomerang" /> Boomerang</label>

                            <label style="display:flex; align-items:center; gap:8px; margin:0;"><input type="checkbox" id="animRandom" /> Aléatoire</label>

                            <div>

                                <label style="margin-bottom:5px;">Vitesse: <span id="animSpeedValue">1.00</span></label>

                                <input type="range" id="animSpeed" min="0.05" max="4" step="0.01" value="1" />

                            </div>

                        </div>



                        <div class="form-group">

                            <label>Lecture auto toutes les (s) — 0 = off</label>

                            <input type="number" id="animTriggerEvery" min="0" step="0.1" value="0" />

                        </div>



                        <div class="btn-group" style="margin-bottom:10px;">

                            <button id="animPlayBtn" type="button">Play</button>

                            <button id="animPauseBtn" type="button">Pause</button>

                            <button id="animStopBtn" type="button" style="background: rgba(239,68,68,0.55);">Stop</button>

                            <button id="animBackToListBtn" type="button">Retour liste</button>

                        </div>



                        <div class="form-group">

                            <label>Temps: <span id="animTimeValue">0.000000</span>s</label>

                            <input type="range" id="animTimeSlider" min="0" max="3" step="0.001" value="0" />

                            <div style="display:flex; gap:8px; margin-top:8px;">

                                <input type="number" id="animTimeNumber" min="0" step="0.000001" value="0" style="flex:1;" />

                                <button id="animApplyTimeBtn" type="button" style="flex:0 0 auto;">Aller</button>

                            </div>

                        </div>



                        <div class="form-group">

                            <label>Pellicule (keyframes)</label>

                            <div id="animFilmstrip" style="display:flex; gap:8px; overflow-x:auto; padding:8px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.18); border-radius:6px;">

                                <!-- markers injectés -->

                            </div>

                            <div class="info" style="margin-top:8px;">• Glisse un keyframe pour changer son temps</div>

                        </div>



                        <div class="form-group">

                            <label>Canal</label>

                            <select id="animChannelSelect">

                                <option value="position" selected>Position</option>

                                <option value="rotation">Rotation</option>

                                <option value="scale">Scale</option>

                                <option value="intensity">Intensité (light)</option>

                            </select>

                        </div>



                        <div class="btn-group" style="margin-bottom:10px;">

                            <button id="animKeyframeFromObjectBtn" type="button">Ajouter keyframe (depuis objet)</button>

                            <button id="animKeyframeApplyToObjectBtn" type="button">Appliquer keyframe à l’objet</button>

                            <button id="animDeleteKeyframeBtn" type="button" style="background: rgba(239,68,68,0.55);">Supprimer keyframe</button>

                        </div>



                        <div class="form-group">

                            <label>Valeurs (éditables)</label>

                            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:8px;">

                                <input type="number" id="animValX" step="0.000001" value="0" />

                                <input type="number" id="animValY" step="0.000001" value="0" />

                                <input type="number" id="animValZ" step="0.000001" value="0" />

                            </div>

                            <div class="info" style="margin-top:8px;">• Rotation: valeurs en degrés (X,Y,Z)</div>

                        </div>



                        <div class="panel" style="margin-top:12px;">

                            <h3>Animation automatique (points)</h3>

                            <div class="btn-group" style="margin-bottom:10px;">

                                <button id="animAddPointBtn" type="button">Ajouter point (position actuelle)</button>

                                <button id="animClearPointsBtn" type="button" style="background: rgba(239,68,68,0.55);">Effacer points</button>

                            </div>

                            <div class="form-group">

                                <label>Temps par point (s)</label>

                                <input type="number" id="animPointTime" min="0.000001" step="0.000001" value="1" />

                            </div>

                            <div class="form-group">

                                <label>Fluidité (samples/segment)</label>

                                <input type="range" id="animSmoothness" min="5" max="60" step="1" value="20" />

                            </div>

                            <label style="display:flex; align-items:center; gap:8px; margin: 0 0 10px 0;">

                                <input type="checkbox" id="animEaseInOut" checked />

                                <span>Accélération douce (ease in/out)</span>

                            </label>

                            <div class="btn-group" style="margin-bottom:10px;">

                                <button id="animGeneratePathBtn" type="button">Générer l’animation (trajet)</button>

                            </div>

                            <div class="form-group">

                                <label>Points</label>

                                <ul id="animPointsList" class="object-list"></ul>

                            </div>



                            <div class="form-group">

                                <label>Édition point(s) sélectionné(s)</label>

                                <div class="info" style="margin-top:0; margin-bottom:8px;">• Clique sur #1/#2/#3… pour sélectionner. Ctrl/Cmd: multi-sélection. Shift: plage.</div>

                                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:8px;">

                                    <input type="number" id="animPointEditX" step="0.000001" value="0" />

                                    <input type="number" id="animPointEditY" step="0.000001" value="0" />

                                    <input type="number" id="animPointEditZ" step="0.000001" value="0" />

                                </div>

                                <div class="btn-group" style="margin-top:10px;">

                                    <button id="animPointApplyEditBtn" type="button">Appliquer XYZ</button>

                                    <button id="animPointClearSelectionBtn" type="button" style="background: rgba(239,68,68,0.55);">Désélectionner</button>

                                </div>

                                <div class="btn-group" style="margin-top:10px;">

                                    <button id="animPointAssignGroupBtn" type="button">Grouper sélection…</button>

                                </div>

                            </div>

                        </div>



                        <div class="panel" style="margin-top:12px;">

                            <h3>Importer / éditer animation (clip)</h3>

                            <div class="form-group">

                                <label>Choisir une animation existante (objet sélectionné / scène)</label>

                                <select id="animImportClipSelect"></select>

                            </div>

                            <div class="btn-group" style="margin-bottom:10px;">

                                <button id="animImportLoadBtn" type="button">Importer (afficher points squelette)</button>

                                <button id="animImportApplyBtn" type="button" style="background: rgba(34,197,94,0.55);">Valider (remplace le clip à l’export)</button>

                            </div>

                            <div class="form-group">

                                <label>Points squelette (os)</label>

                                <ul id="animImportBonesList" class="object-list"></ul>

                            </div>

                            <div class="form-group">

                                <label>Frames (frame par frame)</label>

                                <ul id="animImportFramesList" class="object-list"></ul>

                            </div>

                            <div class="form-group">

                                <label>Position (X,Y,Z) — frame sélectionnée</label>

                                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:8px;">

                                    <input type="number" id="animImportValX" step="0.000001" value="0" />

                                    <input type="number" id="animImportValY" step="0.000001" value="0" />

                                    <input type="number" id="animImportValZ" step="0.000001" value="0" />

                                </div>

                                <div class="btn-group" style="margin-top:10px;">

                                    <button id="animImportApplyFrameBtn" type="button">Appliquer XYZ aux points sélectionnés</button>

                                    <button id="animImportClearSelectionBtn" type="button" style="background: rgba(239,68,68,0.55);">Désélectionner</button>

                                </div>

                                <div class="btn-group" style="margin-top:10px;">

                                    <button id="animImportAssignGroupBtn" type="button">Grouper sélection…</button>

                                </div>

                                <div class="info" style="margin-top:8px;">• Cette section prépare l’édition frame par frame pour les os. Valider remplace le clip dans l’export GLB.</div>

                            </div>

                        </div>



                        <div class="btn-group" style="margin-top:10px;">

                            <button id="animSaveBtn" type="button">Sauvegarder</button>

                            <button id="animDeleteBtn" type="button" style="background: rgba(239,68,68,0.55);">Supprimer l’animation</button>

                        </div>

                    </div>

                </div>



                <div class="panel" id="rig-panel" style="display:none;">

                    <h3>Squelette / Animation</h3>

                    <div class="info" id="rigStatus">Aucun squelette détecté.</div>



                    <div class="form-group" style="margin-top:10px;">

                        <label>Créer un squelette (objet sans rig)</label>

                        <div class="btn-group">

                            <button id="createRigBtn" type="button">Créer squelette (coins+arêtes)</button>

                        </div>

                        <div class="info" style="margin-top:6px;">• Fonctionne sur un mesh simple (ex: cube). Plus d'articulations = plus souple.</div>

                    </div>



                    <div class="form-group" style="margin-top:10px;">

                        <label>Os sélectionné</label>

                        <div id="selectedBoneName" style="font-size:12px; color:#333; word-break:break-word;">-</div>

                    </div>



                    <div class="form-group">

                        <label>Mode mouvement</label>

                        <select id="rigMoveMode">

                            <option value="rotate" selected>Rotation</option>

                            <option value="translate">Déplacement</option>

                        </select>

                    </div>



                    <div class="form-group">

                        <label>Animation</label>

                        <div class="btn-group">

                            <button id="capturePoseBtn" type="button">Capture pose</button>

                            <button id="playAnimBtn" type="button">Play</button>

                            <button id="pauseAnimBtn" type="button">Pause</button>

                            <button id="resetAnimBtn" type="button">Reset</button>

                        </div>

                    </div>



                    <div class="form-group">

                        <label>Temps (s)</label>

                        <input type="number" id="animTime" min="0" step="0.05" value="0" style="width:100%;">

                    </div>

                    <div class="form-group">

                        <label>Durée (s)</label>

                        <input type="number" id="animDuration" min="0.05" step="0.05" value="2" style="width:100%;">

                    </div>



                    <div class="form-group" style="margin-top:10px;">

                        <label>Captures (keyframes)</label>

                        <select id="capturedKeysSelect" disabled>

                            <option value="">Aucune capture</option>

                        </select>

                        <div class="btn-group" style="margin-top:8px;">

                            <button id="previewCapturedBtn" type="button">Prévisualiser</button>

                            <button id="deleteCapturedBtn" type="button" style="background:#ef4444;">Supprimer</button>

                        </div>

                        <div class="form-group" style="margin-top:8px;">

                            <label>Changer le temps (s)</label>

                            <input type="number" id="capturedNewTime" min="0" step="0.05" value="0" style="width:100%;">

                            <button id="applyCapturedTimeBtn" type="button" style="margin-top:8px;">Appliquer</button>

                        </div>

                        <div class="info" style="margin-top:6px;">• Clique une capture → Prévisualiser pour voir la pose en temps réel.<br>• Changer le temps modifie la vitesse entre poses.</div>

                    </div>



                    <div class="btn-group">

                        <button id="clearAnimBtn" type="button" style="background:#ef4444;">Effacer animation</button>

                    </div>



                    <div class="form-group" style="margin-top:12px;">

                        <label>Animations importées</label>

                        <select id="importedClipSelect" disabled>

                            <option value="">Aucune</option>

                        </select>

                        <div class="btn-group" style="margin-top:8px;">

                            <button id="playImportedBtn" type="button">Play</button>

                            <button id="pauseImportedBtn" type="button">Pause</button>

                            <button id="stopImportedBtn" type="button" style="background:#ef4444;">Stop</button>

                        </div>

                        <div class="form-group" style="margin-top:8px;">

                            <label><input type="checkbox" id="importedLoopInfinite" checked> Loop infini</label>

                        </div>

                        <div class="form-group">

                            <label>Boucles (si non infini)</label>

                            <input type="number" id="importedLoopCount" min="1" step="1" value="1" disabled>

                        </div>

                        <div class="form-group">

                            <label>Vitesse: <span id="importedSpeedValue">1.0</span></label>

                            <input type="range" id="importedSpeed" min="0.1" max="5" step="0.1" value="1">

                        </div>

                        <div class="form-group">

                            <label><input type="checkbox" id="importedReverse"> Inverser (reverse)</label>

                        </div>

                        <div class="form-group">

                            <label><input type="checkbox" id="importedBoomerang"> Boomerang (normal↔envers)</label>

                        </div>

                        <div class="form-group">

                            <label>Délai début (s)</label>

                            <input type="number" id="importedStartDelay" min="0" step="0.05" value="0">

                        </div>

                        <div class="form-group">

                            <label>Délai entre boucles (s)</label>

                            <input type="number" id="importedLoopDelay" min="0" step="0.05" value="0">

                        </div>

                        <div class="form-group">

                            <label>FPS (1–200): <span id="importedFpsValue">60</span></label>

                            <input type="range" id="importedFps" min="1" max="200" step="1" value="60">

                        </div>

                        <div class="info" id="importedAnimStatus" style="margin-top:8px;">Aucune animation importée.</div>

                    </div>



                    <div class="form-group" style="margin-top:12px;">

                        <label>Rotation auto (360°)</label>

                        <label style="font-size:14px;"><input type="checkbox" id="autoRotateEnabled"> Activer</label>

                        <div class="form-group" style="margin-top:6px;">

                            <label>Vitesse (deg/s): <span id="autoRotateSpeedValue">20</span></label>

                            <input type="range" id="autoRotateSpeed" min="0" max="360" step="1" value="20">

                        </div>

                    </div>



                    <div class="info" style="margin-top:8px;">

                        • Importe un modèle avec squelette (.glb) → bouton activé<br>

                        • En mode squelette: touche un os pour le sélectionner<br>

                        • "Capture pose" enregistre la pose au temps choisi

                    </div>

                </div>



                <div class="panel" id="sculpt-panel" style="display:none;">

                    <h3>Pinceau (Sculpt)</h3>

                    <div class="form-group">

                        <label>Action</label>

                        <select id="sculptAction">

                            <option value="raise">Créer du relief (gonfler)</option>

                            <option value="lower">Creuser</option>

                            <option value="smooth" selected>Adoucir / Arrondir</option>

                            <option value="flatten">Étalonner (aplanir)</option>

                        </select>

                    </div>



                    <div class="form-group">

                        <label>Rayon (px): <span id="brushRadiusValue">80</span></label>

                        <input type="range" id="brushRadius" min="1" max="2000" step="1" value="80">

                    </div>



                    <div class="form-group">

                        <label>Intensité: <span id="brushStrengthValue">0.6</span></label>

                        <input type="range" id="brushStrength" min="0" max="3" step="0.01" value="0.6">

                    </div>



                    <div class="form-group">

                        <label>Texture pinceau</label>

                        <select id="brushTextureSelect">

                            <option value="">Aucune</option>

                            <?php foreach (list_textures($textureDir) as $t): ?>

                                <option value="<?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?></option>

                            <?php endforeach; ?>

                        </select>

                        <div style="margin-top:8px;">

                            <input type="file" id="brushTextureUpload" class="file-input" accept="image/png,image/jpeg,image/webp,image/svg+xml" />

                            <label for="brushTextureUpload" class="file-picker" style="margin-top:8px;">

                                <svg viewBox="0 0 24 24" aria-hidden="true">

                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.73z"/>

                                    <path d="M3.3 7.2 12 12l8.7-4.8"/>

                                    <path d="M12 22V12"/>

                                </svg>

                                <span>Choisir un fichier</span>

                            </label>

                            <button id="uploadTextureBtn" type="button" style="margin-top:8px;">Importer texture</button>

                            <div id="uploadTextureStatus" style="margin-top:6px; font-size:12px; color:#666;"></div>

                        </div>

                    </div>



                    <div class="info">

                        • Sélectionne un objet, puis “Sculpter”<br>

                        • Appui + glisse pour modifier la forme<br>

                        • Rayon en pixels (1 → 2000+)

                    </div>

                </div>



                <div class="panel" id="paint-panel" style="display:none;">

                    <h3>Brush (Peinture)</h3>



                    <div class="form-group">

                        <label>Taille brush (px): <span id="paintBrushSizeValue">64</span></label>

                        <input type="range" id="paintBrushSize" min="0.1" max="5000" step="0.1" value="64">

                    </div>



                    <div class="form-group">

                        <label>Couleur</label>

                        <input type="color" id="paintColor" value="#ff0000">

                    </div>



                    <div class="form-group">

                        <label>Opacité (RGBA): <span id="paintAlphaValue">1.00</span></label>

                        <input type="range" id="paintAlpha" min="0" max="1" step="0.01" value="1">

                    </div>



                    <div class="form-group">

                        <label>Estompage (soft): <span id="paintSoftnessValue">0.65</span></label>

                        <input type="range" id="paintSoftness" min="0" max="1" step="0.01" value="0.65">

                    </div>



                    <div class="form-group">

                        <label>Style material</label>

                        <select id="paintMaterialPreset">

                            <option value="basic" selected>Basique</option>

                            <option value="plastic">Plastique</option>

                            <option value="metal">Métalisé</option>

                            <option value="metal-reflect">Metal reflet lumière</option>

                            <option value="matte">Mat (matte)</option>

                            <option value="glossy">Brillant (glossy)</option>

                            <option value="rubber">Caoutchouc</option>

                            <option value="ceramic">Céramique</option>

                            <option value="chrome">Chrome</option>

                            <option value="gold">Or (gold)</option>

                            <option value="copper">Cuivre (copper)</option>

                            <option value="neon">Néon (emissive)</option>

                            <option value="glass">Verre (glass)</option>

                            <option value="velvet">Velours</option>

                        </select>

                        <div class="info" style="margin-top:6px;">• Les presets agissent sur le matériau de l’objet peint.</div>

                    </div>



                    <div class="form-group">

                        <label><input type="checkbox" id="paintUseTexture"> Utiliser une texture (fusion couleur + texture)</label>

                    </div>



                    <div id="paintTextureOptions" style="display:none;">

                        <div class="form-group">

                            <label>Texture</label>

                            <select id="paintTextureSelect">

                                <option value="">Aucune</option>

                                <?php foreach (list_textures($textureDir) as $t): ?>

                                    <option value="<?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?></option>

                                <?php endforeach; ?>

                            </select>

                        </div>



                        <div class="form-group">

                            <label>Taille texture (px): <span id="paintTextureSizeValue">256</span></label>

                            <input type="range" id="paintTextureSize" min="1" max="5000" step="1" value="256">

                        </div>



                        <div class="form-group">

                            <label>Répétition X</label>

                            <input type="number" id="paintTextureRepeatX" min="0.1" max="100" step="0.1" value="1">

                        </div>

                        <div class="form-group">

                            <label>Répétition Y</label>

                            <input type="number" id="paintTextureRepeatY" min="0.1" max="100" step="0.1" value="1">

                        </div>



                        <div class="form-group">

                            <label>Rotation texture (deg): <span id="paintTextureRotationValue">0</span></label>

                            <input type="range" id="paintTextureRotation" min="0" max="360" step="1" value="0">

                        </div>



                        <div class="form-group">

                            <label>Opacité texture: <span id="paintTextureOpacityValue">0.70</span></label>

                            <input type="range" id="paintTextureOpacity" min="0" max="1" step="0.01" value="0.7">

                        </div>



                        <div class="form-group">

                            <label>Qualité texture (résolution map)</label>

                            <select id="paintMapResolution">

                                <option value="512">512</option>

                                <option value="1024" selected>1024</option>

                                <option value="2048">2048</option>

                                <option value="4096">4096</option>

                            </select>

                        </div>



                        <div class="form-group">

                            <label><input type="checkbox" id="paintTextureSmoothing" checked> Lissage texture (smoothing)</label>

                        </div>

                    </div>



                    <div class="form-group" style="margin-top:10px;">

                        <label><input type="checkbox" id="paintReliefEnabled"> Brush Relief (mini relief)</label>

                    </div>



                    <div id="paintReliefOptions" style="display:none;">

                        <div class="form-group">

                            <label>Taille relief: <span id="paintReliefStrengthValue">0.35</span></label>

                            <input type="range" id="paintReliefStrength" min="0" max="1" step="0.01" value="0.35">

                        </div>



                        <div class="form-group">

                            <label>Type relief</label>

                            <select id="paintReliefType">

                                <option value="raise" selected>Relief (gonfler)</option>

                                <option value="engrave">Creuser</option>

                                <option value="noise">Bruit (texture)</option>

                            </select>

                        </div>



                        <div class="form-group">

                            <label>Polygone (facettage): <span id="paintReliefPolygonValue">0</span></label>

                            <input type="range" id="paintReliefPolygon" min="0" max="64" step="1" value="0">

                            <div class="info" style="margin-top:6px;">• 0 = off, sinon quantification UV (effet facettes).</div>

                        </div>



                        <div class="form-group">

                            <label>Qualité relief (résolution height)</label>

                            <select id="paintReliefResolution">

                                <option value="512">512</option>

                                <option value="1024" selected>1024</option>

                                <option value="2048">2048</option>

                                <option value="4096">4096</option>

                            </select>

                        </div>



                        <div class="form-group">

                            <label>Texture relief</label>

                            <select id="paintReliefTextureSelect">

                                <option value="">Aucune</option>

                                <?php foreach (list_textures($textureDir) as $t): ?>

                                    <option value="<?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?></option>

                                <?php endforeach; ?>

                            </select>

                        </div>



                        <div class="form-group">

                            <label>Material relief</label>

                            <select id="paintReliefMaterialPreset">

                                <option value="basic">Basique</option>

                                <option value="plastic" selected>Plastique</option>

                                <option value="metal">Métalisé</option>

                                <option value="metal-reflect">Metal reflet lumière</option>

                            </select>

                        </div>

                    </div>



                    <div class="info">

                        • Sélectionne un objet, puis “Brush (peindre)”<br>

                        • Appui + glisse pour peindre sur la surface (UV requis)<br>

                        • Texture optionnelle: fusion couleur + texture + réglages

                    </div>

                </div>



                <div class="panel" id="shape-panel" style="display:none;">

                    <h3>Ajouter une forme</h3>

                    <div class="btn-group">

                        <button data-shape="cube">Cube</button>

                        <button data-shape="sphere">Sphère</button>

                        <button data-shape="cylinder">Cylindre</button>

                        <button data-shape="cone">Cône</button>

                        <button data-shape="plane">Plan</button>

                        <button data-shape="triangle">Triangle</button>

                        <button data-shape="torus">Tore</button>

                        <button data-shape="ring">Anneau</button>

                        <button data-shape="icosahedron">Icosaèdre</button>

                        <button data-shape="dodecahedron">Dodécaèdre</button>

                        <button data-shape="octahedron">Octaèdre</button>

                        <button data-shape="torusknot">Nœud Tore</button>

                    </div>

                </div>



                <div class="panel" id="import-panel" style="display:none;">

                    <h3>Importer un modèle</h3>

                    <div class="form-group">

                        <label>GLB / GLTF</label>

                        <input type="file" id="glbUpload" class="file-input" accept=".glb,.gltf,model/gltf-binary,model/gltf+json" />

                        <label for="glbUpload" class="file-picker">

                            <svg viewBox="0 0 24 24" aria-hidden="true">

                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.73z"/>

                                <path d="M3.3 7.2 12 12l8.7-4.8"/>

                                <path d="M12 22V12"/>

                            </svg>

                            <span>Choisir un fichier 3D</span>

                        </label>

                        <button id="importGlbBtn" type="button" style="margin-top:8px;">Importer</button>

                        <div id="importGlbStatus" style="margin-top:6px; font-size:12px; color:rgba(255,255,255,0.75);"></div>

                        <div class="info" style="margin-top:8px;">

                            • Recommandé: .glb (tout-en-un)<br>

                            • Les .gltf avec fichiers externes peuvent ne pas se charger

                        </div>

                    </div>

                </div>



                <div class="panel" id="properties-panel" style="display:none;">

                    <h3>Propriétés de l'objet</h3>

                    <div class="form-group">

                        <label>Couleur</label>

                        <input type="color" id="color-picker" value="#ff0000">

                    </div>

                    <div class="form-group">

                        <label>Taille: <span id="scale-value">1.0</span></label>

                        <input type="range" id="scale-slider" min="0.1" max="3" step="0.1" value="1">

                    </div>

                    <div class="form-group">

                        <label>Opacité: <span id="opacity-value">1.0</span></label>

                        <input type="range" id="opacity-slider" min="0.1" max="1" step="0.1" value="1">

                    </div>



                    <div class="panel" style="margin-top:12px;">

                        <h3>Déplacement précis (monde)</h3>

                        <div class="form-group">

                            <label>Coordonnées XYZ</label>

                            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:8px;">

                                <input type="number" id="posXInput" step="0.000001" value="0" />

                                <input type="number" id="posYInput" step="0.000001" value="0" />

                                <input type="number" id="posZInput" step="0.000001" value="0" />

                            </div>

                        </div>

                        <div class="btn-group">

                            <button id="posUseSelectionBtn" type="button">Utiliser sélection</button>

                            <button id="posApplyBtn" type="button">Appliquer</button>

                        </div>

                        <div class="info" style="margin-top:6px;">• Applique au centre du groupe (multi-sélection) ou à l'objet.</div>

                    </div>



                    <div class="form-group">

                        <label>Priorité (renderOrder)</label>

                        <div class="btn-group">

                            <button id="priorityUpBtn" type="button">Priorité +</button>

                            <button id="priorityDownBtn" type="button">Priorité -</button>

                        </div>

                        <div class="info" style="margin-top:6px;">• Agit sur l'objet sélectionné (ou tout le groupe).</div>

                    </div>

                </div>



                <div class="panel" id="light-panel" style="display:none;">

                    <h3>Lumière</h3>

                    <div class="form-group">

                        <label>Qualité rendu</label>

                        <select id="renderQuality">

                            <option value="performance">Performance</option>

                            <option value="balanced" selected>Équilibré</option>

                            <option value="hd">HD</option>

                        </select>

                        <div class="info" style="margin-top:8px;">

                            • HD = plus net, plus lourd<br>

                            • Si ça rame, repasse en Équilibré

                        </div>

                    </div>



                    <div class="form-group">

                        <label>Exposition: <span id="exposureValue">1.00</span></label>

                        <input type="range" id="exposure" min="0.2" max="2.5" step="0.01" value="1">

                    </div>



                    <div class="form-group">

                        <label>Ombres</label>

                        <select id="shadowQuality">

                            <option value="off">Désactivées</option>

                            <option value="low">Basse</option>

                            <option value="medium" selected>Moyenne</option>

                            <option value="high">Haute</option>

                        </select>

                    </div>



                    <div class="form-group">

                        <label>Couleur lumière</label>

                        <input type="color" id="light-color" value="#ffffff">

                    </div>

                    <div class="form-group">

                        <label>Intensité: <span id="intensity-value">1.0</span></label>

                        <input type="range" id="intensity-slider" min="0.1" max="3" step="0.1" value="1">

                    </div>

                    <div class="form-group">

                        <label>Position X: <span id="light-x">0</span></label>

                        <input type="range" id="light-x-slider" min="-10" max="10" step="0.5" value="0">

                    </div>

                    <div class="form-group">

                        <label>Position Y: <span id="light-y">5</span></label>

                        <input type="range" id="light-y-slider" min="0" max="10" step="0.5" value="5">

                    </div>

                    <div class="form-group">

                        <label>Position Z: <span id="light-z">5</span></label>

                        <input type="range" id="light-z-slider" min="-10" max="10" step="0.5" value="5">

                    </div>



                    <div class="panel" style="margin-top:15px;">

                        <h3>Point Lights</h3>

                        <div class="btn-group">

                            <button id="togglePlaceLightBtn" type="button">Placer un point light</button>

                            <button id="addPointLightAtSelectionBtn" type="button">Ajouter sur sélection</button>

                        </div>



                        <div class="form-group" style="margin-top:10px;">

                            <label>Type</label>

                            <select id="placeLightType" class="tool-select">

                                <option value="mini">Mini</option>

                                <option value="sun">Sun</option>

                                <option value="fire">Fire</option>

                                <option value="static" selected>Static</option>

                            </select>

                        </div>

                        <div class="info">

                            • Mode placement: clique dans la scène (plan sol y=0)<br>

                            • “Ajouter sur sélection” crée une lumière sur l’objet

                        </div>



                        <div class="form-group" style="margin-top:10px;">

                            <label>Point lights dans la scène</label>

                            <ul id="pointLightsList" class="object-list"></ul>

                        </div>



                        <div id="selectedLightPanel" style="display:none;">

                            <div class="form-group">

                                <label>Type</label>

                                <select id="selectedLightType" class="tool-select">

                                    <option value="mini">Mini</option>

                                    <option value="sun">Sun</option>

                                    <option value="fire">Fire</option>

                                    <option value="static">Static</option>

                                </select>

                            </div>

                            <div class="form-group" id="selectedLightFlickerRow" style="display:none;">

                                <label style="display:flex; align-items:center; gap:8px;">

                                    <input type="checkbox" id="selectedLightFlicker" />

                                    <span>Flicker (Fire)</span>

                                </label>

                            </div>

                            <div class="form-group">

                                <label>Couleur</label>

                                <input type="color" id="selectedLightColor" value="#ffffff">

                            </div>

                            <div class="form-group">

                                <label>Intensité: <span id="selectedLightIntensityValue">1.0</span></label>

                                <input type="range" id="selectedLightIntensity" min="0" max="10" step="0.1" value="1">

                            </div>

                            <div class="form-group">

                                <label>Position X: <span id="selectedLightX">0</span></label>

                                <input type="range" id="selectedLightXSlider" min="-20" max="20" step="0.5" value="0">

                            </div>

                            <div class="form-group">

                                <label>Position Y: <span id="selectedLightY">5</span></label>

                                <input type="range" id="selectedLightYSlider" min="0" max="30" step="0.5" value="5">

                            </div>

                            <div class="form-group">

                                <label>Position Z: <span id="selectedLightZ">5</span></label>

                                <input type="range" id="selectedLightZSlider" min="-20" max="20" step="0.5" value="5">

                            </div>

                            <button id="deleteSelectedLightBtn" type="button" style="background:#ef4444;">Supprimer ce point light</button>

                        </div>

                    </div>

                </div>



                <div class="panel" id="objects-panel" style="display:none;">

                    <h3>Objets dans la scène</h3>

                    <ul id="object-list" class="object-list"></ul>

                </div>



                <div class="panel" id="export-panel" style="display:none;">

                    <h3>Export</h3>

                    <form method="post" id="export-form">

                        <input type="hidden" name="scene_data" id="scene-data">

                        <button type="submit" name="export_glb" class="export-btn">Exporter en GLB</button>

                    </form>



                    <div class="panel" style="margin-top:12px;">

                        <h3>Caméra (export)</h3>

                        <label style="display:flex; align-items:center; gap:8px; margin:0 0 10px 0;">

                            <input type="checkbox" id="exportIncludeCamera" checked />

                            <span>Inclure la caméra dans le GLB</span>

                        </label>

                        <div class="form-group">

                            <label>Position caméra (X,Y,Z)</label>

                            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:8px;">

                                <input type="number" id="exportCameraPosX" step="0.000001" value="10" />

                                <input type="number" id="exportCameraPosY" step="0.000001" value="10" />

                                <input type="number" id="exportCameraPosZ" step="0.000001" value="10" />

                            </div>

                        </div>

                        <div class="form-group">

                            <label>Cible caméra (lookAt) (X,Y,Z)</label>

                            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:8px;">

                                <input type="number" id="exportCameraTargetX" step="0.000001" value="0" />

                                <input type="number" id="exportCameraTargetY" step="0.000001" value="0" />

                                <input type="number" id="exportCameraTargetZ" step="0.000001" value="0" />

                            </div>

                        </div>

                        <div class="btn-group">

                            <button id="exportUseCurrentCameraBtn" type="button">Utiliser caméra actuelle</button>

                        </div>

                    </div>

                </div>



                <div class="panel" id="help-panel" style="display:none;">

                    <h3>Aide</h3>

                    <div class="info" style="margin-top:0;">

                        <strong>Instructions :</strong><br>

                        • Cliquez sur un objet pour le sélectionner<br>

                        • Utilisez les outils pour déplacer/redimensionner<br>

                        • Choisissez une forme dans la palette pour l'ajouter

                    </div>

                </div>



                </div>

            </div>



            <div class="viewer-container">

                <canvas id="viewer"></canvas>

                <div id="groupSelectRect"></div>

                <div class="controls-info">

                    Molette : Zoom | Clic droit : Tourner | Shift+Clic : Déplacer la vue

                </div>

            </div>

        </div>

    </div>



    <div id="cameraStatusBar">Cam: x=0.000 y=0.000 z=0.000</div>



    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/exporters/GLTFExporter.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/TransformControls.js"></script>



    <script>

        // Variables globales

        let scene, camera, renderer, controls;

        let clock = null;

        let selectedObject = null;

        let selectedObjects = [];

        let currentTool = 'select';

        let objects = [];

        let light;

        let ambientLight;

        let pointLights = [];

        let selectedPointLight = null;

        let isPlacingPointLight = false;

        let placingPointLightType = 'static';



        // Qualité rendu

        let renderQuality = 'balanced';

        let shadowQuality = 'medium';

        let desiredPixelRatio = 1.5;

        let raycaster = new THREE.Raycaster();

        let mouse = new THREE.Vector2();



        // Loader GLB/GLTF

        let gltfLoader = null;



        // Export GLB

        let gltfExporter = null;



        // Squelette / Animation

        let transformControls = null;

        let activeRig = null; // { root, skinnedMeshes, bones, bonePickersGroup, skeletonHelper, selectedBone }

        let animMixer = null;

        let animAction = null;

        let capturedClip = null;

        let captureTracks = null; // Map name -> { pos:{times,values}, quat:{times,values}, scale:{times,values} }

        let isAnimPlaying = false;



        // Captures (édition)

        let capturedKeyTimes = [];



        // Lecture manuelle des "Captures" (panneau Rig)

        // (On évite AnimationMixer ici car les tracks basées sur UUID ne se bindent pas de façon fiable,

        // et on veut que les bones/squelettes s'animent à coup sûr.)

        let capturedPlayTime = 0;



        // Animations importées (clips présents dans le GLB/GLTF)

        let importedTargetRoot = null;

        let importedClips = [];

        let importedMixer = null;

        let importedAction = null;

        let importedIsPlaying = false;

        let importedFps = 60;

        let importedAccumulator = 0;

        let importedLoopInfinite = true;

        let importedLoopCount = 1;

        let importedSpeed = 1;

        let importedReverse = false;

        let importedBoomerang = false;

        let importedStartDelaySec = 0;

        let importedLoopDelaySec = 0;

        let importedStartDelayRemaining = 0;

        let importedLoopDelayRemaining = 0;

        let importedMixerLoopListenerAttached = false;



        // Rotation auto (360°)

        let autoRotateEnabled = false;

        let autoRotateSpeedDeg = 20;



        // Animations utilisateur (transform keyframes)

        let userAnimations = []; // {id,name,scope,targetUuids,keyframes,autoPointsByUuid,options}

        let userAnimSelectedId = null;

        let userAnimMixer = null;

        let userAnimAction = null;

        let userAnimIsPlaying = false;

        let userAnimActiveClip = null;

        let userAnimActiveAnim = null;

        let userAnimPlayTime = 0;

        let userAnimPlayDirection = 1; // 1 forward, -1 backward (boomerang)

        let userAnimLastTriggerAt = new Map(); // animId -> timeSec

        let userAnimAffectsCamera = false;

        let userAnimCameraWasInScene = false;

        let userAnimPrevControlsEnabled = null;



        // Helpers de sélection + poignées (handles)

        let selectionBoxHelper = null;

        let handlesGroup = null;

        let cornerHandles = [];

        let edgeHandles = [];

        const selectionBox3 = new THREE.Box3();

        const selectionBoxTmp = new THREE.Box3();

        const isCoarsePointer = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);

        const HANDLE_SCREEN_SCALE = isCoarsePointer ? 0.06 : 0.045;

        const HANDLE_BASE_RADIUS = isCoarsePointer ? 0.22 : 0.14;



        // Grouper (sélection rectangle)

        let groupRectActive = false;

        const groupRectStart = { x: 0, y: 0 };

        const groupRectNow = { x: 0, y: 0 };



        // Rotation (outil)

        let rotateAxis = null; // 'x'|'y'|'z'



        // Déplacements groupés

        let dragStartPositions = new Map(); // uuid -> Vector3



        // Drag / interaction

        let isPointerDown = false;

        let dragMode = null; // 'move' | 'scale' | 'deform'

        let activeHandle = null;

        let activePointerId = null;

        const activePointers = new Map(); // pointerId -> { type }

        const dragStartClient = { x: 0, y: 0 };

        const dragStartScale = new THREE.Vector3(1, 1, 1);

        const dragStartPosition = new THREE.Vector3();

        const dragStartRotations = new Map(); // uuid -> Quaternion



        // Sculpt brush

        let brushRadiusPx = 80;

        let brushStrength = 0.6;

        let sculptAction = 'smooth';

        let brushMaskCanvas = null;

        let brushMaskCtx = null;

        let brushMaskSize = 128;

        let brushMaskReady = false;

        let sculptLastHit = null;

        const tmpVec3 = new THREE.Vector3();

        const tmpVec3b = new THREE.Vector3();

        const tmpVec3c = new THREE.Vector3();

        const tmpMat3 = new THREE.Matrix3();

        const tmpMat4 = new THREE.Matrix4();

        const groundPlane = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0);

        const tmpRayPoint = new THREE.Vector3();



        // Brush peinture (UV)

        let paintBrushSizePx = 64;

        let paintAlpha = 1.0;

        let paintSoftness = 0.65;

        let paintMaterialPreset = 'basic';

        // Espacement des coups (en fraction du rayon). 0.35 = trait régulier sans trous

        let paintStrokeSpacingFactor = 0.35;

        let paintLastStamp = null; // { meshUuid, u, v }

        let paintUseTexture = false;

        let paintTextureFile = '';

        let paintTextureSizePx = 256;

        let paintTextureRepeatX = 1;

        let paintTextureRepeatY = 1;

        let paintTextureRotationDeg = 0;

        let paintTextureOpacity = 0.7;

        let paintMapResolution = 1024;

        let paintTextureSmoothing = true;



        let paintReliefEnabled = false;

        let paintReliefStrength = 0.35;

        let paintReliefType = 'raise';

        let paintReliefPolygon = 0;

        let paintReliefResolution = 1024;

        let paintReliefTextureFile = '';

        let paintReliefMaterialPreset = 'plastic';



        const paintTextureCache = new Map();

        const paintOffscreenTile = document.createElement('canvas');

        const paintOffscreenTileCtx = paintOffscreenTile.getContext('2d');



        // Initialisation

        function init() {

            // Créer la scène

            scene = new THREE.Scene();

            // Background sera défini par le thème, valeur temporaire

            scene.background = new THREE.Color(0x1a1a1a);



            // Créer la caméra

            camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);

            camera.position.set(10, 10, 10);



            // Créer le renderer

            const canvas = document.getElementById('viewer');

            const container = canvas.parentElement;

            renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, powerPreference: 'high-performance' });

            renderer.setSize(container.offsetWidth, container.offsetHeight);

            applyRenderQuality();

            renderer.shadowMap.enabled = true;

            renderer.shadowMap.type = THREE.PCFSoftShadowMap;



            // Color management / tonemapping (r128)

            renderer.outputEncoding = THREE.sRGBEncoding;

            renderer.toneMapping = THREE.ACESFilmicToneMapping;

            renderer.toneMappingExposure = 1.0;

            renderer.physicallyCorrectLights = true;



            // Ajouter les contrôles

            controls = new THREE.OrbitControls(camera, renderer.domElement);

            controls.enableDamping = true;

            controls.dampingFactor = 0.05;



            // Clock pour animations

            clock = new THREE.Clock();



            // Ajouter une grille

            const gridHelper = new THREE.GridHelper(20, 20, 0x444444, 0x888888);

            scene.add(gridHelper);



            // Ajouter les axes

            const axesHelper = new THREE.AxesHelper(5);

            scene.add(axesHelper);



            // Ajouter la lumière principale

            light = new THREE.PointLight(0xffffff, 1, 100);

            light.position.set(0, 5, 5);

            light.castShadow = true;

            scene.add(light);

            applyShadowQualityToLight(light);



            // Ajouter une lumière ambiante

            ambientLight = new THREE.AmbientLight(0x404040, 0.55);

            scene.add(ambientLight);



            // APPLIQUER THEME (Background & Grid) - avec délai pour s'assurer que tout est chargé

            setTimeout(() => {

                if (window.appThemeManager && typeof window.appThemeManager.updateScene3D === 'function') {

                    window.appThemeManager.updateScene3D();

                }

            }, 100);

            // Aussi lors du chargement complet de la page

            window.addEventListener('load', () => {

                setTimeout(() => {

                    if (window.appThemeManager && typeof window.appThemeManager.updateScene3D === 'function') {

                        window.appThemeManager.updateScene3D();

                    }

                }, 500);

            });



            // Gestion du redimensionnement

            window.addEventListener('resize', onWindowResize);



            // Interactions souris + tactile

            setupPointerEvents(canvas);



            // Mettre à jour les contrôles de la lumière

            updateLightControls();



            // UI qualité / lights

            initQualityUI();

            initPointLightsUI();



            // Import GLB

            initGlbImportUI();



            // UI squelette / animation

            initRigUI();



            // Export GLB (réel)

            initGlbExportUI();



            // État initial des boutons outils

            updateToolButtonsEnabledState();



            // Init UI sculpt

            initSculptUI();



            // Init UI brush peinture

            initPaintUI();



            // UI sidebar (toolbar icônes + popup)

            initSidebarPopupUI();



            // Compte / premium

            initAccountPremiumUI();



            // UI animations (nouveau système)

            initAnimationsUI();



            // Animer

            animate();

        }



        function genId(prefix) {

            return (prefix || 'id') + '-' + Math.random().toString(36).slice(2) + '-' + Date.now().toString(36);

        }



        function safeJsonParse(str, fallback) {

            try { return JSON.parse(str); } catch (_) { return fallback; }

        }



        function clamp(v, a, b) {

            return Math.max(a, Math.min(b, v));

        }



        function loadUserAnimationsFromStorage() {

            const raw = localStorage.getItem('editor3d_userAnimations_v1');

            const parsed = safeJsonParse(raw, null);

            if (!parsed || !Array.isArray(parsed)) {

                userAnimations = [];

                return;

            }

            // Normalisation minimale

            userAnimations = parsed.map((a) => {

                const anim = a && typeof a === 'object' ? a : {};

                anim.id = anim.id || genId('anim');

                anim.name = anim.name || 'Animation';

                anim.scope = (anim.scope === 'selection' || anim.scope === 'scene') ? anim.scope : 'scene';

                anim.targetUuids = Array.isArray(anim.targetUuids) ? anim.targetUuids : [];

                anim.keyframes = (anim.keyframes && typeof anim.keyframes === 'object') ? anim.keyframes : {};

                anim.autoPointsByUuid = (anim.autoPointsByUuid && typeof anim.autoPointsByUuid === 'object') ? anim.autoPointsByUuid : {};

                anim.options = (anim.options && typeof anim.options === 'object') ? anim.options : {};

                if (typeof anim.options.duration !== 'number') anim.options.duration = 3;

                if (typeof anim.options.speed !== 'number') anim.options.speed = 1;

                if (typeof anim.options.loopInfinite !== 'boolean') anim.options.loopInfinite = true;

                if (typeof anim.options.boomerang !== 'boolean') anim.options.boomerang = false;

                if (typeof anim.options.random !== 'boolean') anim.options.random = false;

                if (typeof anim.options.triggerEverySec !== 'number') anim.options.triggerEverySec = 0;

                return anim;

            });

        }



        function saveUserAnimationsToStorage() {

            try {

                localStorage.setItem('editor3d_userAnimations_v1', JSON.stringify(userAnimations));

            } catch (_) {

                // ignore

            }

        }



        function getObjectLabelByUuid(uuid) {

            if (camera && uuid === camera.uuid) return 'Caméra';

            // Vérifier si c'est une point light

            if (typeof pointLights !== 'undefined' && Array.isArray(pointLights)) {

                for (const pl of pointLights) {

                    if (pl && pl.uuid === uuid) {

                        const type = (pl.userData && pl.userData.type) || 'static';

                        return 'Light ' + type + ' (' + uuid.slice(0, 8) + ')';

                    }

                }

            }

            const obj = findObjectByUuid(uuid);

            if (!obj) return uuid;

            // Vérifier si c'est un bone

            if (obj.isBone || obj.type === 'Bone') {

                const name = obj.name || 'Bone';

                return 'Bone: ' + name + ' (' + uuid.slice(0, 8) + ')';

            }

            const name = (obj.userData && obj.userData.name) ? obj.userData.name : (obj.name || obj.type || 'Objet');

            return name + ' (' + uuid.slice(0, 8) + ')';

        }



        function getOrCreateAnimById(id) {

            return userAnimations.find((a) => a && a.id === id) || null;

        }



        function createUserAnimation(scope, targetUuids) {

            const anim = {

                id: genId('anim'),

                name: (scope === 'selection') ? 'Anim sélection' : 'Anim scène',

                scope: scope || 'scene',

                targetUuids: Array.isArray(targetUuids) ? Array.from(new Set(targetUuids.filter(Boolean))) : [],

                keyframes: {},

                autoPointsByUuid: {},

                options: {

                    duration: 3,

                    speed: 1,

                    loopInfinite: true,

                    boomerang: false,

                    random: false,

                    triggerEverySec: 0,

                },

            };

            userAnimations.push(anim);

            saveUserAnimationsToStorage();

            return anim;

        }



        function ensureKeyframes(anim, uuid) {

            if (!anim.keyframes[uuid]) {

                anim.keyframes[uuid] = { position: [], rotation: [], scale: [], intensity: [] };

            }

            if (!Array.isArray(anim.keyframes[uuid].position)) anim.keyframes[uuid].position = [];

            if (!Array.isArray(anim.keyframes[uuid].rotation)) anim.keyframes[uuid].rotation = [];

            if (!Array.isArray(anim.keyframes[uuid].scale)) anim.keyframes[uuid].scale = [];

            if (!Array.isArray(anim.keyframes[uuid].intensity)) anim.keyframes[uuid].intensity = [];

        }



        function upsertKeyframe(list, t, value) {

            const time = Math.max(0, Number(t) || 0);

            const v = Array.isArray(value) ? value.map((x) => Number(x) || 0) : [0, 0, 0];

            // remplace si même temps (tolérance micro)

            const eps = 1e-6;

            const idx = list.findIndex((k) => Math.abs((Number(k.t) || 0) - time) <= eps);

            if (idx >= 0) list[idx] = { t: time, v };

            else list.push({ t: time, v });

            list.sort((a, b) => (a.t || 0) - (b.t || 0));

        }



        function deleteKeyframeNear(list, t) {

            const time = Math.max(0, Number(t) || 0);

            const eps = 1e-3; // suppression tolérée autour de la ms

            const idx = list.findIndex((k) => Math.abs((Number(k.t) || 0) - time) <= eps);

            if (idx >= 0) list.splice(idx, 1);

        }



        function eulerDegToQuatArray(xDeg, yDeg, zDeg) {

            const ex = (Number(xDeg) || 0) * Math.PI / 180;

            const ey = (Number(yDeg) || 0) * Math.PI / 180;

            const ez = (Number(zDeg) || 0) * Math.PI / 180;

            const e = new THREE.Euler(ex, ey, ez, 'XYZ');

            const q = new THREE.Quaternion().setFromEuler(e);

            return [q.x, q.y, q.z, q.w];

        }



        function quatToEulerDegArray(qArr) {

            const q = new THREE.Quaternion(qArr[0], qArr[1], qArr[2], qArr[3]);

            const e = new THREE.Euler().setFromQuaternion(q, 'XYZ');

            return [e.x * 180 / Math.PI, e.y * 180 / Math.PI, e.z * 180 / Math.PI];

        }



        // Trouve un objet par UUID (scene, camera, lights, bones, squelettes inclus)

        function findObjectByUuid(uuid) {

            if (!uuid) return null;

            if (camera && uuid === camera.uuid) return camera;

            // Point lights

            if (typeof pointLights !== 'undefined' && Array.isArray(pointLights)) {

                for (const pl of pointLights) {

                    if (pl && pl.uuid === uuid) return pl;

                }

            }

            // Recherche dans toute la scène (inclut les bones ajoutés à la scène)

            if (scene) {

                let found = null;

                scene.traverse((o) => {

                    if (o && o.uuid === uuid) found = o;

                    // Si l'objet est un SkinnedMesh, chercher dans son squelette

                    if (o && o.isSkinnedMesh && o.skeleton && o.skeleton.bones) {

                        for (const bone of o.skeleton.bones) {

                            if (bone && bone.uuid === uuid) found = bone;

                            // Parcourir les enfants des bones

                            bone.traverse && bone.traverse((child) => {

                                if (child && child.uuid === uuid) found = child;

                            });

                        }

                    }

                    // Si l'objet a un squelette directement

                    if (o && o.skeleton && o.skeleton.bones) {

                        for (const bone of o.skeleton.bones) {

                            if (bone && bone.uuid === uuid) found = bone;

                        }

                    }

                });

                if (found) return found;

            }

            return null;

        }



        // Calcule le chemin complet d'un objet pour le binding d'animation

        function getObjectBindingName(obj) {

            if (!obj) return null;

            // Pour les objets avec UUID, on assigne temporairement le nom = uuid pour le binding

            // Le mixer utilisera ce nom

            if (!obj.name || obj.name === '') {

                obj.name = obj.uuid;

            }

            return obj.uuid;

        }



        function buildClipFromUserAnimation(anim) {

            if (!anim) return null;

            const tracks = [];

            let maxT = 0;

            for (const uuid of (anim.targetUuids || [])) {

                if (!uuid) continue;



                // Trouver l'objet et s'assurer qu'il a un nom pour le binding

                const targetObj = findObjectByUuid(uuid);

                if (!targetObj) {

                    console.warn('[Animation] Objet non trouvé pour UUID:', uuid);

                    continue; // Skip si objet non trouvé

                }



                // IMPORTANT: on n'altère pas la hiérarchie (surtout pour les bones).

                // Pour l'export, on se base sur le nom actuel si possible.

                const hasName = !!(targetObj.name && String(targetObj.name).trim());

                const isBone = !!(targetObj.isBone || targetObj.type === 'Bone');

                const nodeName = hasName ? String(targetObj.name) : uuid;

                // Si l'objet n'a pas de nom, on peut en assigner un (SAUF bones) pour rendre l'export plus fiable.

                if (!hasName && !isBone && targetObj !== camera) {

                    targetObj.name = nodeName;

                }



                ensureKeyframes(anim, uuid);

                const kf = anim.keyframes[uuid];



                // position

                if (kf.position && kf.position.length > 0) {

                    const times = [];

                    const values = [];

                    for (const k of kf.position) {

                        const t = Number(k.t) || 0;

                        const v = Array.isArray(k.v) ? k.v : [0, 0, 0];

                        times.push(t);

                        values.push(Number(v[0]) || 0, Number(v[1]) || 0, Number(v[2]) || 0);

                        maxT = Math.max(maxT, t);

                    }

                    tracks.push(new THREE.VectorKeyframeTrack(nodeName + '.position', times, values));

                }



                // rotation (quaternion)

                if (kf.rotation && kf.rotation.length > 0) {

                    const times = [];

                    const values = [];

                    for (const k of kf.rotation) {

                        const t = Number(k.t) || 0;

                        const v = Array.isArray(k.v) ? k.v : [0, 0, 0, 1];

                        times.push(t);

                        values.push(Number(v[0]) || 0, Number(v[1]) || 0, Number(v[2]) || 0, Number(v[3]) || 1);

                        maxT = Math.max(maxT, t);

                    }

                    tracks.push(new THREE.QuaternionKeyframeTrack(nodeName + '.quaternion', times, values));

                }



                // scale

                if (kf.scale && kf.scale.length > 0) {

                    const times = [];

                    const values = [];

                    for (const k of kf.scale) {

                        const t = Number(k.t) || 0;

                        const v = Array.isArray(k.v) ? k.v : [1, 1, 1];

                        times.push(t);

                        values.push(Number(v[0]) || 1, Number(v[1]) || 1, Number(v[2]) || 1);

                        maxT = Math.max(maxT, t);

                    }

                    tracks.push(new THREE.VectorKeyframeTrack(nodeName + '.scale', times, values));

                }



                // intensity (pour les lights)

                if (kf.intensity && kf.intensity.length > 0) {

                    const times = [];

                    const values = [];

                    for (const k of kf.intensity) {

                        const t = Number(k.t) || 0;

                        const v = Array.isArray(k.v) ? k.v[0] : (typeof k.v === 'number' ? k.v : 1);

                        times.push(t);

                        values.push(Number(v) || 1);

                        maxT = Math.max(maxT, t);

                    }

                    tracks.push(new THREE.NumberKeyframeTrack(nodeName + '.intensity', times, values));

                }

            }



            if (tracks.length === 0) return null;

            const duration = Math.max(0.000001, Number(anim.options && anim.options.duration) || 0, maxT);

            const clipName = (anim.name || 'Animation') + ' [' + anim.id.slice(0, 8) + ']';

            const clip = new THREE.AnimationClip(clipName, duration, tracks);

            return clip;

        }



        function stopUserAnimationPlayback() {

            userAnimIsPlaying = false;

            if (userAnimAction) {

                try { userAnimAction.stop(); } catch (_) {}

            }

            userAnimAction = null;

            userAnimActiveClip = null;

            userAnimActiveAnim = null;

            userAnimPlayTime = 0;

            userAnimPlayDirection = 1;



            if (userAnimPrevControlsEnabled !== null && controls) {

                controls.enabled = !!userAnimPrevControlsEnabled;

            }

            userAnimPrevControlsEnabled = null;



            if (userAnimAffectsCamera && scene && camera) {

                if (!userAnimCameraWasInScene && camera.parent === scene) {

                    scene.remove(camera);

                }

            }

            userAnimAffectsCamera = false;

            userAnimCameraWasInScene = false;



            // Nettoyer les objets temporairement ajoutés pour l'animation

            // (on ne les retire pas car les lights/bones doivent rester visibles)

        }



        // Stocke les objets temporairement ajoutés à la scène pour l'animation

        let userAnimTempAddedObjects = [];



        function getUserAnimEffectiveDuration(anim) {

            if (!anim) return 0.000001;

            let maxT = 0;

            const targets = Array.isArray(anim.targetUuids) ? anim.targetUuids : [];

            for (const uuid of targets) {

                if (!uuid) continue;

                ensureKeyframes(anim, uuid);

                const kf = anim.keyframes[uuid];

                const lists = [kf.position, kf.rotation, kf.scale, kf.intensity];

                for (const list of lists) {

                    if (!Array.isArray(list) || list.length === 0) continue;

                    const last = list[list.length - 1];

                    const t = Number(last && last.t) || 0;

                    if (t > maxT) maxT = t;

                }

            }

            const opt = Math.max(0.000001, Number(anim.options && anim.options.duration) || 0.000001);

            return Math.max(opt, maxT, 0.000001);

        }



        function sampleVec3(list, t, fallback) {

            if (!Array.isArray(list) || list.length === 0) return fallback;

            if (list.length === 1) return Array.isArray(list[0].v) ? list[0].v.slice(0, 3) : fallback;



            const time = Number(t) || 0;

            if (time <= (Number(list[0].t) || 0)) return (Array.isArray(list[0].v) ? list[0].v.slice(0, 3) : fallback);

            const lastIdx = list.length - 1;

            if (time >= (Number(list[lastIdx].t) || 0)) return (Array.isArray(list[lastIdx].v) ? list[lastIdx].v.slice(0, 3) : fallback);



            // recherche binaire

            let lo = 0;

            let hi = lastIdx;

            while (hi - lo > 1) {

                const mid = (lo + hi) >> 1;

                const mt = Number(list[mid].t) || 0;

                if (mt <= time) lo = mid; else hi = mid;

            }

            const k0 = list[lo];

            const k1 = list[hi];

            const t0 = Number(k0.t) || 0;

            const t1 = Number(k1.t) || 0;

            const v0 = Array.isArray(k0.v) ? k0.v : fallback;

            const v1 = Array.isArray(k1.v) ? k1.v : fallback;

            const a = (t1 > t0) ? clamp((time - t0) / (t1 - t0), 0, 1) : 0;

            return [

                (Number(v0[0]) || 0) + ((Number(v1[0]) || 0) - (Number(v0[0]) || 0)) * a,

                (Number(v0[1]) || 0) + ((Number(v1[1]) || 0) - (Number(v0[1]) || 0)) * a,

                (Number(v0[2]) || 0) + ((Number(v1[2]) || 0) - (Number(v0[2]) || 0)) * a,

            ];

        }



        function sampleNumber(list, t, fallback) {

            if (!Array.isArray(list) || list.length === 0) return fallback;

            if (list.length === 1) return Number(Array.isArray(list[0].v) ? list[0].v[0] : list[0].v) || fallback;



            const time = Number(t) || 0;

            const firstT = Number(list[0].t) || 0;

            const lastIdx = list.length - 1;

            const lastT = Number(list[lastIdx].t) || 0;

            if (time <= firstT) return Number(Array.isArray(list[0].v) ? list[0].v[0] : list[0].v) || fallback;

            if (time >= lastT) return Number(Array.isArray(list[lastIdx].v) ? list[lastIdx].v[0] : list[lastIdx].v) || fallback;



            let lo = 0;

            let hi = lastIdx;

            while (hi - lo > 1) {

                const mid = (lo + hi) >> 1;

                const mt = Number(list[mid].t) || 0;

                if (mt <= time) lo = mid; else hi = mid;

            }

            const k0 = list[lo];

            const k1 = list[hi];

            const t0 = Number(k0.t) || 0;

            const t1 = Number(k1.t) || 0;

            const v0 = Number(Array.isArray(k0.v) ? k0.v[0] : k0.v) || fallback;

            const v1 = Number(Array.isArray(k1.v) ? k1.v[0] : k1.v) || fallback;

            const a = (t1 > t0) ? clamp((time - t0) / (t1 - t0), 0, 1) : 0;

            return v0 + (v1 - v0) * a;

        }



        function sampleQuat(list, t, fallbackQuatArr) {

            if (!Array.isArray(list) || list.length === 0) return fallbackQuatArr;

            if (list.length === 1) return Array.isArray(list[0].v) ? list[0].v.slice(0, 4) : fallbackQuatArr;



            const time = Number(t) || 0;

            const lastIdx = list.length - 1;

            if (time <= (Number(list[0].t) || 0)) return (Array.isArray(list[0].v) ? list[0].v.slice(0, 4) : fallbackQuatArr);

            if (time >= (Number(list[lastIdx].t) || 0)) return (Array.isArray(list[lastIdx].v) ? list[lastIdx].v.slice(0, 4) : fallbackQuatArr);



            let lo = 0;

            let hi = lastIdx;

            while (hi - lo > 1) {

                const mid = (lo + hi) >> 1;

                const mt = Number(list[mid].t) || 0;

                if (mt <= time) lo = mid; else hi = mid;

            }

            const k0 = list[lo];

            const k1 = list[hi];

            const t0 = Number(k0.t) || 0;

            const t1 = Number(k1.t) || 0;

            const v0 = Array.isArray(k0.v) ? k0.v : fallbackQuatArr;

            const v1 = Array.isArray(k1.v) ? k1.v : fallbackQuatArr;

            const a = (t1 > t0) ? clamp((time - t0) / (t1 - t0), 0, 1) : 0;

            const q0 = new THREE.Quaternion(Number(v0[0]) || 0, Number(v0[1]) || 0, Number(v0[2]) || 0, Number(v0[3]) || 1);

            const q1 = new THREE.Quaternion(Number(v1[0]) || 0, Number(v1[1]) || 0, Number(v1[2]) || 0, Number(v1[3]) || 1);

            const out = new THREE.Quaternion();

            out.copy(q0).slerp(q1, a);

            return [out.x, out.y, out.z, out.w];

        }



        function applyUserAnimationAtTime(anim, t) {

            if (!anim) return;

            const time = Number(t) || 0;

            const targets = Array.isArray(anim.targetUuids) ? anim.targetUuids : [];



            for (const uuid of targets) {

                if (!uuid) continue;

                const obj = findObjectByUuid(uuid);

                if (!obj) continue;

                ensureKeyframes(anim, uuid);

                const kf = anim.keyframes[uuid];



                if (kf.position && kf.position.length > 0) {

                    const v = sampleVec3(kf.position, time, [obj.position.x, obj.position.y, obj.position.z]);

                    obj.position.set(Number(v[0]) || 0, Number(v[1]) || 0, Number(v[2]) || 0);

                }

                if (kf.scale && kf.scale.length > 0) {

                    const v = sampleVec3(kf.scale, time, [obj.scale.x, obj.scale.y, obj.scale.z]);

                    obj.scale.set(Number(v[0]) || 1, Number(v[1]) || 1, Number(v[2]) || 1);

                }

                if (kf.rotation && kf.rotation.length > 0) {

                    const qArr = sampleQuat(kf.rotation, time, [obj.quaternion.x, obj.quaternion.y, obj.quaternion.z, obj.quaternion.w]);

                    obj.quaternion.set(Number(qArr[0]) || 0, Number(qArr[1]) || 0, Number(qArr[2]) || 0, Number(qArr[3]) || 1);

                }

                if (kf.intensity && kf.intensity.length > 0 && typeof obj.intensity === 'number') {

                    const v = sampleNumber(kf.intensity, time, obj.intensity);

                    obj.intensity = Number(v) || 0;

                }

            }

        }



        function advanceUserAnimation(anim, deltaSec) {

            if (!anim) return;

            const d = getUserAnimEffectiveDuration(anim);

            const speed = clamp(Number(anim.options && anim.options.speed) || 1, 0.01, 10);

            const boomerang = !!(anim.options && anim.options.boomerang);

            const loopInfinite = !!(anim.options && anim.options.loopInfinite);



            let ended = false;



            let t = Number(userAnimPlayTime) || 0;

            let dir = (userAnimPlayDirection === -1) ? -1 : 1;

            const dt = (Number(deltaSec) || 0) * speed * dir;

            t += dt;



            if (boomerang) {

                if (loopInfinite) {

                    // Ping-pong infini: reflète le temps et inverse la direction à chaque extrémité.

                    while (t > d || t < 0) {

                        if (t > d) {

                            t = d - (t - d);

                            dir *= -1;

                        } else if (t < 0) {

                            t = -t;

                            dir *= -1;

                        }

                    }

                } else {

                    // Ping-pong une seule fois: forward jusqu'à fin, puis backward jusqu'à 0.

                    if (dir === 1 && t >= d) {

                        t = d;

                        dir = -1;

                    } else if (dir === -1 && t <= 0) {

                        t = 0;

                        userAnimIsPlaying = false;

                        ended = true;

                    }

                }

            } else {

                if (loopInfinite) {

                    if (d > 0) {

                        t = ((t % d) + d) % d;

                    } else {

                        t = 0;

                    }

                } else {

                    if (t >= d) {

                        t = d;

                        userAnimIsPlaying = false;

                        ended = true;

                    } else if (t <= 0) {

                        t = 0;

                    }

                }

            }



            userAnimPlayTime = clamp(t, 0, d);

            userAnimPlayDirection = dir;

            applyUserAnimationAtTime(anim, userAnimPlayTime);



            // Si l'animation vient de se terminer, restaure controls/caméra comme un "pause".

            if (ended) {

                pauseUserAnimation();

            }

        }



        function playUserAnimation(anim, fromTime = 0) {

            if (!scene) return;



            // Stop l'ancienne lecture AVANT de configurer caméra/controls (sinon ça annule nos réglages)

            stopUserAnimationPlayback();



            // Nettoyer les objets temporaires précédents (legacy)

            userAnimTempAddedObjects = [];



            // Caméra: doit être dans la hiérarchie du mixer (root = scene)

            userAnimAffectsCamera = !!(anim && Array.isArray(anim.targetUuids) && camera && anim.targetUuids.includes(camera.uuid));

            if (userAnimAffectsCamera && camera) {

                userAnimCameraWasInScene = (camera.parent === scene);

                if (!userAnimCameraWasInScene) {

                    scene.add(camera);

                }

                if (controls) {

                    userAnimPrevControlsEnabled = controls.enabled;

                    controls.enabled = false;

                }

            }



            // S'assurer que tous les objets cibles ont leur name défini ET sont dans la hiérarchie de la scène

            for (const uuid of (anim.targetUuids || [])) {

                const obj = findObjectByUuid(uuid);

                if (!obj) continue;

            }



            userAnimActiveAnim = anim;



            const d = getUserAnimEffectiveDuration(anim);

            let start = clamp(Number(fromTime) || 0, 0, d);

            if (anim.options && anim.options.random) {

                start = Math.random() * d;

            }

            userAnimPlayTime = start;

            userAnimPlayDirection = 1;



            applyUserAnimationAtTime(anim, userAnimPlayTime);

            userAnimIsPlaying = true;

        }



        function pauseUserAnimation() {

            userAnimIsPlaying = false;



            if (userAnimPrevControlsEnabled !== null && controls) {

                controls.enabled = !!userAnimPrevControlsEnabled;

            }

            userAnimPrevControlsEnabled = null;



            if (userAnimAffectsCamera && scene && camera) {

                if (!userAnimCameraWasInScene && camera.parent === scene) {

                    scene.remove(camera);

                }

            }

            userAnimAffectsCamera = false;

            userAnimCameraWasInScene = false;

        }



        function setUserAnimationTime(t) {

            const anim = userAnimActiveAnim || getOrCreateAnimById(userAnimSelectedId);

            if (!anim) return;

            const d = getUserAnimEffectiveDuration(anim);

            const tt = clamp(Number(t) || 0, 0, d);

            userAnimPlayTime = tt;

            applyUserAnimationAtTime(anim, tt);

        }



        function getUserAnimExportClips() {

            const clips = [];

            for (const anim of userAnimations) {

                const clip = buildClipFromUserAnimation(anim);

                if (clip) clips.push(clip);

            }

            return clips;

        }



        // Génère des clips d'animation pour le flicker des point lights de type "fire"

        function getFireFlickerExportClips() {

            const clips = [];

            if (typeof pointLights === 'undefined' || !Array.isArray(pointLights)) return clips;



            for (const pl of pointLights) {

                if (!pl || !pl.userData) continue;

                if (pl.userData.type !== 'fire' || !pl.userData.flicker) continue;



                // Générer une animation de flicker d'environ 3 secondes en boucle

                const baseIntensity = (typeof pl.userData.baseIntensity === 'number') ? pl.userData.baseIntensity : pl.intensity;

                const duration = 3.0;

                const fps = 24;

                const numFrames = Math.floor(duration * fps);



                const times = [];

                const values = [];



                for (let i = 0; i <= numFrames; i++) {

                    const t = (i / fps);

                    times.push(t);

                    // Reproduire le même calcul de flicker que dans animate()

                    const n = (Math.sin(t * 17.3 + pl.position.x * 1.7) + Math.sin(t * 9.1 + pl.position.z * 1.3)) * 0.5;

                    const k = 0.85 + 0.25 * (0.5 + 0.5 * n);

                    values.push(Math.max(0, baseIntensity * k));

                }



                const track = new THREE.NumberKeyframeTrack(pl.uuid + '.intensity', times, values);

                const clipName = 'FireFlicker_' + pl.uuid.slice(0, 8);

                const clip = new THREE.AnimationClip(clipName, duration, [track]);

                clips.push(clip);

            }

            return clips;

        }



        function initAnimationsUI() {

            loadUserAnimationsFromStorage();



            const listView = document.getElementById('animListView');

            const detailView = document.getElementById('animDetailView');

            const listEl = document.getElementById('myAnimationsList');

            const btnCreateScene = document.getElementById('createSceneAnimBtn');

            const btnCreateSel = document.getElementById('createSelectionAnimBtn');

            const btnMy = document.getElementById('myAnimationsBtn');



            const backBtn = document.getElementById('animBackToListBtn');

            const nameInput = document.getElementById('animNameInput');

            const scopeSelect = document.getElementById('animScopeSelect');

            const targetSelect = document.getElementById('animTargetSelect');

            const durationInput = document.getElementById('animDurationInput');

            const loopCb = document.getElementById('animLoopInfinite');

            const boomCb = document.getElementById('animBoomerang');

            const randomCb = document.getElementById('animRandom');

            const speedRange = document.getElementById('animSpeed');

            const speedVal = document.getElementById('animSpeedValue');

            const triggerEvery = document.getElementById('animTriggerEvery');



            const playBtn = document.getElementById('animPlayBtn');

            const pauseBtn = document.getElementById('animPauseBtn');

            const stopBtn = document.getElementById('animStopBtn');



            const tLabel = document.getElementById('animTimeValue');

            const tSlider = document.getElementById('animTimeSlider');

            const tNumber = document.getElementById('animTimeNumber');

            const applyTimeBtn = document.getElementById('animApplyTimeBtn');



            const channelSel = document.getElementById('animChannelSelect');

            const valX = document.getElementById('animValX');

            const valY = document.getElementById('animValY');

            const valZ = document.getElementById('animValZ');

            const kfFromObjBtn = document.getElementById('animKeyframeFromObjectBtn');

            const kfApplyToObjBtn = document.getElementById('animKeyframeApplyToObjectBtn');

            const kfDelBtn = document.getElementById('animDeleteKeyframeBtn');

            const filmstrip = document.getElementById('animFilmstrip');



            const addTargetBtn = document.getElementById('animAddSelectedTargetBtn');

            const addCameraTargetBtn = document.getElementById('animAddCameraTargetBtn');

            const removeTargetBtn = document.getElementById('animRemoveTargetBtn');



            const saveBtn = document.getElementById('animSaveBtn');

            const deleteBtn = document.getElementById('animDeleteBtn');



            const addPointBtn = document.getElementById('animAddPointBtn');

            const clearPointsBtn = document.getElementById('animClearPointsBtn');

            const pointTimeInput = document.getElementById('animPointTime');

            const smoothnessRange = document.getElementById('animSmoothness');

            const easeCb = document.getElementById('animEaseInOut');

            const generatePathBtn = document.getElementById('animGeneratePathBtn');

            const pointsList = document.getElementById('animPointsList');



            // UI édition points

            const pointEditX = document.getElementById('animPointEditX');

            const pointEditY = document.getElementById('animPointEditY');

            const pointEditZ = document.getElementById('animPointEditZ');

            const pointApplyEditBtn = document.getElementById('animPointApplyEditBtn');

            const pointClearSelBtn = document.getElementById('animPointClearSelectionBtn');

            const pointAssignGroupBtn = document.getElementById('animPointAssignGroupBtn');



            // UI import/édition clip

            const importClipSelect = document.getElementById('animImportClipSelect');

            const importLoadBtn = document.getElementById('animImportLoadBtn');

            const importApplyBtn = document.getElementById('animImportApplyBtn');

            const importBonesList = document.getElementById('animImportBonesList');

            const importFramesList = document.getElementById('animImportFramesList');

            const importValX = document.getElementById('animImportValX');

            const importValY = document.getElementById('animImportValY');

            const importValZ = document.getElementById('animImportValZ');

            const importApplyFrameBtn = document.getElementById('animImportApplyFrameBtn');

            const importClearSelBtn = document.getElementById('animImportClearSelectionBtn');

            const importAssignGroupBtn = document.getElementById('animImportAssignGroupBtn');



            // Sélection points: { [uuid]: Set(indices) }

            const selectedPointIdxByUuid = new Map();

            const getSelectedPointSet = (uuid) => {

                if (!uuid) return new Set();

                if (!selectedPointIdxByUuid.has(uuid)) selectedPointIdxByUuid.set(uuid, new Set());

                return selectedPointIdxByUuid.get(uuid);

            };



            // Import/édition clip (état local)

            let importedEdit = null; // { clipIndex, clipName, fps, frames:[t], bones:[{uuid,name,group}], data:{uuid:{pos:[[x,y,z]...]}} , selectedBones:Set(uuid), selectedFrameIdx:number, root, srcClip, previewMixer, previewAction }



            const openList = () => {

                if (detailView) detailView.style.display = 'none';

                if (listView) listView.style.display = 'block';

                stopUserAnimationPlayback();

                userAnimSelectedId = null;

                refreshList();

            };



            const openDetail = (animId) => {

                if (!animId) return;

                userAnimSelectedId = animId;

                if (listView) listView.style.display = 'none';

                if (detailView) detailView.style.display = 'block';

                refreshDetail();

            };



            const getSelectedAnim = () => getOrCreateAnimById(userAnimSelectedId);



            const refreshTargetsSelect = (anim) => {

                if (!targetSelect) return;

                targetSelect.innerHTML = '';

                const uuids = (anim && anim.targetUuids) ? anim.targetUuids : [];

                for (const uuid of uuids) {

                    const opt = document.createElement('option');

                    opt.value = uuid;

                    opt.textContent = getObjectLabelByUuid(uuid);

                    targetSelect.appendChild(opt);

                }

            };



            const currentTargetUuid = () => {

                const anim = getSelectedAnim();

                if (!anim) return null;

                if (targetSelect && targetSelect.value) return targetSelect.value;

                return (anim.targetUuids && anim.targetUuids[0]) ? anim.targetUuids[0] : null;

            };



            const readCurrentTime = () => {

                if (tNumber && tNumber.value !== '') return Number(tNumber.value) || 0;

                if (tSlider && tSlider.value !== '') return Number(tSlider.value) || 0;

                return 0;

            };



            const setTimeUI = (t, duration) => {

                const d = Math.max(0.000001, Number(duration) || 0.000001);

                const tt = clamp(Number(t) || 0, 0, d);

                if (tLabel) tLabel.textContent = tt.toFixed(6);

                if (tSlider) { tSlider.max = String(d); tSlider.value = String(tt); }

                if (tNumber) { tNumber.value = String(tt); }

            };



            const getChannelKeyframeList = (anim, uuid, channel) => {

                ensureKeyframes(anim, uuid);

                if (channel === 'rotation') return anim.keyframes[uuid].rotation;

                if (channel === 'scale') return anim.keyframes[uuid].scale;

                if (channel === 'intensity') return anim.keyframes[uuid].intensity;

                return anim.keyframes[uuid].position;

            };



            const readKeyframeValueAtTime = (list, t) => {

                const time = Number(t) || 0;

                if (!list || list.length === 0) return null;

                let best = list[0];

                let bestDist = Math.abs((Number(best.t) || 0) - time);

                for (const k of list) {

                    const dist = Math.abs((Number(k.t) || 0) - time);

                    if (dist < bestDist) { best = k; bestDist = dist; }

                }

                return best;

            };



            const refreshFilmstrip = (anim) => {

                if (!filmstrip) return;

                filmstrip.innerHTML = '';

                const uuid = currentTargetUuid();

                const channel = channelSel ? channelSel.value : 'position';

                if (!anim || !uuid) return;



                const list = getChannelKeyframeList(anim, uuid, channel);

                const duration = Math.max(0.000001, Number(anim.options && anim.options.duration) || 0.000001);



                for (const k of list) {

                    const chip = document.createElement('button');

                    chip.type = 'button';

                    chip.className = 'object-item';

                    chip.style.display = 'inline-flex';

                    chip.style.alignItems = 'center';

                    chip.style.justifyContent = 'center';

                    chip.style.whiteSpace = 'nowrap';

                    chip.style.flex = '0 0 auto';

                    chip.textContent = (Number(k.t) || 0).toFixed(3) + 's';



                    chip.addEventListener('click', () => {

                        setTimeUI(Number(k.t) || 0, duration);

                        setUserAnimationTime(Number(k.t) || 0);

                        refreshDetail();

                    });



                    // Drag: modifie le temps du keyframe

                    let dragging = false;

                    let startX = 0;

                    let startT = 0;

                    chip.addEventListener('pointerdown', (e) => {

                        dragging = true;

                        startX = e.clientX;

                        startT = Number(k.t) || 0;

                        try { chip.setPointerCapture(e.pointerId); } catch (_) {}

                    });

                    chip.addEventListener('pointermove', (e) => {

                        if (!dragging) return;

                        const dx = e.clientX - startX;

                        // mapping simple: 200px ~ durée

                        const dt = (dx / 200) * duration;

                        const nt = clamp(startT + dt, 0, duration);

                        k.t = nt;

                        list.sort((a, b) => (a.t || 0) - (b.t || 0));

                        chip.textContent = (Number(k.t) || 0).toFixed(3) + 's';

                        setTimeUI(nt, duration);

                        setUserAnimationTime(nt);

                    });

                    chip.addEventListener('pointerup', () => {

                        dragging = false;

                    });

                    chip.addEventListener('pointercancel', () => {

                        dragging = false;

                    });



                    filmstrip.appendChild(chip);

                }

            };



            const refreshPointsList = (anim) => {

                if (!pointsList) return;

                pointsList.innerHTML = '';

                const uuid = currentTargetUuid();

                if (!anim || !uuid) return;

                const entry = anim.autoPointsByUuid && anim.autoPointsByUuid[uuid] ? anim.autoPointsByUuid[uuid] : null;

                const pts = entry && Array.isArray(entry.points) ? entry.points : [];



                const selSet = getSelectedPointSet(uuid);



                // Groupage simple: si p.g existe

                const hasGroups = pts.some((p) => p && p.g);

                const groups = new Map();

                if (hasGroups) {

                    for (let i = 0; i < pts.length; i++) {

                        const p = pts[i];

                        const g = (p && p.g) ? String(p.g) : '';

                        const key = g || '— Sans groupe —';

                        if (!groups.has(key)) groups.set(key, []);

                        groups.get(key).push(i);

                    }

                }



                const formatPointText = (p, idx) => {

                    return '#' + (idx + 1) + '  x:' + (Number(p.x) || 0).toFixed(3) + '  y:' + (Number(p.y) || 0).toFixed(3) + '  z:' + (Number(p.z) || 0).toFixed(3);

                };



                const applySelectionToInputs = () => {

                    if (!pointEditX || !pointEditY || !pointEditZ) return;

                    if (selSet.size === 0) {

                        pointEditX.value = '0';

                        pointEditY.value = '0';

                        pointEditZ.value = '0';

                        return;

                    }

                    const firstIdx = Array.from(selSet.values()).sort((a, b) => a - b)[0];

                    const p = pts[firstIdx];

                    if (!p) return;

                    pointEditX.value = String(Number(p.x) || 0);

                    pointEditY.value = String(Number(p.y) || 0);

                    pointEditZ.value = String(Number(p.z) || 0);

                };



                const setObjectToPointWorld = (idx) => {

                    const obj = findObjectByUuid(uuid);

                    const p = pts[idx];

                    if (!obj || !p) return;

                    obj.updateMatrixWorld(true);

                    if (obj.parent) obj.parent.updateMatrixWorld(true);

                    const pWorld = new THREE.Vector3(Number(p.x) || 0, Number(p.y) || 0, Number(p.z) || 0);

                    const pLocal = obj.parent ? obj.parent.worldToLocal(pWorld.clone()) : pWorld;

                    obj.position.copy(pLocal);

                };



                const toggleIndex = (idx, ev) => {

                    const i = Number(idx);

                    if (!isFinite(i)) return;



                    const sorted = Array.from(selSet.values()).sort((a, b) => a - b);

                    const last = sorted.length ? sorted[sorted.length - 1] : null;



                    const isMulti = !!(ev && (ev.ctrlKey || ev.metaKey));

                    const isRange = !!(ev && ev.shiftKey);



                    if (isRange && last !== null) {

                        const a = Math.min(last, i);

                        const b = Math.max(last, i);

                        for (let k = a; k <= b; k++) selSet.add(k);

                    } else if (isMulti) {

                        if (selSet.has(i)) selSet.delete(i);

                        else selSet.add(i);

                    } else {

                        selSet.clear();

                        selSet.add(i);

                    }

                    applySelectionToInputs();

                    refreshPointsList(anim);

                    setObjectToPointWorld(i);

                };



                const addPointLi = (idx) => {

                    const p = pts[idx];

                    if (!p) return;

                    const li = document.createElement('li');

                    li.className = 'object-item';

                    if (selSet.has(idx)) li.classList.add('point-selected');

                    li.dataset.pointIndex = String(idx);

                    li.textContent = formatPointText(p, idx);

                    li.addEventListener('click', (ev) => {

                        ev.preventDefault();

                        ev.stopPropagation();

                        toggleIndex(idx, ev);

                    });

                    pointsList.appendChild(li);

                };



                if (hasGroups) {

                    for (const [gName, indices] of groups.entries()) {

                        const header = document.createElement('li');

                        header.className = 'object-item group-header';

                        header.textContent = gName;

                        header.addEventListener('click', (ev) => {

                            ev.preventDefault();

                            ev.stopPropagation();

                            // Sélectionne tout le groupe

                            selSet.clear();

                            for (const idx of indices) selSet.add(idx);

                            applySelectionToInputs();

                            refreshPointsList(anim);

                        });

                        pointsList.appendChild(header);

                        for (const idx of indices) addPointLi(idx);

                    }

                } else {

                    for (let i = 0; i < pts.length; i++) addPointLi(i);

                }



                applySelectionToInputs();

            };



            const applyPointEditsToSelection = () => {

                const anim = getSelectedAnim();

                const uuid = currentTargetUuid();

                if (!anim || !uuid) return;

                const entry = anim.autoPointsByUuid && anim.autoPointsByUuid[uuid] ? anim.autoPointsByUuid[uuid] : null;

                const pts = entry && Array.isArray(entry.points) ? entry.points : [];

                if (!pts.length) return;



                const selSet = getSelectedPointSet(uuid);

                if (selSet.size === 0) return;



                const nx = Number(pointEditX && pointEditX.value);

                const ny = Number(pointEditY && pointEditY.value);

                const nz = Number(pointEditZ && pointEditZ.value);

                if (!isFinite(nx) || !isFinite(ny) || !isFinite(nz)) return;



                for (const idx of selSet.values()) {

                    const p = pts[idx];

                    if (!p) continue;

                    p.x = nx;

                    p.y = ny;

                    p.z = nz;

                }

                refreshDetail();

            };



            const clearPointSelection = () => {

                const uuid = currentTargetUuid();

                if (!uuid) return;

                const selSet = getSelectedPointSet(uuid);

                selSet.clear();

            };



            const assignPointGroupToSelection = () => {

                const anim = getSelectedAnim();

                const uuid = currentTargetUuid();

                if (!anim || !uuid) return;

                const entry = anim.autoPointsByUuid && anim.autoPointsByUuid[uuid] ? anim.autoPointsByUuid[uuid] : null;

                const pts = entry && Array.isArray(entry.points) ? entry.points : [];

                if (!pts.length) return;



                const selSet = getSelectedPointSet(uuid);

                if (selSet.size === 0) return;



                const g = prompt('Nom du groupe (ex: bras, jambe, head…) :', 'groupe');

                if (!g) return;

                for (const idx of selSet.values()) {

                    const p = pts[idx];

                    if (!p) continue;

                    p.g = String(g);

                }

                refreshDetail();

            };



            const refreshDetail = () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                if (nameInput) nameInput.value = anim.name || 'Animation';

                if (scopeSelect) scopeSelect.value = anim.scope || 'scene';

                refreshTargetsSelect(anim);



                const dur = Math.max(0.000001, Number(anim.options && anim.options.duration) || 3);

                if (durationInput) durationInput.value = String(dur);

                if (loopCb) loopCb.checked = !!(anim.options && anim.options.loopInfinite);

                if (boomCb) boomCb.checked = !!(anim.options && anim.options.boomerang);

                if (randomCb) randomCb.checked = !!(anim.options && anim.options.random);

                if (speedRange && speedVal) {

                    const sp = clamp(Number(anim.options && anim.options.speed) || 1, 0.05, 4);

                    speedRange.value = String(sp);

                    speedVal.textContent = sp.toFixed(2);

                }

                if (triggerEvery) triggerEvery.value = String(Number(anim.options && anim.options.triggerEverySec) || 0);



                const t = readCurrentTime();

                setTimeUI(t, dur);



                // valeurs: keyframe la plus proche

                const uuid = currentTargetUuid();

                const channel = channelSel ? channelSel.value : 'position';

                if (uuid) {

                    const list = getChannelKeyframeList(anim, uuid, channel);

                    const k = readKeyframeValueAtTime(list, t);

                    if (k && Array.isArray(k.v)) {

                        if (channel === 'rotation') {

                            const eDeg = quatToEulerDegArray(k.v);

                            if (valX) valX.value = String(eDeg[0].toFixed(6));

                            if (valY) valY.value = String(eDeg[1].toFixed(6));

                            if (valZ) valZ.value = String(eDeg[2].toFixed(6));

                        } else if (channel === 'intensity') {

                            // Pour intensity, une seule valeur

                            if (valX) valX.value = String((Number(k.v[0]) || 1).toFixed(6));

                            if (valY) valY.value = '0';

                            if (valZ) valZ.value = '0';

                        } else {

                            if (valX) valX.value = String((Number(k.v[0]) || 0).toFixed(6));

                            if (valY) valY.value = String((Number(k.v[1]) || 0).toFixed(6));

                            if (valZ) valZ.value = String((Number(k.v[2]) || 0).toFixed(6));

                        }

                    }

                }



                refreshFilmstrip(anim);

                refreshPointsList(anim);



                // Remplir la liste des clips importés pour l'éditeur frame par frame

                if (importClipSelect) {

                    importClipSelect.innerHTML = '';

                    if (Array.isArray(importedClips) && importedClips.length > 0) {

                        for (let i = 0; i < importedClips.length; i++) {

                            const c = importedClips[i];

                            const opt = document.createElement('option');

                            opt.value = String(i);

                            opt.textContent = (c && c.name) ? c.name : ('Clip #' + (i + 1));

                            importClipSelect.appendChild(opt);

                        }

                    } else {

                        const opt = document.createElement('option');

                        opt.value = '';

                        opt.textContent = '— Aucun clip importé —';

                        importClipSelect.appendChild(opt);

                    }

                }

            };



            // Permet de synchroniser l'"Élément animé" depuis d'autres outils (ex: clic sur un bone en mode rig)

            // Ajoute la cible à l'animation courante si nécessaire et la sélectionne dans le <select>.

            window.__animSelectTargetUuid = (uuid) => {

                const anim = getSelectedAnim();

                if (!anim || !uuid) return;

                if (!anim.targetUuids) anim.targetUuids = [];

                if (!anim.targetUuids.includes(uuid)) anim.targetUuids.push(uuid);

                refreshTargetsSelect(anim);

                if (targetSelect) targetSelect.value = uuid;

                refreshDetail();

            };



            const refreshList = () => {

                if (!listEl) return;

                listEl.innerHTML = '';



                // Animations utilisateur

                for (const anim of userAnimations) {

                    const li = document.createElement('li');

                    li.className = 'object-item';

                    li.textContent = (anim.name || 'Animation') + ' — ' + (anim.scope === 'selection' ? 'sélection' : 'scène');

                    li.addEventListener('click', () => openDetail(anim.id));

                    listEl.appendChild(li);

                }



                // Clips importés

                if (Array.isArray(importedClips) && importedClips.length > 0) {

                    const sep = document.createElement('li');

                    sep.className = 'object-item';

                    sep.textContent = '— Clips importés —';

                    sep.style.opacity = '0.8';

                    sep.style.cursor = 'default';

                    listEl.appendChild(sep);

                    for (let i = 0; i < importedClips.length; i++) {

                        const c = importedClips[i];

                        const li = document.createElement('li');

                        li.className = 'object-item';

                        li.textContent = (c && c.name ? c.name : 'Clip') + ' (import)';

                        li.addEventListener('click', () => {

                            // Re-ouvre simplement le panel rig (où les contrôles existent déjà) via le popup

                            const openRigBtn = document.querySelector('[data-open-panel="rig-panel"],[data-popup-panel="rig-panel"],#rig-btn');

                            if (openRigBtn) openRigBtn.click();

                        });

                        listEl.appendChild(li);

                    }

                }

            };



            if (btnCreateScene) btnCreateScene.addEventListener('click', () => {

                const anim = createUserAnimation('scene', []);

                openDetail(anim.id);

            });



            if (btnCreateSel) btnCreateSel.addEventListener('click', () => {

                const sel = getEffectiveSelection();

                const uuids = (sel || []).map((o) => o && o.uuid).filter(Boolean);

                const anim = createUserAnimation('selection', uuids);

                openDetail(anim.id);

            });



            if (btnMy) btnMy.addEventListener('click', () => {

                openList();

            });



            if (backBtn) backBtn.addEventListener('click', () => openList());



            if (nameInput) nameInput.addEventListener('input', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                anim.name = nameInput.value || 'Animation';

            });



            if (scopeSelect) scopeSelect.addEventListener('change', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                anim.scope = scopeSelect.value || 'scene';

            });



            if (durationInput) durationInput.addEventListener('input', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                const d = Math.max(0.000001, Number(durationInput.value) || 0.000001);

                anim.options.duration = d;

                refreshDetail();

            });



            if (loopCb) loopCb.addEventListener('change', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                anim.options.loopInfinite = !!loopCb.checked;

            });

            if (boomCb) boomCb.addEventListener('change', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                anim.options.boomerang = !!boomCb.checked;

            });

            if (randomCb) randomCb.addEventListener('change', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                anim.options.random = !!randomCb.checked;

            });

            if (speedRange && speedVal) speedRange.addEventListener('input', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                const sp = clamp(Number(speedRange.value) || 1, 0.05, 4);

                anim.options.speed = sp;

                speedVal.textContent = sp.toFixed(2);

            });

            if (triggerEvery) triggerEvery.addEventListener('input', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                anim.options.triggerEverySec = Math.max(0, Number(triggerEvery.value) || 0);

            });



            if (tSlider) tSlider.addEventListener('input', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                const d = Math.max(0.000001, Number(anim.options && anim.options.duration) || 0.000001);

                const t = clamp(Number(tSlider.value) || 0, 0, d);

                if (tNumber) tNumber.value = String(t);

                if (tLabel) tLabel.textContent = t.toFixed(6);

                setUserAnimationTime(t);

                refreshDetail();

            });



            if (applyTimeBtn) applyTimeBtn.addEventListener('click', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                const d = Math.max(0.000001, Number(anim.options && anim.options.duration) || 0.000001);

                const t = clamp(Number(tNumber.value) || 0, 0, d);

                setTimeUI(t, d);

                setUserAnimationTime(t);

                refreshDetail();

            });



            if (channelSel) channelSel.addEventListener('change', () => {

                refreshDetail();

            });



            if (targetSelect) targetSelect.addEventListener('change', () => {

                refreshDetail();

            });



            if (addTargetBtn) addTargetBtn.addEventListener('click', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                const sel = getEffectiveSelection();

                if (!sel || sel.length === 0) return;

                for (const obj of sel) {

                    if (!obj) continue;

                    if (!anim.targetUuids.includes(obj.uuid)) anim.targetUuids.push(obj.uuid);

                }

                refreshDetail();

            });



            if (addCameraTargetBtn) addCameraTargetBtn.addEventListener('click', () => {

                const anim = getSelectedAnim();

                if (!anim || !camera) return;

                if (!anim.targetUuids.includes(camera.uuid)) anim.targetUuids.push(camera.uuid);

                refreshDetail();

            });



            // Bouton ajouter une light

            const addLightTargetBtn = document.getElementById('animAddLightTargetBtn');

            if (addLightTargetBtn) addLightTargetBtn.addEventListener('click', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                if (typeof pointLights === 'undefined' || !Array.isArray(pointLights) || pointLights.length === 0) {

                    alert('Aucune point light n\'existe. Créez-en une dans le panneau Lumières.');

                    return;

                }

                // Ajouter la light sélectionnée ou la première

                const plToAdd = selectedPointLight || pointLights[0];

                if (plToAdd && !anim.targetUuids.includes(plToAdd.uuid)) {

                    anim.targetUuids.push(plToAdd.uuid);

                }

                refreshDetail();

            });



            // Bouton ajouter un bone

            const addBoneTargetBtn = document.getElementById('animAddBoneTargetBtn');

            if (addBoneTargetBtn) addBoneTargetBtn.addEventListener('click', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                // Chercher les bones dans la scène/sélection

                const bones = [];

                const searchRoot = selectedObject || importedTargetRoot || scene;

                if (searchRoot) {

                    searchRoot.traverse((o) => {

                        if (o && (o.isBone || o.type === 'Bone')) bones.push(o);

                    });

                }

                if (bones.length === 0) {

                    alert('Aucun bone trouvé dans la scène ou sélection.');

                    return;

                }

                // Ajouter tous les bones ou le premier

                for (const bone of bones) {

                    if (!anim.targetUuids.includes(bone.uuid)) {

                        anim.targetUuids.push(bone.uuid);

                    }

                }

                refreshDetail();

            });



            if (removeTargetBtn) removeTargetBtn.addEventListener('click', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                const uuid = currentTargetUuid();

                if (!uuid) return;

                anim.targetUuids = (anim.targetUuids || []).filter((u) => u !== uuid);

                delete anim.keyframes[uuid];

                delete anim.autoPointsByUuid[uuid];

                refreshDetail();

            });



            if (kfFromObjBtn) kfFromObjBtn.addEventListener('click', () => {

                const anim = getSelectedAnim();

                const uuid = currentTargetUuid();

                if (!anim || !uuid) return;

                const obj = findObjectByUuid(uuid);

                if (!obj) return;

                const t = readCurrentTime();

                const channel = channelSel ? channelSel.value : 'position';

                ensureKeyframes(anim, uuid);

                const list = getChannelKeyframeList(anim, uuid, channel);



                if (channel === 'position') {

                    upsertKeyframe(list, t, [obj.position.x, obj.position.y, obj.position.z]);

                } else if (channel === 'scale') {

                    upsertKeyframe(list, t, [obj.scale.x, obj.scale.y, obj.scale.z]);

                } else if (channel === 'intensity') {

                    // Pour les lights: intensité

                    const intensity = (typeof obj.intensity === 'number') ? obj.intensity : 1;

                    upsertKeyframe(list, t, [intensity]);

                } else {

                    upsertKeyframe(list, t, [obj.quaternion.x, obj.quaternion.y, obj.quaternion.z, obj.quaternion.w]);

                }

                refreshDetail();

            });



            if (kfApplyToObjBtn) kfApplyToObjBtn.addEventListener('click', () => {

                const anim = getSelectedAnim();

                const uuid = currentTargetUuid();

                if (!anim || !uuid) return;

                const obj = findObjectByUuid(uuid);

                if (!obj) return;

                const t = readCurrentTime();

                const channel = channelSel ? channelSel.value : 'position';

                const list = getChannelKeyframeList(anim, uuid, channel);

                const k = readKeyframeValueAtTime(list, t);

                if (!k || !Array.isArray(k.v)) return;



                if (channel === 'position') {

                    obj.position.set(Number(k.v[0]) || 0, Number(k.v[1]) || 0, Number(k.v[2]) || 0);

                } else if (channel === 'scale') {

                    obj.scale.set(Number(k.v[0]) || 1, Number(k.v[1]) || 1, Number(k.v[2]) || 1);

                } else if (channel === 'intensity') {

                    if (typeof obj.intensity !== 'undefined') {

                        obj.intensity = Number(k.v[0]) || 1;

                    }

                } else {

                    obj.quaternion.set(Number(k.v[0]) || 0, Number(k.v[1]) || 0, Number(k.v[2]) || 0, Number(k.v[3]) || 1);

                }

            });



            if (kfDelBtn) kfDelBtn.addEventListener('click', () => {

                const anim = getSelectedAnim();

                const uuid = currentTargetUuid();

                if (!anim || !uuid) return;

                const t = readCurrentTime();

                const channel = channelSel ? channelSel.value : 'position';

                const list = getChannelKeyframeList(anim, uuid, channel);

                deleteKeyframeNear(list, t);

                refreshDetail();

            });



            const applyValueInputsToKeyframe = () => {

                const anim = getSelectedAnim();

                const uuid = currentTargetUuid();

                if (!anim || !uuid) return;

                const t = readCurrentTime();

                const channel = channelSel ? channelSel.value : 'position';

                const list = getChannelKeyframeList(anim, uuid, channel);

                if (channel === 'rotation') {

                    const q = eulerDegToQuatArray(valX.value, valY.value, valZ.value);

                    upsertKeyframe(list, t, q);

                } else if (channel === 'intensity') {

                    // Pour intensity, une seule valeur

                    upsertKeyframe(list, t, [Number(valX.value) || 1]);

                } else {

                    upsertKeyframe(list, t, [Number(valX.value) || 0, Number(valY.value) || 0, Number(valZ.value) || 0]);

                }

                refreshDetail();

            };

            if (valX) valX.addEventListener('change', applyValueInputsToKeyframe);

            if (valY) valY.addEventListener('change', applyValueInputsToKeyframe);

            if (valZ) valZ.addEventListener('change', applyValueInputsToKeyframe);



            if (playBtn) playBtn.addEventListener('click', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                const t = readCurrentTime();

                playUserAnimation(anim, t);

            });

            if (pauseBtn) pauseBtn.addEventListener('click', () => pauseUserAnimation());

            if (stopBtn) stopBtn.addEventListener('click', () => {

                stopUserAnimationPlayback();

                setUserAnimationTime(0);

                refreshDetail();

            });



            if (saveBtn) saveBtn.addEventListener('click', () => {

                saveUserAnimationsToStorage();

                refreshList();

            });



            if (deleteBtn) deleteBtn.addEventListener('click', () => {

                const anim = getSelectedAnim();

                if (!anim) return;

                userAnimations = userAnimations.filter((a) => a.id !== anim.id);

                saveUserAnimationsToStorage();

                openList();

            });



            if (addPointBtn) addPointBtn.addEventListener('click', () => {

                const anim = getSelectedAnim();

                const uuid = currentTargetUuid();

                if (!anim || !uuid) return;

                const obj = findObjectByUuid(uuid);

                if (!obj) return;

                anim.autoPointsByUuid = anim.autoPointsByUuid || {};

                if (!anim.autoPointsByUuid[uuid]) anim.autoPointsByUuid[uuid] = { points: [] };



                // On stocke des coordonnées MONDE (réelles à l'écran), puis on reconvertit en local lors de la génération.

                obj.updateMatrixWorld(true);

                const wp = new THREE.Vector3();

                obj.getWorldPosition(wp);

                anim.autoPointsByUuid[uuid].points.push({ x: wp.x, y: wp.y, z: wp.z });

                refreshDetail();

            });



            if (pointApplyEditBtn) pointApplyEditBtn.addEventListener('click', applyPointEditsToSelection);

            if (pointClearSelBtn) pointClearSelBtn.addEventListener('click', () => {

                clearPointSelection();

                refreshDetail();

            });

            if (pointAssignGroupBtn) pointAssignGroupBtn.addEventListener('click', assignPointGroupToSelection);



            if (clearPointsBtn) clearPointsBtn.addEventListener('click', () => {

                const anim = getSelectedAnim();

                const uuid = currentTargetUuid();

                if (!anim || !uuid) return;

                if (anim.autoPointsByUuid && anim.autoPointsByUuid[uuid]) {

                    anim.autoPointsByUuid[uuid].points = [];

                }

                refreshDetail();

            });



            if (generatePathBtn) generatePathBtn.addEventListener('click', () => {

                const anim = getSelectedAnim();

                const uuid = currentTargetUuid();

                if (!anim || !uuid) return;

                const entry = anim.autoPointsByUuid && anim.autoPointsByUuid[uuid] ? anim.autoPointsByUuid[uuid] : null;

                const pts = entry && Array.isArray(entry.points) ? entry.points : [];

                if (pts.length < 2) return;



                const targetObj = findObjectByUuid(uuid);

                if (!targetObj) return;

                targetObj.updateMatrixWorld(true);

                if (targetObj.parent) targetObj.parent.updateMatrixWorld(true);



                const perPoint = Math.max(0.000001, Number(pointTimeInput && pointTimeInput.value) || 1);

                const smoothness = clamp(Number(smoothnessRange && smoothnessRange.value) || 20, 5, 60);



                // Points (Vector3)

                const vecPts = pts.map((p) => new THREE.Vector3(Number(p.x) || 0, Number(p.y) || 0, Number(p.z) || 0));

                if (anim.options && anim.options.random) {

                    // shuffle léger

                    for (let i = vecPts.length - 1; i > 0; i--) {

                        const j = Math.floor(Math.random() * (i + 1));

                        const tmp = vecPts[i]; vecPts[i] = vecPts[j]; vecPts[j] = tmp;

                    }

                }



                const curve = new THREE.CatmullRomCurve3(vecPts, false, 'catmullrom', 0.5);

                const segments = Math.max(1, vecPts.length - 1);

                const samples = segments * smoothness;

                const totalDuration = perPoint * (vecPts.length - 1);

                anim.options.duration = Math.max(anim.options.duration || 0, totalDuration);



                const useEase = !!(easeCb && easeCb.checked);

                const easeInOut = (u) => {

                    const x = clamp(u, 0, 1);

                    return x * x * (3 - 2 * x);

                };



                ensureKeyframes(anim, uuid);

                anim.keyframes[uuid].position = [];

                for (let i = 0; i <= samples; i++) {

                    const u = i / samples;

                    const up = useEase ? easeInOut(u) : u;

                    const pWorld = curve.getPoint(up);

                    // Conversion monde -> local pour que ça marche aussi sur les bones (position locale relative au parent)

                    const p = (targetObj.parent)

                        ? targetObj.parent.worldToLocal(pWorld.clone())

                        : pWorld;

                    const t = u * totalDuration;

                    anim.keyframes[uuid].position.push({ t, v: [p.x, p.y, p.z] });

                }

                refreshDetail();

            });



            // ===== Import / édition frame par frame (squelette) =====

            const collectBones = (root) => {

                const bones = [];

                if (!root) return bones;

                root.traverse((o) => {

                    if (o && (o.isBone || o.type === 'Bone')) bones.push(o);

                });

                return bones;

            };



            const clearImportedEditUI = (resetInputs = false) => {

                if (importBonesList) importBonesList.innerHTML = '';

                if (importFramesList) importFramesList.innerHTML = '';

                if (resetInputs) {

                    if (importValX) importValX.value = '0';

                    if (importValY) importValY.value = '0';

                    if (importValZ) importValZ.value = '0';

                }

            };



            let importAutoApplyTimer = null;

            const scheduleImportAutoApply = () => {

                if (!importedEdit) return;

                if (importAutoApplyTimer) clearTimeout(importAutoApplyTimer);

                importAutoApplyTimer = setTimeout(() => {

                    try { applyImportFrameEdit(); } catch (_) {}

                }, 120);

            };



            const refreshImportedEditUI = () => {

                clearImportedEditUI(false);

                if (!importedEdit) {

                    clearImportedEditUI(true);

                    return;

                }



                const previewCurrentFrame = () => {

                    if (!importedEdit) return;

                    const root = importedEdit.root;

                    const clip = importedEdit.srcClip;

                    if (!root || !clip) return;

                    const fi = Number(importedEdit.selectedFrameIdx);

                    if (!isFinite(fi) || fi < 0 || fi >= importedEdit.frames.length) return;



                    // Stop lecture importée en cours pour éviter les conflits de pose

                    importedIsPlaying = false;



                    // Initialiser mixer/action de preview une fois

                    if (!importedEdit.previewMixer || importedEdit.previewRoot !== root) {

                        try {

                            if (importedEdit.previewAction) importedEdit.previewAction.stop();

                        } catch (_) {}

                        importedEdit.previewMixer = new THREE.AnimationMixer(root);

                        importedEdit.previewRoot = root;

                        importedEdit.previewAction = importedEdit.previewMixer.clipAction(clip);

                        importedEdit.previewAction.play();

                        importedEdit.previewAction.paused = true;

                    }



                    const t = Number(importedEdit.frames[fi]) || 0;

                    importedEdit.previewAction.time = t;

                    importedEdit.previewMixer.update(0);



                    // Appliquer les positions éditées sur les bones sélectionnés (sur ce frame)

                    if (importedEdit.selectedBones && importedEdit.selectedBones.size) {

                        for (const bu of importedEdit.selectedBones.values()) {

                            const series = importedEdit.data[bu] ? importedEdit.data[bu].pos : null;

                            if (!series || !series[fi]) continue;

                            const p = series[fi];

                            const bone = findObjectByUuid(bu);

                            if (!bone) continue;

                            bone.position.set(Number(p[0]) || 0, Number(p[1]) || 0, Number(p[2]) || 0);

                        }

                    }

                    try { root.updateMatrixWorld(true); } catch (_) {}

                };



                // Bones list

                if (importBonesList) {

                    for (const b of importedEdit.bones) {

                        const li = document.createElement('li');

                        li.className = 'object-item';

                        if (importedEdit.selectedBones && importedEdit.selectedBones.has(b.uuid)) li.classList.add('point-selected');

                        li.textContent = (b.group ? '[' + b.group + '] ' : '') + (b.name || b.uuid);

                        li.addEventListener('click', (ev) => {

                            ev.preventDefault();

                            ev.stopPropagation();

                            const isMulti = !!(ev.ctrlKey || ev.metaKey);

                            if (!isMulti) importedEdit.selectedBones.clear();

                            if (importedEdit.selectedBones.has(b.uuid)) importedEdit.selectedBones.delete(b.uuid);

                            else importedEdit.selectedBones.add(b.uuid);

                            refreshImportedEditUI();

                        });

                        importBonesList.appendChild(li);

                    }

                }



                // Frames list

                if (importFramesList) {

                    for (let i = 0; i < importedEdit.frames.length; i++) {

                        const t = importedEdit.frames[i];

                        const li = document.createElement('li');

                        li.className = 'object-item';

                        if (i === importedEdit.selectedFrameIdx) li.classList.add('point-selected');

                        li.textContent = 't=' + Number(t).toFixed(3) + 's';

                        li.addEventListener('click', () => {

                            importedEdit.selectedFrameIdx = i;

                            refreshImportedEditUI();

                            previewCurrentFrame();

                        });

                        importFramesList.appendChild(li);

                    }

                }



                // Mettre XYZ depuis la sélection (1er bone sélectionné + frame courante)

                const fi = Number(importedEdit.selectedFrameIdx);

                const firstBoneUuid = (importedEdit.selectedBones && importedEdit.selectedBones.size)

                    ? Array.from(importedEdit.selectedBones.values())[0]

                    : null;

                if (firstBoneUuid && isFinite(fi) && fi >= 0 && fi < importedEdit.frames.length) {

                    const series = importedEdit.data[firstBoneUuid] ? importedEdit.data[firstBoneUuid].pos : null;

                    const p = (series && series[fi]) ? series[fi] : null;

                    if (p) {

                        if (importValX) importValX.value = String(Number(p[0]) || 0);

                        if (importValY) importValY.value = String(Number(p[1]) || 0);

                        if (importValZ) importValZ.value = String(Number(p[2]) || 0);

                    } else {

                        if (importValX) importValX.value = '0';

                        if (importValY) importValY.value = '0';

                        if (importValZ) importValZ.value = '0';

                    }

                } else {

                    if (importValX) importValX.value = '0';

                    if (importValY) importValY.value = '0';

                    if (importValZ) importValZ.value = '0';

                }



                // Preview immédiat (quand on ouvre / change sélection)

                previewCurrentFrame();

            };



            const loadImportedClipForEdit = () => {

                if (!importClipSelect) return;

                const idx = Number(importClipSelect.value);

                if (!isFinite(idx) || !Array.isArray(importedClips) || !importedClips[idx]) return;

                const clip = importedClips[idx];



                // Root: priorité à l'objet importé, sinon scène

                const root = importedTargetRoot || (selectedObject || scene);

                const bones = collectBones(root);

                const fps = 24;

                const duration = Math.max(0.000001, Number(clip.duration) || 0.000001);

                const frameCount = Math.max(1, Math.floor(duration * fps));

                const frames = [];

                for (let i = 0; i <= frameCount; i++) frames.push((i / frameCount) * duration);



                // Sampling via AnimationMixer (clip importé)

                const mixer = new THREE.AnimationMixer(root);

                const action = mixer.clipAction(clip);

                action.play();

                action.paused = true;



                const data = {};

                for (const b of bones) {

                    data[b.uuid] = { pos: [] };

                }



                for (let fi = 0; fi < frames.length; fi++) {

                    const t = frames[fi];

                    action.time = t;

                    mixer.update(0);

                    for (const b of bones) {

                        data[b.uuid].pos.push([b.position.x, b.position.y, b.position.z]);

                    }

                }



                try { action.stop(); } catch (_) {}

                try { mixer.uncacheAction(clip, root); } catch (_) {}



                importedEdit = {

                    clipIndex: idx,

                    clipName: clip.name || ('Clip #' + (idx + 1)),

                    fps,

                    frames,

                    bones: bones.map((b) => ({ uuid: b.uuid, name: b.name || ('Bone_' + b.uuid.slice(0, 6)), group: '' })),

                    data,

                    selectedBones: new Set(bones.length ? [bones[0].uuid] : []),

                    selectedFrameIdx: 0,

                    root,

                    srcClip: clip,

                    previewMixer: null,

                    previewAction: null,

                    previewRoot: null,

                };



                // Pré-remplir XYZ

                if (importedEdit.selectedBones.size) {

                    const bu = Array.from(importedEdit.selectedBones.values())[0];

                    const p = importedEdit.data[bu] && importedEdit.data[bu].pos[0] ? importedEdit.data[bu].pos[0] : [0, 0, 0];

                    if (importValX) importValX.value = String(Number(p[0]) || 0);

                    if (importValY) importValY.value = String(Number(p[1]) || 0);

                    if (importValZ) importValZ.value = String(Number(p[2]) || 0);

                }



                refreshImportedEditUI();

            };



            const applyImportFrameEdit = () => {

                if (!importedEdit) return;

                if (!importedEdit.selectedBones || importedEdit.selectedBones.size === 0) return;

                const fi = Number(importedEdit.selectedFrameIdx);

                if (!isFinite(fi) || fi < 0 || fi >= importedEdit.frames.length) return;

                const nx = Number(importValX && importValX.value);

                const ny = Number(importValY && importValY.value);

                const nz = Number(importValZ && importValZ.value);

                if (!isFinite(nx) || !isFinite(ny) || !isFinite(nz)) return;

                for (const bu of importedEdit.selectedBones.values()) {

                    if (!importedEdit.data[bu] || !importedEdit.data[bu].pos[fi]) continue;

                    importedEdit.data[bu].pos[fi] = [nx, ny, nz];

                }

                refreshImportedEditUI();

            };



            const clearImportSelection = () => {

                if (!importedEdit) return;

                importedEdit.selectedBones.clear();

                importedEdit.selectedFrameIdx = 0;

            };



            const assignImportGroup = () => {

                if (!importedEdit) return;

                if (!importedEdit.selectedBones || importedEdit.selectedBones.size === 0) return;

                const g = prompt('Nom du groupe (ex: bras, jambe, head…) :', 'groupe');

                if (!g) return;

                for (const b of importedEdit.bones) {

                    if (importedEdit.selectedBones.has(b.uuid)) b.group = String(g);

                }

                refreshImportedEditUI();

            };



            const applyImportedEditToClip = () => {

                if (!importedEdit) return;

                if (!Array.isArray(importedClips) || !importedClips[importedEdit.clipIndex]) return;

                const srcClip = importedClips[importedEdit.clipIndex];



                // IMPORTANT: on préserve les tracks d'origine (rotations/quaternions etc)

                // et on ne remplace QUE les tracks .position des bones édités.

                const nextTracks = [];

                const times = importedEdit.frames.slice();



                // Map boneName -> uuid (pour associer les tracks)

                const boneNameToUuid = new Map();

                for (const b of importedEdit.bones) {

                    if (b && b.name) boneNameToUuid.set(String(b.name), b.uuid);

                }



                // Helper: détecte si un track est un .position d'un bone qu'on a échantillonné

                const parseTrack = (trackName) => {

                    try {

                        if (THREE.PropertyBinding && typeof THREE.PropertyBinding.parseTrackName === 'function') {

                            return THREE.PropertyBinding.parseTrackName(trackName);

                        }

                    } catch (_) {}

                    // fallback ultra simple

                    const s = String(trackName || '');

                    const lastDot = s.lastIndexOf('.');

                    if (lastDot < 0) return { nodeName: s, propertyName: '' };

                    return { nodeName: s.slice(0, lastDot), propertyName: s.slice(lastDot + 1) };

                };



                // Pré-calc des valeurs pos par bone uuid

                const posValuesByUuid = new Map();

                for (const b of importedEdit.bones) {

                    const series = importedEdit.data[b.uuid] ? importedEdit.data[b.uuid].pos : null;

                    if (!series || series.length !== times.length) continue;

                    const values = [];

                    for (let i = 0; i < series.length; i++) {

                        const p = series[i];

                        values.push(Number(p[0]) || 0, Number(p[1]) || 0, Number(p[2]) || 0);

                    }

                    posValuesByUuid.set(b.uuid, values);

                }



                const replacedUuids = new Set();

                const srcTracks = Array.isArray(srcClip.tracks) ? srcClip.tracks : [];

                for (const tr of srcTracks) {

                    if (!tr || !tr.name) continue;

                    const info = parseTrack(tr.name);

                    const nodeName = info && info.nodeName ? String(info.nodeName) : '';

                    const prop = info && info.propertyName ? String(info.propertyName) : '';



                    if (prop === 'position' && boneNameToUuid.has(nodeName)) {

                        const bu = boneNameToUuid.get(nodeName);

                        if (posValuesByUuid.has(bu)) {

                            const values = posValuesByUuid.get(bu);

                            nextTracks.push(new THREE.VectorKeyframeTrack(tr.name, times, values));

                            replacedUuids.add(bu);

                            continue;

                        }

                    }

                    // Par défaut on conserve la track d'origine

                    nextTracks.push(tr);

                }



                // Ajouter des tracks position manquantes (si on n'a pas trouvé de .position existant)

                for (const b of importedEdit.bones) {

                    if (!b || replacedUuids.has(b.uuid)) continue;

                    if (!posValuesByUuid.has(b.uuid)) continue;

                    // Essaye de dériver un nom depuis une track existante de ce bone

                    let inferred = null;

                    for (const tr of srcTracks) {

                        const info = parseTrack(tr && tr.name);

                        if (info && String(info.nodeName) === String(b.name)) {

                            const tn = String(tr.name);

                            const dot = tn.lastIndexOf('.');

                            if (dot >= 0) inferred = tn.slice(0, dot) + '.position';

                            break;

                        }

                    }

                    const trackName = inferred || (String(b.name || b.uuid) + '.position');

                    nextTracks.push(new THREE.VectorKeyframeTrack(trackName, times, posValuesByUuid.get(b.uuid)));

                }



                const newClip = new THREE.AnimationClip(srcClip.name || importedEdit.clipName, srcClip.duration || -1, nextTracks);

                importedClips[importedEdit.clipIndex] = newClip;

                alert('Clip modifié: il remplacera l\'original à l\'export GLB.');

            };



            if (importLoadBtn) importLoadBtn.addEventListener('click', loadImportedClipForEdit);

            if (importApplyFrameBtn) importApplyFrameBtn.addEventListener('click', applyImportFrameEdit);

            if (importValX) importValX.addEventListener('input', scheduleImportAutoApply);

            if (importValY) importValY.addEventListener('input', scheduleImportAutoApply);

            if (importValZ) importValZ.addEventListener('input', scheduleImportAutoApply);

            if (importClearSelBtn) importClearSelBtn.addEventListener('click', () => {

                clearImportSelection();

                refreshImportedEditUI();

            });

            if (importAssignGroupBtn) importAssignGroupBtn.addEventListener('click', assignImportGroup);

            if (importApplyBtn) importApplyBtn.addEventListener('click', applyImportedEditToClip);



            // initial

            openList();

            refreshList();

        }



        function initSidebarPopupUI() {

            const popupHost = document.getElementById('sidebarPopupHost');

            const popupTitle = document.getElementById('sidebarPopupTitle');

            const popupBody = document.getElementById('sidebarPopupBody');

            const closeBtn = document.getElementById('sidebarPopupCloseBtn');

            const panelStore = document.getElementById('panelStore');



            if (!popupHost || !popupTitle || !popupBody || !panelStore) return;



            let activePanelEl = null;



            // Détecter si on est sur mobile (écran <= 900px)

            const isMobile = () => window.innerWidth <= 900;



            const storePanel = (panelEl) => {

                if (!panelEl) return;

                panelEl.classList.remove('popup-panel');

                panelEl.style.display = 'none';

                panelStore.appendChild(panelEl);

            };



            const closePopup = () => {

                if (activePanelEl) {

                    storePanel(activePanelEl);

                    activePanelEl = null;

                }

                popupHost.classList.add('hidden');

                popupTitle.textContent = 'Paramètres';

                popupBody.innerHTML = '';

            };



            // Ouvrir dans le modal fullpage (pour mobile ou account/admin)

            const openInFullModal = (content, title) => {

                const modal = document.getElementById('pp3FullPageModal');

                const modalBody = document.getElementById('pp3FullModalBody');

                if (!modal || !modalBody) return false;



                // Fermer le popup sidebar si ouvert

                closePopup();



                // Nettoyer le modal body

                while (modalBody.firstChild) {

                    const child = modalBody.firstChild;

                    if (child.id && document.getElementById(child.id)) {

                        child.style.display = 'none';

                        child.classList.remove('popup-panel');

                        panelStore.appendChild(child);

                    } else {

                        modalBody.removeChild(child);

                    }

                }



                // Ajouter un titre si fourni

                if (title) {

                    const titleEl = document.createElement('h3');

                    titleEl.textContent = title;

                    titleEl.style.cssText = 'margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.2);';

                    modalBody.appendChild(titleEl);

                }



                // Ajouter le contenu

                if (typeof content === 'string') {

                    const wrap = document.createElement('div');

                    wrap.className = 'panel popup-panel';

                    wrap.innerHTML = content;

                    modalBody.appendChild(wrap);

                } else if (content instanceof HTMLElement) {

                    content.classList.add('popup-panel');

                    content.style.display = 'block';

                    modalBody.appendChild(content);

                    activePanelEl = content;

                }



                modal.classList.remove('hidden');

                return true;

            };



            const openInfoPopup = (title, lines = []) => {

                const infoHtml = (lines && lines.length)

                    ? '<div class="info" style="margin-top:0;">' + lines.map((l) => String(l)).join('<br>') + '</div>'

                    : '<div class="info" style="margin-top:0;">Aucun paramètre pour cet outil.</div>';



                // Sur mobile, utiliser le modal fullpage

                if (isMobile()) {

                    openInFullModal(infoHtml, title);

                    return;

                }



                // Sur desktop, utiliser le popup sidebar classique

                if (activePanelEl) {

                    storePanel(activePanelEl);

                    activePanelEl = null;

                }

                popupTitle.textContent = title || 'Paramètres';

                popupBody.innerHTML = '';

                const wrap = document.createElement('div');

                wrap.className = 'panel popup-panel';

                const info = document.createElement('div');

                info.className = 'info';

                info.style.marginTop = '0';

                info.innerHTML = (lines && lines.length)

                    ? lines.map((l) => String(l)).join('<br>')

                    : 'Aucun paramètre pour cet outil.';

                wrap.appendChild(info);

                popupBody.appendChild(wrap);

                popupHost.classList.remove('hidden');

            };



            window.pp3_closeFullModal = () => {

                const modal = document.getElementById('pp3FullPageModal');

                const modalBody = document.getElementById('pp3FullModalBody');

                const panelStore = document.getElementById('panelStore');

                if (modal) modal.classList.add('hidden');

                // Remettre le panel dans le store sans le détruire

                if (modalBody && panelStore) {

                    while (modalBody.firstChild) {

                        const child = modalBody.firstChild;

                        // Ne pas stocker les éléments créés dynamiquement (titres, etc.)

                        if (child.id && child.classList.contains('panel')) {

                            child.style.display = 'none';

                            child.classList.remove('popup-panel');

                            panelStore.appendChild(child);

                        } else {

                            modalBody.removeChild(child);

                        }

                    }

                }

                activePanelEl = null;

            };



            const openPanelPopup = (panelId, title) => {

                const panelEl = document.getElementById(panelId);

                if (!panelEl) {

                    openInfoPopup(title || 'Paramètres', ['Panel introuvable: ' + panelId]);

                    return;

                }



                // Sur mobile OU pour account/admin: utiliser le modal fullpage

                const useFullModal = isMobile() || panelId === 'account-panel' || panelId === 'admin-panel';



                if (useFullModal) {

                    const modal = document.getElementById('pp3FullPageModal');

                    const modalBody = document.getElementById('pp3FullModalBody');

                    if (modal && modalBody) {

                        // Fermer le popup sidebar si ouvert

                        closePopup();



                        if (activePanelEl && activePanelEl !== panelEl && activePanelEl.parentElement !== modalBody) {

                            storePanel(activePanelEl);

                        }

                        activePanelEl = panelEl;



                        // Nettoyer le modal body sans détruire les panels

                        while (modalBody.firstChild) {

                            const child = modalBody.firstChild;

                            if (child !== panelEl && child.id && child.classList.contains('panel')) {

                                child.style.display = 'none';

                                child.classList.remove('popup-panel');

                                panelStore.appendChild(child);

                            } else if (child !== panelEl) {

                                modalBody.removeChild(child);

                            } else {

                                break; // Le panel est déjà là

                            }

                        }



                        if (panelEl.parentElement !== modalBody) {

                            modalBody.appendChild(panelEl);

                        }

                        panelEl.classList.add('popup-panel');

                        panelEl.style.display = 'block';

                        modal.classList.remove('hidden');

                        return;

                    }

                }



                // Desktop: utiliser le popup sidebar classique

                if (activePanelEl && activePanelEl !== panelEl) {

                    storePanel(activePanelEl);

                }

                activePanelEl = panelEl;



                popupTitle.textContent = title || (panelEl.querySelector('h3') ? panelEl.querySelector('h3').textContent : 'Paramètres');

                popupBody.innerHTML = '';

                popupBody.appendChild(panelEl);

                panelEl.classList.add('popup-panel');

                panelEl.style.display = 'block';

                popupHost.classList.remove('hidden');

            };



            if (closeBtn) closeBtn.addEventListener('click', closePopup);



            // Boutons outils (icônes) : si un panel est associé, ouvrir ce panel, sinon une mini info

            const toolButtons = Array.from(document.querySelectorAll('#toolbar-panel [data-popup-title]'));

            for (const btn of toolButtons) {

                btn.addEventListener('click', () => {

                    const featureId = btn.getAttribute('data-feature-id');

                    if (featureId && window.pp3ShouldBlockFeature && window.pp3ShouldBlockFeature(featureId)) {

                        return;

                    }

                    const title = btn.getAttribute('data-popup-title') || 'Paramètres';

                    const panelId = btn.getAttribute('data-popup-panel');

                    if (panelId) {

                        openPanelPopup(panelId, title);

                        return;

                    }

                    openInfoPopup(title);

                }, true);

            }



            // Boutons de panneaux (objets/export/lumière/propriétés/import/formes)

            const openPanelButtons = Array.from(document.querySelectorAll('#toolbar-panel [data-open-panel]'));

            for (const btn of openPanelButtons) {

                btn.addEventListener('click', () => {

                    const featureId = btn.getAttribute('data-feature-id');

                    if (featureId && window.pp3ShouldBlockFeature && window.pp3ShouldBlockFeature(featureId)) {

                        return;

                    }

                    const panelId = btn.getAttribute('data-open-panel');

                    const title = btn.getAttribute('title') || 'Paramètres';

                    if (panelId) openPanelPopup(panelId, title);



                    // Icône "Play" du toolbar: si c'est le bouton Animations, on lance/stoppe l'animation sélectionnée.

                    // (L'utilisateur s'attend à un play réel, pas seulement à ouvrir le panel.)

                    if (btn.id === 'openAnimationsPanelBtn') {

                        // Toggle si déjà en lecture

                        if (userAnimIsPlaying && userAnimActiveAnim) {

                            pauseUserAnimation();

                            return;

                        }



                        // Joue l'animation sélectionnée, sinon la première disponible

                        let anim = null;

                        if (userAnimSelectedId) anim = getOrCreateAnimById(userAnimSelectedId);

                        if (!anim && Array.isArray(userAnimations) && userAnimations.length > 0) {

                            anim = userAnimations[0];

                            userAnimSelectedId = anim.id;

                        }

                        if (anim) {

                            playUserAnimation(anim, 0);

                        }

                    }

                }, true);

            }



            // Fermer le popup si on clique en dehors (dans la sidebar)

            document.addEventListener('pointerdown', (e) => {

                if (!popupHost || popupHost.classList.contains('hidden')) return;

                const sidebar = document.querySelector('.sidebar');

                if (!sidebar) return;

                if (!sidebar.contains(e.target)) return;

                const toolbar = document.getElementById('toolbar-panel');

                if (popupHost.contains(e.target)) return;

                if (toolbar && toolbar.contains(e.target)) return;

                // clic dans la sidebar mais pas sur toolbar/popup

                // -> on ferme

                closePopup();

            }, true);



            // Au chargement: s'assurer que tous les panels store sont bien masqués

            const panelsToHide = Array.from(panelStore.children)

                .filter((el) => el && el.classList && el.classList.contains('panel'));

            for (const p of panelsToHide) {

                if (p && p.id) p.style.display = 'none';

            }



            // ===== Gestionnaire pour la modal fullpage =====

            // Empêcher la fermeture quand on clique dans le contenu

            const fullModal = document.getElementById('pp3FullPageModal');

            const fullModalContent = fullModal ? fullModal.querySelector('.pp3-full-modal-content') : null;

            if (fullModalContent) {

                fullModalContent.addEventListener('click', (e) => {

                    e.stopPropagation();

                }, false);

            }

            // Fermer quand on clique sur l'overlay (en dehors du contenu)

            if (fullModal) {

                fullModal.addEventListener('click', (e) => {

                    if (e.target === fullModal) {

                        window.pp3_closeFullModal();

                    }

                }, false);

            }

        }



        function initGlbImportUI() {

            const input = document.getElementById('glbUpload');

            const btn = document.getElementById('importGlbBtn');

            const status = document.getElementById('importGlbStatus');



            if (!btn || !input) return;

            if (!gltfLoader) gltfLoader = new THREE.GLTFLoader();



            const setStatus = (txt) => { if (status) status.textContent = txt; };



            btn.addEventListener('click', async () => {

                if (!input.files || input.files.length === 0) {

                    setStatus('Choisis un fichier .glb/.gltf.');

                    return;

                }

                const file = input.files[0];

                setStatus('Import en cours...');

                try {

                    await importGlbFile(file);

                    setStatus('Modèle importé.');

                } catch (e) {

                    console.error('Erreur import GLB/GLTF:', e);

                    const msg = (e && (e.message || e.toString())) ? (e.message || e.toString()) : 'Erreur inconnue';

                    setStatus('Erreur import: ' + msg);

                }

            });

        }



        function setObjectShadowProps(root) {

            root.traverse((child) => {

                if (child && child.isMesh) {

                    child.castShadow = true;

                    child.receiveShadow = true;



                    // IMPORTANT: éviter les matériaux partagés entre meshes importés.

                    // Sinon changer la couleur d'un mesh change aussi les autres.

                    if (child.material) {

                        if (Array.isArray(child.material)) {

                            child.material = child.material.map((m) => (m && m.isMaterial ? m.clone() : m));

                            for (const m of child.material) {

                                if (m && m.isMaterial) m.needsUpdate = true;

                            }

                        } else if (child.material.isMaterial) {

                            child.material = child.material.clone();

                            child.material.needsUpdate = true;

                        }

                    }



                    // S'assure que c'est sélectionnable

                    if (!child.userData) child.userData = {};

                    child.userData.isObject = true;

                }

            });

        }



        function getMeshMaterials(mesh) {

            if (!mesh || !mesh.material) return [];

            return Array.isArray(mesh.material)

                ? mesh.material.filter((m) => m && m.isMaterial)

                : (mesh.material && mesh.material.isMaterial ? [mesh.material] : []);

        }



        function setSelectedMeshColor(hexOrCss) {

            const roots = uniqRootsFrom(getEffectiveSelection());

            if (roots.length === 0) return;

            for (const obj of roots) {

                for (const m of getMeshMaterials(obj)) {

                    if (m.color) {

                        m.color.set(hexOrCss);

                        m.needsUpdate = true;

                    }

                }

            }

        }



        function setSelectedMeshOpacity(value) {

            const roots = uniqRootsFrom(getEffectiveSelection());

            if (roots.length === 0) return;

            const v = Math.max(0, Math.min(1, value));

            for (const obj of roots) {

                for (const m of getMeshMaterials(obj)) {

                    if (typeof m.opacity === 'number') {

                        m.opacity = v;

                        // Si on joue l'opacité, on force transparent pour que ça se voie

                        m.transparent = v < 1 ? true : m.transparent;

                        m.needsUpdate = true;

                    }

                }

            }

        }



        function addImportedToObjects(root, baseName) {

            const added = [];

            root.traverse((child) => {

                if (child && child.isMesh) {

                    child.userData = child.userData || {};

                    child.userData.type = 'imported';

                    child.userData.id = Date.now() + Math.random();

                    const meshName = child.name ? child.name : 'Mesh';

                    child.userData.name = baseName + ' / ' + meshName;

                    objects.push(child);

                    added.push(child);

                }

            });

            updateObjectList();

            if (added.length > 0) selectObject(added[0]);

        }



        function fitObjectToView(root, targetSize = 4) {

            const box = new THREE.Box3().setFromObject(root);

            const size = new THREE.Vector3();

            box.getSize(size);

            const maxDim = Math.max(size.x, size.y, size.z);

            if (!isFinite(maxDim) || maxDim <= 0) return;

            const scale = targetSize / maxDim;

            root.scale.multiplyScalar(scale);



            // recentre sur sol

            const box2 = new THREE.Box3().setFromObject(root);

            const center = new THREE.Vector3();

            box2.getCenter(center);

            root.position.sub(center);

            // remonte pour poser sur y=0

            const minY = box2.min.y;

            root.position.y -= minY;

        }



        async function importGlbFile(file) {

            if (!gltfLoader) gltfLoader = new THREE.GLTFLoader();

            const name = (file.name || 'import').replace(/\.[^/.]+$/, '');



            // Lire le fichier en ArrayBuffer

            const buffer = await file.arrayBuffer();



            // Parse (basePath vide: ok pour .glb)

            const gltf = await new Promise((resolve, reject) => {

                gltfLoader.parse(buffer, '', resolve, reject);

            });



            const root = gltf.scene || (gltf.scenes && gltf.scenes[0]);

            if (!root) throw new Error('GLTF sans scène.');



            // Applique ombres et config

            setObjectShadowProps(root);

            fitObjectToView(root, 4);



            scene.add(root);

            addImportedToObjects(root, name);



            // Animations importées (si présentes)

            setImportedAnimations(root, gltf.animations || []);



            // Auto-play: si le GLB contient des animations, on en joue une immédiatement

            // (sinon l’utilisateur a l’impression que le squelette “ne s’anime pas”).

            if (Array.isArray(gltf.animations) && gltf.animations.length > 0) {

                try {

                    setImportedClipIndex(0);

                    playImportedAnimation(0);

                } catch (_) {

                    // ignore

                }

            }



            // Détection auto squelette

            const rig = detectRig(root);

            if (rig) {

                activeRig = rig;

                updateRigPanelUI();

                updateToolButtonsEnabledState();

            }

        }



        function initGlbExportUI() {

            const form = document.getElementById('export-form');

            if (!form) return;



            form.addEventListener('submit', async (e) => {

                e.preventDefault();

                if (window.pp3ShouldBlockFeature && window.pp3ShouldBlockFeature('export_glb')) {

                    return;

                }

                try {

                    await exportSceneAsGlb();

                } catch (err) {

                    console.error(err);

                    alert('Export GLB impossible. Voir console pour détails.');

                }

            });

        }



        // =========================

        // Compte / Premium / Admin

        // =========================



        let pp3State = {

            configured: false,

            logged: false,

            user: null,

            admin: null,

            csrf: null, // Token CSRF pour les requêtes sécurisées

        };



        function pp3SetMsg(el, text) {

            if (!el) return;

            const msg = String(text || '').trim();

            if (!msg) {

                el.classList.add('pp3-hidden');

                el.textContent = '';

                return;

            }

            el.textContent = msg;

            el.classList.remove('pp3-hidden');

        }



        async function pp3Api(action, data = {}) {

            const fd = new FormData();

            fd.append('pp3_action', action);

            // Ajouter le token CSRF pour les requêtes sensibles

            if (pp3State.csrf) {

                fd.append('pp3_csrf', pp3State.csrf);

            }

            for (const [k, v] of Object.entries(data || {})) {

                fd.append(k, v);

            }

            const res = await fetch(window.location.href.split('#')[0], {

                method: 'POST',

                body: fd,

                credentials: 'same-origin'

            });

            const json = await res.json();

            if (!json || !json.ok) {

                throw new Error((json && json.error) ? json.error : 'Erreur');

            }

            // Mettre à jour le token CSRF si fourni

            if (json.csrf) {

                pp3State.csrf = json.csrf;

            }

            return json;

        }



        function pp3OpenAccountPanel() {

            const btn = document.getElementById('openAccountPanelBtn');

            if (btn) btn.click();

        }



        function pp3IsPremiumActive() {

            const opt = pp3State && pp3State.admin && pp3State.admin.opt ? pp3State.admin.opt : null;

            return !!(opt && opt.premiumActive);

        }



        // Retourne l'accès d'une feature: 'all', 'premium', ou 'none'

        function pp3GetFeatureAccess(featureId) {

            const opt = pp3State && pp3State.admin && pp3State.admin.opt ? pp3State.admin.opt : null;

            const access = (opt && typeof opt.featureAccess === 'object') ? opt.featureAccess : {};

            return access[featureId] || 'all'; // Par défaut, tout le monde a accès

        }



        // Retourne un Set des features qui sont réservées aux premium

        function pp3GetPremiumFeatures() {

            const opt = pp3State && pp3State.admin && pp3State.admin.opt ? pp3State.admin.opt : null;

            const access = (opt && typeof opt.featureAccess === 'object') ? opt.featureAccess : {};

            const premiumList = Object.entries(access).filter(([k, v]) => v === 'premium').map(([k]) => k);

            return new Set(premiumList);

        }



        // Retourne un Set des features qui sont désactivées pour tout le monde

        function pp3GetDisabledFeatures() {

            const opt = pp3State && pp3State.admin && pp3State.admin.opt ? pp3State.admin.opt : null;

            const access = (opt && typeof opt.featureAccess === 'object') ? opt.featureAccess : {};

            const disabledList = Object.entries(access).filter(([k, v]) => v === 'none').map(([k]) => k);

            return new Set(disabledList);

        }



        function pp3ExportRequiresPremium() {

            const opt = pp3State && pp3State.admin && pp3State.admin.opt ? pp3State.admin.opt : null;

            return !!(opt && opt.exportRequiresPremium);

        }



        // Met à jour la visibilité du bouton IA en fonction des permissions

        function pp3UpdateAIButtonVisibility() {

            const aiLauncherBtn = document.getElementById('ai-launcher-btn');

            if (!aiLauncherBtn) return;



            const access = pp3GetFeatureAccess('ai_generator');

            const premiumActive = pp3IsPremiumActive();

            const isPremiumUser = !!(pp3State && pp3State.user && pp3State.user.is_premium);



            // Si désactivé (none), cacher le bouton

            if (access === 'none') {

                aiLauncherBtn.style.display = 'none';

                return;

            }



            // Sinon, afficher le bouton

            aiLauncherBtn.style.display = '';



            // Si premium uniquement et utilisateur non-premium, ajouter une couronne

            let crown = aiLauncherBtn.querySelector('.pp3-premium-crown');

            if (access === 'premium' && premiumActive && !isPremiumUser) {

                if (!crown) {

                    crown = document.createElement('span');

                    crown.className = 'pp3-premium-crown';

                    crown.setAttribute('aria-hidden', 'true');

                    crown.textContent = '👑';

                    aiLauncherBtn.appendChild(crown);

                }

            } else {

                if (crown) crown.remove();

            }

        }



        function pp3UpdatePremiumBadges() {

            const set = pp3GetPremiumFeatures();

            const disabledSet = pp3GetDisabledFeatures();

            const isPremiumUser = !!(pp3State && pp3State.user && pp3State.user.is_premium);

            const premiumActive = pp3IsPremiumActive();

            const buttons = Array.from(document.querySelectorAll('#toolbar-panel [data-feature-id]'));

            for (const btn of buttons) {

                const fid = btn.getAttribute('data-feature-id');

                if (!fid) continue;



                // Cacher le bouton si la feature est désactivée (none)

                if (disabledSet.has(fid)) {

                    btn.style.display = 'none';

                    continue;

                } else {

                    btn.style.display = '';

                }



                let shouldShow = premiumActive && set.has(fid) && !isPremiumUser;

                // Cas particulier export: si l'action export est premium ou si l'export est bloqué sans premium,

                // on affiche la couronne sur l'icône du panel export.

                if (fid === 'panel_export') {

                    shouldShow = premiumActive && !isPremiumUser && (set.has('export_glb') || pp3ExportRequiresPremium() || set.has('panel_export'));

                }

                let crown = btn.querySelector('.pp3-premium-crown');

                if (shouldShow) {

                    if (!crown) {

                        crown = document.createElement('span');

                        crown.className = 'pp3-premium-crown';

                        crown.setAttribute('aria-hidden', 'true');

                        crown.textContent = '👑';

                        btn.appendChild(crown);

                    }

                } else {

                    if (crown) crown.remove();

                }

            }



            // Mettre à jour la visibilité du bouton IA

            pp3UpdateAIButtonVisibility();

        }



        // Retourne true si on bloque l'action (et déclenche l'ouverture Compte)

        // Gère 3 niveaux d'accès: all (tout le monde), premium (premium uniquement), none (désactivé)

        window.pp3ShouldBlockFeature = function (featureId, options = {}) {

            const fid = String(featureId || '').trim();

            if (!fid) return false;



            const showMessage = options.showMessage !== false; // Par défaut, afficher le message



            // Toujours autoriser l'accès au panneau compte

            if (fid === 'panel_account') return false;



            // Si DB pas configurée: on n'empêche pas l'usage général de l'app.

            // On bloque uniquement l'accès à l\'admin (qui nécessite la DB).

            if (!pp3State.configured) {

                if (fid === 'panel_admin') {

                    pp3OpenAccountPanel();

                    pp3SetMsg(document.getElementById('pp3AccountMsg'), 'Configure la base de données pour accéder à l’admin.');

                    return true;

                }

                return false;

            }



            // Admin panel: nécessite admin

            if (fid === 'panel_admin') {

                if (!pp3State.logged) {

                    pp3OpenAccountPanel();

                    pp3SetMsg(document.getElementById('pp3AccountMsg'), 'Connecte-toi pour accéder à l’admin.');

                    return true;

                }

                if (!pp3State.user || !pp3State.user.is_admin) {

                    pp3OpenAccountPanel();

                    pp3SetMsg(document.getElementById('pp3AccountMsg'), 'Accès admin requis.');

                    return true;

                }

                return false;

            }



            const premiumActive = pp3IsPremiumActive();

            const isPremiumUser = !!(pp3State && pp3State.user && pp3State.user.is_premium);

            const disabledFeatures = pp3GetDisabledFeatures();

            const premiumFeatures = pp3GetPremiumFeatures();

            const featureAccess = pp3GetFeatureAccess(fid);



            // Feature désactivée pour tout le monde (none)

            if (disabledFeatures.has(fid) || featureAccess === 'none') {

                // Ne pas afficher de message, juste bloquer silencieusement

                return true;

            }



            // Export gating option (cas particulier legacy)

            if (fid === 'export_glb' && premiumActive && pp3ExportRequiresPremium() && !isPremiumUser) {

                if (showMessage) {

                    if (!pp3State.logged) {

                        pp3OpenAccountPanel();

                        pp3SetMsg(document.getElementById('pp3AccountMsg'), 'Connecte-toi pour souscrire au premium et exporter.');

                    } else {

                        pp3OpenAccountPanel();

                        pp3SetMsg(document.getElementById('pp3AccountMsg'), 'L\'export est réservé aux comptes premium.');

                        const sub = document.getElementById('pp3SubscribeBox');

                        if (sub) sub.classList.remove('pp3-hidden');

                    }

                }

                return true;

            }



            // Premium gating par feature (quand featureAccess === 'premium')

            if (premiumActive && (premiumFeatures.has(fid) || featureAccess === 'premium') && !isPremiumUser) {

                if (showMessage) {

                    if (!pp3State.logged) {

                        pp3OpenAccountPanel();

                        pp3SetMsg(document.getElementById('pp3AccountMsg'), 'Connecte-toi pour souscrire au premium.');

                    } else {

                        pp3OpenAccountPanel();

                        pp3SetMsg(document.getElementById('pp3AccountMsg'), 'Fonction premium. Choisis un plan pour débloquer.');

                        const sub = document.getElementById('pp3SubscribeBox');

                        if (sub) sub.classList.remove('pp3-hidden');

                    }

                }

                return true;

            }

            return false;

        };



        function pp3RenderAccountUI() {

            const msg = document.getElementById('pp3AccountMsg');

            const dbBox = document.getElementById('pp3DbSetupBox');

            const authBox = document.getElementById('pp3AuthBox');

            const loginBox = document.getElementById('pp3LoginBox');

            const registerBox = document.getElementById('pp3RegisterBox');

            const loggedBox = document.getElementById('pp3LoggedBox');

            const subscribeBox = document.getElementById('pp3SubscribeBox');

            const userMail = document.getElementById('pp3UserMail');

            const userPremium = document.getElementById('pp3UserPremium');



            if (!pp3State.configured) {

                if (dbBox) dbBox.classList.remove('pp3-hidden');

                if (authBox) authBox.classList.add('pp3-hidden');

                return;

            }



            if (dbBox) dbBox.classList.add('pp3-hidden');

            if (authBox) authBox.classList.remove('pp3-hidden');



            const isLogged = !!pp3State.logged;

            const openAdminBtn = document.getElementById('openAdminPanelBtn');

            if (openAdminBtn) {

                // Seulement si connecté ET admin

                if (isLogged && pp3State.user && pp3State.user.is_admin) {

                    openAdminBtn.style.display = 'inline-flex';

                } else {

                    openAdminBtn.style.display = 'none';

                }

            }



            if (isLogged) {

                if (loginBox) loginBox.classList.add('pp3-hidden');

                if (registerBox) registerBox.classList.add('pp3-hidden');

                const toggle = document.getElementById('pp3AuthToggle');

                if (toggle) toggle.classList.add('pp3-hidden');

                if (loggedBox) loggedBox.classList.remove('pp3-hidden');

                if (userMail) userMail.textContent = pp3State.user?.mail || '-';

                if (userPremium) {

                    if (pp3State.user?.is_premium) {

                        userPremium.textContent = 'Actif';

                    } else {

                        userPremium.textContent = 'Inactif';

                    }

                }

            } else {

                const toggle = document.getElementById('pp3AuthToggle');

                if (toggle) toggle.classList.remove('pp3-hidden');

                if (loggedBox) loggedBox.classList.add('pp3-hidden');

                // mode connexion par défaut

                if (loginBox) loginBox.classList.remove('pp3-hidden');

                if (registerBox) registerBox.classList.add('pp3-hidden');

            }



            // Abonnement

            const premiumActive = pp3IsPremiumActive();

            const showSub = premiumActive && isLogged && !(pp3State.user && pp3State.user.is_premium);

            if (subscribeBox) {

                if (showSub) subscribeBox.classList.remove('pp3-hidden');

                else subscribeBox.classList.add('pp3-hidden');

            }

            if (showSub) {

                pp3BuildPlansUI();

                try {

                    if (typeof window.pp3RenderSubscribeAdsOffer === 'function') {

                        window.pp3RenderSubscribeAdsOffer();

                    }

                } catch (_) {}

                try {

                    if (typeof window.pp3UpdateSubscribeCtaLabel === 'function') {

                        window.pp3UpdateSubscribeCtaLabel();

                    }

                } catch (_) {}

            }



            // Admin icon

            const adminBtn = document.getElementById('openAdminPanelBtn');

            if (adminBtn) {

                const isAdmin = (pp3State.user && (pp3State.user.is_admin === true || pp3State.user.is_admin == 1));

                adminBtn.style.display = isAdmin ? 'inline-flex' : 'none';

                if (!isAdmin) adminBtn.classList.add('pp3-hidden');

                else adminBtn.classList.remove('pp3-hidden');

            }



            pp3UpdatePremiumBadges();

            // Nettoie message si tout ok

            if (msg && !msg.classList.contains('pp3-hidden')) {

                // ne rien faire: message reste visible jusqu'à action

            }

        }



        function pp3BuildPlansUI() {

            const container = document.getElementById('pp3PlansContainer');

            if (!container) return;

            container.innerHTML = '';

            const opt = pp3State && pp3State.admin && pp3State.admin.opt ? pp3State.admin.opt : {};

            const enabled = Array.isArray(opt.enabledPlans) ? opt.enabledPlans.map(String) : [];

            const plans = (opt.plans && typeof opt.plans === 'object') ? opt.plans : {};



            const labels = {

                unique: 'Unique',

                day: 'Jour',

                week: 'Semaine',

                month: 'Mois',

                month3: '3 mois',

                month6: '6 mois',

                year: 'Année',

                lifetime: 'À vie',

            };



            for (const plan of enabled) {

                const amount = Number(plans[plan] || 0);

                if (!amount || amount <= 0) continue;

                const euros = (amount / 100).toFixed(2);

                const btn = document.createElement('button');

                btn.type = 'button';

                btn.textContent = `${labels[plan] || plan} - ${euros}€`;

                btn.addEventListener('click', async () => {

                    try {

                        pp3SetMsg(document.getElementById('pp3AccountMsg'), 'Redirection vers Stripe...');

                        const out = await pp3Api('create_checkout', { plan });

                        if (out && out.url) {

                            window.location.href = out.url;

                            return;

                        }

                        throw new Error('URL Stripe introuvable.');

                    } catch (e) {

                        pp3SetMsg(document.getElementById('pp3AccountMsg'), e.message || String(e));

                    }

                });

                container.appendChild(btn);

            }

            if (!container.children.length) {

                const info = document.createElement('div');

                info.className = 'info';

                info.style.marginTop = '0';

                info.textContent = 'Aucun plan actif configuré par l’admin.';

                container.appendChild(info);

            }

        }



        function pp3RenderAdminUI() {

            const msg = document.getElementById('pp3AdminMsg');

            if (!pp3State.user || !pp3State.user.is_admin) {

                pp3SetMsg(msg, 'Accès admin requis.');

                return;

            }

            pp3SetMsg(msg, '');

            const admin = pp3State.admin || {};

            const opt = admin.opt || {};



            const sk = document.getElementById('pp3StripeSk');

            const pk = document.getElementById('pp3StripePk');

            if (sk) sk.value = admin.stripe && admin.stripe.sk ? admin.stripe.sk : '';

            if (pk) pk.value = admin.stripe && admin.stripe.pk ? admin.stripe.pk : '';



            const premiumActive = document.getElementById('pp3PremiumActive');

            const exportReq = document.getElementById('pp3ExportRequiresPremium');

            if (premiumActive) premiumActive.checked = !!opt.premiumActive;

            if (exportReq) exportReq.checked = !!opt.exportRequiresPremium;



            const enabled = Array.isArray(opt.enabledPlans) ? opt.enabledPlans.map(String) : [];

            const plans = (opt.plans && typeof opt.plans === 'object') ? opt.plans : {};

            const planEnabledEls = Array.from(document.querySelectorAll('.pp3PlanEnabled'));

            const planPriceEls = Array.from(document.querySelectorAll('.pp3PlanPrice'));

            for (const el of planEnabledEls) {

                const plan = el.getAttribute('data-plan');

                if (!plan) continue;

                el.checked = enabled.includes(plan);

            }

            for (const el of planPriceEls) {

                const plan = el.getAttribute('data-plan');

                if (!plan) continue;

                const cents = Number(plans[plan] || 0);

                el.value = ((cents || 0) / 100).toFixed(2);

            }



            const featureAccess = (opt.featureAccess && typeof opt.featureAccess === 'object') ? opt.featureAccess : {};

            const accessEls = Array.from(document.querySelectorAll('.pp3FeatureAccess'));

            for (const el of accessEls) {

                const f = el.getAttribute('data-feature');

                if (!f) continue;

                el.value = featureAccess[f] || 'all';

            }



            // Pub (Monetag)

            try {

                const adsEnabled = document.getElementById('pp3AdsEnabled');

                if (adsEnabled) adsEnabled.checked = !!(opt.ads && opt.ads.enabled);

                if (typeof window.pp3AdsSetDraftZones === 'function') {

                    const zones = (opt.ads && Array.isArray(opt.ads.zones)) ? opt.ads.zones : [];

                    window.pp3AdsSetDraftZones(zones);

                }

                if (typeof window.pp3UpdateFreeExportAdsVisibility === 'function') {

                    window.pp3UpdateFreeExportAdsVisibility();

                }

            } catch (_) {}

        }



        // =========================

        // Pub (Monetag)

        // =========================



        let pp3AdsZonesDraft = [];



        window.pp3AdsSetDraftZones = function (zones) {

            const clean = Array.isArray(zones) ? zones : [];

            pp3AdsZonesDraft = clean

                .map(z => ({ url: String(z?.url || '').trim(), zone: String(z?.zone || '').trim() }))

                .filter(z => z.url && z.zone)

                .slice(0, 5);

            window.pp3RenderAdsZonesList();

        };



        window.pp3RenderAdsZonesList = function () {

            const list = document.getElementById('pp3AdsZonesList');

            if (!list) return;

            list.innerHTML = '';



            if (!pp3AdsZonesDraft.length) {

                const info = document.createElement('div');

                info.className = 'info';

                info.style.marginTop = '0';

                info.textContent = 'Aucune zone Monetag configurée.';

                list.appendChild(info);

                return;

            }



            pp3AdsZonesDraft.forEach((z, idx) => {

                const row = document.createElement('div');

                row.style.cssText = 'display:flex; gap:8px; align-items:center; justify-content:space-between; padding:10px; border:1px solid rgba(255,255,255,0.18); border-radius:10px;';



                const left = document.createElement('div');

                left.style.cssText = 'display:grid; gap:4px; min-width: 0;';

                const url = document.createElement('div');

                url.style.cssText = 'font-size:12px; color: rgba(255,255,255,0.85); word-break: break-all;';

                url.textContent = z.url;

                const zone = document.createElement('div');

                zone.style.cssText = 'font-size:12px; color: rgba(255,255,255,0.70);';

                zone.textContent = 'data-zone: ' + z.zone;

                left.appendChild(url);

                left.appendChild(zone);



                const remove = document.createElement('button');

                remove.type = 'button';

                remove.textContent = 'Supprimer';

                remove.style.cssText = 'background: rgba(239,68,68,0.55); border:none; border-radius:10px; padding:8px 10px; color:#fff; cursor:pointer;';

                remove.addEventListener('click', () => {

                    pp3AdsZonesDraft.splice(idx, 1);

                    window.pp3RenderAdsZonesList();

                });



                row.appendChild(left);

                row.appendChild(remove);

                list.appendChild(row);

            });

        };



        window.pp3GetMonetagConfig = function () {

            const opt = pp3State && pp3State.admin && pp3State.admin.opt ? pp3State.admin.opt : {};

            const ads = (opt && typeof opt.ads === 'object' && opt.ads) ? opt.ads : {};

            const enabled = !!ads.enabled;

            const zones = Array.isArray(ads.zones) ? ads.zones.map(z => ({ url: String(z?.url || '').trim(), zone: String(z?.zone || '').trim() })).filter(z => z.url && z.zone).slice(0, 5) : [];

            return { enabled, zones };

        };



        window.pp3UpdateFreeExportAdsVisibility = function () {

            try {

                // La popup IA se mettra à jour quand elle s'ouvre, mais on peut aussi pousser ici.

                if (typeof window.aiInterface?.refreshAdsExportButton === 'function') {

                    window.aiInterface.refreshAdsExportButton();

                }

                if (typeof window.pp3RenderSubscribeAdsOffer === 'function') {

                    window.pp3RenderSubscribeAdsOffer();

                }

            } catch (_) {}

        };



        function pp3CleanupMonetagScripts() {

            const olds = Array.from(document.querySelectorAll('script[data-pp3-monetag="1"]'));

            for (const s of olds) {

                try { s.remove(); } catch (_) {}

            }

        }



        function pp3ResetFreeExportAdsUI() {

            try {

                const chk = document.getElementById('pp3AdsFreeExportCheck');

                const btn = document.getElementById('pp3AdsFreeExportBtn');

                if (chk) chk.checked = false;

                if (btn) btn.style.display = 'none';

            } catch (_) {}

        }



        function pp3EnsureAdsOverlay() {

            let overlay = document.getElementById('pp3AdsDownloadOverlay');

            if (overlay) return overlay;



            overlay = document.createElement('div');

            overlay.id = 'pp3AdsDownloadOverlay';

            overlay.style.cssText = [

                'position:fixed',

                'top:0',

                'left:0',

                'right:0',

                'bottom:0',

                'width:100vw',

                'height:100vh',

                'z-index:999999',

                'display:none',

                'background: rgba(0,0,0,0.78)',

                'backdrop-filter: blur(6px)',

                '-webkit-backdrop-filter: blur(6px)',

                'color:#fff',

                'font-family: Segoe UI, sans-serif',

                'overflow-y:auto',

                '-webkit-overflow-scrolling:touch'

            ].join(';');



            overlay.innerHTML = `

                <div style="position:absolute; top:0; left:0; right:0; height:54px; display:flex; align-items:center; padding:0 16px; gap:12px; border-bottom:1px solid rgba(255,255,255,0.14); background: rgba(20,20,30,0.85);">

                    <div style="font-weight:700;">Téléchargement en cours…</div>

                    <div id="pp3AdsCooldownText" style="color: rgba(255,255,255,0.75); font-size: 13px;">15s</div>

                    <div style="flex:1;"></div>

                    <button id="pp3AdsCancelBtn" type="button" style="background: rgba(239,68,68,0.55); border:none; border-radius:10px; padding:8px 12px; color:#fff; cursor:pointer;">Annuler</button>

                </div>

                <div style="position:absolute; top:54px; left:0; right:0; height:4px; background: rgba(255,255,255,0.10);">

                    <div id="pp3AdsProgressBar" style="height:4px; width:0%; background: rgba(74,158,255,0.85);"></div>

                </div>

                <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; padding-top: 58px;">

                    <div style="text-align:center; max-width: 720px; padding: 24px;">

                        <div style="font-size: 20px; font-weight: 800; margin-bottom: 10px;">Download in progress</div>

                        <div style="color: rgba(255,255,255,0.75); font-size: 14px;">Merci de patienter pendant l’affichage de la pub (Monetag). Le bouton de téléchargement s’active après 15s.</div>

                        <button id="pp3AdsDownloadBtn" type="button" disabled style="

                            margin-top: 16px;

                            width: min(420px, 90vw);

                            padding: 12px;

                            border: none;

                            border-radius: 12px;

                            cursor: pointer;

                            background: rgba(59,130,246,0.55);

                            color: #fff;

                            font-weight: 900;

                            opacity: 0.55;

                        ">Télécharger (15s)</button>

                        <div id="pp3AdsOverlayMsg" style="margin-top: 14px; color: rgba(255,255,255,0.70); font-size: 12px;"></div>

                    </div>

                </div>

            `;



            document.body.appendChild(overlay);



            const cancel = overlay.querySelector('#pp3AdsCancelBtn');

            if (cancel) {

                cancel.addEventListener('click', () => {

                    overlay.style.display = 'none';

                    overlay.dataset.running = '';

                    try { pp3CleanupMonetagScripts(); } catch (_) {}

                    try { pp3ResetFreeExportAdsUI(); } catch (_) {}

                });

            }

            return overlay;

        }



        function pp3InjectMonetagScripts(zones) {

            // Nettoyer les anciens scripts injectés par nous

            pp3CleanupMonetagScripts();

            for (const z of zones) {

                const s = document.createElement('script');

                s.src = z.url;

                s.async = true;

                s.setAttribute('data-zone', z.zone);

                s.setAttribute('data-cfasync', 'false');

                s.setAttribute('data-pp3-monetag', '1');

                document.head.appendChild(s);

            }

        }



        window.pp3RenderSubscribeAdsOffer = function () {

            const box = document.getElementById('pp3SubscribeBox');

            if (!box) return;



            // Visible uniquement quand:

            // - premium actif

            // - utilisateur connecté et non premium

            // - pub activée + au moins 1 zone

            const premiumActive = (typeof pp3IsPremiumActive === 'function') ? pp3IsPremiumActive() : false;

            const isLogged = !!(window.pp3State && window.pp3State.logged);

            const isPremiumUser = !!(window.pp3State && window.pp3State.user && window.pp3State.user.is_premium);

            const cfg = (typeof window.pp3GetMonetagConfig === 'function') ? window.pp3GetMonetagConfig() : { enabled: false, zones: [] };

            const shouldShow = !!(premiumActive && isLogged && !isPremiumUser && cfg.enabled && Array.isArray(cfg.zones) && cfg.zones.length > 0);



            let host = document.getElementById('pp3AdsFreeExportOffer');

            if (!host) {

                host = document.createElement('div');

                host.id = 'pp3AdsFreeExportOffer';

                host.style.cssText = 'margin-top:10px; padding:10px; border: 1px solid rgba(255,255,255,0.18); border-radius: 10px; background: rgba(255,255,255,0.04);';

                host.innerHTML = `

                    <label style="display:flex; align-items:center; gap:8px; margin:0;">

                        <input type="checkbox" id="pp3AdsFreeExportCheck" />

                        <span>avec pub</span>

                    </label>

                    <button id="pp3AdsFreeExportBtn" type="button" style="

                        display:none;

                        margin-top:10px;

                        width:100%;

                        padding: 12px;

                        border: none;

                        border-radius: 10px;

                        cursor: pointer;

                        background: rgba(59,130,246,0.55);

                        color: #fff;

                        font-weight: 800;

                    ">Télécharger gratuitement avec pub</button>

                    <div class="info" style="margin-top:8px;">Le téléchargement démarre après 15s.</div>

                `;



                // Insérer juste après le titre "Abonnement Premium"

                const h3 = box.querySelector('h3');

                if (h3 && h3.nextSibling) {

                    h3.parentNode.insertBefore(host, h3.nextSibling);

                } else {

                    box.insertBefore(host, box.firstChild);

                }



                const chk = host.querySelector('#pp3AdsFreeExportCheck');

                const btn = host.querySelector('#pp3AdsFreeExportBtn');

                if (chk && btn) {

                    chk.addEventListener('change', () => {

                        btn.style.display = chk.checked ? 'block' : 'none';

                    });

                    btn.addEventListener('click', async () => {

                        try {

                            if (typeof window.pp3StartFreeExportWithAds === 'function') {

                                await window.pp3StartFreeExportWithAds();

                            } else {

                                alert('Fonction pub indisponible.');

                            }

                        } catch (e) {

                            console.error(e);

                            alert('Erreur pub/export.');

                        }

                    });

                }

            }



            host.style.display = shouldShow ? 'block' : 'none';

            if (!shouldShow) {

                pp3ResetFreeExportAdsUI();

            }

        };



        window.pp3UpdateSubscribeCtaLabel = function () {

            const btn = document.getElementById('pp3SubscribeCtaBtn');

            if (!btn) return;

            const cfg = (typeof window.pp3GetMonetagConfig === 'function') ? window.pp3GetMonetagConfig() : { enabled: false, zones: [] };

            const okAds = !!(cfg && cfg.enabled && Array.isArray(cfg.zones) && cfg.zones.length > 0);

            btn.textContent = okAds ? 'Télécharger avec pub' : 'Voir tous les plans';

        };



        window.pp3SubscribeCtaClick = async function () {

            const cfg = (typeof window.pp3GetMonetagConfig === 'function') ? window.pp3GetMonetagConfig() : { enabled: false, zones: [] };

            const okAds = !!(cfg && cfg.enabled && Array.isArray(cfg.zones) && cfg.zones.length > 0);

            if (okAds && typeof window.pp3StartFreeExportWithAds === 'function') {

                await window.pp3StartFreeExportWithAds();

                return;

            }

            if (typeof window.pp3_openPremiumModal === 'function') {

                window.pp3_openPremiumModal();

                return;

            }

            alert('Aucune action disponible.');

        };



        window.pp3StartFreeExportWithAds = async function () {

            const cfg = window.pp3GetMonetagConfig ? window.pp3GetMonetagConfig() : { enabled: false, zones: [] };

            if (!cfg.enabled) {

                alert('Pub désactivée par l’admin.');

                return;

            }

            if (!Array.isArray(cfg.zones) || cfg.zones.length <= 0) {

                alert('Aucune zone Monetag configurée.');

                return;

            }

            if (typeof exportSceneAsGlb !== 'function') {

                alert('Export indisponible.');

                return;

            }



            const overlay = pp3EnsureAdsOverlay();

            const text = overlay.querySelector('#pp3AdsCooldownText');

            const bar = overlay.querySelector('#pp3AdsProgressBar');

            const msg = overlay.querySelector('#pp3AdsOverlayMsg');

            const dlBtn = overlay.querySelector('#pp3AdsDownloadBtn');

            overlay.style.display = 'block';

            overlay.dataset.running = '1';



            if (dlBtn) {

                dlBtn.disabled = true;

                dlBtn.style.opacity = '0.55';

                dlBtn.textContent = 'Télécharger (15s)';

                dlBtn.onclick = null;

            }



            try {

                pp3InjectMonetagScripts(cfg.zones);

                if (msg) msg.textContent = cfg.zones.length > 1 ? `${cfg.zones.length} zone(s) Monetag injectée(s).` : 'Zone Monetag injectée.';

            } catch (e) {

                console.warn('Monetag inject error', e);

            }



            const totalMs = 15000;

            const start = Date.now();



            await new Promise((resolve) => {

                const tick = () => {

                    if (overlay.dataset.running !== '1') {

                        resolve();

                        return;

                    }

                    const elapsed = Date.now() - start;

                    const t = Math.min(1, Math.max(0, elapsed / totalMs));

                    const remaining = Math.max(0, Math.ceil((totalMs - elapsed) / 1000));

                    if (text) text.textContent = remaining + 's';

                    if (bar) bar.style.width = (t * 100).toFixed(1) + '%';

                    if (dlBtn) dlBtn.textContent = remaining > 0 ? `Télécharger (${remaining}s)` : 'Télécharger';

                    if (elapsed >= totalMs) {

                        resolve();

                        return;

                    }

                    requestAnimationFrame(tick);

                };

                requestAnimationFrame(tick);

            });



            if (overlay.dataset.running !== '1') {

                overlay.style.display = 'none';

                try { pp3CleanupMonetagScripts(); } catch (_) {}

                try { pp3ResetFreeExportAdsUI(); } catch (_) {}

                return;

            }



            // Cooldown terminé: on affiche/active le bouton Télécharger, puis on export au clic.

            if (!dlBtn) {

                // fallback: si bouton introuvable, on export directement

                try {

                    await exportSceneAsGlb();

                } catch (e) {

                    console.error(e);

                    alert('Export GLB impossible. Voir console pour détails.');

                } finally {

                    overlay.style.display = 'none';

                    overlay.dataset.running = '';

                    try { pp3CleanupMonetagScripts(); } catch (_) {}

                    try { pp3ResetFreeExportAdsUI(); } catch (_) {}

                }

                return;

            }



            dlBtn.disabled = false;

            dlBtn.style.opacity = '1';

            if (msg) msg.textContent = 'Tu peux télécharger maintenant.';



            await new Promise((resolve) => {

                dlBtn.onclick = async () => {

                    try {

                        dlBtn.disabled = true;

                        dlBtn.style.opacity = '0.55';

                        dlBtn.textContent = 'Téléchargement...';

                        await exportSceneAsGlb();

                    } catch (e) {

                        console.error(e);

                        alert('Export GLB impossible. Voir console pour détails.');

                    } finally {

                        overlay.style.display = 'none';

                        overlay.dataset.running = '';

                        // Pub OFF en temps réel dès fermeture overlay

                        try { pp3CleanupMonetagScripts(); } catch (_) {}

                        try { pp3ResetFreeExportAdsUI(); } catch (_) {}

                        resolve();

                    }

                };

            });

        };



        function pp3CollectAdminOpt() {

            const opt = {

                premiumActive: !!document.getElementById('pp3PremiumActive')?.checked,

                exportRequiresPremium: !!document.getElementById('pp3ExportRequiresPremium')?.checked,

                ads: {

                    enabled: !!document.getElementById('pp3AdsEnabled')?.checked,

                    zones: [],

                },

                plans: {},

                enabledPlans: [],

                featureAccess: {}, // Nouveau: stocke access par feature (all/premium/none)

            };



            const planEnabledEls = Array.from(document.querySelectorAll('.pp3PlanEnabled'));

            const planPriceEls = Array.from(document.querySelectorAll('.pp3PlanPrice'));

            const priceByPlan = {};

            for (const el of planPriceEls) {

                const plan = el.getAttribute('data-plan');

                if (!plan) continue;

                const euros = Number(el.value || 0);

                const cents = Math.max(0, Math.round(euros * 100));

                priceByPlan[plan] = cents;

                opt.plans[plan] = cents;

            }

            for (const el of planEnabledEls) {

                const plan = el.getAttribute('data-plan');

                if (!plan) continue;

                if (el.checked) opt.enabledPlans.push(plan);

                if (!(plan in opt.plans)) opt.plans[plan] = Number(priceByPlan[plan] || 0);

            }



            // Nouveau système: collecter les valeurs des selects d'accès (all/premium/none)

            const accessEls = Array.from(document.querySelectorAll('.pp3FeatureAccess'));

            for (const el of accessEls) {

                const f = el.getAttribute('data-feature');

                if (!f) continue;

                const val = el.value || 'all';

                opt.featureAccess[f] = val;

            }



            // Zones Monetag: utiliser le draft (max 5)

            try {

                const zones = Array.isArray(pp3AdsZonesDraft) ? pp3AdsZonesDraft : [];

                opt.ads.zones = zones

                    .map(z => ({ url: String(z?.url || '').trim(), zone: String(z?.zone || '').trim() }))

                    .filter(z => z.url && z.zone)

                    .slice(0, 5);

            } catch (_) {}

            return opt;

        }



        function initAccountPremiumUI() {

            // Tab admin

            const tabBtns = Array.from(document.querySelectorAll('#admin-panel [data-pp3-tab]'));

            const tabPanels = Array.from(document.querySelectorAll('#admin-panel [data-pp3-tabpanel]'));

            const setTab = (name) => {

                for (const b of tabBtns) b.classList.toggle('active', b.getAttribute('data-pp3-tab') === name);

                for (const p of tabPanels) p.classList.toggle('pp3-hidden', p.getAttribute('data-pp3-tabpanel') !== name);

            };

            for (const b of tabBtns) {

                b.addEventListener('click', () => setTab(b.getAttribute('data-pp3-tab') || 'stripe'));

            }

            setTab('stripe');



            // Pub (Monetag) UI

            const adsEnabled = document.getElementById('pp3AdsEnabled');

            if (adsEnabled) {

                adsEnabled.addEventListener('change', () => {

                    if (typeof window.pp3UpdateFreeExportAdsVisibility === 'function') {

                        window.pp3UpdateFreeExportAdsVisibility();

                    }

                });

            }

            const addZoneBtn = document.getElementById('pp3AdsAddZoneBtn');

            const addZoneBox = document.getElementById('pp3AdsAddZoneBox');

            if (addZoneBtn && addZoneBox) {

                addZoneBtn.addEventListener('click', () => {

                    if (pp3AdsZonesDraft.length >= 5) {

                        alert('Maximum 5 zones Monetag.');

                        return;

                    }

                    addZoneBox.classList.toggle('pp3-hidden');

                });

            }

            const validateZoneBtn = document.getElementById('pp3AdsZoneValidateBtn');

            if (validateZoneBtn) {

                validateZoneBtn.addEventListener('click', () => {

                    if (pp3AdsZonesDraft.length >= 5) {

                        alert('Maximum 5 zones Monetag.');

                        return;

                    }

                    const url = String(document.getElementById('pp3AdsZoneUrl')?.value || '').trim();

                    const zone = String(document.getElementById('pp3AdsZoneCode')?.value || '').trim();

                    if (!url || !/^https?:\/\//i.test(url)) {

                        alert('URL invalide (doit commencer par http(s)://).');

                        return;

                    }

                    if (!zone) {

                        alert('data-zone manquant.');

                        return;

                    }

                    pp3AdsZonesDraft.push({ url, zone });

                    pp3AdsZonesDraft = pp3AdsZonesDraft.slice(0, 5);

                    if (typeof window.pp3RenderAdsZonesList === 'function') window.pp3RenderAdsZonesList();

                    const box = document.getElementById('pp3AdsAddZoneBox');

                    if (box) box.classList.add('pp3-hidden');

                    const u = document.getElementById('pp3AdsZoneUrl');

                    const z = document.getElementById('pp3AdsZoneCode');

                    if (u) u.value = '';

                    if (z) z.value = '';

                });

            }

            if (typeof window.pp3RenderAdsZonesList === 'function') window.pp3RenderAdsZonesList();



            // Toggle login/register

            const showLoginBtn = document.getElementById('pp3ShowLoginBtn');

            const showRegisterBtn = document.getElementById('pp3ShowRegisterBtn');

            const loginBox = document.getElementById('pp3LoginBox');

            const registerBox = document.getElementById('pp3RegisterBox');

            const setAuthMode = (mode) => {

                if (showLoginBtn) showLoginBtn.classList.toggle('active', mode === 'login');

                if (showRegisterBtn) showRegisterBtn.classList.toggle('active', mode === 'register');

                if (loginBox) loginBox.classList.toggle('pp3-hidden', mode !== 'login');

                if (registerBox) registerBox.classList.toggle('pp3-hidden', mode !== 'register');

            };

            if (showLoginBtn) showLoginBtn.addEventListener('click', () => setAuthMode('login'));

            if (showRegisterBtn) showRegisterBtn.addEventListener('click', () => setAuthMode('register'));

            setAuthMode('login');



            // Actions

            const setupBtn = document.getElementById('pp3SetupDbBtn');

            if (setupBtn) {

                setupBtn.addEventListener('click', async () => {

                    try {

                        pp3SetMsg(document.getElementById('pp3AccountMsg'), 'Initialisation...');

                        const host = document.getElementById('pp3DbHost')?.value || '';

                        const dbname = document.getElementById('pp3DbName')?.value || '';

                        const user = document.getElementById('pp3DbUser')?.value || '';

                        const pass = document.getElementById('pp3DbPass')?.value || '';

                        const charset = document.getElementById('pp3DbCharset')?.value || 'utf8mb4';

                        await pp3Api('setup_db', { host, dbname, user, pass, charset });

                        window.location.reload();

                    } catch (e) {

                        pp3SetMsg(document.getElementById('pp3AccountMsg'), e.message || String(e));

                    }

                });

            }



            const loginBtn = document.getElementById('pp3LoginBtn');

            if (loginBtn) {

                loginBtn.addEventListener('click', async () => {

                    try {

                        const mail = document.getElementById('pp3LoginMail')?.value || '';

                        const pwd = document.getElementById('pp3LoginPwd')?.value || '';

                        pp3SetMsg(document.getElementById('pp3AccountMsg'), 'Connexion...');

                        const out = await pp3Api('login', { mail, pwd });

                        pp3State.logged = true;

                        pp3State.user = out.user;

                        pp3SetMsg(document.getElementById('pp3AccountMsg'), '');

                        pp3RenderAccountUI();

                    } catch (e) {

                        pp3SetMsg(document.getElementById('pp3AccountMsg'), e.message || String(e));

                    }

                });

            }



            const registerBtn = document.getElementById('pp3RegisterBtn');

            if (registerBtn) {

                registerBtn.addEventListener('click', async () => {

                    try {

                        const mail = document.getElementById('pp3RegisterMail')?.value || '';

                        const pwd = document.getElementById('pp3RegisterPwd')?.value || '';

                        pp3SetMsg(document.getElementById('pp3AccountMsg'), 'Création...');

                        const out = await pp3Api('register', { mail, pwd });

                        pp3State.logged = true;

                        pp3State.user = out.user;

                        pp3SetMsg(document.getElementById('pp3AccountMsg'), '');

                        pp3RenderAccountUI();

                    } catch (e) {

                        pp3SetMsg(document.getElementById('pp3AccountMsg'), e.message || String(e));

                    }

                });

            }



            const logoutBtn = document.getElementById('pp3LogoutBtn');

            if (logoutBtn) {

                logoutBtn.addEventListener('click', async () => {

                    try {

                        await pp3Api('logout', {});

                    } catch (_) {

                        // ignore

                    }

                    pp3State.logged = false;

                    pp3State.user = null;

                    pp3RenderAccountUI();

                });

            }



            const adminSaveBtn = document.getElementById('pp3AdminSaveBtn');

            if (adminSaveBtn) {

                adminSaveBtn.addEventListener('click', async () => {

                    try {

                        const msg = document.getElementById('pp3AdminMsg');

                        pp3SetMsg(msg, 'Enregistrement...');

                        const sk = document.getElementById('pp3StripeSk')?.value || '';

                        const pk = document.getElementById('pp3StripePk')?.value || '';

                        const opt = pp3CollectAdminOpt();

                        const out = await pp3Api('admin_save', { sk, pk, opt_json: JSON.stringify(opt) });

                        pp3State.admin = out.admin;

                        pp3SetMsg(msg, 'Enregistré.');

                        pp3RenderAdminUI();

                        pp3RenderAccountUI();

                    } catch (e) {

                        pp3SetMsg(document.getElementById('pp3AdminMsg'), e.message || String(e));

                    }

                });

            }



            // Boot: status + éventuelle confirmation Stripe

            (async () => {

                try {

                    const url = new URL(window.location.href);

                    const success = url.searchParams.get('pp3_success');

                    const sessionId = url.searchParams.get('session_id');

                    const plan = url.searchParams.get('plan');



                    const st = await pp3Api('status', {});

                    pp3State.configured = !!st.configured;

                    pp3State.logged = !!st.logged;

                    pp3State.user = st.user || null;

                    pp3State.admin = st.admin || null;

                    pp3State.csrf = st.csrf || null; // Stocker le token CSRF



                    if (success === '1' && sessionId && plan) {

                        try {

                            const out = await pp3Api('confirm_checkout', { session_id: sessionId, plan });

                            pp3State.logged = true;

                            pp3State.user = out.user;

                        } catch (e) {

                            // on affiche l'erreur dans le panneau compte

                            pp3SetMsg(document.getElementById('pp3AccountMsg'), e.message || String(e));

                        }

                        // nettoyer l'URL

                        url.searchParams.delete('pp3_success');

                        url.searchParams.delete('session_id');

                        url.searchParams.delete('plan');

                        window.history.replaceState({}, '', url.toString());

                    }



                    pp3RenderAccountUI();



                    // Ouvrir automatiquement le panneau compte si pas configuré

                    if (!pp3State.configured) {

                        pp3OpenAccountPanel();

                    }



                    // Préparer admin UI si nécessaire

                    pp3RenderAdminUI();

                } catch (e) {

                    // Ne pas casser l'app 3D si le backend n'est pas utilisable

                    console.warn('Compte/premium indisponible:', e);

                }

            })();

        }



        function getExportRootsFromObjects() {

            const roots = new Set();

            for (const obj of objects) {

                if (!obj) continue;

                let root = obj;

                while (root.parent && root.parent !== scene) root = root.parent;

                if (root && root.userData && root.userData.isHelper) continue;

                if (root) roots.add(root);

            }

            return Array.from(roots);

        }



        function makeDownload(blob, filename) {

            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');

            a.href = url;

            a.download = filename;

            document.body.appendChild(a);

            a.click();

            a.remove();

            setTimeout(() => URL.revokeObjectURL(url), 1000);

        }



        async function exportSceneAsGlb() {

            if (!gltfExporter) gltfExporter = new THREE.GLTFExporter();



            const readExportCameraUi = () => {

                const include = document.getElementById('exportIncludeCamera');

                const on = !include ? false : !!include.checked;

                const px = Number(document.getElementById('exportCameraPosX')?.value) || 0;

                const py = Number(document.getElementById('exportCameraPosY')?.value) || 0;

                const pz = Number(document.getElementById('exportCameraPosZ')?.value) || 0;

                const tx = Number(document.getElementById('exportCameraTargetX')?.value) || 0;

                const ty = Number(document.getElementById('exportCameraTargetY')?.value) || 0;

                const tz = Number(document.getElementById('exportCameraTargetZ')?.value) || 0;

                return { on, pos: new THREE.Vector3(px, py, pz), target: new THREE.Vector3(tx, ty, tz) };

            };



            // Masque temporairement les helpers/overlays (évite de polluer l'export)

            const hidden = [];

            scene.traverse((o) => {

                if (!o) return;

                const isHelper = (o.userData && (o.userData.isHelper || o.userData.isLightMarker)) || o.type === 'GridHelper' || o.type === 'AxesHelper';

                if (isHelper && o.visible) {

                    hidden.push(o);

                    o.visible = false;

                }

            });



            // Les TransformControls doivent être masqués sinon ils peuvent être exportés.

            if (transformControls && transformControls.visible) {

                hidden.push(transformControls);

                transformControls.visible = false;

            }



            // Caméra export (optionnelle)

            const camCfg = readExportCameraUi();

            let exportCam = null;

            if (camCfg && camCfg.on) {

                const fov = (camera && typeof camera.fov === 'number') ? camera.fov : 75;

                const aspect = (camera && typeof camera.aspect === 'number') ? camera.aspect : 1;

                const near = (camera && typeof camera.near === 'number') ? camera.near : 0.1;

                const far = (camera && typeof camera.far === 'number') ? camera.far : 1000;

                exportCam = new THREE.PerspectiveCamera(fov, aspect, near, far);

                exportCam.name = 'ExportCamera';

                exportCam.position.copy(camCfg.pos);

                exportCam.lookAt(camCfg.target);

                exportCam.updateMatrixWorld(true);

                scene.add(exportCam);

            }



            scene.updateMatrixWorld(true);



            const result = await new Promise((resolve, reject) => {

                const options = {

                    binary: true,

                    embedImages: true,

                    onlyVisible: true,

                    truncateDrawRange: true,

                    animations: [],

                };



                // Ajoute animations: capturée + importée (si présentes)

                if (capturedClip) options.animations.push(capturedClip);

                if (Array.isArray(importedClips) && importedClips.length > 0) {

                    for (const c of importedClips) options.animations.push(c);

                }



                // Ajoute animations créées dans l'éditeur

                try {

                    const userClips = getUserAnimExportClips();

                    for (const c of userClips) options.animations.push(c);

                } catch (_) {}



                // Ajoute animations fire flicker pour les point lights de type fire

                try {

                    const fireClips = getFireFlickerExportClips();

                    for (const c of fireClips) options.animations.push(c);

                } catch (_) {}



                // La signature de GLTFExporter.parse() varie selon les versions.

                // r128 (examples/js) utilise généralement: parse(input, onDone, options)

                // Certaines versions supportent: parse(input, onDone, onError, options)

                try {

                    if (gltfExporter.parse.length >= 4) {

                        gltfExporter.parse(scene, (res) => resolve(res), (err) => reject(err), options);

                    } else {

                        gltfExporter.parse(scene, (res) => resolve(res), options);

                    }

                } catch (err) {

                    reject(err);

                }

            });



            // Restore visibilité

            for (const o of hidden) {

                if (o) o.visible = true;

            }



            if (exportCam) {

                try { scene.remove(exportCam); } catch (_) {}

            }



            let buffer = null;

            if (result instanceof ArrayBuffer) buffer = result;

            else if (result && result.buffer instanceof ArrayBuffer) buffer = result.buffer;



            if (!buffer) {

                throw new Error('Export GLB: résultat inattendu (non binaire).');

            }



            const blob = new Blob([buffer], { type: 'model/gltf-binary' });

            makeDownload(blob, 'scene.glb');

        }



        function initRigUI() {

            const rigBtn = document.getElementById('rig-btn');

            const moveMode = document.getElementById('rigMoveMode');

            const captureBtn = document.getElementById('capturePoseBtn');

            const playBtn = document.getElementById('playAnimBtn');

            const pauseBtn = document.getElementById('pauseAnimBtn');

            const resetBtn = document.getElementById('resetAnimBtn');

            const clearBtn = document.getElementById('clearAnimBtn');

            const timeInput = document.getElementById('animTime');

            const durInput = document.getElementById('animDuration');

            const createRigBtn = document.getElementById('createRigBtn');



            // Captures (éditeur)

            const capturedKeysSelect = document.getElementById('capturedKeysSelect');

            const previewCapturedBtn = document.getElementById('previewCapturedBtn');

            const deleteCapturedBtn = document.getElementById('deleteCapturedBtn');

            const capturedNewTime = document.getElementById('capturedNewTime');

            const applyCapturedTimeBtn = document.getElementById('applyCapturedTimeBtn');



            // Animations importées

            const clipSelect = document.getElementById('importedClipSelect');

            const playImportedBtn = document.getElementById('playImportedBtn');

            const pauseImportedBtn = document.getElementById('pauseImportedBtn');

            const stopImportedBtn = document.getElementById('stopImportedBtn');

            const loopInf = document.getElementById('importedLoopInfinite');

            const loopCount = document.getElementById('importedLoopCount');

            const speed = document.getElementById('importedSpeed');

            const speedVal = document.getElementById('importedSpeedValue');

            const reverse = document.getElementById('importedReverse');

            const boomerang = document.getElementById('importedBoomerang');

            const startDelay = document.getElementById('importedStartDelay');

            const loopDelay = document.getElementById('importedLoopDelay');

            const fps = document.getElementById('importedFps');

            const fpsVal = document.getElementById('importedFpsValue');



            // Auto-rotation

            const arEnabled = document.getElementById('autoRotateEnabled');

            const arSpeed = document.getElementById('autoRotateSpeed');

            const arSpeedVal = document.getElementById('autoRotateSpeedValue');



            if (rigBtn) {

                rigBtn.addEventListener('click', () => {

                    setTool('rig');

                });

            }



            if (createRigBtn) {

                createRigBtn.addEventListener('click', () => {

                    if (!selectedObject) return;

                    // Si déjà skinned, rien à faire

                    if (selectedObject.isSkinnedMesh) return;

                    const skinned = createAutoRigForMesh(selectedObject);

                    if (skinned) {

                        // Active rig sur le root

                        const root = getRootForObject(skinned) || skinned;

                        const rig = detectRig(root);

                        if (rig) {

                            activeRig = rig;

                            updateRigPanelUI();

                            updateToolButtonsEnabledState();

                        }

                    }

                });

            }



            if (moveMode) {

                moveMode.addEventListener('change', () => {

                    if (!transformControls) return;

                    const v = moveMode.value || 'rotate';

                    transformControls.setMode(v === 'translate' ? 'translate' : 'rotate');

                });

            }



            if (captureBtn && timeInput && durInput) {

                captureBtn.addEventListener('click', () => {

                    const t = normalizeTimeSec(timeInput.value);

                    const d = Math.max(0.05, normalizeTimeSec(durInput.value || 2));

                    capturePoseAtTime(t, d);

                    updateRigPanelUI();

                });

            }



            if (playBtn) {

                playBtn.addEventListener('click', () => {

                    if (!capturedClip) return;

                    // Lecture manuelle (garantit l'animation des bones)

                    isAnimPlaying = true;

                });

            }



            if (pauseBtn) {

                pauseBtn.addEventListener('click', () => {

                    isAnimPlaying = false;

                });

            }



            if (resetBtn) {

                resetBtn.addEventListener('click', () => {

                    isAnimPlaying = false;

                    capturedPlayTime = 0;

                    applyCapturedAnimationAtTime(0);

                });

            }



            if (clearBtn) {

                clearBtn.addEventListener('click', () => {

                    captureTracks = null;

                    capturedClip = null;

                    capturedKeyTimes = [];

                    isAnimPlaying = false;

                    capturedPlayTime = 0;

                    updateCapturedKeysUI();

                });

            }



            if (previewCapturedBtn && capturedKeysSelect) {

                previewCapturedBtn.addEventListener('click', () => {

                    const t = normalizeTimeSec(capturedKeysSelect.value);

                    previewCapturedPose(t);

                });

            }

            if (deleteCapturedBtn && capturedKeysSelect) {

                deleteCapturedBtn.addEventListener('click', () => {

                    const t = normalizeTimeSec(capturedKeysSelect.value);

                    deleteCapturedKeyframe(t);

                });

            }

            if (applyCapturedTimeBtn && capturedKeysSelect && capturedNewTime) {

                applyCapturedTimeBtn.addEventListener('click', () => {

                    const oldT = normalizeTimeSec(capturedKeysSelect.value);

                    const newT = normalizeTimeSec(capturedNewTime.value);

                    moveCapturedKeyframeTime(oldT, newT);

                });

            }



            // --- Animations importées ---

            if (clipSelect) {

                clipSelect.addEventListener('change', () => {

                    const idx = parseInt(clipSelect.value, 10);

                    if (Number.isFinite(idx)) setImportedClipIndex(idx);

                });

            }



            if (loopInf && loopCount) {

                loopInf.addEventListener('change', () => {

                    importedLoopInfinite = !!loopInf.checked;

                    loopCount.disabled = importedLoopInfinite;

                    applyImportedLoopSettings();

                });

                loopCount.addEventListener('input', () => {

                    importedLoopCount = Math.max(1, parseInt(loopCount.value, 10) || 1);

                    applyImportedLoopSettings();

                });

            }



            if (speed && speedVal) {

                speedVal.textContent = (parseFloat(speed.value) || 1).toFixed(1);

                speed.addEventListener('input', () => {

                    importedSpeed = Math.max(0.1, parseFloat(speed.value) || 1);

                    speedVal.textContent = importedSpeed.toFixed(1);

                    applyImportedTimeScale();

                });

            }



            if (reverse) {

                reverse.addEventListener('change', () => {

                    importedReverse = !!reverse.checked;

                    applyImportedTimeScale();

                });

            }



            if (boomerang) {

                boomerang.addEventListener('change', () => {

                    importedBoomerang = !!boomerang.checked;

                    applyImportedLoopSettings();

                });

            }



            if (startDelay) {

                startDelay.addEventListener('input', () => {

                    importedStartDelaySec = Math.max(0, parseFloat(startDelay.value) || 0);

                });

            }



            if (loopDelay) {

                loopDelay.addEventListener('input', () => {

                    importedLoopDelaySec = Math.max(0, parseFloat(loopDelay.value) || 0);

                });

            }



            if (fps && fpsVal) {

                fpsVal.textContent = String(parseInt(fps.value, 10) || 60);

                fps.addEventListener('input', () => {

                    importedFps = clamp(parseInt(fps.value, 10) || 60, 1, 200);

                    fpsVal.textContent = String(importedFps);

                });

            }



            if (playImportedBtn) playImportedBtn.addEventListener('click', () => playImportedAnimation());

            if (pauseImportedBtn) pauseImportedBtn.addEventListener('click', () => { importedIsPlaying = false; });

            if (stopImportedBtn) stopImportedBtn.addEventListener('click', () => stopImportedAnimation());



            // --- Auto-rotation ---

            if (arEnabled) {

                arEnabled.addEventListener('change', () => {

                    autoRotateEnabled = !!arEnabled.checked;

                });

            }

            if (arSpeed && arSpeedVal) {

                arSpeedVal.textContent = String(parseInt(arSpeed.value, 10) || 20);

                arSpeed.addEventListener('input', () => {

                    autoRotateSpeedDeg = clamp(parseInt(arSpeed.value, 10) || 0, 0, 360);

                    arSpeedVal.textContent = String(autoRotateSpeedDeg);

                });

            }



            updateRigPanelUI();

            updateImportedAnimUI();

            updateCapturedKeysUI();

        }



        function updateCapturedKeysUI() {

            const sel = document.getElementById('capturedKeysSelect');

            const tInput = document.getElementById('capturedNewTime');

            if (!sel) return;



            const times = Array.isArray(capturedKeyTimes) ? capturedKeyTimes.slice() : [];

            times.sort((a, b) => a - b);

            capturedKeyTimes = times;



            sel.innerHTML = '';

            if (times.length === 0) {

                sel.disabled = true;

                const opt = document.createElement('option');

                opt.value = '';

                opt.textContent = 'Aucune capture';

                sel.appendChild(opt);

                if (tInput) tInput.value = '0';

                return;

            }



            sel.disabled = false;

            for (const t of times) {

                const opt = document.createElement('option');

                opt.value = String(t);

                opt.textContent = `${t.toFixed(3)} s`;

                sel.appendChild(opt);

            }

            if (tInput) tInput.value = String(times[0]);

            sel.onchange = () => {

                const v = normalizeTimeSec(sel.value);

                if (tInput) tInput.value = String(v);

            };

        }



        function previewCapturedPose(timeSec) {

            if (!capturedClip) return;

            isAnimPlaying = false;

            const t = clamp(timeSec, 0, capturedClip.duration || timeSec);

            capturedPlayTime = t;

            applyCapturedAnimationAtTime(t);

        }



        function deleteCapturedKeyframe(timeSec) {

            const t = normalizeTimeSec(timeSec);

            if (!captureTracks) return;



            for (const name of Object.keys(captureTracks)) {

                const kinds = captureTracks[name];

                for (const k of ['pos', 'quat', 'scale']) {

                    const tr = kinds[k];

                    if (!tr || !Array.isArray(tr.times) || !Array.isArray(tr.values)) continue;

                    const stride = (k === 'quat') ? 4 : 3;

                    const nextTimes = [];

                    const nextVals = [];



                    for (let i = 0; i < tr.times.length; i++) {

                        const tt = normalizeTimeSec(tr.times[i]);

                        if (Math.abs(tt - t) < 1e-6) continue;

                        nextTimes.push(tt);

                        const base = i * stride;

                        for (let s = 0; s < stride; s++) nextVals.push(tr.values[base + s]);

                    }

                    tr.times = nextTimes;

                    tr.values = nextVals;

                }

            }



            capturedKeyTimes = (capturedKeyTimes || []).filter(x => Math.abs(x - t) > 1e-6);

            const durInput = document.getElementById('animDuration');

            const duration = Math.max(0.05, normalizeTimeSec(durInput ? durInput.value : 2));

            capturedClip = rebuildCapturedClip(duration);

            ensureMixerAndAction();

            updateCapturedKeysUI();

        }



        function moveCapturedKeyframeTime(oldTimeSec, newTimeSec) {

            const oldT = normalizeTimeSec(oldTimeSec);

            const newT = normalizeTimeSec(newTimeSec);

            if (!captureTracks) return;

            if (Math.abs(oldT - newT) < 1e-6) return;



            for (const name of Object.keys(captureTracks)) {

                const kinds = captureTracks[name];

                for (const k of ['pos', 'quat', 'scale']) {

                    const tr = kinds[k];

                    if (!tr || !Array.isArray(tr.times)) continue;

                    for (let i = 0; i < tr.times.length; i++) {

                        const tt = normalizeTimeSec(tr.times[i]);

                        if (Math.abs(tt - oldT) < 1e-6) tr.times[i] = newT;

                    }

                }

            }



            capturedKeyTimes = (capturedKeyTimes || []).map(x => (Math.abs(x - oldT) < 1e-6 ? newT : x));

            capturedKeyTimes = Array.from(new Set(capturedKeyTimes.map(normalizeTimeSec)));



            const durInput = document.getElementById('animDuration');

            const duration = Math.max(0.05, normalizeTimeSec(durInput ? durInput.value : 2));

            capturedClip = rebuildCapturedClip(duration);

            ensureMixerAndAction();

            updateCapturedKeysUI();

        }



        function getRootForObject(obj) {

            if (!obj) return null;

            let r = obj;

            while (r.parent && r.parent !== scene) r = r.parent;

            return r;

        }



        function setImportedAnimations(root, clips) {

            importedTargetRoot = root || null;

            importedClips = Array.isArray(clips) ? clips : [];

            importedAccumulator = 0;

            importedIsPlaying = false;

            importedMixerLoopListenerAttached = false;

            if (importedAction) {

                try { importedAction.stop(); } catch (_) {}

                importedAction = null;

            }

            importedMixer = null;

            if (importedTargetRoot && importedClips.length > 0) {

                importedMixer = new THREE.AnimationMixer(importedTargetRoot);

                attachImportedMixerLoopListener();

            }

            updateImportedAnimUI();

        }



        function replaceObjectInList(oldObj, newObj) {

            if (!oldObj || !newObj) return;

            for (let i = 0; i < objects.length; i++) {

                if (objects[i] === oldObj) {

                    objects[i] = newObj;

                }

            }

        }



        function createAutoRigForMesh(mesh) {

            try {

                if (!mesh || !mesh.geometry) return null;

                const parent = mesh.parent || scene;



                // Clone geometry/material

                const geom = mesh.geometry.clone();

                geom.computeBoundingBox();

                const box = geom.boundingBox;

                if (!box) return null;



                // Positions clés (local)

                const min = box.min;

                const max = box.max;

                const cx = (min.x + max.x) / 2;

                const cy = (min.y + max.y) / 2;

                const cz = (min.z + max.z) / 2;



                const cornerPts = [

                    new THREE.Vector3(min.x, min.y, min.z),

                    new THREE.Vector3(max.x, min.y, min.z),

                    new THREE.Vector3(min.x, max.y, min.z),

                    new THREE.Vector3(max.x, max.y, min.z),

                    new THREE.Vector3(min.x, min.y, max.z),

                    new THREE.Vector3(max.x, min.y, max.z),

                    new THREE.Vector3(min.x, max.y, max.z),

                    new THREE.Vector3(max.x, max.y, max.z),

                ];

                const edgeMidPts = [

                    new THREE.Vector3(cx, min.y, min.z),

                    new THREE.Vector3(cx, min.y, max.z),

                    new THREE.Vector3(cx, max.y, min.z),

                    new THREE.Vector3(cx, max.y, max.z),

                    new THREE.Vector3(min.x, cy, min.z),

                    new THREE.Vector3(min.x, cy, max.z),

                    new THREE.Vector3(max.x, cy, min.z),

                    new THREE.Vector3(max.x, cy, max.z),

                    new THREE.Vector3(min.x, min.y, cz),

                    new THREE.Vector3(min.x, max.y, cz),

                    new THREE.Vector3(max.x, min.y, cz),

                    new THREE.Vector3(max.x, max.y, cz),

                ];



                // Bones: root + corners + edges

                const rootBone = new THREE.Bone();

                rootBone.name = 'AutoRig_Root';

                rootBone.position.set(cx, cy, cz);



                const bones = [rootBone];

                const bonePositions = [new THREE.Vector3(cx, cy, cz)];



                const makeChildBone = (pos, name) => {

                    const b = new THREE.Bone();

                    b.name = name;

                    b.position.copy(pos).sub(new THREE.Vector3(cx, cy, cz));

                    rootBone.add(b);

                    bones.push(b);

                    bonePositions.push(pos.clone());

                };



                cornerPts.forEach((p, i) => makeChildBone(p, 'AutoRig_Corner_' + (i + 1)));

                edgeMidPts.forEach((p, i) => makeChildBone(p, 'AutoRig_Edge_' + (i + 1)));



                // Skinning: 4 influences max, poids par distance

                const posAttr = geom.attributes.position;

                const vcount = posAttr.count;

                const skinIndices = new Uint16Array(vcount * 4);

                const skinWeights = new Float32Array(vcount * 4);



                const eps = 1e-6;

                const tmp = new THREE.Vector3();

                for (let i = 0; i < vcount; i++) {

                    tmp.set(posAttr.getX(i), posAttr.getY(i), posAttr.getZ(i));



                    // distances vers tous les bones (en local)

                    const dists = bonePositions.map((bp, bi) => {

                        const d = tmp.distanceTo(bp);

                        return { bi, d };

                    });

                    dists.sort((a, b) => a.d - b.d);

                    const top = dists.slice(0, 4);



                    let sum = 0;

                    const w = top.map(x => {

                        const inv = 1 / (x.d + eps);

                        sum += inv;

                        return inv;

                    });

                    for (let k = 0; k < 4; k++) {

                        const item = top[k];

                        const wi = (sum > 0) ? (w[k] / sum) : (k === 0 ? 1 : 0);

                        skinIndices[i * 4 + k] = item ? item.bi : 0;

                        skinWeights[i * 4 + k] = wi;

                    }

                }



                geom.setAttribute('skinIndex', new THREE.BufferAttribute(skinIndices, 4));

                geom.setAttribute('skinWeight', new THREE.BufferAttribute(skinWeights, 4));



                // Matériau

                let mat = mesh.material;

                if (Array.isArray(mat)) {

                    // SkinnedMesh + multi-material: on conserve mais on clone

                    mat = mat.map(m => (m && m.isMaterial ? m.clone() : m));

                } else if (mat && mat.isMaterial) {

                    mat = mat.clone();

                }



                const skinned = new THREE.SkinnedMesh(geom, mat);

                skinned.name = (mesh.name ? mesh.name : 'Mesh') + '_Skinned';

                skinned.userData = Object.assign({}, mesh.userData || {}, { type: (mesh.userData && mesh.userData.type) ? mesh.userData.type : 'skinned' });



                // Copie transforms monde (local par rapport au parent)

                skinned.position.copy(mesh.position);

                skinned.rotation.copy(mesh.rotation);

                skinned.scale.copy(mesh.scale);



                // Shadows

                skinned.castShadow = true;

                skinned.receiveShadow = true;



                // Bind skeleton

                skinned.add(rootBone);

                const skeleton = new THREE.Skeleton(bones);

                skinned.bind(skeleton);



                // Remplace dans scène

                parent.add(skinned);

                parent.remove(mesh);

                replaceObjectInList(mesh, skinned);



                // Sélectionne le nouveau mesh

                selectObject(skinned);

                return skinned;

            } catch (e) {

                console.error('createAutoRigForMesh error', e);

                return null;

            }

        }



        function attachImportedMixerLoopListener() {

            if (!importedMixer || importedMixerLoopListenerAttached) return;

            importedMixer.addEventListener('loop', () => {

                // Pause volontaire entre boucles

                if (importedLoopDelaySec > 0) {

                    importedLoopDelayRemaining = importedLoopDelaySec;

                }

            });

            importedMixerLoopListenerAttached = true;

        }



        function updateImportedAnimUI() {

            const clipSelect = document.getElementById('importedClipSelect');

            const status = document.getElementById('importedAnimStatus');

            const loopInf = document.getElementById('importedLoopInfinite');

            const loopCount = document.getElementById('importedLoopCount');

            const speed = document.getElementById('importedSpeed');

            const speedVal = document.getElementById('importedSpeedValue');

            const reverse = document.getElementById('importedReverse');

            const boomerang = document.getElementById('importedBoomerang');

            const startDelay = document.getElementById('importedStartDelay');

            const loopDelay = document.getElementById('importedLoopDelay');

            const fps = document.getElementById('importedFps');

            const fpsVal = document.getElementById('importedFpsValue');

            if (!clipSelect || !status) return;



            if (!importedClips || importedClips.length === 0) {

                clipSelect.innerHTML = '<option value="">Aucune</option>';

                clipSelect.disabled = true;

                status.textContent = 'Aucune animation importée.';

            } else {

                const prev = clipSelect.value;

                clipSelect.innerHTML = '';

                importedClips.forEach((c, i) => {

                    const opt = document.createElement('option');

                    opt.value = String(i);

                    opt.textContent = c && c.name ? c.name : ('Clip ' + (i + 1));

                    clipSelect.appendChild(opt);

                });

                clipSelect.disabled = false;

                clipSelect.value = prev !== '' ? prev : '0';

                status.textContent = `${importedClips.length} animation(s) importée(s).`;

            }



            if (loopInf) loopInf.checked = importedLoopInfinite;

            if (loopCount) { loopCount.value = String(importedLoopCount); loopCount.disabled = importedLoopInfinite; }

            if (speed) speed.value = String(importedSpeed);

            if (speedVal) speedVal.textContent = importedSpeed.toFixed(1);

            if (reverse) reverse.checked = importedReverse;

            if (boomerang) boomerang.checked = importedBoomerang;

            if (startDelay) startDelay.value = String(importedStartDelaySec);

            if (loopDelay) loopDelay.value = String(importedLoopDelaySec);

            if (fps) fps.value = String(importedFps);

            if (fpsVal) fpsVal.textContent = String(importedFps);

        }



        function setImportedClipIndex(idx) {

            const i = clamp(idx, 0, Math.max(0, (importedClips ? importedClips.length - 1 : 0)));

            const clipSelect = document.getElementById('importedClipSelect');

            if (clipSelect) clipSelect.value = String(i);

        }



        function applyImportedLoopSettings() {

            if (!importedAction) return;

            const loopMode = importedBoomerang ? THREE.LoopPingPong : THREE.LoopRepeat;

            if (importedLoopInfinite) {

                importedAction.setLoop(loopMode, Infinity);

                importedAction.clampWhenFinished = false;

            } else {

                const reps = Math.max(1, importedLoopCount || 1);

                if (reps === 1 && !importedBoomerang) {

                    importedAction.setLoop(THREE.LoopOnce, 1);

                    importedAction.clampWhenFinished = true;

                } else {

                    importedAction.setLoop(loopMode, reps);

                    importedAction.clampWhenFinished = true;

                }

            }

        }



        function applyImportedTimeScale() {

            if (!importedAction) return;

            const s = Math.max(0.1, importedSpeed || 1);

            const ts = importedReverse ? -s : s;

            if (typeof importedAction.setEffectiveTimeScale === 'function') importedAction.setEffectiveTimeScale(ts);

            else importedAction.timeScale = ts;

        }



        function playImportedAnimation(forcedIdx) {

            const clipSelect = document.getElementById('importedClipSelect');

            if (!importedMixer || !importedClips || importedClips.length === 0) return;

            const idx = Number.isFinite(forcedIdx)

                ? clamp(parseInt(forcedIdx, 10) || 0, 0, importedClips.length - 1)

                : (clipSelect ? (parseInt(clipSelect.value, 10) || 0) : 0);

            const clip = importedClips[idx];

            if (!clip) return;



            if (importedAction) {

                try { importedAction.stop(); } catch (_) {}

                importedAction = null;

            }

            importedAction = importedMixer.clipAction(clip);

            importedAction.reset();

            applyImportedLoopSettings();

            applyImportedTimeScale();



            importedStartDelayRemaining = Math.max(0, importedStartDelaySec || 0);

            importedLoopDelayRemaining = 0;



            // Si reverse, on part de la fin

            if (importedReverse) {

                importedAction.time = clip.duration;

            }



            importedAction.play();

            importedIsPlaying = true;

            importedAccumulator = 0;

        }



        function stopImportedAnimation() {

            importedIsPlaying = false;

            importedAccumulator = 0;

            if (importedAction) {

                try { importedAction.stop(); } catch (_) {}

                importedAction = null;

            }

            if (importedMixer && typeof importedMixer.setTime === 'function') {

                try { importedMixer.setTime(0); } catch (_) {}

            }

        }



        function onWindowResize() {

            const container = document.querySelector('.viewer-container');

            camera.aspect = container.offsetWidth / container.offsetHeight;

            camera.updateProjectionMatrix();

            renderer.setSize(container.offsetWidth, container.offsetHeight);

            applyRenderQuality();

        }



        function applyRenderQuality() {

            if (!renderer) return;



            // Pixel ratio (netteté)

            const dpr = window.devicePixelRatio || 1;

            const pr = Math.min(dpr, desiredPixelRatio);

            renderer.setPixelRatio(pr);

        }



        function applyShadowQualityToLight(pl) {

            if (!pl) return;

            const q = shadowQuality;

            if (q === 'off') {

                pl.castShadow = false;

                return;

            }

            pl.castShadow = true;

            const size = q === 'low' ? 1024 : (q === 'medium' ? 2048 : 4096);

            pl.shadow.mapSize.width = size;

            pl.shadow.mapSize.height = size;

            pl.shadow.radius = q === 'high' ? 2.5 : 1.5;

            pl.shadow.bias = -0.0003;

            pl.shadow.normalBias = 0.02;

            // Une zone d'ombre un peu plus large

            pl.shadow.camera.near = 0.5;

            pl.shadow.camera.far = 120;

        }



        function applyShadowQualityAllLights() {

            if (!renderer) return;

            if (shadowQuality === 'off') {

                renderer.shadowMap.enabled = false;

            } else {

                renderer.shadowMap.enabled = true;

            }

            applyShadowQualityToLight(light);

            for (const pl of pointLights) applyShadowQualityToLight(pl);

        }



        function initQualityUI() {

            const qualitySelect = document.getElementById('renderQuality');

            const exposure = document.getElementById('exposure');

            const exposureValue = document.getElementById('exposureValue');

            const shadowSelect = document.getElementById('shadowQuality');



            if (qualitySelect) {

                qualitySelect.addEventListener('change', () => {

                    renderQuality = qualitySelect.value || 'balanced';

                    // Limites par preset

                    if (renderQuality === 'performance') desiredPixelRatio = 1;

                    if (renderQuality === 'balanced') desiredPixelRatio = 1.5;

                    if (renderQuality === 'hd') desiredPixelRatio = 2;

                    applyRenderQuality();

                });

            }



            if (exposure && exposureValue) {

                exposureValue.textContent = (parseFloat(exposure.value) || 1).toFixed(2);

                exposure.addEventListener('input', () => {

                    const v = parseFloat(exposure.value) || 1;

                    exposureValue.textContent = v.toFixed(2);

                    if (renderer) renderer.toneMappingExposure = v;

                });

            }



            if (shadowSelect) {

                shadowSelect.addEventListener('change', () => {

                    shadowQuality = shadowSelect.value || 'medium';

                    applyShadowQualityAllLights();

                });

            }

        }



        function initPointLightsUI() {

            const toggleBtn = document.getElementById('togglePlaceLightBtn');

            const addAtSelBtn = document.getElementById('addPointLightAtSelectionBtn');

            const typeSel = document.getElementById('placeLightType');



            if (typeSel) {

                placingPointLightType = typeSel.value || 'static';

                typeSel.addEventListener('change', () => {

                    placingPointLightType = typeSel.value || 'static';

                    if (toggleBtn && isPlacingPointLight) {

                        toggleBtn.textContent = 'Placement actif (' + placingPointLightType + ')';

                    }

                });

            }



            if (toggleBtn) {

                toggleBtn.addEventListener('click', () => {

                    isPlacingPointLight = !isPlacingPointLight;

                    toggleBtn.textContent = isPlacingPointLight ? ('Placement actif (' + (placingPointLightType || 'static') + ')') : 'Placer un point light';

                });

            }



            if (addAtSelBtn) {

                addAtSelBtn.addEventListener('click', () => {

                    if (!selectedObject) return;

                    const p = selectedObject.position.clone();

                    // Position monde (approx)

                    const pw = p.applyMatrix4(selectedObject.parent ? selectedObject.parent.matrixWorld : new THREE.Matrix4());

                    addPointLight(pw.x, pw.y + 2, pw.z, placingPointLightType || 'static');

                });

            }



            // Panel selected light

            const panel = document.getElementById('selectedLightPanel');

            const typeEdit = document.getElementById('selectedLightType');

            const flickerRow = document.getElementById('selectedLightFlickerRow');

            const flickerCb = document.getElementById('selectedLightFlicker');

            const c = document.getElementById('selectedLightColor');

            const inten = document.getElementById('selectedLightIntensity');

            const intenVal = document.getElementById('selectedLightIntensityValue');

            const xS = document.getElementById('selectedLightXSlider');

            const yS = document.getElementById('selectedLightYSlider');

            const zS = document.getElementById('selectedLightZSlider');

            const xV = document.getElementById('selectedLightX');

            const yV = document.getElementById('selectedLightY');

            const zV = document.getElementById('selectedLightZ');

            const del = document.getElementById('deleteSelectedLightBtn');



            const refresh = () => {

                const has = !!selectedPointLight;

                if (panel) panel.style.display = has ? 'block' : 'none';

                if (!has) return;



                const t = (selectedPointLight.userData && selectedPointLight.userData.type) ? selectedPointLight.userData.type : 'static';

                if (typeEdit) typeEdit.value = t;

                const showFlicker = (t === 'fire');

                if (flickerRow) flickerRow.style.display = showFlicker ? 'block' : 'none';

                if (flickerCb) flickerCb.checked = !!(selectedPointLight.userData && selectedPointLight.userData.flicker);



                if (c) c.value = '#' + selectedPointLight.color.getHexString();

                if (inten && intenVal) {

                    inten.value = String(selectedPointLight.intensity);

                    intenVal.textContent = selectedPointLight.intensity.toFixed(1);

                }

                if (xS && xV) { xS.value = String(selectedPointLight.position.x); xV.textContent = selectedPointLight.position.x.toFixed(1); }

                if (yS && yV) { yS.value = String(selectedPointLight.position.y); yV.textContent = selectedPointLight.position.y.toFixed(1); }

                if (zS && zV) { zS.value = String(selectedPointLight.position.z); zV.textContent = selectedPointLight.position.z.toFixed(1); }

            };



            const applyPointLightType = (pl, type) => {

                if (!pl) return;

                const t = type || 'static';

                pl.userData = pl.userData || {};

                pl.userData.type = t;



                // Presets simples (PointLight)

                if (t === 'mini') {

                    pl.distance = 35;

                    pl.decay = 2;

                    pl.intensity = 0.6;

                    pl.userData.flicker = false;

                } else if (t === 'sun') {

                    pl.distance = 0; // infini

                    pl.decay = 2;

                    pl.intensity = 2.2;

                    pl.userData.flicker = false;

                } else if (t === 'fire') {

                    pl.distance = 55;

                    pl.decay = 2;

                    pl.intensity = 1.35;

                    pl.userData.flicker = true;

                    // teinte feu par défaut si la couleur est encore blanche

                    if (pl.color && pl.color.getHexString && pl.color.getHexString() === 'ffffff') {

                        pl.color.set('#ff7a18');

                    }

                } else {

                    // static

                    pl.distance = 120;

                    pl.decay = 2;

                    pl.intensity = 1.2;

                    pl.userData.flicker = false;

                }



                pl.userData.baseIntensity = pl.intensity;

                pl.castShadow = shadowQuality !== 'off';

                applyShadowQualityToLight(pl);



                if (pl.userData && pl.userData.helper && pl.userData.helper.material) {

                    const col = (t === 'fire') ? 0xff7a18 : (t === 'sun') ? 0xfff3cc : (t === 'mini') ? 0xaaffff : 0xffffaa;

                    try { pl.userData.helper.material.color.setHex(col); } catch (_) {}

                }

            };



            if (typeEdit) {

                typeEdit.addEventListener('change', () => {

                    if (!selectedPointLight) return;

                    applyPointLightType(selectedPointLight, typeEdit.value);

                    updatePointLightsList();

                    refresh();

                });

            }



            if (flickerCb) {

                flickerCb.addEventListener('change', () => {

                    if (!selectedPointLight) return;

                    selectedPointLight.userData = selectedPointLight.userData || {};

                    selectedPointLight.userData.flicker = !!flickerCb.checked;

                    selectedPointLight.userData.baseIntensity = selectedPointLight.intensity;

                    refresh();

                });

            }



            if (c) c.addEventListener('input', () => {

                if (!selectedPointLight) return;

                selectedPointLight.color.set(c.value);

            });



            if (inten && intenVal) inten.addEventListener('input', () => {

                if (!selectedPointLight) return;

                const v = parseFloat(inten.value) || 0;

                selectedPointLight.intensity = v;

                selectedPointLight.userData = selectedPointLight.userData || {};

                selectedPointLight.userData.baseIntensity = v;

                intenVal.textContent = v.toFixed(1);

            });



            const bindPos = (slider, label, axis) => {

                if (!slider || !label) return;

                slider.addEventListener('input', () => {

                    if (!selectedPointLight) return;

                    const v = parseFloat(slider.value) || 0;

                    selectedPointLight.position[axis] = v;

                    label.textContent = v.toFixed(1);

                    if (selectedPointLight.userData && selectedPointLight.userData.helper) {

                        selectedPointLight.userData.helper.position.copy(selectedPointLight.position);

                    }

                });

            };

            bindPos(xS, xV, 'x');

            bindPos(yS, yV, 'y');

            bindPos(zS, zV, 'z');



            if (del) del.addEventListener('click', () => {

                if (!selectedPointLight) return;

                removePointLight(selectedPointLight);

                selectedPointLight = null;

                updatePointLightsList();

                refresh();

            });



            // expose refresh

            window.__refreshSelectedLightPanel = refresh;

            refresh();

        }



        function addPointLight(x, y, z, type) {

            const pl = new THREE.PointLight(0xffffff, 1.2, 120);

            pl.position.set(x, y, z);

            pl.castShadow = shadowQuality !== 'off';

            applyShadowQualityToLight(pl);

            pl.userData = { id: Date.now() + Math.random(), type: (type || 'static') };

            scene.add(pl);

            pointLights.push(pl);



            // petit repère visible

            const marker = new THREE.Mesh(

                new THREE.SphereGeometry(0.15, 12, 12),

                new THREE.MeshBasicMaterial({ color: 0xffffaa })

            );

            marker.position.copy(pl.position);

            marker.userData.isLightMarker = true;

            scene.add(marker);

            pl.userData.helper = marker;



            // Preset

            if (pl.userData.type) {

                const t = pl.userData.type;

                if (t === 'mini') {

                    pl.distance = 35; pl.decay = 2; pl.intensity = 0.6; pl.userData.flicker = false;

                    marker.material.color.setHex(0xaaffff);

                } else if (t === 'sun') {

                    pl.distance = 0; pl.decay = 2; pl.intensity = 2.2; pl.userData.flicker = false;

                    marker.material.color.setHex(0xfff3cc);

                } else if (t === 'fire') {

                    pl.distance = 55; pl.decay = 2; pl.intensity = 1.35; pl.userData.flicker = true;

                    pl.color.set('#ff7a18');

                    marker.material.color.setHex(0xff7a18);

                } else {

                    pl.distance = 120; pl.decay = 2; pl.intensity = 1.2; pl.userData.flicker = false;

                    marker.material.color.setHex(0xffffaa);

                }

                pl.userData.baseIntensity = pl.intensity;

            }



            selectedPointLight = pl;

            updatePointLightsList();

            if (window.__refreshSelectedLightPanel) window.__refreshSelectedLightPanel();

        }



        function removePointLight(pl) {

            if (!pl) return;

            scene.remove(pl);

            if (pl.userData && pl.userData.helper) {

                scene.remove(pl.userData.helper);

            }

            pointLights = pointLights.filter(x => x !== pl);

        }



        function updatePointLightsList() {

            const list = document.getElementById('pointLightsList');

            if (!list) return;

            list.innerHTML = '';



            pointLights.forEach((pl, idx) => {

                const li = document.createElement('li');

                li.className = 'object-item' + (selectedPointLight === pl ? ' selected' : '');

                const t = (pl.userData && pl.userData.type) ? pl.userData.type : 'static';

                li.textContent = 'PointLight ' + (idx + 1) + ' (' + t + ')';

                li.onclick = () => {

                    selectedPointLight = pl;

                    updatePointLightsList();

                    if (window.__refreshSelectedLightPanel) window.__refreshSelectedLightPanel();

                };

                list.appendChild(li);

            });

        }



        function animate() {

            requestAnimationFrame(animate);

            if (controls && !(userAnimIsPlaying && userAnimAffectsCamera)) controls.update();



            // Delta unique

            const delta = clock.getDelta();



            // Animation capturée

            if (isAnimPlaying && captureTracks && capturedClip) {

                advanceCapturedAnimation(delta);

            }



            // Animations utilisateur (keyframes)

            if (userAnimIsPlaying && userAnimActiveAnim) {

                advanceUserAnimation(userAnimActiveAnim, delta);

            }



            // Animations importées (avec stepping FPS)

            if (importedMixer && importedIsPlaying) {

                // Délais

                if (importedStartDelayRemaining > 0) {

                    importedStartDelayRemaining = Math.max(0, importedStartDelayRemaining - delta);

                } else if (importedLoopDelayRemaining > 0) {

                    importedLoopDelayRemaining = Math.max(0, importedLoopDelayRemaining - delta);

                } else {

                    const fps = clamp(parseInt(importedFps, 10) || 60, 1, 200);

                    const step = 1 / fps;

                    importedAccumulator += delta;

                    // Évite les boucles infinies si tab en arrière-plan

                    importedAccumulator = Math.min(importedAccumulator, 0.5);

                    while (importedAccumulator >= step) {

                        importedMixer.update(step);

                        importedAccumulator -= step;

                    }

                }



                // Stop auto si loop fini et action terminée

                if (!importedLoopInfinite && importedAction && typeof importedAction.isRunning === 'function') {

                    if (!importedAction.isRunning()) importedIsPlaying = false;

                }

            }



            // Rotation auto 360° (sur la sélection si possible)

            if (autoRotateEnabled) {

                const root = selectedObject ? getRootForObject(selectedObject) : importedTargetRoot;

                if (root) {

                    root.rotation.y += THREE.MathUtils.degToRad(autoRotateSpeedDeg) * delta;

                }

            }



            // Flicker simple pour les lights de type "fire"

            if (pointLights && pointLights.length) {

                const t = performance.now() * 0.001;

                for (const pl of pointLights) {

                    if (!pl || !pl.userData) continue;

                    const isFire = (pl.userData.type === 'fire');

                    if (!isFire) continue;

                    if (!pl.userData.flicker) {

                        // Stabilise

                        if (typeof pl.userData.baseIntensity === 'number') pl.intensity = pl.userData.baseIntensity;

                        continue;

                    }

                    const base = (typeof pl.userData.baseIntensity === 'number') ? pl.userData.baseIntensity : pl.intensity;

                    // variation douce pseudo-aléatoire

                    const n = (Math.sin(t * 17.3 + pl.position.x * 1.7) + Math.sin(t * 9.1 + pl.position.z * 1.3)) * 0.5;

                    const k = 0.85 + 0.25 * (0.5 + 0.5 * n);

                    pl.intensity = Math.max(0, base * k);

                }

            }



            // Squelette: met à jour helper + pickers

            updateRigVisuals();



            // Met à jour la box + poignées en continu

            updateSelectionVisuals();



            // Barre statut: coordonnées caméra

            const bar = document.getElementById('cameraStatusBar');

            if (bar && camera && camera.position) {

                bar.textContent = 'Cam: x=' + camera.position.x.toFixed(3) + ' y=' + camera.position.y.toFixed(3) + ' z=' + camera.position.z.toFixed(3);

            }

            renderer.render(scene, camera);

        }



        function samplePackedVec3(pack, t, fallback) {

            if (!pack || !Array.isArray(pack.times) || !Array.isArray(pack.values) || pack.times.length === 0) return fallback;

            const times = pack.times;

            const values = pack.values;

            if (times.length === 1) return [values[0] || 0, values[1] || 0, values[2] || 0];

            const time = Number(t) || 0;

            const lastIdx = times.length - 1;

            const t0 = Number(times[0]) || 0;

            const tN = Number(times[lastIdx]) || 0;

            if (time <= t0) return [values[0] || 0, values[1] || 0, values[2] || 0];

            if (time >= tN) {

                const base = lastIdx * 3;

                return [values[base] || 0, values[base + 1] || 0, values[base + 2] || 0];

            }

            // recherche binaire

            let lo = 0;

            let hi = lastIdx;

            while (hi - lo > 1) {

                const mid = (lo + hi) >> 1;

                const mt = Number(times[mid]) || 0;

                if (mt <= time) lo = mid; else hi = mid;

            }

            const aT0 = Number(times[lo]) || 0;

            const aT1 = Number(times[hi]) || 0;

            const a = (aT1 > aT0) ? clamp((time - aT0) / (aT1 - aT0), 0, 1) : 0;

            const b0 = lo * 3;

            const b1 = hi * 3;

            const x0 = Number(values[b0]) || 0;

            const y0 = Number(values[b0 + 1]) || 0;

            const z0 = Number(values[b0 + 2]) || 0;

            const x1 = Number(values[b1]) || 0;

            const y1 = Number(values[b1 + 1]) || 0;

            const z1 = Number(values[b1 + 2]) || 0;

            return [x0 + (x1 - x0) * a, y0 + (y1 - y0) * a, z0 + (z1 - z0) * a];

        }



        function samplePackedQuat(pack, t, fallbackQuatArr) {

            if (!pack || !Array.isArray(pack.times) || !Array.isArray(pack.values) || pack.times.length === 0) return fallbackQuatArr;

            const times = pack.times;

            const values = pack.values;

            if (times.length === 1) return [values[0] || 0, values[1] || 0, values[2] || 0, values[3] || 1];

            const time = Number(t) || 0;

            const lastIdx = times.length - 1;

            const t0 = Number(times[0]) || 0;

            const tN = Number(times[lastIdx]) || 0;

            if (time <= t0) return [values[0] || 0, values[1] || 0, values[2] || 0, values[3] || 1];

            if (time >= tN) {

                const base = lastIdx * 4;

                return [values[base] || 0, values[base + 1] || 0, values[base + 2] || 0, values[base + 3] || 1];

            }

            let lo = 0;

            let hi = lastIdx;

            while (hi - lo > 1) {

                const mid = (lo + hi) >> 1;

                const mt = Number(times[mid]) || 0;

                if (mt <= time) lo = mid; else hi = mid;

            }

            const aT0 = Number(times[lo]) || 0;

            const aT1 = Number(times[hi]) || 0;

            const a = (aT1 > aT0) ? clamp((time - aT0) / (aT1 - aT0), 0, 1) : 0;

            const b0 = lo * 4;

            const b1 = hi * 4;

            const q0 = new THREE.Quaternion(Number(values[b0]) || 0, Number(values[b0 + 1]) || 0, Number(values[b0 + 2]) || 0, Number(values[b0 + 3]) || 1);

            const q1 = new THREE.Quaternion(Number(values[b1]) || 0, Number(values[b1 + 1]) || 0, Number(values[b1 + 2]) || 0, Number(values[b1 + 3]) || 1);

            const out = new THREE.Quaternion();

            out.copy(q0).slerp(q1, a);

            return [out.x, out.y, out.z, out.w];

        }



        function applyCapturedAnimationAtTime(timeSec) {

            if (!captureTracks) return;

            const t = Number(timeSec) || 0;

            for (const uuid of Object.keys(captureTracks)) {

                const obj = findObjectByUuid(uuid);

                if (!obj) continue;

                const pack = captureTracks[uuid];

                if (!pack) continue;

                if (pack.pos && Array.isArray(pack.pos.times) && pack.pos.times.length > 0) {

                    const v = samplePackedVec3(pack.pos, t, [obj.position.x, obj.position.y, obj.position.z]);

                    obj.position.set(Number(v[0]) || 0, Number(v[1]) || 0, Number(v[2]) || 0);

                }

                if (pack.scale && Array.isArray(pack.scale.times) && pack.scale.times.length > 0) {

                    const v = samplePackedVec3(pack.scale, t, [obj.scale.x, obj.scale.y, obj.scale.z]);

                    obj.scale.set(Number(v[0]) || 1, Number(v[1]) || 1, Number(v[2]) || 1);

                }

                if (pack.quat && Array.isArray(pack.quat.times) && pack.quat.times.length > 0) {

                    const qArr = samplePackedQuat(pack.quat, t, [obj.quaternion.x, obj.quaternion.y, obj.quaternion.z, obj.quaternion.w]);

                    obj.quaternion.set(Number(qArr[0]) || 0, Number(qArr[1]) || 0, Number(qArr[2]) || 0, Number(qArr[3]) || 1);

                }

            }

        }



        function advanceCapturedAnimation(deltaSec) {

            const d = Math.max(0.05, (capturedClip && capturedClip.duration) ? capturedClip.duration : 0.05);

            let t = (Number(capturedPlayTime) || 0) + (Number(deltaSec) || 0);

            // LoopRepeat

            t = ((t % d) + d) % d;

            capturedPlayTime = t;

            applyCapturedAnimationAtTime(t);

        }



        function getSelectionWorldCenter() {

            const roots = uniqRootsFrom(getEffectiveSelection());

            if (!roots || roots.length === 0) return null;

            const groupBox = new THREE.Box3();

            groupBox.makeEmpty();

            for (const r of roots) {

                if (!r) continue;

                selectionBoxTmp.setFromObject(r);

                groupBox.union(selectionBoxTmp);

            }

            const center = new THREE.Vector3();

            groupBox.getCenter(center);

            return center;

        }



        function moveSelectionCenterToWorldPosition(targetX, targetY, targetZ) {

            const roots = uniqRootsFrom(getEffectiveSelection());

            if (!roots || roots.length === 0) return;



            const center = getSelectionWorldCenter();

            if (!center) return;



            const target = new THREE.Vector3(Number(targetX) || 0, Number(targetY) || 0, Number(targetZ) || 0);

            const delta = new THREE.Vector3().subVectors(target, center);

            const tmpPosWorld = new THREE.Vector3();



            for (const r of roots) {

                if (!r) continue;

                r.updateMatrixWorld(true);

                r.getWorldPosition(tmpPosWorld);

                tmpPosWorld.add(delta);

                if (r.parent) {

                    r.parent.updateMatrixWorld(true);

                    r.position.copy(r.parent.worldToLocal(tmpPosWorld.clone()));

                } else {

                    r.position.copy(tmpPosWorld);

                }

            }



            updateSelectionVisuals();

            updateObjectControls();

        }



        function ensureUniqueNodeNames(nodes, prefix = 'Node') {

            const used = new Set();

            for (let i = 0; i < nodes.length; i++) {

                const n = nodes[i];

                if (!n) continue;

                let name = (n.name && String(n.name).trim()) ? String(n.name).trim() : `${prefix}_${i}`;

                name = name.replace(/\s+/g, '_');

                let base = name;

                let k = 1;

                while (used.has(name)) {

                    name = `${base}_${k++}`;

                }

                used.add(name);

                n.name = name;

            }

        }



        function detectRig(root) {

            if (!root) return null;

            const skinnedMeshes = [];

            const bones = [];

            root.traverse((o) => {

                if (o && o.isSkinnedMesh && o.skeleton) {

                    skinnedMeshes.push(o);

                    if (o.skeleton && Array.isArray(o.skeleton.bones)) {

                        for (const b of o.skeleton.bones) bones.push(b);

                    }

                }

            });

            if (skinnedMeshes.length === 0 || bones.length === 0) return null;



            // IMPORTANT: ne pas renommer les bones/nœuds ici.

            // Les animations importées (gltf.animations) ciblent les noms originaux.

            return { root, skinnedMeshes, bones, bonePickersGroup: null, skeletonHelper: null, selectedBone: null };

        }



        function clearRig() {

            if (!activeRig) return;

            if (activeRig.skeletonHelper) scene.remove(activeRig.skeletonHelper);

            if (activeRig.bonePickersGroup) scene.remove(activeRig.bonePickersGroup);

            if (transformControls) {

                try { transformControls.detach(); } catch (_) {}

            }

            activeRig = null;

            updateRigPanelUI();

            updateToolButtonsEnabledState();

        }



        function buildBonePickers(rig) {

            const g = new THREE.Group();

            g.userData.isHelper = true;



            // Points d'articulation volontairement gros (mobile-friendly)

            const geom = new THREE.SphereGeometry(0.12, 16, 16);

            const mat = new THREE.MeshBasicMaterial({

                color: 0x00aaff,

                transparent: true,

                opacity: 0.95,

                depthTest: false,

                depthWrite: false,

            });



            for (const bone of rig.bones) {

                const m = new THREE.Mesh(geom, mat.clone());

                m.userData = { isBoneHandle: true, bone };

                m.renderOrder = 999;

                g.add(m);

            }

            rig.bonePickersGroup = g;

            scene.add(g);

        }



        function ensureTransformControls() {

            if (transformControls) return;

            transformControls = new THREE.TransformControls(camera, renderer.domElement);

            transformControls.setMode('rotate');

            transformControls.setSpace('local');

            transformControls.size = 0.8;

            transformControls.showX = true;

            transformControls.showY = true;

            transformControls.showZ = true;

            transformControls.addEventListener('dragging-changed', (event) => {

                if (controls) controls.enabled = !event.value;

            });

            transformControls.addEventListener('change', () => {

                // update en temps réel

                // Three r128: SkeletonHelper.update() est inutile et spam la console.

            });

            scene.add(transformControls);

        }



        function updateRigVisuals() {

            if (!activeRig) return;

            // Three r128: SkeletonHelper.update() est inutile et spam la console.

            // Force la mise à jour des matrices d'os pour les meshes skinnés

            if (activeRig.skinnedMeshes && Array.isArray(activeRig.skinnedMeshes)) {

                for (const sm of activeRig.skinnedMeshes) {

                    if (sm && sm.skeleton && typeof sm.skeleton.update === 'function') {

                        sm.skeleton.update();

                    }

                }

            }

            if (!activeRig.bonePickersGroup) return;



            const children = activeRig.bonePickersGroup.children;

            for (let i = 0; i < children.length; i++) {

                const h = children[i];

                const bone = h.userData && h.userData.bone;

                if (!bone) continue;

                bone.getWorldPosition(h.position);

                const dist = camera.position.distanceTo(h.position);

                const s = clamp(dist * 0.06, 0.08, 1.5);

                h.scale.setScalar(s);



                const isSel = activeRig.selectedBone === bone;

                if (h.material && h.material.color) h.material.color.setHex(isSel ? 0xff00ff : 0x00aaff);

                if (h.material && typeof h.material.opacity === 'number') h.material.opacity = isSel ? 1.0 : 0.95;

            }

        }



        function updateRigPanelUI() {

            const status = document.getElementById('rigStatus');

            const boneName = document.getElementById('selectedBoneName');

            const panel = document.getElementById('rig-panel');

            if (!panel) return;



            if (!activeRig) {

                if (status) status.textContent = 'Aucun squelette détecté.';

                if (boneName) boneName.textContent = '-';

                return;

            }



            if (status) status.textContent = `Squelette détecté: ${activeRig.bones.length} os.`;

            if (boneName) boneName.textContent = activeRig.selectedBone ? activeRig.selectedBone.name : '-';

        }



        function upsertTrack(trackMap, nodeName, kind, time, valuesArr) {

            if (!trackMap[nodeName]) {

                trackMap[nodeName] = {

                    pos: { times: [], values: [] },

                    quat: { times: [], values: [] },

                    scale: { times: [], values: [] },

                };

            }

            const t = trackMap[nodeName][kind];

            const idx = t.times.indexOf(time);

            if (idx >= 0) {

                // overwrite

                const stride = (kind === 'quat') ? 4 : 3;

                for (let i = 0; i < stride; i++) {

                    t.values[idx * stride + i] = valuesArr[i];

                }

                return;

            }



            t.times.push(time);

            for (const v of valuesArr) t.values.push(v);

        }



        function sortTrackTimes(trackMap) {

            for (const name of Object.keys(trackMap)) {

                for (const kind of ['pos', 'quat', 'scale']) {

                    const t = trackMap[name][kind];

                    if (t.times.length <= 1) continue;

                    const stride = (kind === 'quat') ? 4 : 3;

                    const zipped = t.times.map((time, i) => {

                        const start = i * stride;

                        return { time, vals: t.values.slice(start, start + stride) };

                    });

                    zipped.sort((a, b) => a.time - b.time);

                    t.times = zipped.map(z => z.time);

                    t.values = zipped.flatMap(z => z.vals);

                }

            }

        }



        function rebuildCapturedClip(duration) {

            if (!captureTracks) return null;

            sortTrackTimes(captureTracks);

            const tracks = [];

            for (const name of Object.keys(captureTracks)) {

                const pack = captureTracks[name];

                if (pack.pos.times.length > 0) {

                    const t = new THREE.VectorKeyframeTrack(name + '.position', pack.pos.times, pack.pos.values);

                    if (typeof t.setInterpolation === 'function') t.setInterpolation(THREE.InterpolateSmooth);

                    tracks.push(t);

                }

                if (pack.quat.times.length > 0) {

                    tracks.push(new THREE.QuaternionKeyframeTrack(name + '.quaternion', pack.quat.times, pack.quat.values));

                }

                if (pack.scale.times.length > 0) {

                    const t = new THREE.VectorKeyframeTrack(name + '.scale', pack.scale.times, pack.scale.values);

                    if (typeof t.setInterpolation === 'function') t.setInterpolation(THREE.InterpolateSmooth);

                    tracks.push(t);

                }

            }



            const clip = new THREE.AnimationClip('Captured', duration, tracks);

            return clip;

        }



        function ensureMixerAndAction() {

            if (!capturedClip) return;

            if (!animMixer) animMixer = new THREE.AnimationMixer(scene);

            if (animAction) {

                animAction.stop();

                animAction = null;

            }

            animAction = animMixer.clipAction(capturedClip);

            animAction.setLoop(THREE.LoopRepeat);

            animAction.clampWhenFinished = false;

            animAction.enabled = true;

        }



        function capturePoseAtTime(timeSec, durationSec) {

            if (!captureTracks) captureTracks = {};



            const tNorm = normalizeTimeSec(timeSec);

            const dNorm = Math.max(0.05, normalizeTimeSec(durationSec));



            // On capture la scène: objets exportables + squelette si présent

            const roots = getExportRootsFromObjects();



            for (const r of roots) {

                r.updateMatrixWorld(true);

                // On utilise UUID pour éviter collisions de noms et rester compatible exporter.

                upsertTrack(captureTracks, r.uuid, 'pos', tNorm, [r.position.x, r.position.y, r.position.z]);

                upsertTrack(captureTracks, r.uuid, 'quat', tNorm, [r.quaternion.x, r.quaternion.y, r.quaternion.z, r.quaternion.w]);

                upsertTrack(captureTracks, r.uuid, 'scale', tNorm, [r.scale.x, r.scale.y, r.scale.z]);

            }



            if (activeRig && Array.isArray(activeRig.bones)) {

                for (const b of activeRig.bones) {

                    // bones utilisent quaternion/position local

                    upsertTrack(captureTracks, b.uuid, 'pos', tNorm, [b.position.x, b.position.y, b.position.z]);

                    upsertTrack(captureTracks, b.uuid, 'quat', tNorm, [b.quaternion.x, b.quaternion.y, b.quaternion.z, b.quaternion.w]);

                    upsertTrack(captureTracks, b.uuid, 'scale', tNorm, [b.scale.x, b.scale.y, b.scale.z]);

                }

            }



            if (!capturedKeyTimes.includes(tNorm)) capturedKeyTimes.push(tNorm);

            updateCapturedKeysUI();



            capturedClip = rebuildCapturedClip(dNorm);

            ensureMixerAndAction();

        }



        function selectObject(object, options = null) {

            const preserveMulti = !!(options && options.preserveMulti);



            // Désélectionner l'objet précédent

            if (!preserveMulti) {

                deselectObject();

            } else {

                clearSelectionHelpers();

            }



            // Sélectionner le nouvel objet

            selectedObject = object;

            if (!preserveMulti) selectedObjects = object ? [object] : [];



            // Ajouter helper + poignées

            createSelectionHelpers();



            // Mettre à jour les contrôles

            updateObjectControls();



            // Mettre à jour la liste des objets

            updateObjectList();



            // Activer boutons scale/deform uniquement si sélection

            updateToolButtonsEnabledState();



            if (currentTool === 'move') {

                try {

                    ensureTransformControls();

                    transformControls.setMode('translate');

                    transformControls.showX = true;

                    transformControls.showY = true;

                    transformControls.showZ = true;

                    if (selectedObject) transformControls.attach(selectedObject);

                } catch (_) {}

            }

        }



        function deselectObject() {

            clearSelectionHelpers();



            selectedObject = null;

            selectedObjects = [];



            // Revenir en mode sélection si besoin

            if (currentTool === 'scale' || currentTool === 'deform' || currentTool === 'move' || currentTool === 'rotate') {

                setTool('select');

            }



            updateToolButtonsEnabledState();



            // Réinitialiser les contrôles

            document.getElementById('color-picker').value = '#ff0000';

            document.getElementById('scale-slider').value = '1';

            document.getElementById('scale-value').textContent = '1.0';

            document.getElementById('opacity-slider').value = '1';

            document.getElementById('opacity-value').textContent = '1.0';

        }



        function updateToolButtonsEnabledState() {

            const hasSelection = !!selectedObject;

            const scaleBtn = document.getElementById('scale-btn');

            const deformBtn = document.getElementById('deform-btn');

            const rotateBtn = document.getElementById('rotate-btn');

            const groupBtn = document.getElementById('group-btn');

            const brushBtn = document.getElementById('brush-btn');

            const sculptBtn = document.getElementById('sculpt-btn');

            const rigBtn = document.getElementById('rig-btn');

            if (scaleBtn) scaleBtn.disabled = !hasSelection;

            if (deformBtn) deformBtn.disabled = !hasSelection;

            if (rotateBtn) rotateBtn.disabled = !hasSelection;

            if (groupBtn) groupBtn.disabled = false;

            if (brushBtn) brushBtn.disabled = !hasSelection;

            if (sculptBtn) sculptBtn.disabled = !hasSelection;

            // Le panneau Animation est utile même sans squelette (objets/environnement)

            if (rigBtn) rigBtn.disabled = false;

        }



        function clearSelectionHelpers() {

            if (selectionBoxHelper) {

                scene.remove(selectionBoxHelper);

                selectionBoxHelper = null;

            }

            if (handlesGroup) {

                scene.remove(handlesGroup);

                handlesGroup = null;

            }

            cornerHandles = [];

            edgeHandles = [];

        }



        function createSelectionHelpers() {

            clearSelectionHelpers();

            if (!selectedObject) return;



            // Helper: box de sélection (supporte multi-sélection)

            const geom = new THREE.BufferGeometry();

            geom.setAttribute('position', new THREE.BufferAttribute(new Float32Array(12 * 2 * 3), 3));

            const mat = new THREE.LineBasicMaterial({ color: 0xffff00 });

            selectionBoxHelper = new THREE.LineSegments(geom, mat);

            selectionBoxHelper.userData.isHelper = true;

            selectionBoxHelper.renderOrder = 998;

            scene.add(selectionBoxHelper);



            handlesGroup = new THREE.Group();

            handlesGroup.userData.isHelper = true;

            scene.add(handlesGroup);



            buildHandles();

            updateSelectionVisuals();



            // Prépare l'adjacence pour le lissage

            prepareGeometryAdjacency(selectedObject);

        }



        function buildHandles() {

            if (!handlesGroup) return;



            const cornerGeom = new THREE.SphereGeometry(HANDLE_BASE_RADIUS, 16, 16);

            const cornerMat = new THREE.MeshBasicMaterial({ color: 0xffff00 });



            // 8 coins (scale)

            for (let i = 0; i < 8; i++) {

                const handle = new THREE.Mesh(cornerGeom, cornerMat);

                handle.userData = { isHandle: true, tool: 'scale', kind: 'corner', index: i };

                handle.visible = false;

                handlesGroup.add(handle);

                cornerHandles.push(handle);

            }



            const edgeGeom = new THREE.SphereGeometry(HANDLE_BASE_RADIUS, 16, 16);

            const edgeMatX = new THREE.MeshBasicMaterial({ color: 0xff4444 });

            const edgeMatY = new THREE.MeshBasicMaterial({ color: 0x44ff44 });

            const edgeMatZ = new THREE.MeshBasicMaterial({ color: 0x4444ff });



            // 12 arêtes (deform) - points milieu, avec axis

            for (let i = 0; i < 12; i++) {

                const axis = i < 4 ? 'x' : (i < 8 ? 'y' : 'z');

                const mat = axis === 'x' ? edgeMatX : (axis === 'y' ? edgeMatY : edgeMatZ);

                const handle = new THREE.Mesh(edgeGeom, mat);

                handle.userData = { isHandle: true, tool: 'deform', kind: 'edge', index: i, axis };

                handle.visible = false;

                handlesGroup.add(handle);

                edgeHandles.push(handle);

            }

        }



        function updateSelectionVisuals() {

            if (!selectedObject) return;



            // Calcul box monde sur la sélection effective (multi-sélection incluse)

            const roots = uniqRootsFrom(getEffectiveSelection());

            selectionBox3.makeEmpty();

            for (const r of roots) {

                selectionBoxTmp.setFromObject(r);

                selectionBox3.union(selectionBoxTmp);

            }



            // Met à jour le helper box (LineSegments)

            if (selectionBoxHelper && selectionBoxHelper.geometry && selectionBoxHelper.geometry.attributes && selectionBoxHelper.geometry.attributes.position) {

                const pos = selectionBoxHelper.geometry.attributes.position;

                const a = pos.array;

                const min = selectionBox3.min;

                const max = selectionBox3.max;



                const p000 = [min.x, min.y, min.z];

                const p001 = [min.x, min.y, max.z];

                const p010 = [min.x, max.y, min.z];

                const p011 = [min.x, max.y, max.z];

                const p100 = [max.x, min.y, min.z];

                const p101 = [max.x, min.y, max.z];

                const p110 = [max.x, max.y, min.z];

                const p111 = [max.x, max.y, max.z];



                const segs = [

                    // bas

                    [p000, p100], [p100, p101], [p101, p001], [p001, p000],

                    // haut

                    [p010, p110], [p110, p111], [p111, p011], [p011, p010],

                    // montants

                    [p000, p010], [p100, p110], [p101, p111], [p001, p011],

                ];



                let k = 0;

                for (const s of segs) {

                    a[k++] = s[0][0]; a[k++] = s[0][1]; a[k++] = s[0][2];

                    a[k++] = s[1][0]; a[k++] = s[1][1]; a[k++] = s[1][2];

                }

                pos.needsUpdate = true;

            }

            if (!handlesGroup) return;

            const min = selectionBox3.min;

            const max = selectionBox3.max;



            // Coins (8)

            const corners = [

                new THREE.Vector3(min.x, min.y, min.z),

                new THREE.Vector3(max.x, min.y, min.z),

                new THREE.Vector3(min.x, max.y, min.z),

                new THREE.Vector3(max.x, max.y, min.z),

                new THREE.Vector3(min.x, min.y, max.z),

                new THREE.Vector3(max.x, min.y, max.z),

                new THREE.Vector3(min.x, max.y, max.z),

                new THREE.Vector3(max.x, max.y, max.z),

            ];



            for (let i = 0; i < cornerHandles.length; i++) {

                const handle = cornerHandles[i];

                handle.position.copy(corners[i]);

            }



            // Arêtes (12) - milieux

            const edges = [

                // x edges (y=min, z=min/max) and (y=max, z=min/max)

                new THREE.Vector3((min.x + max.x) / 2, min.y, min.z),

                new THREE.Vector3((min.x + max.x) / 2, min.y, max.z),

                new THREE.Vector3((min.x + max.x) / 2, max.y, min.z),

                new THREE.Vector3((min.x + max.x) / 2, max.y, max.z),

                // y edges (x=min/max, z=min/max)

                new THREE.Vector3(min.x, (min.y + max.y) / 2, min.z),

                new THREE.Vector3(min.x, (min.y + max.y) / 2, max.z),

                new THREE.Vector3(max.x, (min.y + max.y) / 2, min.z),

                new THREE.Vector3(max.x, (min.y + max.y) / 2, max.z),

                // z edges (x=min/max, y=min/max)

                new THREE.Vector3(min.x, min.y, (min.z + max.z) / 2),

                new THREE.Vector3(min.x, max.y, (min.z + max.z) / 2),

                new THREE.Vector3(max.x, min.y, (min.z + max.z) / 2),

                new THREE.Vector3(max.x, max.y, (min.z + max.z) / 2),

            ];



            for (let i = 0; i < edgeHandles.length; i++) {

                const handle = edgeHandles[i];

                handle.position.copy(edges[i]);

            }



            // Affichage selon outil

            const showCorners = currentTool === 'scale';

            const showEdges = (currentTool === 'deform' || currentTool === 'rotate');

            cornerHandles.forEach(h => (h.visible = showCorners));

            edgeHandles.forEach(h => (h.visible = showEdges));



            // Grossir/adapter visuellement les points (stable à l'écran)

            const allHandles = showCorners ? cornerHandles : (showEdges ? edgeHandles : []);

            for (const h of allHandles) {

                const dist = camera.position.distanceTo(h.position);

                const s = Math.max(0.001, dist * HANDLE_SCREEN_SCALE);

                h.scale.setScalar(s);

            }

        }



        function setMouseFromEvent(event) {

            const rect = renderer.domElement.getBoundingClientRect();

            mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;

            mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

        }



        function clamp(value, min, max) {

            return Math.max(min, Math.min(max, value));

        }



        function normalizeTimeSec(t) {

            const v = Math.max(0, parseFloat(t) || 0);

            return Math.round(v * 1000) / 1000;

        }



        function getEffectiveSelection() {

            if (Array.isArray(selectedObjects) && selectedObjects.length > 0) return selectedObjects;

            return selectedObject ? [selectedObject] : [];

        }



        function uniqRootsFrom(objectsArr) {

            const set = new Set();

            const roots = [];

            for (const obj of (objectsArr || [])) {

                const r = getRootForObject(obj);

                if (!r) continue;

                if (set.has(r.uuid)) continue;

                set.add(r.uuid);

                roots.push(r);

            }

            return roots;

        }



        function setSelectedObjects(arr, primary = null) {

            selectedObjects = Array.isArray(arr) ? arr.filter(Boolean) : [];

            if (selectedObjects.length === 0) {

                deselectObject();

                return;

            }

            const p = primary && selectedObjects.includes(primary) ? primary : selectedObjects[0];

            selectObject(p, { preserveMulti: true });

            updateObjectList();

        }



        function adjustSelectionRenderOrder(delta) {

            const roots = uniqRootsFrom(getEffectiveSelection());

            for (const r of roots) {

                r.renderOrder = (r.renderOrder || 0) + delta;

            }

        }



        function rotateSelectionAroundWorldAxis(axisWorld, angleRad) {

            const roots = uniqRootsFrom(getEffectiveSelection());

            if (roots.length === 0) return;



            const groupBox = new THREE.Box3();

            groupBox.makeEmpty();

            for (const r of roots) {

                selectionBoxTmp.setFromObject(r);

                groupBox.union(selectionBoxTmp);

            }

            const pivot = new THREE.Vector3();

            groupBox.getCenter(pivot);



            const qWorld = new THREE.Quaternion().setFromAxisAngle(axisWorld, angleRad);

            const parentWorldQuat = new THREE.Quaternion();

            const invParentWorldQuat = new THREE.Quaternion();

            const axisLocal = new THREE.Vector3();

            const tmpPosWorld = new THREE.Vector3();

            const tmpOffset = new THREE.Vector3();

            const tmpNewWorld = new THREE.Vector3();



            for (const r of roots) {

                r.updateMatrixWorld(true);

                r.getWorldPosition(tmpPosWorld);

                tmpOffset.copy(tmpPosWorld).sub(pivot);

                tmpOffset.applyQuaternion(qWorld);

                tmpNewWorld.copy(pivot).add(tmpOffset);



                if (r.parent) {

                    r.parent.updateMatrixWorld(true);

                    r.position.copy(r.parent.worldToLocal(tmpNewWorld));

                } else {

                    r.position.copy(tmpNewWorld);

                }



                if (r.parent) {

                    r.parent.getWorldQuaternion(parentWorldQuat);

                    invParentWorldQuat.copy(parentWorldQuat).invert();

                    axisLocal.copy(axisWorld).applyQuaternion(invParentWorldQuat).normalize();

                } else {

                    axisLocal.copy(axisWorld);

                }

                r.quaternion.premultiply(new THREE.Quaternion().setFromAxisAngle(axisLocal, angleRad));

            }

        }



        function setTool(tool) {

            currentTool = tool;



            const selectBtn = document.getElementById('select-btn');

            const moveBtn = document.getElementById('move-btn');

            const scaleBtn = document.getElementById('scale-btn');

            const deformBtn = document.getElementById('deform-btn');

            const rotateBtn = document.getElementById('rotate-btn');

            const groupBtn = document.getElementById('group-btn');

            const brushBtn = document.getElementById('brush-btn');

            const sculptBtn = document.getElementById('sculpt-btn');

            const rigBtn = document.getElementById('rig-btn');



            if (selectBtn) selectBtn.classList.toggle('active', tool === 'select');

            if (moveBtn) moveBtn.classList.toggle('active', tool === 'move');

            if (scaleBtn) scaleBtn.classList.toggle('active', tool === 'scale');

            if (deformBtn) deformBtn.classList.toggle('active', tool === 'deform');

            if (rotateBtn) rotateBtn.classList.toggle('active', tool === 'rotate');

            if (groupBtn) groupBtn.classList.toggle('active', tool === 'group');

            if (brushBtn) brushBtn.classList.toggle('active', tool === 'brush');

            if (sculptBtn) sculptBtn.classList.toggle('active', tool === 'sculpt');

            if (rigBtn) rigBtn.classList.toggle('active', tool === 'rig');



            // Panel sculpt

            const panel = document.getElementById('sculpt-panel');

            if (panel) panel.style.display = tool === 'sculpt' ? 'block' : 'none';



            // Panel brush peinture

            const paintPanel = document.getElementById('paint-panel');

            if (paintPanel) paintPanel.style.display = tool === 'brush' ? 'block' : 'none';



            // Mobile: amener le panneau actif au premier plan (utilisable au doigt)

            if (tool === 'brush' && paintPanel && (isCoarsePointer || (window.innerWidth || 0) <= 900)) {

                requestAnimationFrame(() => {

                    const sidebar = document.querySelector('.sidebar');

                    if (!sidebar) return;

                    const top = Math.max(0, paintPanel.offsetTop - 10);

                    try {

                        sidebar.scrollTo({ top, behavior: 'smooth' });

                    } catch (_) {

                        sidebar.scrollTop = top;

                    }

                });

            }



            // Panel rig

            const rigPanel = document.getElementById('rig-panel');

            if (rigPanel) rigPanel.style.display = tool === 'rig' ? 'block' : 'none';



            if (tool === 'rig') {

                // En mode squelette: on évite que la caméra tourne pendant les clics/touch.

                if (controls) {

                    controls.enableRotate = false;

                    controls.enablePan = false;

                    controls.enableZoom = true;

                }

                // Prépare les contrôles de squelette si dispo

                if (activeRig) {

                    ensureTransformControls();

                    if (!activeRig.skeletonHelper) {

                        activeRig.skeletonHelper = new THREE.SkeletonHelper(activeRig.root);

                        activeRig.skeletonHelper.userData.isHelper = true;

                        scene.add(activeRig.skeletonHelper);

                    }

                    if (!activeRig.bonePickersGroup) buildBonePickers(activeRig);

                    updateRigPanelUI();

                }

            } else if (tool === 'move') {

                // Déplacement X/Y/Z via gizmo (corrige le bug Z)

                if (controls) {

                    controls.enableRotate = true;

                    controls.enablePan = true;

                    controls.enableZoom = true;

                }

                ensureTransformControls();

                try {

                    transformControls.setMode('translate');

                    transformControls.setSpace('local');

                    transformControls.showX = true;

                    transformControls.showY = true;

                    transformControls.showZ = true;

                    if (selectedObject) transformControls.attach(selectedObject);

                    else { try { transformControls.detach(); } catch (_) {} }

                } catch (_) {}

            } else {

                // Rétablit navigation caméra pour les autres outils

                if (controls) {

                    controls.enableRotate = true;

                    controls.enablePan = true;

                    controls.enableZoom = true;

                }

                if (transformControls) {

                    try { transformControls.detach(); } catch (_) {}

                }

            }



            updateSelectionVisuals();

        }



        function ensureMeshSingleMaterial(mesh) {

            if (!mesh || !mesh.material) return null;

            if (Array.isArray(mesh.material)) return mesh.material[0] || null;

            return mesh.material;

        }



        function applyPaintMaterialPresetToMesh(mesh, preset) {

            if (!mesh) return;

            const mat0 = ensureMeshSingleMaterial(mesh);

            if (!mat0) return;



            // Préserve transparence/opacité

            const prevOpacity = (typeof mat0.opacity === 'number') ? mat0.opacity : 1;

            const prevTransparent = !!mat0.transparent;

            const prevSide = mat0.side;



            let mat = mat0;

            const needsStandard = (preset !== 'basic');

            if (needsStandard && !(mat && mat.isMeshStandardMaterial)) {

                mat = new THREE.MeshStandardMaterial();

                mat.name = (mat0.name || '') + '_paint';

                mat.transparent = prevTransparent;

                mat.opacity = prevOpacity;

                mat.side = prevSide;

                mesh.material = mat;

            }



            if (mat && mat.isMeshStandardMaterial) {

                // Reset commun (évite que des presets laissent des restes)

                mat.emissive = mat.emissive || new THREE.Color(0x000000);

                mat.emissive.set(0x000000);

                mat.emissiveIntensity = 0.0;

                mat.envMapIntensity = (typeof mat.envMapIntensity === 'number') ? mat.envMapIntensity : 1.0;



                if (preset === 'plastic') {

                    mat.metalness = 0.05;

                    mat.roughness = 0.35;

                    mat.envMapIntensity = 0.8;

                } else if (preset === 'metal') {

                    mat.metalness = 0.9;

                    mat.roughness = 0.25;

                    mat.envMapIntensity = 1.0;

                } else if (preset === 'metal-reflect') {

                    mat.metalness = 1.0;

                    mat.roughness = 0.05;

                    mat.envMapIntensity = 1.2;

                } else if (preset === 'matte') {

                    mat.metalness = 0.0;

                    mat.roughness = 0.95;

                    mat.envMapIntensity = 0.4;

                } else if (preset === 'glossy') {

                    mat.metalness = 0.1;

                    mat.roughness = 0.08;

                    mat.envMapIntensity = 1.0;

                } else if (preset === 'rubber') {

                    mat.metalness = 0.0;

                    mat.roughness = 0.85;

                    mat.envMapIntensity = 0.2;

                } else if (preset === 'ceramic') {

                    mat.metalness = 0.0;

                    mat.roughness = 0.25;

                    mat.envMapIntensity = 0.95;

                } else if (preset === 'chrome') {

                    mat.metalness = 1.0;

                    mat.roughness = 0.03;

                    mat.envMapIntensity = 1.4;

                } else if (preset === 'gold') {

                    mat.metalness = 1.0;

                    mat.roughness = 0.2;

                    mat.envMapIntensity = 1.15;

                } else if (preset === 'copper') {

                    mat.metalness = 1.0;

                    mat.roughness = 0.35;

                    mat.envMapIntensity = 1.1;

                } else if (preset === 'neon') {

                    mat.metalness = 0.0;

                    mat.roughness = 0.25;

                    mat.envMapIntensity = 0.6;

                    // Emissive sur la couleur de peinture actuelle

                    try {

                        const col = document.getElementById('paintColor')?.value || '#ff00ff';

                        mat.emissive.set(col);

                    } catch (_) {

                        mat.emissive.set(0xff00ff);

                    }

                    mat.emissiveIntensity = 1.6;

                } else if (preset === 'glass') {

                    mat.metalness = 0.0;

                    mat.roughness = 0.02;

                    mat.envMapIntensity = 1.2;

                    mat.transparent = true;

                    mat.opacity = Math.min(prevOpacity, 0.4);

                } else if (preset === 'velvet') {

                    mat.metalness = 0.0;

                    mat.roughness = 0.75;

                    mat.envMapIntensity = 0.3;

                }

            }



            if (mat) mat.needsUpdate = true;

        }



        function ensurePaintCanvasesForMesh(mesh) {

            if (!mesh || !mesh.geometry) return null;

            const geom = mesh.geometry;

            if (!geom.attributes || !geom.attributes.uv) return null;



            mesh.userData = mesh.userData || {};

            mesh.userData.__paint = mesh.userData.__paint || {};

            const st = mesh.userData.__paint;



            const res = clamp(parseInt(paintMapResolution, 10) || 1024, 128, 4096);

            if (!st.canvas || st.res !== res) {

                st.res = res;

                st.canvas = document.createElement('canvas');

                st.canvas.width = res;

                st.canvas.height = res;

                st.ctx = st.canvas.getContext('2d');



                // Initialise: reprend texture existante si possible, sinon couleur matériau

                const mat0 = ensureMeshSingleMaterial(mesh);

                const baseColor = (mat0 && mat0.color) ? ('#' + mat0.color.getHexString()) : '#ffffff';

                st.ctx.clearRect(0, 0, res, res);



                // Si une map image existe, on la copie

                let drawn = false;

                if (mat0 && mat0.map && mat0.map.image) {

                    try {

                        st.ctx.drawImage(mat0.map.image, 0, 0, res, res);

                        drawn = true;

                    } catch (_) {}

                }

                if (!drawn) {

                    st.ctx.fillStyle = baseColor;

                    st.ctx.fillRect(0, 0, res, res);

                }



                st.texture = new THREE.CanvasTexture(st.canvas);

                st.texture.wrapS = THREE.RepeatWrapping;

                st.texture.wrapT = THREE.RepeatWrapping;

                st.texture.needsUpdate = true;

            }



            // Relief canvases

            const hres = clamp(parseInt(paintReliefResolution, 10) || 1024, 128, 4096);

            if (paintReliefEnabled && (!st.heightCanvas || st.heightRes !== hres)) {

                st.heightRes = hres;

                st.heightCanvas = document.createElement('canvas');

                st.heightCanvas.width = hres;

                st.heightCanvas.height = hres;

                st.heightCtx = st.heightCanvas.getContext('2d');

                // baseline mid-gray

                st.heightCtx.fillStyle = 'rgb(128,128,128)';

                st.heightCtx.fillRect(0, 0, hres, hres);

                st.heightTexture = new THREE.CanvasTexture(st.heightCanvas);

                st.heightTexture.wrapS = THREE.RepeatWrapping;

                st.heightTexture.wrapT = THREE.RepeatWrapping;

                st.heightTexture.needsUpdate = true;

            }



            const mat = ensureMeshSingleMaterial(mesh);

            if (mat) {

                applyPaintMaterialPresetToMesh(mesh, paintMaterialPreset);

                const m = ensureMeshSingleMaterial(mesh);

                if (m && m.isMeshStandardMaterial) {

                    m.map = st.texture;

                    m.color.set(0xffffff);

                    m.needsUpdate = true;



                    if (paintReliefEnabled && st.heightTexture) {

                        applyPaintMaterialPresetToMesh(mesh, paintReliefMaterialPreset);

                        const mm = ensureMeshSingleMaterial(mesh);

                        if (mm && mm.isMeshStandardMaterial) {

                            mm.displacementMap = st.heightTexture;

                            mm.displacementScale = clamp(paintReliefStrength * 0.35, 0, 0.35);

                            mm.needsUpdate = true;

                        }

                    }

                } else {

                    // Matériaux non-standard: on met map si supporté

                    try {

                        mat.map = st.texture;

                        mat.needsUpdate = true;

                    } catch (_) {}

                }

            }



            return st;

        }



        function loadPaintImage(file) {

            if (!file) return Promise.resolve(null);

            if (paintTextureCache.has(file)) return Promise.resolve(paintTextureCache.get(file));

            return new Promise((resolve) => {

                const img = new Image();

                img.crossOrigin = 'anonymous';

                img.onload = () => {

                    paintTextureCache.set(file, img);

                    resolve(img);

                };

                img.onerror = () => resolve(null);

                img.src = 'textureplan/' + file;

            });

        }



        function cssColorToRgba(css, a) {

            const c = new THREE.Color(css);

            const r = Math.round(clamp(c.r, 0, 1) * 255);

            const g = Math.round(clamp(c.g, 0, 1) * 255);

            const b = Math.round(clamp(c.b, 0, 1) * 255);

            const aa = clamp(a, 0, 1);

            return `rgba(${r},${g},${b},${aa})`;

        }



        function paintStampAtUv(mesh, uv) {

            if (!mesh || !uv) return;

            const st = ensurePaintCanvasesForMesh(mesh);

            if (!st || !st.ctx) return;



            const res = st.res;

            let u = uv.x;

            let v = uv.y;



            // Polygone relief: quantifie UV

            const poly = clamp(parseInt(paintReliefPolygon, 10) || 0, 0, 64);

            if (paintReliefEnabled && poly > 0) {

                const q = poly;

                u = Math.round(u * q) / q;

                v = Math.round(v * q) / q;

            }



            const x = clamp(u, 0, 1) * res;

            const y = (1 - clamp(v, 0, 1)) * res;

            const r = clamp(parseFloat(paintBrushSizePx) || 1, 0.1, 5000);

            const rr = clamp(r, 0.1, Math.max(1, res * 0.5));

            const soft = clamp(parseFloat(paintSoftness) || 0, 0, 1);



            const ctx = st.ctx;

            ctx.save();

            ctx.globalCompositeOperation = 'source-over';

            ctx.imageSmoothingEnabled = !!paintTextureSmoothing;



            const g = ctx.createRadialGradient(x, y, Math.max(0.01, rr * Math.max(0.0, 1 - soft)), x, y, rr);

            g.addColorStop(0, cssColorToRgba(document.getElementById('paintColor')?.value || '#ff0000', paintAlpha));

            g.addColorStop(1, cssColorToRgba(document.getElementById('paintColor')?.value || '#ff0000', 0));

            ctx.fillStyle = g;

            ctx.beginPath();

            ctx.arc(x, y, rr, 0, Math.PI * 2);

            ctx.fill();



            ctx.restore();



            // Texture fusion

            if (paintUseTexture && paintTextureFile) {

                loadPaintImage(paintTextureFile).then((img) => {

                    if (!img) return;

                    const ctx2 = st.ctx;

                    ctx2.save();

                    ctx2.imageSmoothingEnabled = !!paintTextureSmoothing;



                    // Tinted tile (texture * couleur)

                    const tileSize = 256;

                    paintOffscreenTile.width = tileSize;

                    paintOffscreenTile.height = tileSize;

                    paintOffscreenTileCtx.clearRect(0, 0, tileSize, tileSize);

                    try {

                        paintOffscreenTileCtx.drawImage(img, 0, 0, tileSize, tileSize);

                    } catch (_) {

                        ctx2.restore();

                        return;

                    }

                    paintOffscreenTileCtx.globalCompositeOperation = 'multiply';

                    paintOffscreenTileCtx.fillStyle = document.getElementById('paintColor')?.value || '#ff0000';

                    paintOffscreenTileCtx.fillRect(0, 0, tileSize, tileSize);

                    paintOffscreenTileCtx.globalCompositeOperation = 'source-over';



                    const pattern = ctx2.createPattern(paintOffscreenTile, 'repeat');

                    if (pattern) {

                        ctx2.globalAlpha = clamp(parseFloat(paintTextureOpacity) || 0, 0, 1);

                        ctx2.globalCompositeOperation = 'source-atop';



                        const rot = THREE.MathUtils.degToRad(parseFloat(paintTextureRotationDeg) || 0);

                        const repX = Math.max(0.1, parseFloat(paintTextureRepeatX) || 1);

                        const repY = Math.max(0.1, parseFloat(paintTextureRepeatY) || 1);

                        const sizePx = Math.max(1, parseFloat(paintTextureSizePx) || 256);

                        const sx = ((sizePx / tileSize) / repX);

                        const sy = ((sizePx / tileSize) / repY);



                        ctx2.translate(x, y);

                        ctx2.rotate(rot);

                        ctx2.scale(sx, sy);

                        ctx2.translate(-x, -y);

                        ctx2.fillStyle = pattern;

                        ctx2.fillRect(x - rr * 3, y - rr * 3, rr * 6, rr * 6);

                    }

                    ctx2.restore();

                    if (st.texture) st.texture.needsUpdate = true;

                });

            }



            if (st.texture) st.texture.needsUpdate = true;



            // Relief (heightmap)

            if (paintReliefEnabled && st.heightCtx && st.heightTexture) {

                const hctx = st.heightCtx;

                const hres = st.heightRes || paintReliefResolution;

                const hx = clamp(u, 0, 1) * hres;

                const hy = (1 - clamp(v, 0, 1)) * hres;

                const hr = clamp(rr * (hres / res), 0.1, Math.max(1, hres * 0.5));



                hctx.save();

                hctx.imageSmoothingEnabled = true;

                const strength = clamp(parseFloat(paintReliefStrength) || 0, 0, 1);

                const type = paintReliefType || 'raise';

                const col = (type === 'engrave') ? `rgba(0,0,0,${strength})` : `rgba(255,255,255,${strength})`;



                const hg = hctx.createRadialGradient(hx, hy, Math.max(0.01, hr * Math.max(0.0, 1 - soft)), hx, hy, hr);

                hg.addColorStop(0, col);

                hg.addColorStop(1, 'rgba(128,128,128,0)');

                hctx.fillStyle = hg;

                hctx.beginPath();

                hctx.arc(hx, hy, hr, 0, Math.PI * 2);

                hctx.fill();

                hctx.restore();



                if (type === 'noise' && paintReliefTextureFile) {

                    loadPaintImage(paintReliefTextureFile).then((img) => {

                        if (!img) return;

                        const ctx3 = st.heightCtx;

                        ctx3.save();

                        ctx3.globalAlpha = clamp(strength, 0, 1);

                        ctx3.globalCompositeOperation = 'overlay';

                        const p = ctx3.createPattern(img, 'repeat');

                        if (p) {

                            ctx3.fillStyle = p;

                            ctx3.beginPath();

                            ctx3.arc(hx, hy, hr, 0, Math.PI * 2);

                            ctx3.clip();

                            ctx3.fillRect(hx - hr * 2, hy - hr * 2, hr * 4, hr * 4);

                        }

                        ctx3.restore();

                        st.heightTexture.needsUpdate = true;

                    });

                }



                st.heightTexture.needsUpdate = true;

            }

        }



        function paintAtPointerEvent(e) {

            const roots = uniqRootsFrom(getEffectiveSelection());

            if (roots.length === 0) return;

            setMouseFromEvent(e);

            raycaster.setFromCamera(mouse, camera);



            const meshes = [];

            for (const r of roots) {

                r.traverse((o) => {

                    if (o && o.isMesh && o.geometry && o.visible) meshes.push(o);

                });

            }

            if (meshes.length === 0) return;



            const hits = raycaster.intersectObjects(meshes, true);

            if (!hits || hits.length === 0) return;



            const hit = hits[0];

            if (!hit.uv || !hit.object) return;



            // Espacement régulier: on interpole des stamps entre 2 points UV

            try {

                const mesh = hit.object;

                const uv = hit.uv;

                const st = ensurePaintCanvasesForMesh(mesh);

                const res = (st && st.res) ? st.res : paintMapResolution;

                const radiusPx = clamp(parseFloat(paintBrushSizePx) || 1, 0.1, 5000);

                const stepPx = Math.max(1, radiusPx * Math.max(0.05, Math.min(1, paintStrokeSpacingFactor)));

                const stepUv = stepPx / Math.max(1, res);



                const last = paintLastStamp;

                if (!last || last.meshUuid !== mesh.uuid) {

                    paintStampAtUv(mesh, uv);

                    paintLastStamp = { meshUuid: mesh.uuid, u: uv.x, v: uv.y };

                    return;

                }



                const du = uv.x - last.u;

                const dv = uv.y - last.v;

                const distUv = Math.sqrt(du * du + dv * dv);



                if (distUv <= stepUv) {

                    // Toujours peindre un peu pour éviter un trait haché si la main bouge lentement

                    paintStampAtUv(mesh, uv);

                    paintLastStamp = { meshUuid: mesh.uuid, u: uv.x, v: uv.y };

                    return;

                }



                const steps = Math.min(128, Math.floor(distUv / stepUv));

                for (let i = 1; i <= steps; i++) {

                    const t = i / steps;

                    const u2 = last.u + du * t;

                    const v2 = last.v + dv * t;

                    paintStampAtUv(mesh, new THREE.Vector2(u2, v2));

                }

                paintLastStamp = { meshUuid: mesh.uuid, u: uv.x, v: uv.y };

            } catch (_) {

                paintStampAtUv(hit.object, hit.uv);

                paintLastStamp = { meshUuid: hit.object.uuid, u: hit.uv.x, v: hit.uv.y };

            }

        }



        function initPaintUI() {

            const panel = document.getElementById('paint-panel');

            const size = document.getElementById('paintBrushSize');

            const sizeVal = document.getElementById('paintBrushSizeValue');

            const alpha = document.getElementById('paintAlpha');

            const alphaVal = document.getElementById('paintAlphaValue');

            const soft = document.getElementById('paintSoftness');

            const softVal = document.getElementById('paintSoftnessValue');

            const preset = document.getElementById('paintMaterialPreset');

            const useTex = document.getElementById('paintUseTexture');

            const texOptions = document.getElementById('paintTextureOptions');

            const texSel = document.getElementById('paintTextureSelect');

            const texSize = document.getElementById('paintTextureSize');

            const texSizeVal = document.getElementById('paintTextureSizeValue');

            const repX = document.getElementById('paintTextureRepeatX');

            const repY = document.getElementById('paintTextureRepeatY');

            const texRot = document.getElementById('paintTextureRotation');

            const texRotVal = document.getElementById('paintTextureRotationValue');

            const texOp = document.getElementById('paintTextureOpacity');

            const texOpVal = document.getElementById('paintTextureOpacityValue');

            const mapRes = document.getElementById('paintMapResolution');

            const smoothTex = document.getElementById('paintTextureSmoothing');



            const rel = document.getElementById('paintReliefEnabled');

            const relOptions = document.getElementById('paintReliefOptions');

            const relStrength = document.getElementById('paintReliefStrength');

            const relStrengthVal = document.getElementById('paintReliefStrengthValue');

            const relType = document.getElementById('paintReliefType');

            const relPoly = document.getElementById('paintReliefPolygon');

            const relPolyVal = document.getElementById('paintReliefPolygonValue');

            const relRes = document.getElementById('paintReliefResolution');

            const relTexSel = document.getElementById('paintReliefTextureSelect');

            const relMat = document.getElementById('paintReliefMaterialPreset');



            if (size && sizeVal) {

                paintBrushSizePx = parseFloat(size.value) || 64;

                sizeVal.textContent = String(paintBrushSizePx);

                size.addEventListener('input', () => {

                    paintBrushSizePx = parseFloat(size.value) || 0.1;

                    sizeVal.textContent = String(paintBrushSizePx);

                });

            }

            if (alpha && alphaVal) {

                paintAlpha = parseFloat(alpha.value) || 1;

                alphaVal.textContent = paintAlpha.toFixed(2);

                alpha.addEventListener('input', () => {

                    paintAlpha = parseFloat(alpha.value) || 0;

                    alphaVal.textContent = paintAlpha.toFixed(2);

                });

            }

            if (soft && softVal) {

                paintSoftness = parseFloat(soft.value) || 0;

                softVal.textContent = paintSoftness.toFixed(2);

                soft.addEventListener('input', () => {

                    paintSoftness = parseFloat(soft.value) || 0;

                    softVal.textContent = paintSoftness.toFixed(2);

                });

            }



            if (preset) {

                paintMaterialPreset = preset.value || 'basic';

                preset.addEventListener('change', () => {

                    paintMaterialPreset = preset.value || 'basic';

                    if (selectedObject) applyPaintMaterialPresetToMesh(selectedObject, paintMaterialPreset);

                });

            }



            if (useTex && texOptions) {

                paintUseTexture = !!useTex.checked;

                texOptions.style.display = paintUseTexture ? 'block' : 'none';

                useTex.addEventListener('change', () => {

                    paintUseTexture = !!useTex.checked;

                    texOptions.style.display = paintUseTexture ? 'block' : 'none';

                });

            }

            if (texSel) texSel.addEventListener('change', () => { paintTextureFile = texSel.value || ''; });

            if (texSize && texSizeVal) {

                paintTextureSizePx = parseInt(texSize.value, 10) || 256;

                texSizeVal.textContent = String(paintTextureSizePx);

                texSize.addEventListener('input', () => {

                    paintTextureSizePx = parseInt(texSize.value, 10) || 1;

                    texSizeVal.textContent = String(paintTextureSizePx);

                });

            }

            if (repX) repX.addEventListener('input', () => { paintTextureRepeatX = parseFloat(repX.value) || 1; });

            if (repY) repY.addEventListener('input', () => { paintTextureRepeatY = parseFloat(repY.value) || 1; });

            if (texRot && texRotVal) {

                paintTextureRotationDeg = parseFloat(texRot.value) || 0;

                texRotVal.textContent = String(paintTextureRotationDeg);

                texRot.addEventListener('input', () => {

                    paintTextureRotationDeg = parseFloat(texRot.value) || 0;

                    texRotVal.textContent = String(paintTextureRotationDeg);

                });

            }

            if (texOp && texOpVal) {

                paintTextureOpacity = parseFloat(texOp.value) || 0;

                texOpVal.textContent = paintTextureOpacity.toFixed(2);

                texOp.addEventListener('input', () => {

                    paintTextureOpacity = parseFloat(texOp.value) || 0;

                    texOpVal.textContent = paintTextureOpacity.toFixed(2);

                });

            }

            if (mapRes) {

                paintMapResolution = parseInt(mapRes.value, 10) || 1024;

                mapRes.addEventListener('change', () => {

                    paintMapResolution = parseInt(mapRes.value, 10) || 1024;

                });

            }

            if (smoothTex) {

                paintTextureSmoothing = !!smoothTex.checked;

                smoothTex.addEventListener('change', () => { paintTextureSmoothing = !!smoothTex.checked; });

            }



            if (rel && relOptions) {

                paintReliefEnabled = !!rel.checked;

                relOptions.style.display = paintReliefEnabled ? 'block' : 'none';

                rel.addEventListener('change', () => {

                    paintReliefEnabled = !!rel.checked;

                    relOptions.style.display = paintReliefEnabled ? 'block' : 'none';

                });

            }

            if (relStrength && relStrengthVal) {

                paintReliefStrength = parseFloat(relStrength.value) || 0;

                relStrengthVal.textContent = paintReliefStrength.toFixed(2);

                relStrength.addEventListener('input', () => {

                    paintReliefStrength = parseFloat(relStrength.value) || 0;

                    relStrengthVal.textContent = paintReliefStrength.toFixed(2);

                });

            }

            if (relType) relType.addEventListener('change', () => { paintReliefType = relType.value || 'raise'; });

            if (relPoly && relPolyVal) {

                paintReliefPolygon = parseInt(relPoly.value, 10) || 0;

                relPolyVal.textContent = String(paintReliefPolygon);

                relPoly.addEventListener('input', () => {

                    paintReliefPolygon = parseInt(relPoly.value, 10) || 0;

                    relPolyVal.textContent = String(paintReliefPolygon);

                });

            }

            if (relRes) relRes.addEventListener('change', () => { paintReliefResolution = parseInt(relRes.value, 10) || 1024; });

            if (relTexSel) relTexSel.addEventListener('change', () => { paintReliefTextureFile = relTexSel.value || ''; });

            if (relMat) relMat.addEventListener('change', () => { paintReliefMaterialPreset = relMat.value || 'plastic'; });

        }



        function updateGroupSelectRectUI(startClient, nowClient, show) {

            const rectEl = document.getElementById('groupSelectRect');

            if (!rectEl) return;

            if (!show) {

                rectEl.style.display = 'none';

                return;

            }

            const canvasRect = renderer.domElement.getBoundingClientRect();

            const x1 = clamp(Math.min(startClient.x, nowClient.x) - canvasRect.left, 0, canvasRect.width);

            const y1 = clamp(Math.min(startClient.y, nowClient.y) - canvasRect.top, 0, canvasRect.height);

            const x2 = clamp(Math.max(startClient.x, nowClient.x) - canvasRect.left, 0, canvasRect.width);

            const y2 = clamp(Math.max(startClient.y, nowClient.y) - canvasRect.top, 0, canvasRect.height);



            rectEl.style.left = x1 + 'px';

            rectEl.style.top = y1 + 'px';

            rectEl.style.width = Math.max(0, x2 - x1) + 'px';

            rectEl.style.height = Math.max(0, y2 - y1) + 'px';

            rectEl.style.display = 'block';

        }



        function selectObjectsInClientRect(startClient, endClient) {

            if (!renderer || !camera) return [];

            const canvasRect = renderer.domElement.getBoundingClientRect();

            const rx1 = clamp(Math.min(startClient.x, endClient.x) - canvasRect.left, 0, canvasRect.width);

            const ry1 = clamp(Math.min(startClient.y, endClient.y) - canvasRect.top, 0, canvasRect.height);

            const rx2 = clamp(Math.max(startClient.x, endClient.x) - canvasRect.left, 0, canvasRect.width);

            const ry2 = clamp(Math.max(startClient.y, endClient.y) - canvasRect.top, 0, canvasRect.height);

            const rw = Math.max(0, rx2 - rx1);

            const rh = Math.max(0, ry2 - ry1);

            if (rw < 2 || rh < 2) return [];



            const selected = [];

            const box = new THREE.Box3();

            const corners = [

                new THREE.Vector3(), new THREE.Vector3(), new THREE.Vector3(), new THREE.Vector3(),

                new THREE.Vector3(), new THREE.Vector3(), new THREE.Vector3(), new THREE.Vector3(),

            ];



            for (const obj of (objects || [])) {

                if (!obj || !obj.visible) continue;

                if (obj.userData && obj.userData.isHelper) continue;

                if (obj.userData && obj.userData.isLightMarker) continue;



                box.setFromObject(obj);

                const min = box.min;

                const max = box.max;

                corners[0].set(min.x, min.y, min.z);

                corners[1].set(max.x, min.y, min.z);

                corners[2].set(min.x, max.y, min.z);

                corners[3].set(max.x, max.y, min.z);

                corners[4].set(min.x, min.y, max.z);

                corners[5].set(max.x, min.y, max.z);

                corners[6].set(min.x, max.y, max.z);

                corners[7].set(max.x, max.y, max.z);



                let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;

                let ok = 0;

                for (let i = 0; i < 8; i++) {

                    const v = corners[i].clone().project(camera);

                    if (!isFinite(v.x) || !isFinite(v.y) || !isFinite(v.z)) continue;

                    // Convert NDC -> px dans le canvas

                    const sx = (v.x * 0.5 + 0.5) * canvasRect.width;

                    const sy = (-v.y * 0.5 + 0.5) * canvasRect.height;

                    minX = Math.min(minX, sx);

                    minY = Math.min(minY, sy);

                    maxX = Math.max(maxX, sx);

                    maxY = Math.max(maxY, sy);

                    ok++;

                }

                if (ok < 2) continue;



                const intersects = !(maxX < rx1 || minX > rx2 || maxY < ry1 || minY > ry2);

                if (intersects) selected.push(obj);

            }



            return selected;

        }



        function initSculptUI() {

            const radius = document.getElementById('brushRadius');

            const radiusVal = document.getElementById('brushRadiusValue');

            const strength = document.getElementById('brushStrength');

            const strengthVal = document.getElementById('brushStrengthValue');

            const action = document.getElementById('sculptAction');

            const textureSelect = document.getElementById('brushTextureSelect');

            const uploadBtn = document.getElementById('uploadTextureBtn');

            const uploadInput = document.getElementById('brushTextureUpload');

            const status = document.getElementById('uploadTextureStatus');



            if (radius && radiusVal) {

                brushRadiusPx = parseInt(radius.value, 10) || 80;

                radiusVal.textContent = String(brushRadiusPx);

                radius.addEventListener('input', () => {

                    brushRadiusPx = parseInt(radius.value, 10) || 1;

                    radiusVal.textContent = String(brushRadiusPx);

                });

            }



            if (strength && strengthVal) {

                brushStrength = parseFloat(strength.value) || 0.6;

                strengthVal.textContent = brushStrength.toFixed(2);

                strength.addEventListener('input', () => {

                    brushStrength = parseFloat(strength.value) || 0;

                    strengthVal.textContent = brushStrength.toFixed(2);

                });

            }



            if (action) {

                sculptAction = action.value || 'smooth';

                action.addEventListener('change', () => {

                    sculptAction = action.value || 'smooth';

                });

            }



            if (textureSelect) {

                textureSelect.addEventListener('change', async () => {

                    await loadBrushMaskFromSelection();

                });

            }



            if (uploadBtn && uploadInput) {

                uploadBtn.addEventListener('click', async () => {

                    if (!uploadInput.files || uploadInput.files.length === 0) {

                        if (status) status.textContent = 'Choisis une image à importer.';

                        return;

                    }

                    const file = uploadInput.files[0];

                    const form = new FormData();

                    form.append('upload_texture', '1');

                    form.append('texture', file);

                    if (status) status.textContent = 'Upload en cours...';



                    try {

                        const res = await fetch(window.location.href, { method: 'POST', body: form });

                        const data = await res.json();

                        if (!data || !data.ok) {

                            if (status) status.textContent = data && data.error ? data.error : 'Erreur upload.';

                            return;

                        }

                        if (status) status.textContent = 'Texture importée.';

                        if (Array.isArray(data.textures) && textureSelect) {

                            const current = textureSelect.value;

                            textureSelect.innerHTML = '<option value="">Aucune</option>';

                            for (const name of data.textures) {

                                const opt = document.createElement('option');

                                opt.value = name;

                                opt.textContent = name;

                                textureSelect.appendChild(opt);

                            }

                            // Sélectionner la texture importée

                            textureSelect.value = file.name;

                            if (textureSelect.value !== file.name) {

                                // Si nom modifié (sanitization), tenter la dernière texture

                                const last = data.textures[data.textures.length - 1];

                                if (last) textureSelect.value = last;

                            }

                            await loadBrushMaskFromSelection();

                        }

                    } catch (err) {

                        if (status) status.textContent = 'Erreur réseau.';

                    }

                });

            }



            // Canvas masque

            brushMaskCanvas = document.createElement('canvas');

            brushMaskCanvas.width = brushMaskSize;

            brushMaskCanvas.height = brushMaskSize;

            brushMaskCtx = brushMaskCanvas.getContext('2d', { willReadFrequently: true });

            brushMaskReady = false;

        }



        async function loadBrushMaskFromSelection() {

            const textureSelect = document.getElementById('brushTextureSelect');

            if (!textureSelect || !brushMaskCtx) return;



            const file = textureSelect.value;

            brushMaskReady = false;



            brushMaskCtx.clearRect(0, 0, brushMaskSize, brushMaskSize);



            if (!file) {

                // masque neutre

                brushMaskCtx.fillStyle = '#ffffff';

                brushMaskCtx.fillRect(0, 0, brushMaskSize, brushMaskSize);

                brushMaskReady = false;

                return;

            }



            const img = new Image();

            img.decoding = 'async';

            img.src = 'textureplan/' + encodeURIComponent(file);

            await new Promise((resolve) => {

                img.onload = () => resolve(true);

                img.onerror = () => resolve(false);

            });



            brushMaskCtx.clearRect(0, 0, brushMaskSize, brushMaskSize);

            brushMaskCtx.drawImage(img, 0, 0, brushMaskSize, brushMaskSize);

            brushMaskReady = true;

        }



        function prepareGeometryAdjacency(mesh) {

            if (!mesh || !mesh.geometry || !mesh.geometry.attributes || !mesh.geometry.attributes.position) return;

            const geom = mesh.geometry;

            if (mesh.userData && mesh.userData.adjacencyPrepared) return;



            // Assure une géométrie indexée pour l'adjacence

            if (!geom.index) {

                geom.setIndex([...Array(geom.attributes.position.count).keys()]);

            }



            const idx = geom.index.array;

            const vcount = geom.attributes.position.count;

            const neighbors = Array.from({ length: vcount }, () => new Set());



            for (let i = 0; i < idx.length; i += 3) {

                const a = idx[i], b = idx[i + 1], c = idx[i + 2];

                neighbors[a].add(b); neighbors[a].add(c);

                neighbors[b].add(a); neighbors[b].add(c);

                neighbors[c].add(a); neighbors[c].add(b);

            }



            mesh.userData.neighbors = neighbors.map(s => Array.from(s));

            mesh.userData.adjacencyPrepared = true;

        }



        // Regroupe les sommets "dupliqués" (même position) pour éviter les fissures lors du sculpt.

        // Beaucoup de meshes ont des sommets séparés sur les seams UV / angles: sans weld, ils bougent différemment.

        function prepareGeometryWeldGroups(mesh) {

            if (!mesh || !mesh.geometry || !mesh.geometry.attributes || !mesh.geometry.attributes.position) return;

            mesh.userData = mesh.userData || {};

            if (mesh.userData.weldPrepared) return;



            const geom = mesh.geometry;

            const pos = geom.attributes.position;

            const vcount = pos.count;



            const eps = 1e-4; // tolérance de regroupement

            const keyToGroup = new Map();

            const groups = [];

            const indexToGroup = new Int32Array(vcount);



            for (let i = 0; i < vcount; i++) {

                const x = pos.getX(i);

                const y = pos.getY(i);

                const z = pos.getZ(i);

                const kx = Math.round(x / eps);

                const ky = Math.round(y / eps);

                const kz = Math.round(z / eps);

                const key = kx + ',' + ky + ',' + kz;

                let gid = keyToGroup.get(key);

                if (gid === undefined) {

                    gid = groups.length;

                    groups.push([]);

                    keyToGroup.set(key, gid);

                }

                groups[gid].push(i);

                indexToGroup[i] = gid;

            }



            mesh.userData.weldGroups = groups;

            mesh.userData.weldIndexToGroup = indexToGroup;

            mesh.userData.weldPrepared = true;

        }



        function getWorldRadiusFromPixels(px, hitPointWorld) {

            const container = document.querySelector('.viewer-container');

            const heightPx = container ? container.offsetHeight : window.innerHeight;

            const dist = camera.position.distanceTo(hitPointWorld);

            const vFov = THREE.MathUtils.degToRad(camera.fov);

            const worldPerPixel = (2 * dist * Math.tan(vFov / 2)) / Math.max(1, heightPx);

            return px * worldPerPixel;

        }



        function sampleBrushMask(u, v) {

            if (!brushMaskReady || !brushMaskCtx) return 1;

            const x = Math.max(0, Math.min(brushMaskSize - 1, Math.floor(u * brushMaskSize)));

            const y = Math.max(0, Math.min(brushMaskSize - 1, Math.floor(v * brushMaskSize)));

            const data = brushMaskCtx.getImageData(x, y, 1, 1).data;

            // gris (0..1)

            return (data[0] + data[1] + data[2]) / (3 * 255);

        }



        function sculptAtPointerEvent(e) {

            if (!selectedObject || !selectedObject.geometry || !selectedObject.geometry.attributes || !selectedObject.geometry.attributes.position) return;

            setMouseFromEvent(e);

            raycaster.setFromCamera(mouse, camera);

            const hits = raycaster.intersectObject(selectedObject, false);

            if (!hits || hits.length === 0) return;

            const hit = hits[0];

            const hitPointWorld = hit.point.clone();

            const hitNormalWorld = hit.face ? hit.face.normal.clone().applyMatrix3(tmpMat3.getNormalMatrix(selectedObject.matrixWorld)).normalize() : new THREE.Vector3(0, 1, 0);

            sculptLastHit = { p: hitPointWorld, n: hitNormalWorld };



            const geom = selectedObject.geometry;

            const pos = geom.attributes.position;

            if (!geom.attributes.normal) {

                geom.computeVertexNormals();

            }

            const normalAttr = geom.attributes.normal;

            const normalMatrix = tmpMat3.getNormalMatrix(selectedObject.matrixWorld);



            const worldRadius = getWorldRadiusFromPixels(brushRadiusPx, hitPointWorld);

            const invWorld = tmpVec3c.copy(hitPointWorld).applyMatrix4(new THREE.Matrix4().copy(selectedObject.matrixWorld).invert());



            // Base brush plane

            const n = hitNormalWorld;

            const up = Math.abs(n.y) < 0.9 ? new THREE.Vector3(0, 1, 0) : new THREE.Vector3(1, 0, 0);

            const t = tmpVec3.copy(up).cross(n).normalize();

            const b = tmpVec3b.copy(n).cross(t).normalize();



            // Cache adjacency

            prepareGeometryAdjacency(selectedObject);

            const neighbors = selectedObject.userData.neighbors || null;



            // Cache weld groups (anti fissures)

            prepareGeometryWeldGroups(selectedObject);

            const weldGroups = selectedObject.userData.weldGroups || null;

            const weldIndexToGroup = selectedObject.userData.weldIndexToGroup || null;

            const processedGroups = new Set();



            // Modifs sur vertices dans le rayon

            for (let i = 0; i < pos.count; i++) {

                // Appliquer 1 seule fois par groupe soudé

                let groupIndices = null;

                let baseIndex = i;

                if (weldGroups && weldIndexToGroup) {

                    const gid = weldIndexToGroup[i];

                    if (processedGroups.has(gid)) continue;

                    processedGroups.add(gid);

                    groupIndices = weldGroups[gid] || [i];

                    baseIndex = groupIndices[0] ?? i;

                }



                // position monde du sommet (baseIndex)

                tmpVec3.set(pos.getX(baseIndex), pos.getY(baseIndex), pos.getZ(baseIndex));

                tmpVec3.applyMatrix4(selectedObject.matrixWorld);



                const dist = tmpVec3.distanceTo(hitPointWorld);

                if (dist > worldRadius) continue;



                const falloff = Math.pow(1 - dist / worldRadius, 2);



                // UV local du pinceau (0..1)

                const off = tmpVec3b.copy(tmpVec3).sub(hitPointWorld);

                const u = clamp((off.dot(t) / worldRadius) * 0.5 + 0.5, 0, 1);

                const v = clamp((off.dot(b) / worldRadius) * 0.5 + 0.5, 0, 1);

                const mask = sampleBrushMask(u, v);

                const w = falloff * mask;



                if (w <= 0) continue;



                if (sculptAction === 'smooth') {

                    if (!neighbors) continue;



                    // Union des voisins sur tout le groupe soudé

                    const nbSet = new Set();

                    const src = groupIndices || [baseIndex];

                    for (const gi of src) {

                        const nbs = neighbors[gi];

                        if (!nbs) continue;

                        for (const j of nbs) nbSet.add(j);

                    }

                    if (nbSet.size === 0) continue;



                    const avg = new THREE.Vector3(0, 0, 0);

                    for (const j of nbSet) {

                        avg.x += pos.getX(j);

                        avg.y += pos.getY(j);

                        avg.z += pos.getZ(j);

                    }

                    avg.multiplyScalar(1 / nbSet.size);

                    const cur = new THREE.Vector3(pos.getX(baseIndex), pos.getY(baseIndex), pos.getZ(baseIndex));

                    const lerpAmt = clamp(brushStrength * 0.25 * w, 0, 1);

                    cur.lerp(avg, lerpAmt);

                    if (groupIndices) {

                        for (const gi of groupIndices) pos.setXYZ(gi, cur.x, cur.y, cur.z);

                    } else {

                        pos.setXYZ(baseIndex, cur.x, cur.y, cur.z);

                    }

                    continue;

                }



                if (sculptAction === 'flatten') {

                    // aplani vers un plan défini au point de hit

                    const pObj = new THREE.Vector3(pos.getX(baseIndex), pos.getY(baseIndex), pos.getZ(baseIndex));

                    const pWorld = tmpVec3.copy(pObj).applyMatrix4(selectedObject.matrixWorld);

                    const toPlane = tmpVec3b.copy(pWorld).sub(hitPointWorld);

                    const d = toPlane.dot(n);

                    const moveWorld = tmpVec3b.copy(n).multiplyScalar(-d * clamp(brushStrength * w, 0, 1));

                    // retour en obj

                    const nextWorld = pWorld.add(moveWorld);

                    const nextObj = nextWorld.applyMatrix4(new THREE.Matrix4().copy(selectedObject.matrixWorld).invert());

                    if (groupIndices) {

                        for (const gi of groupIndices) pos.setXYZ(gi, nextObj.x, nextObj.y, nextObj.z);

                    } else {

                        pos.setXYZ(baseIndex, nextObj.x, nextObj.y, nextObj.z);

                    }

                    continue;

                }



                // raise/lower: déplacement selon normale du hit (évite que des sommets dupliqués partent chacun dans leur direction)

                const dir = (sculptAction === 'lower') ? -1 : 1;

                const delta = dir * brushStrength * w * (worldRadius * 0.15);

                const movedWorld = tmpVec3.copy(tmpVec3).add(tmpVec3b.copy(n).multiplyScalar(delta));

                const movedObj = movedWorld.applyMatrix4(new THREE.Matrix4().copy(selectedObject.matrixWorld).invert());

                if (groupIndices) {

                    for (const gi of groupIndices) pos.setXYZ(gi, movedObj.x, movedObj.y, movedObj.z);

                } else {

                    pos.setXYZ(baseIndex, movedObj.x, movedObj.y, movedObj.z);

                }

            }



            pos.needsUpdate = true;

            geom.computeVertexNormals();

            if (geom.attributes.normal) geom.attributes.normal.needsUpdate = true;

        }



        function setupPointerEvents(canvas) {

            const el = renderer.domElement;



            el.addEventListener('pointerdown', (e) => {

                if (!renderer || !camera) return;



                // Gestion multi-touch: 1 doigt = outil, 2 doigts = navigation caméra (pinch/zoom)

                activePointers.set(e.pointerId, { type: e.pointerType });



                // Spécifique Brush (mobile): si 2 doigts actifs, on ne capture pas et on laisse la caméra

                if (currentTool === 'brush' && selectedObject && e.pointerType === 'touch' && activePointers.size >= 2) {

                    if (controls) controls.enabled = true;

                    return;

                }



                // Mode squelette: on laisse TransformControls gérer les events.

                // On ne capture le pointeur QUE si on clique un point d'os.

                if (currentTool === 'rig' && activeRig && activeRig.bonePickersGroup) {

                    setMouseFromEvent(e);

                    raycaster.setFromCamera(mouse, camera);



                    const hits = raycaster.intersectObjects(activeRig.bonePickersGroup.children, false);

                    if (hits.length > 0) {

                        const h = hits[0].object;

                        const bone = h.userData && h.userData.bone;

                        if (bone) {

                            activeRig.selectedBone = bone;

                            ensureTransformControls();

                            transformControls.attach(bone);

                            updateRigPanelUI();

                            // Sync vers le panneau Animations (Élément animé)

                            if (window.__animSelectTargetUuid) window.__animSelectTargetUuid(bone.uuid);

                            e.preventDefault();

                            return;

                        }

                    }

                    // Pas de hit: on bloque quand même la caméra (mode squelette)

                    e.preventDefault();

                    return;

                }



                // Move: laisser TransformControls gérer le drag (X/Y/Z)

                if (currentTool === 'move' && selectedObject) {

                    try {

                        ensureTransformControls();

                        transformControls.setMode('translate');

                        transformControls.showX = true;

                        transformControls.showY = true;

                        transformControls.showZ = true;

                        transformControls.attach(selectedObject);

                    } catch (_) {}

                    return;

                }



                isPointerDown = true;

                activePointerId = e.pointerId;

                dragStartClient.x = e.clientX;

                dragStartClient.y = e.clientY;



                try { el.setPointerCapture(e.pointerId); } catch (_) {}



                setMouseFromEvent(e);

                raycaster.setFromCamera(mouse, camera);



                // Placement de Point Light (prioritaire)

                if (isPlacingPointLight) {

                    // Ray -> intersection plan sol y=0

                    const ray = raycaster.ray;

                    const ok = ray.intersectPlane(groundPlane, tmpRayPoint);

                    if (ok) {

                        addPointLight(tmpRayPoint.x, tmpRayPoint.y + 2, tmpRayPoint.z, placingPointLightType || 'static');

                        // reste en mode placement pour en poser plusieurs

                        e.preventDefault();

                        return;

                    }

                }



                // Select.Grouper (sélection rectangle)

                if (currentTool === 'group') {

                    groupRectActive = true;

                    groupRectStart.x = e.clientX;

                    groupRectStart.y = e.clientY;

                    groupRectNow.x = e.clientX;

                    groupRectNow.y = e.clientY;

                    dragMode = 'group';

                    if (controls) controls.enabled = false;

                    updateGroupSelectRectUI(groupRectStart, groupRectNow, true);

                    e.preventDefault();

                    return;

                }



                // Rotation: handles (on réutilise les edgeHandles)

                if (selectedObject && currentTool === 'rotate') {

                    const hits = raycaster.intersectObjects(edgeHandles, false);

                    if (hits.length > 0) {

                        activeHandle = hits[0].object;

                        rotateAxis = activeHandle && activeHandle.userData ? activeHandle.userData.axis : null;

                        dragMode = 'rotate';



                        // Snapshot rotation pour application stable (angle total)

                        dragStartRotations.clear();

                        for (const r of uniqRootsFrom(getEffectiveSelection())) {

                            dragStartRotations.set(r.uuid, r.quaternion.clone());

                        }



                        if (controls) controls.enabled = false;

                        e.preventDefault();

                        return;

                    }

                }



                // Priorité: handles si outil scale/deform

                if (selectedObject && (currentTool === 'scale' || currentTool === 'deform')) {

                    const candidates = currentTool === 'scale' ? cornerHandles : edgeHandles;

                    const hits = raycaster.intersectObjects(candidates, false);

                    if (hits.length > 0) {

                        activeHandle = hits[0].object;

                        dragMode = currentTool;

                        dragStartScale.copy(selectedObject.scale);

                        if (controls) controls.enabled = false;

                        e.preventDefault();

                        return;

                    }

                }



                // Sculpt

                if (currentTool === 'sculpt' && selectedObject) {

                    dragMode = 'sculpt';

                    if (controls) controls.enabled = false;

                    sculptAtPointerEvent(e);

                    e.preventDefault();

                    return;

                }



                // Brush peinture

                if (currentTool === 'brush' && selectedObject) {

                    dragMode = 'paint';

                    if (controls) controls.enabled = false;

                    paintAtPointerEvent(e);

                    e.preventDefault();

                    return;

                }



                if (currentTool === 'move' && selectedObject) {

                    dragMode = 'move';

                    // Déplacement groupé (multi-sélection) si besoin

                    dragStartPositions.clear();

                    for (const r of uniqRootsFrom(getEffectiveSelection())) {

                        dragStartPositions.set(r.uuid, r.position.clone());

                    }

                    if (controls) controls.enabled = false;

                    e.preventDefault();

                    return;

                }



                if (currentTool === 'select') {

                    const hits = raycaster.intersectObjects(objects, false);

                    if (hits.length > 0) {

                        selectObject(hits[0].object);

                    } else {

                        deselectObject();

                    }

                    e.preventDefault();

                }

            });



            el.addEventListener('pointermove', (e) => {

                if (!isPointerDown) return;

                if (activePointerId !== null && e.pointerId !== activePointerId) return;

                if (!dragMode) return;



                const deltaX = e.clientX - dragStartClient.x;

                const deltaY = e.clientY - dragStartClient.y;



                if (dragMode === 'group') {

                    groupRectNow.x = e.clientX;

                    groupRectNow.y = e.clientY;

                    updateGroupSelectRectUI(groupRectStart, groupRectNow, true);

                    e.preventDefault();

                    return;

                }



                if (dragMode === 'rotate') {

                    const axis = rotateAxis || (activeHandle && activeHandle.userData ? activeHandle.userData.axis : null);

                    const axisWorld = axis === 'x' ? new THREE.Vector3(1, 0, 0) : (axis === 'y' ? new THREE.Vector3(0, 1, 0) : new THREE.Vector3(0, 0, 1));

                    const angle = (deltaX - deltaY) * 0.01;



                    // Restaure la rotation de départ puis applique l'angle total

                    const roots = uniqRootsFrom(getEffectiveSelection());

                    for (const r of roots) {

                        const q0 = dragStartRotations.get(r.uuid);

                        if (q0) r.quaternion.copy(q0);

                    }

                    rotateSelectionAroundWorldAxis(axisWorld, angle);

                    updateSelectionVisuals();

                    updateObjectControls();

                    e.preventDefault();

                    return;

                }



                if (!selectedObject) return;



                if (dragMode === 'move') {

                    const roots = uniqRootsFrom(getEffectiveSelection());

                    for (const r of roots) {

                        const p0 = dragStartPositions.get(r.uuid);

                        if (!p0) continue;

                        r.position.x = p0.x + deltaX * 0.01;

                        r.position.y = p0.y - deltaY * 0.01;

                    }

                    updateSelectionVisuals();

                    updateObjectControls();

                    e.preventDefault();

                    return;

                }



                if (dragMode === 'sculpt') {

                    sculptAtPointerEvent(e);

                    updateSelectionVisuals();

                    e.preventDefault();

                    return;

                }



                if (dragMode === 'paint') {

                    // Si l'utilisateur passe à 2 doigts (pinch), on rend la main à la caméra

                    if (e.pointerType === 'touch' && activePointers.size >= 2) {

                        dragMode = null;

                        if (controls) controls.enabled = true;

                        return;

                    }

                    paintAtPointerEvent(e);

                    e.preventDefault();

                    return;

                }



                const rawFactor = 1 + (deltaX - deltaY) * 0.005;

                const factor = clamp(rawFactor, 0.02, 50);



                if (dragMode === 'scale') {

                    selectedObject.scale.copy(dragStartScale).multiplyScalar(factor);

                    updateSelectionVisuals();

                    updateObjectControls();

                    e.preventDefault();

                    return;

                }



                if (dragMode === 'deform') {

                    const axis = activeHandle && activeHandle.userData ? activeHandle.userData.axis : null;

                    const next = dragStartScale.clone();

                    if (axis === 'x') next.x = dragStartScale.x * factor;

                    if (axis === 'y') next.y = dragStartScale.y * factor;

                    if (axis === 'z') next.z = dragStartScale.z * factor;

                    selectedObject.scale.copy(next);

                    updateSelectionVisuals();

                    updateObjectControls();

                    e.preventDefault();

                }

            });



            const endDrag = (e) => {

                // Toujours nettoyer le pointeur, même si ce n'est pas le pointeur actif

                activePointers.delete(e.pointerId);



                if (activePointerId !== null && e.pointerId !== activePointerId) return;

                isPointerDown = false;



                if (dragMode === 'group' && groupRectActive) {

                    groupRectActive = false;

                    updateGroupSelectRectUI(groupRectStart, groupRectNow, false);



                    const picked = selectObjectsInClientRect(groupRectStart, groupRectNow);

                    if (picked.length > 0) {

                        setSelectedObjects(picked, picked[0]);

                    } else {

                        deselectObject();

                    }

                }



                dragMode = null;

                activeHandle = null;

                activePointerId = null;

                if (controls) controls.enabled = true;

                try { el.releasePointerCapture(e.pointerId); } catch (_) {}

            };



            el.addEventListener('pointerup', endDrag);

            el.addEventListener('pointercancel', endDrag);

            el.addEventListener('pointerleave', endDrag);

        }



        function addShape(shapeType) {

            if (!scene) return; // Vérifier que la scène existe



            let geometry, material, mesh;



            switch(shapeType) {

                case 'cube':

                    geometry = new THREE.BoxGeometry(1, 1, 1, 20, 20, 20);

                    break;

                case 'sphere':

                    geometry = new THREE.SphereGeometry(0.5, 64, 48);

                    break;

                case 'cylinder':

                    geometry = new THREE.CylinderGeometry(0.5, 0.5, 1, 64, 16);

                    break;

                case 'cone':

                    geometry = new THREE.ConeGeometry(0.5, 1, 64, 16);

                    break;

                case 'plane':

                    geometry = new THREE.PlaneGeometry(2, 2, 120, 120);

                    break;

                case 'triangle':

                    geometry = new THREE.BufferGeometry();

                    const vertices = new Float32Array([

                        0, 1, 0,

                        -1, -1, 0,

                        1, -1, 0

                    ]);

                    geometry.setAttribute('position', new THREE.BufferAttribute(vertices, 3));

                    geometry.computeVertexNormals();

                    break;

                case 'torus':

                    geometry = new THREE.TorusGeometry(0.5, 0.2, 32, 64);

                    break;

                case 'ring':

                    geometry = new THREE.RingGeometry(0.3, 0.7, 64, 8);

                    break;

                case 'icosahedron':

                    geometry = new THREE.IcosahedronGeometry(0.6, 2);

                    break;

                case 'dodecahedron':

                    geometry = new THREE.DodecahedronGeometry(0.6, 0);

                    break;

                case 'octahedron':

                    geometry = new THREE.OctahedronGeometry(0.6, 0);

                    break;

                case 'torusknot':

                    geometry = new THREE.TorusKnotGeometry(0.4, 0.15, 128, 32, 2, 3);

                    break;

            }



            material = new THREE.MeshPhongMaterial({

                color: 0xff0000,

                transparent: true,

                opacity: 1

            });



            mesh = new THREE.Mesh(geometry, material);

            mesh.position.set(

                Math.random() * 4 - 2,

                Math.random() * 4,

                Math.random() * 4 - 2

            );



            mesh.castShadow = true;

            mesh.receiveShadow = true;

            mesh.userData = {

                isObject: true,

                type: shapeType,

                id: Date.now() + Math.random(),

                name: shapeType.charAt(0).toUpperCase() + shapeType.slice(1)

            };



            scene.add(mesh);

            objects.push(mesh);

            selectObject(mesh);

            updateObjectList();

        }



        function updateObjectControls() {

            if (!selectedObject) return;



            // Couleur

            const mats = getMeshMaterials(selectedObject);

            if (mats.length > 0 && mats[0].color) {

                const color = new THREE.Color(mats[0].color);

                document.getElementById('color-picker').value = '#' + color.getHexString();

            }



            // Échelle (si déformation non-uniforme, on affiche une moyenne)

            const scale = (selectedObject.scale.x + selectedObject.scale.y + selectedObject.scale.z) / 3;

            document.getElementById('scale-slider').value = scale;

            document.getElementById('scale-value').textContent = scale.toFixed(1);



            // Opacité

            if (mats.length > 0 && typeof mats[0].opacity === 'number') {

                const opacity = mats[0].opacity;

                document.getElementById('opacity-slider').value = opacity;

                document.getElementById('opacity-value').textContent = opacity.toFixed(1);

            }



            // UI déplacement précis: centre monde de la sélection

            const cx = document.getElementById('posXInput');

            const cy = document.getElementById('posYInput');

            const cz = document.getElementById('posZInput');

            if (cx && cy && cz) {

                const c = getSelectionWorldCenter();

                if (c) {

                    cx.value = String(c.x.toFixed(6));

                    cy.value = String(c.y.toFixed(6));

                    cz.value = String(c.z.toFixed(6));

                }

            }

        }



        function updateLightControls() {

            if (!light) return;

            document.getElementById('light-color').value = '#' + light.color.getHexString();

            document.getElementById('intensity-slider').value = light.intensity;

            document.getElementById('intensity-value').textContent = light.intensity.toFixed(1);

            document.getElementById('light-x-slider').value = light.position.x;

            document.getElementById('light-x').textContent = light.position.x.toFixed(1);

            document.getElementById('light-y-slider').value = light.position.y;

            document.getElementById('light-y').textContent = light.position.y.toFixed(1);

            document.getElementById('light-z-slider').value = light.position.z;

            document.getElementById('light-z').textContent = light.position.z.toFixed(1);

        }



        function updateObjectList() {

            const list = document.getElementById('object-list');

            list.innerHTML = '';



            objects.forEach(obj => {

                const li = document.createElement('li');

                li.className = 'object-item';

                if (selectedObject === obj) {

                    li.className += ' selected';

                }

                li.textContent = obj.userData.name + ' ' + (obj.userData.id.toString().slice(-4));

                li.onclick = () => selectObject(obj);

                list.appendChild(li);

            });

        }



        function prepareExportData() {

            const sceneData = {

                objects: objects.map(obj => ({

                    type: obj.userData.type,

                    position: [obj.position.x, obj.position.y, obj.position.z],

                    scale: [obj.scale.x, obj.scale.y, obj.scale.z],

                    rotation: [obj.rotation.x, obj.rotation.y, obj.rotation.z],

                    color: obj.material.color.getHex(),

                    opacity: obj.material.opacity

                })),

                light: {

                    color: light ? light.color.getHex() : 0xffffff,

                    intensity: light ? light.intensity : 1,

                    position: light ? [light.position.x, light.position.y, light.position.z] : [0, 5, 5]

                }

            };

            return JSON.stringify(sceneData);

        }



        // Gestionnaires d'événements

        document.querySelectorAll('[data-shape]').forEach(btn => {

            btn.addEventListener('click', () => {

                addShape(btn.dataset.shape);

            });

        });



        // Range UI: met à jour le remplissage (compatible Chrome/Edge/Firefox)

        const updateRangeVisual = (el) => {

            if (!el) return;

            const min = Number(el.min ?? 0);

            const max = Number(el.max ?? 100);

            const val = Number(el.value ?? 0);

            const denom = (max - min);

            const pct = denom > 0 ? ((val - min) / denom) * 100 : 0;

            el.style.setProperty('--range-pct', pct.toFixed(3) + '%');

        };

        document.querySelectorAll('input[type="range"]').forEach((r) => {

            updateRangeVisual(r);

            r.addEventListener('input', () => updateRangeVisual(r));

            r.addEventListener('change', () => updateRangeVisual(r));

        });



        document.getElementById('select-btn').addEventListener('click', function() {

            setTool('select');

        });



        document.getElementById('move-btn').addEventListener('click', function() {

            setTool('move');

        });



        document.getElementById('scale-btn').addEventListener('click', function() {

            if (!selectedObject) return;

            setTool('scale');

        });



        document.getElementById('deform-btn').addEventListener('click', function() {

            if (!selectedObject) return;

            setTool('deform');

        });



        const rotateBtn = document.getElementById('rotate-btn');

        if (rotateBtn) {

            rotateBtn.addEventListener('click', function() {

                if (!selectedObject) return;

                setTool('rotate');

            });

        }



        const groupBtn = document.getElementById('group-btn');

        if (groupBtn) {

            groupBtn.addEventListener('click', function() {

                setTool('group');

            });

        }



        const brushBtn = document.getElementById('brush-btn');

        if (brushBtn) {

            brushBtn.addEventListener('click', function() {

                if (!selectedObject) return;

                setTool('brush');

            });

        }



        document.getElementById('sculpt-btn').addEventListener('click', function() {

            if (!selectedObject) return;

            setTool('sculpt');

            // Charge la texture sélectionnée si besoin

            loadBrushMaskFromSelection();

        });



        document.getElementById('color-picker').addEventListener('input', (e) => {

            setSelectedMeshColor(e.target.value);

        });



        document.getElementById('scale-slider').addEventListener('input', (e) => {

            const value = parseFloat(e.target.value);

            document.getElementById('scale-value').textContent = value.toFixed(1);

            if (selectedObject) {

                selectedObject.scale.setScalar(value);

                updateSelectionVisuals();

            }

        });



        document.getElementById('opacity-slider').addEventListener('input', (e) => {

            const value = parseFloat(e.target.value);

            document.getElementById('opacity-value').textContent = value.toFixed(1);

            setSelectedMeshOpacity(value);

        });



        // Déplacement précis

        const posUseSelectionBtn = document.getElementById('posUseSelectionBtn');

        const posApplyBtn = document.getElementById('posApplyBtn');

        const posXInput = document.getElementById('posXInput');

        const posYInput = document.getElementById('posYInput');

        const posZInput = document.getElementById('posZInput');



        if (posUseSelectionBtn) posUseSelectionBtn.addEventListener('click', () => {

            const c = getSelectionWorldCenter();

            if (!c || !posXInput || !posYInput || !posZInput) return;

            posXInput.value = String(c.x.toFixed(6));

            posYInput.value = String(c.y.toFixed(6));

            posZInput.value = String(c.z.toFixed(6));

        });



        if (posApplyBtn) posApplyBtn.addEventListener('click', () => {

            if (!posXInput || !posYInput || !posZInput) return;

            moveSelectionCenterToWorldPosition(posXInput.value, posYInput.value, posZInput.value);

        });



        // Export: remplir depuis caméra actuelle

        const exportUseCurrentCameraBtn = document.getElementById('exportUseCurrentCameraBtn');

        const exportCameraPosX = document.getElementById('exportCameraPosX');

        const exportCameraPosY = document.getElementById('exportCameraPosY');

        const exportCameraPosZ = document.getElementById('exportCameraPosZ');

        const exportCameraTargetX = document.getElementById('exportCameraTargetX');

        const exportCameraTargetY = document.getElementById('exportCameraTargetY');

        const exportCameraTargetZ = document.getElementById('exportCameraTargetZ');

        if (exportUseCurrentCameraBtn) exportUseCurrentCameraBtn.addEventListener('click', () => {

            if (!camera) return;

            if (exportCameraPosX) exportCameraPosX.value = String(camera.position.x.toFixed(6));

            if (exportCameraPosY) exportCameraPosY.value = String(camera.position.y.toFixed(6));

            if (exportCameraPosZ) exportCameraPosZ.value = String(camera.position.z.toFixed(6));

            const tgt = (controls && controls.target) ? controls.target : new THREE.Vector3(0, 0, 0);

            if (exportCameraTargetX) exportCameraTargetX.value = String(tgt.x.toFixed(6));

            if (exportCameraTargetY) exportCameraTargetY.value = String(tgt.y.toFixed(6));

            if (exportCameraTargetZ) exportCameraTargetZ.value = String(tgt.z.toFixed(6));

        });



        // Raccourcis maintien (PC): W=move, X=scale, C=rotate (uniquement en mode select)

        let holdToolPrev = null;

        let holdToolKey = null;



        const isTypingInField = (el) => {

            if (!el) return false;

            const tag = (el.tagName || '').toLowerCase();

            return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;

        };



        document.addEventListener('keydown', (e) => {

            if (!e || e.repeat) return;

            if (isTypingInField(document.activeElement)) return;

            // Seulement sur desktop / clavier

            if (isCoarsePointer) return;

            if (currentTool !== 'select') return;

            if (!selectedObject) return;



            const k = String(e.key || '').toLowerCase();

            let next = null;

            if (k === 'w') next = 'move';

            if (k === 'x') next = 'scale';

            if (k === 'c') next = 'rotate';

            if (!next) return;



            holdToolPrev = currentTool;

            holdToolKey = k;

            setTool(next);

            e.preventDefault();

        });



        document.addEventListener('keyup', (e) => {

            if (!e) return;

            const k = String(e.key || '').toLowerCase();

            if (!holdToolKey || k !== holdToolKey) return;

            holdToolKey = null;

            // Retour au select uniquement si on était bien en mode temporaire

            if (holdToolPrev) {

                setTool(holdToolPrev);

            } else {

                setTool('select');

            }

            holdToolPrev = null;

        });



        document.getElementById('light-color').addEventListener('input', (e) => {

            if (light) {

                light.color.set(e.target.value);

            }

        });



        document.getElementById('intensity-slider').addEventListener('input', (e) => {

            const value = parseFloat(e.target.value);

            document.getElementById('intensity-value').textContent = value.toFixed(1);

            if (light) {

                light.intensity = value;

            }

        });



        document.getElementById('light-x-slider').addEventListener('input', (e) => {

            const value = parseFloat(e.target.value);

            document.getElementById('light-x').textContent = value.toFixed(1);

            if (light) {

                light.position.x = value;

                // Met à jour les ombres si besoin

                applyShadowQualityToLight(light);

            }

        });



        document.getElementById('light-y-slider').addEventListener('input', (e) => {

            const value = parseFloat(e.target.value);

            document.getElementById('light-y').textContent = value.toFixed(1);

            if (light) {

                light.position.y = value;

                applyShadowQualityToLight(light);

            }

        });



        document.getElementById('light-z-slider').addEventListener('input', (e) => {

            const value = parseFloat(e.target.value);

            document.getElementById('light-z').textContent = value.toFixed(1);

            if (light) {

                light.position.z = value;

                applyShadowQualityToLight(light);

            }

        });



        // (Export GLB géré par initGlbExportUI())



        // Initialiser l'application

        init();



        // Ajouter un objet par défaut après l'initialisation

        setTimeout(() => {

            addShape('cube');

        }, 100);



        // === SYSTÈME DE TRADUCTION ===

        let pp3_translatable_texts = [];

        let pp3_current_lang = 'en';



        function pp3_captureTranslatableTexts() {

            pp3_translatable_texts = [];

            const seen = new Set();



            // Capturer les meta tags d'abord

            const metaDesc = document.querySelector('meta[name="description"]');

            if (metaDesc && metaDesc.content && metaDesc.content.trim()) {

                const text = metaDesc.content.trim();

                if (!seen.has(text)) {

                    pp3_translatable_texts.push(text);

                    seen.add(text);

                }

            }



            const metaKeys = document.querySelector('meta[name="keywords"]');

            if (metaKeys && metaKeys.content && metaKeys.content.trim()) {

                const text = metaKeys.content.trim();

                if (!seen.has(text)) {

                    pp3_translatable_texts.push(text);

                    seen.add(text);

                }

            }



            const titleTag = document.querySelector('title');

            if (titleTag && titleTag.textContent && titleTag.textContent.trim()) {

                const text = titleTag.textContent.trim();

                if (!seen.has(text)) {

                    pp3_translatable_texts.push(text);

                    seen.add(text);

                }

            }



            // Fonction pour extraire le texte direct d'un élément (sans enfants)

            function getDirectText(el) {

                let text = '';

                for (let node of el.childNodes) {

                    if (node.nodeType === Node.TEXT_NODE) {

                        text += node.textContent;

                    }

                }

                return text.trim();

            }



            // Capturer TOUS les éléments visibles

            const allElements = document.querySelectorAll('*');



            allElements.forEach(el => {

                // Ignorer les scripts et styles

                if (el.tagName === 'SCRIPT' || el.tagName === 'STYLE') return;



                // 1. Texte direct de l'élément

                const directText = getDirectText(el);

                if (directText && directText.length > 0 && directText.length < 500 && !seen.has(directText)) {

                    // Vérifier que ce n'est pas juste des espaces ou chiffres seuls

                    if (directText.match(/[a-zA-ZÀ-ſ]/)) {

                        seen.add(directText);

                        pp3_translatable_texts.push(directText);

                    }

                }



                // 2. Attributs traduisibles

                ['placeholder', 'title', 'aria-label', 'alt', 'data-label'].forEach(attr => {

                    const val = el.getAttribute(attr);

                    if (val && val.trim() && val.length < 500 && !seen.has(val.trim())) {

                        if (val.trim().match(/[a-zA-ZÀ-ſ]/)) {

                            seen.add(val.trim());

                            pp3_translatable_texts.push(val.trim());

                        }

                    }

                });



                // 3. Options de select

                if (el.tagName === 'OPTION') {

                    const text = el.textContent.trim();

                    if (text && text.length > 0 && text.length < 500 && !seen.has(text)) {

                        if (text.match(/[a-zA-ZÀ-ſ]/)) {

                            seen.add(text);

                            pp3_translatable_texts.push(text);

                        }

                    }

                }



                // 4. Valeur des boutons

                if (el.tagName === 'BUTTON' || el.tagName === 'INPUT') {

                    const val = el.value;

                    if (val && val.trim() && val.length < 500 && !seen.has(val.trim())) {

                        if (val.trim().match(/[a-zA-ZÀ-ſ]/)) {

                            seen.add(val.trim());

                            pp3_translatable_texts.push(val.trim());

                        }

                    }

                }

            });



            // Trier par longueur (plus courts en premier pour éviter les remplacements partiels)

            pp3_translatable_texts.sort((a, b) => b.length - a.length);



            console.log('Textes capturés:', pp3_translatable_texts.length);

        }



        function pp3_loadLanguages() {

            fetch('?pp3_action=traduction_list', {

                method: 'POST',

                headers: {'Content-Type': 'application/x-www-form-urlencoded'},

                body: 'pp3_action=traduction_list'

            })

            .then(r => r.json())

            .then(data => {

                if (data.ok && data.languages) {

                    const select = document.getElementById('pp3TradLangSelect');

                    select.innerHTML = '<option value="">-- Choisir une langue --</option>';

                    Object.entries(data.languages).forEach(([code, name]) => {

                        const opt = document.createElement('option');

                        opt.value = code;

                        opt.textContent = name + ' (' + code + ')';

                        select.appendChild(opt);

                    });

                }

            })

            .catch(err => console.error('Erreur chargement langues:', err));

        }



        function pp3_displayTranslations(lang, translations) {

            const container = document.getElementById('pp3TradContainer');

            container.innerHTML = '';



            pp3_translatable_texts.forEach((text, idx) => {

                const div = document.createElement('div');

                div.className = 'pp3-trad-item';



                const label = document.createElement('label');

                label.textContent = 'Texte #' + (idx + 1);



                const original = document.createElement('div');

                original.className = 'pp3-trad-original';

                original.textContent = 'Original: ' + text;



                const input = document.createElement('textarea');

                input.className = 'pp3-trad-input';

                input.dataset.key = text;

                input.value = translations[text] || '';

                input.placeholder = 'Traduction...';



                div.appendChild(label);

                div.appendChild(original);

                div.appendChild(input);

                container.appendChild(div);

            });



            document.getElementById('pp3TradCount').textContent = pp3_translatable_texts.length;

        }



        document.getElementById('pp3TradLoadBtn')?.addEventListener('click', () => {

            const lang = document.getElementById('pp3TradLangSelect').value;

            if (!lang) {

                alert('Choisir une langue');

                return;

            }



            pp3_current_lang = lang;

            pp3_captureTranslatableTexts();



            const formData = new FormData();

            formData.append('pp3_action', 'traduction_get');

            formData.append('lang', lang);



            fetch('', {method: 'POST', body: formData})

            .then(r => r.json())

            .then(data => {

                if (data.ok) {

                    pp3_displayTranslations(lang, data.translations || {});

                } else {

                    alert(data.error || 'Erreur');

                }

            })

            .catch(err => {

                console.error(err);

                alert('Erreur réseau');

            });

        });



        document.getElementById('pp3TradIaBtn')?.addEventListener('click', () => {

            const lang = pp3_current_lang;

            if (!lang) {

                alert('Charger d\'abord les traductions');

                return;

            }



            const inputs = document.querySelectorAll('.pp3-trad-input');

            const items = [];

            inputs.forEach(input => {

                items.push({key: input.dataset.key, text: input.dataset.key});

            });



            if (items.length === 0) {

                alert('Aucun texte à traduire');

                return;

            }



            const btn = document.getElementById('pp3TradIaBtn');

            btn.disabled = true;

            btn.textContent = 'Traduction en cours...';



            const formData = new FormData();

            formData.append('pp3_action', 'traduction_ia');

            formData.append('lang', lang);

            formData.append('items', JSON.stringify(items));



            fetch('', {method: 'POST', body: formData})

            .then(r => r.json())

            .then(data => {

                if (data.ok && data.translations) {

                    inputs.forEach((input, idx) => {

                        if (data.translations[idx]) {

                            input.value = data.translations[idx];

                        }

                    });

                    alert('Traduction terminée!');

                } else {

                    alert(data.error || 'Erreur traduction');

                }

            })

            .catch(err => {

                console.error(err);

                alert('Erreur réseau');

            })

            .finally(() => {

                btn.disabled = false;

                btn.textContent = 'Traduire avec IA';

            });

        });



        document.getElementById('pp3TradSaveBtn')?.addEventListener('click', () => {

            const lang = pp3_current_lang;

            if (!lang) {

                alert('Charger d\'abord les traductions');

                return;

            }



            const inputs = document.querySelectorAll('.pp3-trad-input');

            const translations = {};

            let count = 0;

            inputs.forEach(input => {

                if (input.value.trim()) {

                    translations[input.dataset.key] = input.value.trim();

                    count++;

                }

            });



            if (count === 0) {

                alert('Aucune traduction à sauvegarder');

                return;

            }



            const btn = document.getElementById('pp3TradSaveBtn');

            btn.disabled = true;

            btn.textContent = 'Sauvegarde en cours...';



            const formData = new FormData();

            formData.append('pp3_action', 'traduction_save');

            formData.append('lang', lang);

            formData.append('translations', JSON.stringify(translations));



            fetch('', {method: 'POST', body: formData})

            .then(r => r.json())

            .then(data => {

                if (data.ok) {

                    alert(`Traductions sauvegardées!\n\n${count} traductions\nDossier: /${lang}/\nFichier: /${lang}/index.php`);

                    pp3_loadExistingTranslations();

                } else {

                    alert(data.error || 'Erreur');

                }

            })

            .catch(err => {

                console.error(err);

                alert('Erreur réseau');

            })

            .finally(() => {

                btn.disabled = false;

                btn.textContent = 'Sauvegarder et générer dossier';

            });

        });



        document.getElementById('pp3SaveGroqKeyBtn')?.addEventListener('click', () => {

            const key = document.getElementById('pp3GroqApiKey').value.trim();

            if (!key) {

                alert('Entrer une clé API Groq');

                return;

            }



            const formData = new FormData();

            formData.append('pp3_action', 'save_groq_key');

            formData.append('groq_key', key);



            fetch('', {method: 'POST', body: formData})

            .then(r => r.json())

            .then(data => {

                if (data.ok) {

                    alert('Clé Groq sauvegardée!');

                } else {

                    alert(data.error || 'Erreur');

                }

            })

            .catch(err => {

                console.error(err);

                alert('Erreur réseau');

            });

        });



        // Initialiser traduction au chargement admin

        document.querySelector('[data-pp3-tab="traduction"]')?.addEventListener('click', () => {

            pp3_loadLanguages();

            pp3_captureTranslatableTexts();

            pp3_loadExistingTranslations();

            const formData = new FormData();

            formData.append('pp3_action', 'traduction_init');

            fetch('', {method: 'POST', body: formData});

        });



        function pp3_loadExistingTranslations() {

            const formData = new FormData();

            formData.append('pp3_action', 'traduction_list_existing');



            fetch('', {method: 'POST', body: formData})

            .then(r => r.json())

            .then(data => {

                if (data.ok && data.existing) {

                    pp3_displayExistingTranslations(data.existing);

                }

            })

            .catch(err => console.error('Erreur chargement traductions existantes:', err));

        }



        function pp3_displayExistingTranslations(existing) {

            const container = document.getElementById('pp3ExistingTranslations');

            if (!container) return;



            if (existing.length === 0) {

                container.innerHTML = '<div class="info" style="margin: 0;">Aucune traduction existante.</div>';

                return;

            }



            let html = '<div style="display: grid; gap: 10px;">';

            existing.forEach(item => {

                html += `

                    <div style="background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.18); border-radius: 6px; padding: 12px; display: flex; justify-content: space-between; align-items: center;">

                        <div>

                            <strong style="color: #fff;">${item.name}</strong> (${item.code.toUpperCase()})<br>

                            <small style="color: rgba(255,255,255,0.6);">${item.count} traductions • ${item.date}</small>

                            ${item.has_index ? '<br><small style="color: #66DF00;">✓ Fichier index.php généré</small>' : '<br><small style="color: #ef4444;">✗ Fichier index.php manquant</small>'}

                        </div>

                        <div style="display: flex; gap: 8px;">

                            <button onclick="pp3_editTranslation('${item.code}')" style="background: rgba(59,130,246,0.55); padding: 8px 12px; min-width: auto;">Modifier</button>

                            <button onclick="pp3_deleteTranslation('${item.code}', '${item.name}')" style="background: rgba(239,68,68,0.55); padding: 8px 12px; min-width: auto;">Supprimer</button>

                        </div>

                    </div>

                `;

            });

            html += '</div>';

            container.innerHTML = html;

        }



        window.pp3_editTranslation = function(lang) {

            // Sélectionner la langue et charger

            const select = document.getElementById('pp3TradLangSelect');

            if (select) {

                select.value = lang;

                document.getElementById('pp3TradLoadBtn')?.click();

            }

        };



        window.pp3_deleteTranslation = function(lang, name) {

            if (!confirm(`Supprimer la traduction ${name} (${lang.toUpperCase()}) ?\n\nCela supprimera le fichier JSON et le dossier.`)) {

                return;

            }



            const formData = new FormData();

            formData.append('pp3_action', 'traduction_delete');

            formData.append('lang', lang);



            fetch('', {method: 'POST', body: formData})

            .then(r => r.json())

            .then(data => {

                if (data.ok) {

                    alert('Traduction supprimée !');

                    pp3_loadExistingTranslations();

                } else {

                    alert(data.error || 'Erreur');

                }

            })

            .catch(err => {

                console.error(err);

                alert('Erreur réseau');

            });

        };



        document.getElementById('pp3RefreshExistingBtn')?.addEventListener('click', () => {

            pp3_loadExistingTranslations();

        });



        // Charger la clé Groq au chargement de l'onglet IA

        document.querySelector('[data-pp3-tab="ia"]')?.addEventListener('click', () => {

            // Charger depuis le fichier local

            const formData = new FormData();

            formData.append('pp3_action', 'get_groq_key');

            fetch('', {method: 'POST', body: formData})

            .then(r => r.json())

            .then(data => {

                if (data.ok && data.groq_key) {

                    const input = document.getElementById('pp3GroqApiKey');

                    if (input) input.value = data.groq_key;

                }

            })

            .catch(err => console.error('Erreur chargement clé Groq:', err));

        });



        // === MODALES CENTRÉES ===

        window.pp3_openModal = function(panelId) {

            const overlay = document.getElementById(panelId + 'Overlay');

            if (overlay) overlay.classList.add('active');

        }



        window.pp3_closeModal = function(panelId) {

            const overlay = document.getElementById(panelId + 'Overlay');

            if (overlay) overlay.classList.remove('active');

            if (typeof window.pp3_closeFullModal === 'function') {

                window.pp3_closeFullModal();

            }

        }



        // Remplacer l'ouverture du panneau compte par la modale

        /*

        const originalOpenAccountBtn = document.getElementById('openAccountPanelBtn');

        if (originalOpenAccountBtn) {

            originalOpenAccountBtn.addEventListener('click', (e) => {

                e.preventDefault();

                e.stopPropagation();

                pp3_openModal('account');

            });

        }

        */



        // Fermer modale UNIQUEMENT si clic sur l'overlay (pas sur le contenu)

        document.querySelectorAll('.pp3-modal-overlay').forEach(overlay => {

            overlay.addEventListener('click', (e) => {

                // Ne fermer que si on clique exactement sur l'overlay, pas sur son contenu

                if (e.target === overlay) {

                    overlay.classList.remove('active');

                }

            });

            // Empêcher la propagation des clics depuis le contenu

            const content = overlay.querySelector('.pp3-modal-content');

            if (content) {

                content.addEventListener('click', (e) => {

                    e.stopPropagation();

                });

            }

        });



        // Premium modal

        window.pp3_openPremiumModal = function() {

            pp3_openModal('premium');

        };



    </script>



    <!-- Modales centrées -->

    <div id="accountModalOverlay" class="pp3-modal-overlay">

        <div class="pp3-modal-content" id="accountModalContent">

            <!-- Le contenu du panneau compte sera cloné ici -->

        </div>

    </div>



    <div id="premiumModalOverlay" class="pp3-modal-overlay">

        <div class="pp3-modal-content">

            <button type="button" class="panel-close-btn" onclick="pp3_closeModal('premium')">&times;</button>

            <h3>Abonnement Premium</h3>

            <div id="pp3PlansContainerModal" class="btn-group"></div>

            <div class="info">Après paiement, tu reviens ici et le premium est activé.</div>

        </div>

    </div>



    <script>

        // Cloner le panneau compte dans la modale

        const accountPanel = document.getElementById('account-panel');

        const accountModalContent = document.getElementById('accountModalContent');

        if (accountPanel && accountModalContent) {

            accountModalContent.innerHTML = accountPanel.innerHTML;

        }



        // Synchroniser les plans premium entre panneau et modale

        function syncPremiumPlans() {

            const original = document.getElementById('pp3PlansContainer');

            const modal = document.getElementById('pp3PlansContainerModal');

            if (original && modal) {

                modal.innerHTML = original.innerHTML;

            }

        }

        setTimeout(syncPremiumPlans, 1000);

    </script>

    <script src="https://cdn.jsdelivr.net/npm/fuse.js@7.0.0"></script>

<script src="https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/dexie@4.0.8/dist/dexie.min.js"></script>



<script>

// ============================================

// SYSTEME IA GENERATIVE 3D AVEC APPRENTISSAGE

// ============================================



// ============================================

// SYSTEME DE GESTION DE THÈME (APPARENCE)

// ============================================

class AppThemeManager {

    constructor() {

        this.root = document.documentElement;

        this.inputs = document.querySelectorAll('.theme-input');

        this.modeSelect = document.getElementById('appThemeMenuMode');

        // Initial config (load from logic or default)

        // Note: idealement on injecte la config PHP dans window.pp3ThemeConfig

        this.config = window.pp3ThemeConfig || {

            desktop: { vars: {}, mode: 'icons' },

            tablet: { vars: {}, mode: 'icons' },

            mobile: { vars: {}, mode: 'icons' }

        };

        this.currentDevice = 'desktop'; // desktop, tablet, mobile

        this.styleTag = document.getElementById('dynamic-theme-style');

        if (!this.styleTag) {

            this.styleTag = document.createElement('style');

            this.styleTag.id = 'dynamic-theme-style';

            document.head.appendChild(this.styleTag);

        }

        this.init();

    }



    init() {

        // Init tabs

        const devTabs = document.querySelectorAll('.app-device-tabs .pp3-tab-btn');

        devTabs.forEach(btn => {

            btn.addEventListener('click', (e) => {

                devTabs.forEach(b => b.classList.remove('active'));

                btn.classList.add('active');

                this.currentDevice = btn.dataset.device || 'desktop';

                this.loadValuesForDevice();

            });

        });



        // Listeners inputs

        this.inputs.forEach(input => {

            input.addEventListener('input', (e) => this.updateVar(e.target));

        });



        // Listeners menu mode

        if (this.modeSelect) {

            this.modeSelect.addEventListener('change', (e) => {

                this.config[this.currentDevice].mode = e.target.value;

                this.renderCSS();

            });

        }



        // Initial load values

        this.loadValuesForDevice();

    }



    loadValuesForDevice() {

        const devCfg = this.config[this.currentDevice] || {};

        const devVars = devCfg.vars || {};



        this.inputs.forEach(input => {

            const k = input.dataset.var;

            // Si valeur définie pour ce device, on l'utilise, sinon on peut

            // soit prendre la valeur desktop (inheritance), soit vide, soit la valeur computée.

            // Pour l'edition: si pas de valeur override, on affiche la valeur desktop ou vide ?

            // On va afficher la valeur explicite stockée. Si vide, on met une valeur par défaut de l'input.

            if (devVars[k] !== undefined) {

                input.value = devVars[k].replace('px',''); // simple cleanup unit

            } else {

                // Si aucune valeur pour ce device, on laisse l'utilisateur choisir ou on met desktop

                // Strategie: on fallback sur desktop si vide

                if (this.currentDevice !== 'desktop' && this.config.desktop.vars && this.config.desktop.vars[k]) {

                     input.value = this.config.desktop.vars[k].replace('px','');

                } else {

                    // fallback computed ?

                    const comp = getComputedStyle(this.root).getPropertyValue(k).trim();

                     if (comp) input.value = comp.replace('px','');

                }

            }

        });



        if (this.modeSelect) {

            this.modeSelect.value = devCfg.mode || 'icons';

        }

    }



    updateVar(input) {

        const variable = input.dataset.var;

        const unit = input.dataset.unit || '';

        let value = input.value;



        if (!this.config[this.currentDevice]) this.config[this.currentDevice] = { vars: {}, mode: 'icons' };

        if (!this.config[this.currentDevice].vars) this.config[this.currentDevice].vars = {};



        this.config[this.currentDevice].vars[variable] = value + unit;

        this.renderCSS();

    }



    renderCSS() {

        let css = '';

        const buildBlock = (vars, mode) => {

            let s = ":root {\n";

            for (const [k, v] of Object.entries(vars || {})) {

                s += `  ${k}: ${v} !important;\n`; // Force override

            }

            s += "}\n";

            // Mode handling via CSS injection is tricky because we need body classes or data attr.

            // Better: use scoped css rules based on a data attribute on body if possible, OR

            // simply inject different rules.

            // Workaround: We will stick to the previous 'body.mode-...' logic BUT we need to check screen size in JS

            // OR use media queries to enforce different displays for .icon-btn elements.

            // Let's generate CSS for mode rules too!



            // NOTE: mode-text/mode-both rules rely on body class. Responsive body class is hard.

            // Instead we write media query rules directly for .icon-btn



            if (mode === 'text') {

                s += `

                  .icon-btn svg { display: none !important; }

                  .icon-btn { width: auto !important; height: auto !important; padding: 8px 12px !important; aspect-ratio: auto !important; }

                  .icon-btn .sr-only { position: static !important; width: auto !important; height: auto !important; clip: auto !important; color: var(--app-text) !important; }

                `;

            } else if (mode === 'both') {

                s += `

                  .icon-btn { width: auto !important; height: auto !important; padding: 8px 12px !important; display: inline-flex !important; gap: 8px; aspect-ratio: auto !important; }

                  .icon-btn .sr-only { position: static !important; width: auto !important; height: auto !important; clip: auto !important; color: var(--app-text) !important; }

                `;

            } else {

               // icons default

               s += `

                  .icon-btn svg { display: block !important; }

                  .icon-btn .sr-only { position: absolute !important; width: 1px !important; height: 1px !important; clip: rect(0,0,0,0) !important; }

               `;

            }

            return s;

        };



        // Desktop

        css += buildBlock(this.config.desktop?.vars, this.config.desktop?.mode);



        // Tablet

        css += `@media (max-width: 1024px) {\n${buildBlock(this.config.tablet?.vars, this.config.tablet?.mode)}\n}\n`;



        // Mobile

        css += `@media (max-width: 768px) {\n${buildBlock(this.config.mobile?.vars, this.config.mobile?.mode)}\n}\n`;



        this.styleTag.innerHTML = css;



        // Mise à jour de la scène 3D (Background & Grid)

        this.updateScene3D();



        // Mise à jour du loader

        this.updateLoader();



        // Mise à jour de la position du menu (via data-attribute sur body)

        this.updateMenuPosition();

    }



    // Appliquer la position du menu

    updateMenuPosition() {

        const getVar = (name) => {

            let v = this.config[this.currentDevice]?.vars?.[name];

            if (!v && this.currentDevice !== 'desktop') v = this.config.desktop?.vars?.[name];

            return v;

        };



        const position = getVar('--menu-position') || 'left';

        document.body.setAttribute('data-menu-position', position);

    }



    updateScene3D() {

        if (typeof scene === 'undefined' || !scene) return;



        // Récupérer les valeurs effectives pour le device courant

        const getVar = (name) => {

            let v = this.config[this.currentDevice]?.vars?.[name];

            // Fallback desktop

            if (!v && this.currentDevice !== 'desktop') v = this.config.desktop?.vars?.[name];

            return v;

        };



        const bg = getVar('--viewer-bg');

        if (bg) {

            scene.background = new THREE.Color(bg);

        }



        const gridColor = getVar('--grid-color');

        if (gridColor && typeof THREE !== 'undefined') {

            scene.traverse(child => {

                if (child.type === 'GridHelper') {

                    // Recréer le helper car modifier les couleurs vertex est complexe

                    const size = 20;

                    const divisions = 20;

                    const c = new THREE.Color(gridColor);

                    // On garde le colorCenter un peu plus sombre ou identique

                    const cCenter = c.clone().multiplyScalar(0.6);



                    // Remplacer l'ancien

                    const newGrid = new THREE.GridHelper(size, divisions, cCenter, c);

                    // Copier position/rotation si nécessaire (normalement 0,0,0)

                    newGrid.position.copy(child.position);

                    newGrid.rotation.copy(child.rotation);



                    // On le remplace dans le parent (scene)

                    child.parent.remove(child);

                    scene.add(newGrid);

                }

            });

        }

    }



    async saveConfig() {

        // Sauvegarder aussi les valeurs 3D dans la config

        // Les valeurs --viewer-bg et --grid-color sont déjà dans this.config[device].vars



        const fd = new FormData();

        fd.append('pp3_action', 'theme_save');

        fd.append('theme_json', JSON.stringify(this.config));



        try {

            const r = await fetch(window.location.href, { method: 'POST', body: fd });

            const data = await r.json();

            if (data.ok) {

                alert('Thème sauvegardé avec succès !');

                // Appliquer immédiatement les changements 3D

                this.updateScene3D();

            }

            else alert('Erreur sauvegarde: ' + (data.error || 'inconnue'));

        } catch(e) {

            alert('Erreur réseau');

        }

    }



    // Mettre à jour le loader dynamiquement

    updateLoader() {

        const loaderType = this.config[this.currentDevice]?.vars?.['--loader-type'] ||

                          this.config.desktop?.vars?.['--loader-type'] || '1';

        const loaderContent = document.getElementById('pp3-loader-content');

        if (!loaderContent) return;



        // Supprimer toutes les classes pp3-loader-*

        loaderContent.className = '';



        const type = parseInt(loaderType);

        if (type === 0) {

            // Désactiver le loader

            const loader = document.getElementById('pp3-loader');

            if (loader) loader.style.display = 'none';

            return;

        }



        loaderContent.className = `pp3-loader-${type}`;



        // Ajouter les spans pour certains loaders

        if (type === 3) {

            loaderContent.innerHTML = '<span></span><span></span><span></span>';

        } else if (type === 7) {

            loaderContent.innerHTML = '<span></span><span></span><span></span><span></span><span></span>';

        } else if (type === 10) {

            loaderContent.innerHTML = '<span></span><span></span>';

        } else {

            loaderContent.innerHTML = '';

        }

    }

}



// Initialisation au chargement

document.addEventListener('DOMContentLoaded', () => {

    // Inject server config if exists

    // This assumes fetchThemeConfig was handled or we just start fresh.

    // Ideally we inject PHP var.



    window.appThemeManager = new AppThemeManager();

    const saveBtn = document.getElementById('pp3AdminSaveBtn');

    if (saveBtn) {

        saveBtn.addEventListener('click', () => {

            if (window.appThemeManager) window.appThemeManager.saveConfig();

        });

    }



    // Bouton de prévisualisation du loader

    const previewLoaderBtn = document.getElementById('pp3PreviewLoaderBtn');

    if (previewLoaderBtn) {

        previewLoaderBtn.addEventListener('click', () => {

            const loader = document.getElementById('pp3-loader');

            if (!loader) return;



            // Mettre à jour le loader avec les paramètres actuels

            if (window.appThemeManager) {

                window.appThemeManager.updateLoader();

            }



            // Afficher le loader

            loader.classList.remove('hidden');

            loader.style.display = 'flex';



            // Cacher après 3 secondes

            setTimeout(() => {

                loader.classList.add('hidden');

            }, 3000);

        });

    }

});



class AIGenerative3D {

    constructor() {

        this.db = null;

        this.memory = new Map();

        this.knowledgeGraph = new Map();

        this.learningRate = 0.1;

        this.creativityLevel = 0.7;

        this.experiencePoints = 0;

        this.generationCount = 0;

        this.stylePreferences = {};

        this.initDatabase();

        this.initKnowledgeGraph();

    }



    async initDatabase() {

        try {

            this.db = new Dexie('AI_3D_Generator_v2');

            this.db.version(1).stores({

                objects: '++id, name, type, complexity, rating, tags, createdAt',

                styles: '++id, name, description, parameters, usageCount',

                patterns: '++id, patternType, vertices, faces, successRate',

                animations: '++id, name, keyframes, duration, rating',

                textures: '++id, name, colors, patterns, uvMapping',

                userFeedback: '++id, objectId, rating, comments, improvements'

            });

            await this.db.open();

            console.log('📊 Base de données IA initialisée');

        } catch (error) {

            console.warn('Base de données IA non disponible (mode hors ligne):', error);

            this.db = null;

        }

    }



    initKnowledgeGraph() {

        // Connaissances de base en 3D

        this.knowledgeGraph.set('basic_shapes', {

            cube: { vertices: 8, faces: 6, complexity: 1 },

            sphere: { vertices: 42, faces: 80, complexity: 3 },

            cylinder: { vertices: 32, faces: 60, complexity: 2 },

            cone: { vertices: 32, faces: 32, complexity: 2 },

            pyramid: { vertices: 5, faces: 5, complexity: 2 },

            torus: { vertices: 96, faces: 96, complexity: 4 },

            ramp: { vertices: 6, faces: 8, complexity: 2 }

        });



        this.knowledgeGraph.set('material_properties', {

            metallic: { min: 0, max: 1, typical: 0.3 },

            roughness: { min: 0, max: 1, typical: 0.5 },

            // IMPORTANT: par défaut on veut des objets visibles (pas transparents).

            // La transparence est réservée aux prompts qui demandent explicitement du verre/translucide.

            opacity: { min: 1, max: 1, typical: 1 },

            emissive: { min: 0, max: 1, typical: 0 },

            envMapIntensity: { min: 0, max: 2, typical: 1 }

        });



        this.knowledgeGraph.set('color_theory', {

            complementary: [[0, 180], [30, 210], [60, 240], [90, 270], [120, 300], [150, 330]],

            analogous: [[0, 30, 60], [90, 120, 150], [180, 210, 240], [270, 300, 330]],

            triadic: [[0, 120, 240], [30, 150, 270], [60, 180, 300], [90, 210, 330]],

            monochromatic: [[0, 15, 30], [90, 105, 120], [180, 195, 210], [270, 285, 300]]

        });



        this.knowledgeGraph.set('animation_patterns', {

            rotation: { axes: ['x', 'y', 'z'], speed: [0.5, 5], easing: ['linear', 'sine', 'quadratic'] },

            pulse: { scale: [0.8, 1.2], frequency: [0.5, 3], phase: [0, Math.PI * 2] },

            hover: { amplitude: [0.1, 0.5], frequency: [0.2, 2], offset: [0, Math.PI] },

            twist: { angle: [5, 45], frequency: [0.1, 1], axis: ['y', 'z'] }

        });



        console.log('🧠 Graphe de connaissances initialisé:', this.knowledgeGraph.size, 'catégories');

    }



    async generateObject(prompt = '', constraints = {}) {

        this.generationCount++;



        // Analyser le prompt avec Fuse.js pour la recherche sémantique

        const promptAnalysis = this.analyzePrompt(prompt);



        // Générer un concept basé sur l'expérience

        const concept = await this.generateConcept(promptAnalysis, constraints);



        // Applique explicitement les contraintes (fortes) quand elles existent

        if (constraints && typeof constraints === 'object') {

            if (typeof constraints.complexity === 'number' && isFinite(constraints.complexity)) {

                concept.complexity = Math.max(1, Math.min(5, Math.floor(constraints.complexity)));

            }

            if (typeof constraints.animate === 'boolean') {

                concept.animate = constraints.animate;

            }

            if (typeof constraints.proceduralTextures === 'boolean') {

                concept.proceduralTextures = constraints.proceduralTextures;

            } else {

                concept.proceduralTextures = true;

            }

            if (typeof constraints.styleHint === 'string' && constraints.styleHint.trim()) {

                const sh = constraints.styleHint.toLowerCase();

                concept.style = sh;

                // petit biais sur tags

                if (!Array.isArray(concept.tags)) concept.tags = [];

                if (!concept.tags.includes(sh)) concept.tags.push(sh);

            }

        }



        // Créer la géométrie

        const geometry = this.createAdaptiveGeometry(concept);



        // Générer le matériau intelligent

        const material = this.createIntelligentMaterial(concept);



        // Créer l'objet 3D

        const object3D = this.assemble3DObject(geometry, material, concept);



        // Ajouter des animations si pertinent

        if (concept.animate) {

            this.addIntelligentAnimations(object3D, concept);

        }



        // Ajouter des propriétés spéciales

        this.addSpecialFeatures(object3D, concept);



        // Sauvegarder dans la mémoire d'apprentissage

        await this.saveToMemory(concept, object3D, prompt);



        // Augmenter l'expérience

        this.experiencePoints += concept.complexity * 10;

        this.updateCreativity();



        console.log(`🎨 IA Génération #${this.generationCount}: ${concept.name}`);

        console.log(`📈 Expérience: ${this.experiencePoints}, Créativité: ${this.creativityLevel.toFixed(2)}`);



        return object3D;

    }



    analyzePrompt(prompt) {

        const words = prompt.toLowerCase().split(/\s+/);

        const analysis = {

            keywords: [],

            shapeHints: [],

            materialHints: [],

            animationHints: [],

            complexity: 1,

            style: 'modern'

        };



        // Dictionnaire sémantique

        const shapeKeywords = {

            'organique': 'organic', 'vivant': 'organic', 'naturel': 'organic',

            'mécanique': 'mechanical', 'robot': 'mechanical', 'tech': 'mechanical',

            'architectural': 'architectural', 'bâtiment': 'architectural', 'structure': 'architectural',

            'abstrait': 'abstract', 'artistique': 'abstract', 'surréaliste': 'abstract',

            'géométrique': 'geometric', 'symétrique': 'geometric', 'précis': 'geometric'

        };



        const complexityKeywords = {

            'simple': 1, 'basique': 1, 'facile': 1,

            'moyen': 2, 'intermédiaire': 2, 'modéré': 2,

            'complexe': 3, 'détaillé': 3, 'élaboré': 3,

            'ultra': 4, 'extrême': 4, 'master': 4

        };



        // Analyse

        words.forEach(word => {

            if (shapeKeywords[word]) analysis.shapeHints.push(shapeKeywords[word]);

            if (complexityKeywords[word]) analysis.complexity = complexityKeywords[word];



            if (word.includes('brillant') || word.includes('shiny')) analysis.materialHints.push('glossy');

            if (word.includes('mat') || word.includes('mate')) analysis.materialHints.push('matte');

            if (word.includes('métal')) analysis.materialHints.push('metallic');

            if (word.includes('verre') || word.includes('glass')) analysis.materialHints.push('glass');

            if (word.includes('lumière') || word.includes('light')) analysis.materialHints.push('emissive');



            if (word.includes('tourne') || word.includes('rotate')) analysis.animationHints.push('rotation');

            if (word.includes('pulse') || word.includes('bat')) analysis.animationHints.push('pulse');

            if (word.includes('flotte') || word.includes('hover')) analysis.animationHints.push('hover');

        });



        return analysis;

    }



    async generateConcept(analysis, constraints) {

        let successfulStyles = [];

        let effectivePatterns = [];



        if (this.db) {

            // Récupérer les styles précédents réussis

            successfulStyles = await this.db.styles

                .where('usageCount')

                .above(3)

                .limit(5)

                .sortBy('usageCount');



            // Récupérer les patterns efficaces

            effectivePatterns = await this.db.patterns

                .where('successRate')

                .above(0.7)

                .limit(5)

                .toArray();

        }



        // Générer un nom créatif

        const name = this.generateCreativeName(analysis);



        // Déterminer le type de forme

        const shapeType = this.determineShapeType(analysis, successfulStyles);



        // Calculer la complexité adaptative

        const complexity = Math.min(analysis.complexity + this.experiencePoints / 1000, 5);



        // Générer une palette de couleurs intelligente

        const colorPalette = this.generateColorPalette(analysis);



        // Déterminer les propriétés du matériau

        const materialProperties = this.determineMaterialProperties(analysis);



        // Déterminer les animations

        const animate = analysis.animationHints.length > 0 || Math.random() > 0.7;



        return {

            name,

            shapeType,

            complexity: Math.floor(complexity),

            colorPalette,

            materialProperties,

            animate,

            style: analysis.style,

            tags: [...analysis.shapeHints, ...analysis.materialHints],

            generationId: Date.now(),

            learningData: {

                basedOnStyles: successfulStyles.slice(0, 3).map(s => s.id),

                basedOnPatterns: effectivePatterns.slice(0, 2).map(p => p.id),

                creativityUsed: this.creativityLevel

            }

        };

    }



    createAdaptiveGeometry(concept) {

        const geometry = new THREE.BufferGeometry();

        let vertices = [];

        let faces = [];

        let uvs = [];



        switch(concept.shapeType) {

            case 'organic':

                vertices = this.generateOrganicShape(concept.complexity);

                faces = this.triangulateOrganicShape(vertices);

                uvs = this.generateOrganicUVs(vertices);

                break;



            case 'mechanical':

                vertices = this.generateMechanicalShape(concept.complexity);

                faces = this.triangulateMechanicalShape(vertices);

                uvs = this.generateMechanicalUVs(vertices);

                break;



            case 'architectural':

                vertices = this.generateArchitecturalShape(concept.complexity);

                faces = this.triangulateArchitecturalShape(vertices);

                uvs = this.generateArchitecturalUVs(vertices);

                break;



            case 'abstract':

                vertices = this.generateAbstractShape(concept.complexity);

                faces = this.triangulateAbstractShape(vertices);

                uvs = this.generateAbstractUVs(vertices);

                break;



            case 'geometric':

            default:

                vertices = this.generateGeometricShape(concept.complexity);

                faces = this.triangulateGeometricShape(vertices);

                uvs = this.generateGeometricUVs(vertices);

                break;

        }



        // Optimiser la géométrie

        if (vertices.length > 0 && vertices[0].length === 3) {

            geometry.setAttribute('position', new THREE.Float32BufferAttribute(vertices.flat(), 3));

        }



        if (faces.length > 0 && faces[0].length === 3) {

            geometry.setIndex(faces.flat());

        }



        if (uvs.length > 0 && uvs[0].length === 2) {

            geometry.setAttribute('uv', new THREE.Float32BufferAttribute(uvs.flat(), 2));

        }



        geometry.computeVertexNormals();

        geometry.computeBoundingBox();

        geometry.computeBoundingSphere();



        // Ajouter des attributs pour la sculpture

        try {

            geometry.setAttribute('tangent', this.computeTangents(geometry));

        } catch (e) {

            console.warn('Impossible de calculer les tangentes:', e);

        }



        return geometry;

    }



    // === MÉTHODES DE GÉNÉRATION DE FORMES ===



    generateOrganicShape(complexity) {

        const vertices = [];

        const segments = 8 + complexity * 8;

        const radius = 1 + complexity * 0.3;



        for (let i = 0; i <= segments; i++) {

            const phi = (i / segments) * Math.PI;

            for (let j = 0; j <= segments; j++) {

                const theta = (j / segments) * Math.PI * 2;



                // Ajouter du bruit pour un aspect organique

                const noise = 1 + 0.2 * Math.sin(phi * 5) * Math.sin(theta * 3) * complexity;



                const x = radius * Math.sin(phi) * Math.cos(theta) * noise;

                const y = radius * Math.cos(phi) * noise;

                const z = radius * Math.sin(phi) * Math.sin(theta) * noise;



                vertices.push([x, y, z]);

            }

        }



        return vertices;

    }



    generateMechanicalShape(complexity) {

        const vertices = [];

        const parts = 3 + Math.floor(complexity);



        // Générer des parties mécaniques imbriquées

        for (let p = 0; p < parts; p++) {

            const offset = p * 0.8;

            const size = 0.3 + (p % 3) * 0.2;



            // Partie centrale

            for (let i = 0; i < 8; i++) {

                const angle = (i / 8) * Math.PI * 2;

                const x = Math.cos(angle) * size;

                const y = offset;

                const z = Math.sin(angle) * size;

                vertices.push([x, y, z]);

            }



            // Engrenages/connections

            if (p < parts - 1) {

                for (let i = 0; i < 6; i++) {

                    const angle = (i / 6) * Math.PI * 2;

                    const x = Math.cos(angle) * (size * 1.5);

                    const y = offset + 0.4;

                    const z = Math.sin(angle) * (size * 1.5);

                    vertices.push([x, y, z]);

                }

            }

        }



        return vertices;

    }



    generateArchitecturalShape(complexity) {

        const vertices = [];

        const floors = 2 + Math.floor(complexity);

        const width = 1.5;

        const depth = 1.2;



        for (let f = 0; f < floors; f++) {

            const height = f * 1.2;



            // Base du bâtiment

            vertices.push([-width, height, -depth]);

            vertices.push([width, height, -depth]);

            vertices.push([width, height, depth]);

            vertices.push([-width, height, depth]);



            // Toit

            if (f === floors - 1) {

                vertices.push([0, height + 0.8, 0]); // Point du toit

            }



            // Fenêtres

            if (f > 0) {

                vertices.push([-width * 0.7, height - 0.3, depth + 0.1]);

                vertices.push([width * 0.7, height - 0.3, depth + 0.1]);

            }

        }



        return vertices;

    }



    generateAbstractShape(complexity) {

        const vertices = [];

        const points = 20 + complexity * 10;



        for (let i = 0; i < points; i++) {

            const t = i / points * Math.PI * 2;

            const r = 1 + 0.5 * Math.sin(t * 3) * complexity;

            const x = r * Math.cos(t) * (1 + 0.3 * Math.sin(t * 2));

            const y = 0.5 * Math.cos(t * 4) * complexity;

            const z = r * Math.sin(t) * (1 + 0.3 * Math.cos(t * 2));



            vertices.push([x, y, z]);

        }



        return vertices;

    }



    generateGeometricShape(complexity) {

        // Créer un icosaèdre pour la géométrie de base

        const vertices = [];

        const t = (1 + Math.sqrt(5)) / 2;



        // Vertices d'un icosaèdre

        vertices.push([-1, t, 0], [1, t, 0], [-1, -t, 0], [1, -t, 0]);

        vertices.push([0, -1, t], [0, 1, t], [0, -1, -t], [0, 1, -t]);

        vertices.push([t, 0, -1], [t, 0, 1], [-t, 0, -1], [-t, 0, 1]);



        // Ajouter plus de détails selon la complexité

        if (complexity > 2) {

            for (let i = 0; i < complexity * 2; i++) {

                const phi = Math.random() * Math.PI * 2;

                const theta = Math.acos(2 * Math.random() - 1);

                const r = 1 + Math.random() * 0.5;



                const x = r * Math.sin(theta) * Math.cos(phi);

                const y = r * Math.sin(theta) * Math.sin(phi);

                const z = r * Math.cos(theta);



                vertices.push([x, y, z]);

            }

        }



        return vertices;

    }



    // === MÉTHODES DE TRIANGULATION ===



    triangulateOrganicShape(vertices) {

        const faces = [];

        const segments = Math.sqrt(vertices.length) - 1;



        if (segments < 1) return this.triangulateConvexHull(vertices);



        for (let i = 0; i < segments; i++) {

            for (let j = 0; j < segments; j++) {

                const a = i * (segments + 1) + j;

                const b = a + 1;

                const c = a + (segments + 1);

                const d = c + 1;



                faces.push([a, b, c]);

                faces.push([b, d, c]);

            }

        }



        return faces;

    }



    triangulateMechanicalShape(vertices) {

        return this.triangulateConvexHull(vertices);

    }



    triangulateArchitecturalShape(vertices) {

        const faces = [];

        const floorCount = Math.floor(vertices.length / 7); // Estimation



        for (let f = 0; f < floorCount; f++) {

            const base = f * 7;



            // Faces du sol

            if (base + 3 < vertices.length) {

                faces.push([base, base + 1, base + 2]);

                faces.push([base, base + 2, base + 3]);

            }



            // Faces du toit

            if (base + 4 < vertices.length && base + 7 < vertices.length) {

                faces.push([base, base + 4, base + 1]);

                faces.push([base + 1, base + 4, base + 2]);

                faces.push([base + 2, base + 4, base + 3]);

                faces.push([base + 3, base + 4, base]);

            }

        }



        return faces.length > 0 ? faces : this.triangulateConvexHull(vertices);

    }



    triangulateAbstractShape(vertices) {

        return this.triangulateConvexHull(vertices);

    }



    triangulateGeometricShape(vertices) {

        // Faces d'un icosaèdre

        const faces = [

            [0, 11, 5], [0, 5, 1], [0, 1, 7], [0, 7, 10], [0, 10, 11],

            [1, 5, 9], [5, 11, 4], [11, 10, 2], [10, 7, 6], [7, 1, 8],

            [3, 9, 4], [3, 4, 2], [3, 2, 6], [3, 6, 8], [3, 8, 9],

            [4, 9, 5], [2, 4, 11], [6, 2, 10], [8, 6, 7], [9, 8, 1]

        ];



        // Ajouter des faces supplémentaires pour les vertices ajoutés

        if (vertices.length > 12) {

            for (let i = 12; i < vertices.length; i++) {

                // Connecter aux vertices les plus proches

                const nearest = [0, 1, 2].map(() => Math.floor(Math.random() * 12));

                faces.push([i, nearest[0], nearest[1]]);

                faces.push([i, nearest[1], nearest[2]]);

                faces.push([i, nearest[2], nearest[0]]);

            }

        }



        return faces;

    }



    triangulateConvexHull(vertices) {

        // Triangulation simple pour les formes convexes

        const faces = [];

        if (vertices.length < 3) return faces;



        // Créer un maillage simple autour du premier point

        for (let i = 1; i < vertices.length - 1; i++) {

            faces.push([0, i, i + 1]);

        }



        // Fermer la forme si assez de points

        if (vertices.length > 3) {

            faces.push([0, vertices.length - 1, 1]);

        }



        return faces;

    }



    // === MÉTHODES UV MAPPING ===



    generateOrganicUVs(vertices) {

        const uvs = [];



        vertices.forEach((v, i) => {

            // Projection sphérique simple

            const phi = Math.acos(Math.max(-1, Math.min(1, v[1])));

            const theta = Math.atan2(v[2], v[0]);



            uvs.push([

                (theta + Math.PI) / (2 * Math.PI),

                phi / Math.PI

            ]);

        });



        return uvs;

    }



    generateMechanicalUVs(vertices) {

        const uvs = [];



        vertices.forEach((v, i) => {

            // UVs cylindriques pour les pièces mécaniques

            const theta = Math.atan2(v[2], v[0]);

            uvs.push([

                (theta + Math.PI) / (2 * Math.PI),

                (v[1] + 1) / 2

            ]);

        });



        return uvs;

    }



    generateArchitecturalUVs(vertices) {

        const uvs = [];



        vertices.forEach((v, i) => {

            // UVs planes pour l'architecture

            uvs.push([

                (v[0] + 2) / 4,

                (v[1] + 2) / 4

            ]);

        });



        return uvs;

    }



    generateAbstractUVs(vertices) {

        const uvs = [];



        vertices.forEach((v, i) => {

            // UVs procédurales pour les formes abstraites

            uvs.push([

                Math.sin(v[0] * 2) * 0.5 + 0.5,

                Math.cos(v[2] * 2) * 0.5 + 0.5

            ]);

        });



        return uvs;

    }



    generateGeometricUVs(vertices) {

        const uvs = [];



        vertices.forEach((v, i) => {

            // UVs sphériques pour la géométrie

            const phi = Math.acos(v[1] / Math.sqrt(v[0]*v[0] + v[1]*v[1] + v[2]*v[2]));

            const theta = Math.atan2(v[2], v[0]);



            uvs.push([

                (theta + Math.PI) / (2 * Math.PI),

                phi / Math.PI

            ]);

        });



        return uvs;

    }



    createIntelligentMaterial(concept) {

        const primaryColor = new THREE.Color().setHSL(

            concept.colorPalette.primary.hue / 360,

            concept.colorPalette.primary.saturation,

            concept.colorPalette.primary.lightness

        );



        const material = new THREE.MeshStandardMaterial({

            color: primaryColor,

            metalness: concept.materialProperties.metalness,

            roughness: concept.materialProperties.roughness,

            emissive: new THREE.Color().setHSL(

                concept.colorPalette.emissive.hue / 360,

                concept.colorPalette.emissive.saturation,

                concept.colorPalette.emissive.lightness

            ),

            emissiveIntensity: concept.materialProperties.emissiveIntensity,

            envMapIntensity: concept.materialProperties.envMapIntensity,

            side: THREE.DoubleSide,

            flatShading: false,

            transparent: concept.materialProperties.opacity < 1,

            opacity: concept.materialProperties.opacity,

            depthTest: true,

            depthWrite: true

        });



        // Ajouter des maps procédurales pour les textures

        const procOk = (typeof concept.proceduralTextures === 'boolean') ? concept.proceduralTextures : true;

        if (procOk && concept.complexity >= 3) {

            material.normalMap = this.generateProceduralNormalMap();

            material.roughnessMap = this.generateProceduralRoughnessMap();

            material.metalnessMap = this.generateProceduralMetalnessMap();

        }



        return material;

    }



    generateColorPalette(analysis) {

        const colorSchemes = this.knowledgeGraph.get('color_theory');

        const scheme = _.sample(Object.keys(colorSchemes));

        const hues = _.sample(colorSchemes[scheme]);



        return {

            scheme,

            primary: {

                hue: hues[0],

                saturation: 0.7 + Math.random() * 0.3,

                lightness: 0.5 + Math.random() * 0.2

            },

            secondary: {

                hue: hues[1] || hues[0] + 30,

                saturation: 0.6 + Math.random() * 0.2,

                lightness: 0.4 + Math.random() * 0.3

            },

            accent: {

                hue: hues[2] || hues[0] + 60,

                saturation: 0.8 + Math.random() * 0.2,

                lightness: 0.6 + Math.random() * 0.2

            },

            emissive: {

                hue: (hues[0] + 180) % 360,

                saturation: 0.9,

                lightness: 0.8

            }

        };

    }



    determineMaterialProperties(analysis) {

        const baseProps = this.knowledgeGraph.get('material_properties');

        const props = {};



        Object.keys(baseProps).forEach(key => {

            const range = baseProps[key];

            let value;



            if (analysis.materialHints.includes(key)) {

                // Renforcer les propriétés suggérées

                value = range.typical + (Math.random() * 0.3 - 0.15);

            } else {

                // Valeur aléatoire dans la plage

                value = range.min + Math.random() * (range.max - range.min);

            }



            // Appliquer la créativité

            value += (Math.random() - 0.5) * 0.2 * this.creativityLevel;

            props[key] = Math.max(range.min, Math.min(range.max, value));

        });



        // Transparence uniquement si le prompt le demande explicitement

        const wantsGlass = Array.isArray(analysis.materialHints) && (analysis.materialHints.includes('glass') || analysis.materialHints.includes('translucide'));

        if (wantsGlass) {

            props.opacity = 0.25 + Math.random() * 0.35; // 0.25..0.60

        } else {

            props.opacity = 1.0;

        }



        return props;

    }



    assemble3DObject(geometry, material, concept) {

        const mesh = new THREE.Mesh(geometry, material);



        mesh.name = concept.name;

        mesh.userData = {

            aiGenerated: true,

            generationId: concept.generationId,

            concept: _.omit(concept, ['learningData']),

            complexity: concept.complexity,

            tags: concept.tags,

            createdAt: new Date().toISOString(),

            experienceValue: concept.complexity * 10,



            // Compatibilité avec les outils de l'éditeur

            isObject: true,

            type: 'ai_generated',

            id: 'ai_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),

            name: concept.name,



            // Pour la sculpture

            sculptCompatible: true,

            paintCompatible: true,

            uvMapped: true,

            vertexCount: geometry.attributes.position.count,

            weldPrepared: false,

            adjacencyPrepared: false

        };



        // Position aléatoire mais intelligente

        mesh.position.set(

            (Math.random() - 0.5) * 8,

            Math.random() * 3 + 1,

            (Math.random() - 0.5) * 8

        );



        mesh.rotation.set(

            Math.random() * Math.PI * 2,

            Math.random() * Math.PI * 2,

            Math.random() * Math.PI * 2

        );



        mesh.castShadow = true;

        mesh.receiveShadow = true;

        mesh.frustumCulled = false;



        return mesh;

    }



    addIntelligentAnimations(mesh, concept) {

        const animationPatterns = this.knowledgeGraph.get('animation_patterns');

        const pattern = _.sample(Object.keys(animationPatterns));

        const config = animationPatterns[pattern];



        mesh.userData.animation = {

            type: pattern,

            config: _.mapValues(config, values => _.isArray(values) ? _.sample(values) : values),

            active: true,

            speed: 1 + Math.random() * 2

        };



        // Créer l'animation

        const clock = new THREE.Clock();



        function animate() {

            if (!mesh.userData.animation?.active) return;



            const elapsed = clock.getElapsedTime() * mesh.userData.animation.speed;



            switch(pattern) {

                case 'rotation':

                    mesh.rotation[config.axes[0]] = elapsed * config.speed[0];

                    break;



                case 'pulse':

                    const scale = 1 + Math.sin(elapsed * config.frequency[0]) *

                                 (config.scale[1] - config.scale[0]) / 2;

                    mesh.scale.setScalar(scale);

                    break;



                case 'hover':

                    mesh.position.y += Math.sin(elapsed * config.frequency[0]) *

                                     config.amplitude[0] * 0.01;

                    break;



                case 'twist':

                    mesh.rotation[config.axis] = Math.sin(elapsed * config.frequency[0]) *

                                               THREE.MathUtils.degToRad(config.angle[0]);

                    break;

            }

        }



        mesh.userData.animation.update = animate;

        mesh.userData.animation.clock = clock;

    }



    addSpecialFeatures(mesh, concept) {

        // Ajouter des particules pour les objets complexes

        if (concept.complexity >= 4 && concept.materialProperties.emissiveIntensity > 0.3) {

            this.addParticleSystem(mesh);

        }



        // Ajouter des LOD (Level of Detail) pour l'optimisation

        if (mesh.geometry.attributes.position.count > 1000) {

            this.addLOD(mesh);

        }



        // Ajouter des interactions

        mesh.userData.interactive = true;

        mesh.userData.onClick = () => {

            this.onObjectInteract(mesh);

        };

    }



    addParticleSystem(parent) {

        try {

            const particleCount = 100;

            const particles = new THREE.BufferGeometry();

            const positions = new Float32Array(particleCount * 3);



            for (let i = 0; i < particleCount; i++) {

                positions[i * 3] = (Math.random() - 0.5) * 2;

                positions[i * 3 + 1] = (Math.random() - 0.5) * 2;

                positions[i * 3 + 2] = (Math.random() - 0.5) * 2;

            }



            particles.setAttribute('position', new THREE.BufferAttribute(positions, 3));



            const particleMaterial = new THREE.PointsMaterial({

                color: 0xffffff,

                size: 0.05,

                transparent: true,

                opacity: 0.6,

                blending: THREE.AdditiveBlending

            });



            const particleSystem = new THREE.Points(particles, particleMaterial);

            parent.add(particleSystem);

            parent.userData.particles = particleSystem;

        } catch (error) {

            console.warn('Impossible d\'ajouter le système de particules:', error);

        }

    }



    addLOD(mesh) {

        try {

            // Créer des niveaux de détail

            const lod = new THREE.LOD();



            // Niveau haute qualité (original)

            lod.addLevel(mesh.clone(), 0);



            // Niveau moyenne qualité

            const mediumGeo = mesh.geometry.clone();

            mediumGeo.deleteAttribute('normal');

            mediumGeo.deleteAttribute('uv');

            const mediumMesh = new THREE.Mesh(mediumGeo, mesh.material);

            lod.addLevel(mediumMesh, 50);



            // Niveau basse qualité (simplifié)

            const lowGeo = new THREE.BoxGeometry(1, 1, 1);

            const lowMesh = new THREE.Mesh(lowGeo, mesh.material);

            lod.addLevel(lowMesh, 100);



            // Remplacer le mesh original par le LOD

            mesh.parent.add(lod);

            mesh.parent.remove(mesh);



            // Mettre à jour la référence

            Object.assign(mesh, lod);

            mesh.isLOD = true;



        } catch (error) {

            console.warn('Impossible d\'ajouter LOD:', error);

        }

    }



    async saveToMemory(concept, object3D, prompt) {

        try {

            if (!this.db) return;



            // Sauvegarder l'objet

            await this.db.objects.put({

                name: concept.name,

                type: concept.shapeType,

                complexity: concept.complexity,

                vertices: object3D.geometry.attributes.position.count,

                faces: object3D.geometry.index ? object3D.geometry.index.count / 3 : 0,

                material: concept.materialProperties,

                colors: concept.colorPalette,

                tags: concept.tags,

                prompt: prompt,

                rating: 0,

                createdAt: new Date()

            });



            // Mettre à jour le style

            const styleName = `${concept.shapeType}_${concept.style}`;

            const existingStyle = await this.db.styles.where('name').equals(styleName).first();



            if (existingStyle) {

                existingStyle.usageCount++;

                existingStyle.parameters = concept.materialProperties;

                await this.db.styles.put(existingStyle);

            } else {

                await this.db.styles.add({

                    name: styleName,

                    description: `Style ${concept.shapeType} ${concept.style}`,

                    parameters: concept.materialProperties,

                    usageCount: 1

                });

            }



            console.log('💾 IA: Données sauvegardées dans la mémoire');

        } catch (error) {

            console.error('Erreur sauvegarde IA:', error);

        }

    }



    updateCreativity() {

        // La créativité augmente avec l'expérience mais diminue si trop routinière

        const experienceFactor = this.experiencePoints / 10000;

        const noveltyFactor = 1 - (this.generationCount % 10) / 10;



        this.creativityLevel = 0.3 +

            (experienceFactor * 0.4) +

            (noveltyFactor * 0.3);



        this.creativityLevel = Math.max(0.1, Math.min(1.0, this.creativityLevel));

    }



    generateCreativeName(analysis) {

        const prefixes = ['Néo', 'Cyber', 'Bio', 'Quantum', 'Hyper', 'Meta', 'Synth', 'Crystal'];

        const stems = ['Sphere', 'Cube', 'Form', 'Shape', 'Structure', 'Entity', 'Construct', 'Object'];

        const suffixes = ['Prime', 'X', 'Zero', 'Alpha', 'Omega', 'Core', 'Node'];



        const style = analysis.shapeHints[0] || 'geometric';

        const prefix = _.sample(prefixes);

        const stem = _.sample(stems);

        const suffix = Math.random() > 0.7 ? _.sample(suffixes) : '';



        return `${prefix}${stem}${suffix}`;

    }



    determineShapeType(analysis, successfulStyles) {

        // Si des styles ont bien marché, les favoriser

        if (successfulStyles.length > 0) {

            const styleNames = successfulStyles.map(s => s.name.split('_')[0]);

            const counts = _.countBy(styleNames);

            const mostSuccessful = _.maxBy(Object.keys(counts), k => counts[k]);



            if (counts[mostSuccessful] > 3) {

                return mostSuccessful;

            }

        }



        // Sinon, basé sur l'analyse du prompt

        if (analysis.shapeHints.length > 0) {

            return analysis.shapeHints[0];

        }



        // Ou aléatoire avec biais vers le géométrique

        const types = ['organic', 'mechanical', 'architectural', 'abstract', 'geometric'];

        const weights = [0.15, 0.2, 0.15, 0.15, 0.35]; // Favorise géométrique



        let sum = 0;

        const rand = Math.random();

        for (let i = 0; i < types.length; i++) {

            sum += weights[i];

            if (rand <= sum) return types[i];

        }



        return 'geometric';

    }



    computeTangents(geometry) {

        try {

            // Implémentation simplifiée du calcul de tangentes

            const vertices = geometry.attributes.position.array;

            const tangents = new Float32Array(vertices.length);



            for (let i = 0; i < vertices.length; i += 9) {

                // Calcul basique des tangentes pour chaque triangle

                const tangent = new THREE.Vector3(1, 0, 0);

                tangents[i] = tangent.x; tangents[i+1] = tangent.y; tangents[i+2] = tangent.z;

                tangents[i+3] = tangent.x; tangents[i+4] = tangent.y; tangents[i+5] = tangent.z;

                tangents[i+6] = tangent.x; tangents[i+7] = tangent.y; tangents[i+8] = tangent.z;

            }



            return new THREE.BufferAttribute(tangents, 3);

        } catch (error) {

            console.warn('Erreur calcul tangentes:', error);

            return new THREE.BufferAttribute(new Float32Array(), 3);

        }

    }



    async onObjectInteract(mesh) {

        // Quand l'utilisateur interagit avec un objet généré par IA

        console.log('🤖 IA: Interaction avec', mesh.name);



        // Apprendre de l'interaction

        try {

            if (this.db) {

                await this.db.userFeedback.add({

                    objectId: mesh.userData.generationId,

                    rating: 1, // L'interaction est positive

                    comments: 'User clicked',

                    improvements: [],

                    timestamp: new Date()

                });

            }

        } catch (error) {

            console.warn('Erreur sauvegarde feedback:', error);

        }



        // Effet visuel de feedback

        try {

            const originalEmissive = mesh.material.emissive.clone();

            mesh.material.emissive.setHex(0xffff00);

            mesh.material.emissiveIntensity = 0.5;



            setTimeout(() => {

                mesh.material.emissive.copy(originalEmissive);

                mesh.material.emissiveIntensity = originalEmissive.length() > 0 ? 0.3 : 0;

            }, 300);

        } catch (error) {

            console.warn('Erreur effet visuel:', error);

        }

    }



    async getStats() {

        try {

            let objects = [];

            let styles = [];



            if (this.db) {

                objects = await this.db.objects.toArray();

                styles = await this.db.styles.toArray();

            }



            return {

                totalGenerations: this.generationCount,

                experiencePoints: this.experiencePoints,

                creativityLevel: this.creativityLevel,

                objectsCount: objects.length,

                stylesCount: styles.length,

                averageComplexity: _.meanBy(objects, 'complexity') || 0,

                mostSuccessfulStyle: _.maxBy(styles, 'usageCount')?.name || 'None'

            };

        } catch (error) {

            console.warn('Erreur récupération stats:', error);

            return {

                totalGenerations: this.generationCount,

                experiencePoints: this.experiencePoints,

                creativityLevel: this.creativityLevel,

                objectsCount: 0,

                stylesCount: 0,

                averageComplexity: 0,

                mostSuccessfulStyle: 'None'

            };

        }

    }



    async generateFromLearning(quantity = 3) {

        // Générer plusieurs objets basés sur l'apprentissage

        const objects = [];



        for (let i = 0; i < quantity; i++) {

            try {

                let bestStyles = [];

                let bestPatterns = [];



                if (this.db) {

                    // Utiliser les connaissances accumulées

                    bestStyles = await this.db.styles

                        .orderBy('usageCount')

                        .reverse()

                        .limit(3)

                        .toArray();



                    bestPatterns = await this.db.patterns

                        .where('successRate')

                        .above(0.8)

                        .limit(2)

                        .toArray();

                }



                // Créer un prompt synthétique basé sur l'apprentissage

                const syntheticPrompt = this.createSyntheticPrompt(bestStyles, bestPatterns);



                const obj = await this.generateObject(syntheticPrompt, {

                    complexity: Math.min(4, 1 + Math.floor(this.experiencePoints / 2000))

                });



                objects.push(obj);

            } catch (error) {

                console.error('Erreur génération apprentissage:', error);

                // Générer un objet simple en fallback

                const fallbackObj = await this.generateObject('objet géométrique simple', {

                    complexity: 2

                });

                objects.push(fallbackObj);

            }

        }



        return objects;

    }



    createSyntheticPrompt(styles, patterns) {

        const style = _.sample(styles);

        const pattern = _.sample(patterns);



        const adjectives = ['complexe', 'détaillé', 'élégant', 'futuriste', 'organique', 'mécanique'];

        const nouns = ['structure', 'forme', 'objet', 'sculpture', 'construction'];



        return `${_.sample(adjectives)} ${_.sample(nouns)} inspiré par ${style?.name?.split('_')[0] || 'géométrie'}`;

    }



    // Génération de textures procédurales

    generateProceduralNormalMap() {

        try {

            const canvas = document.createElement('canvas');

            canvas.width = 256;

            canvas.height = 256;

            const ctx = canvas.getContext('2d');



            // Générer du bruit pour la normale

            const imageData = ctx.createImageData(256, 256);

            for (let i = 0; i < imageData.data.length; i += 4) {

                const value = Math.random() * 255;

                imageData.data[i] = 128; // R

                imageData.data[i + 1] = value; // G

                imageData.data[i + 2] = 255; // B

                imageData.data[i + 3] = 255; // A

            }



            ctx.putImageData(imageData, 0, 0);

            return new THREE.CanvasTexture(canvas);

        } catch (error) {

            console.warn('Erreur génération normal map:', error);

            return null;

        }

    }



    generateProceduralRoughnessMap() {

        try {

            const canvas = document.createElement('canvas');

            canvas.width = 256;

            canvas.height = 256;

            const ctx = canvas.getContext('2d');



            // Dégradé avec du bruit

            const gradient = ctx.createLinearGradient(0, 0, 256, 256);

            gradient.addColorStop(0, 'rgba(100,100,100,1)');

            gradient.addColorStop(1, 'rgba(200,200,200,1)');



            ctx.fillStyle = gradient;

            ctx.fillRect(0, 0, 256, 256);



            // Ajouter du bruit

            const imageData = ctx.getImageData(0, 0, 256, 256);

            for (let i = 0; i < imageData.data.length; i += 4) {

                const noise = Math.random() * 30 - 15;

                imageData.data[i] += noise;

                imageData.data[i + 1] += noise;

                imageData.data[i + 2] += noise;

            }



            ctx.putImageData(imageData, 0, 0);

            return new THREE.CanvasTexture(canvas);

        } catch (error) {

            console.warn('Erreur génération roughness map:', error);

            return null;

        }

    }



    generateProceduralMetalnessMap() {

        try {

            const canvas = document.createElement('canvas');

            canvas.width = 256;

            canvas.height = 256;

            const ctx = canvas.getContext('2d');



            // Motif métallique

            for (let x = 0; x < 256; x += 8) {

                for (let y = 0; y < 256; y += 8) {

                    const bright = Math.random() > 0.7 ? 220 : 80;

                    ctx.fillStyle = `rgb(${bright},${bright},${bright})`;

                    ctx.fillRect(x, y, 4, 4);

                }

            }



            return new THREE.CanvasTexture(canvas);

        } catch (error) {

            console.warn('Erreur génération metalness map:', error);

            return null;

        }

    }

}



// ============================================

// INTERFACE UTILISATEUR POUR L'IA

// ============================================



class AIInterface {

    constructor(aiSystem) {

        this.ai = aiSystem;

        this.panel = null;

        this.isOpen = false;

        this.generatedItems = new Map();

        this.lastGroqModel = null;

        this.trainingItems = null;

        this.initInterface();

    }



    initInterface() {

        // Créer le panneau de contrôle IA

        this.panel = document.createElement('div');

        this.panel.id = 'ai-generator-panel';

        this.panel.style.cssText = `

            position: fixed;

            top: 100px;

            right: 20px;

            width: 320px;

            background: rgba(20, 20, 30, 0.95);

            border: 2px solid #4a9eff;

            border-radius: 12px;

            padding: 20px;

            color: white;

            font-family: 'Segoe UI', sans-serif;

            z-index: 10000;

            display: none;

            backdrop-filter: blur(10px);

            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);

            max-height: 80vh;

            overflow-y: auto;

        `;



        // Header

        const header = document.createElement('div');

        header.innerHTML = `

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">

                <h3 style="margin: 0; color: #4a9eff; font-size: 18px;">

                    🤖 IA Génératrice 3D

                </h3>

                <button id="ai-close-btn" style="

                    background: none;

                    border: none;

                    color: white;

                    font-size: 20px;

                    cursor: pointer;

                    padding: 5px;

                ">×</button>

            </div>



            <button id="ai-free-export-ads-btn" type="button" style="

                display:none;

                width:100%;

                margin: 0 0 12px 0;

                padding: 12px;

                background: rgba(59,130,246,0.20);

                border: 1px solid rgba(255,255,255,0.18);

                border-radius: 10px;

                color: #fff;

                font-weight: 800;

                cursor: pointer;

            ">télécharger gratuitement avec pub</button>

        `;

        this.panel.appendChild(header);



        // Section IA: 10 selects + admin Groq

        const configSection = document.createElement('div');

        configSection.innerHTML = `

            <div id="ai-groq-admin" style="

                display:none;

                margin-bottom: 12px;

                padding: 12px;

                border: 1px solid rgba(255,255,255,0.15);

                border-radius: 8px;

                background: rgba(0,0,0,0.25);

            ">

                <div style="font-weight: 700; color: #4a9eff; margin-bottom: 8px;">Admin · Connexion Groq</div>

                <div style="margin-bottom: 8px;">

                    <label style="display:block; font-size:12px; color:#aaa; margin-bottom:4px;">Clé Groq (gsk_...)</label>

                    <input type="password" id="ai-groq-key" placeholder="gsk_..." style="

                        width:100%; padding:8px; border:1px solid #444; border-radius:6px;

                        background: rgba(255,255,255,0.08); color: white;

                    "/>

                </div>

                <div style="margin-bottom: 10px;">

                    <label style="display:block; font-size:12px; color:#aaa; margin-bottom:4px;">Modèle</label>

                    <select id="ai-groq-model" style="

                        width:100%; padding:8px; border:1px solid #444; border-radius:6px;

                        background: rgba(255,255,255,0.08); color: white;

                    "></select>

                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 8px;">

                    <button id="ai-groq-test-btn" style="

                        padding: 10px; border: none; border-radius: 8px; cursor: pointer;

                        background: rgba(74,158,255,0.18); color: #4a9eff; font-weight: 700;

                    ">Tester</button>

                    <button id="ai-groq-save-btn" style="

                        padding: 10px; border: none; border-radius: 8px; cursor: pointer;

                        background: rgba(46,204,113,0.18); color: #2ecc71; font-weight: 700;

                    ">Enregistrer</button>

                </div>

                <div id="ai-groq-admin-status" style="margin-top:8px; font-size:12px; color:#aaa;"></div>

            </div>



            <div id="ai-train-admin" style="

                display:none;

                margin-bottom: 12px;

                padding: 12px;

                border: 1px solid rgba(255,255,255,0.15);

                border-radius: 8px;

                background: rgba(0,0,0,0.25);

            ">

                <div style="font-weight: 700; color: #2ecc71; margin-bottom: 8px;">Admin · Entraînement (GLB / Sketchfab)</div>



                <div style="display:grid; grid-template-columns: 1fr; gap: 8px; margin-bottom: 10px;">

                    <div style="display:grid; grid-template-columns: 1fr; gap: 4px;">

                        <label style="font-size:12px; color:#aaa;">Catégorie</label>

                        <select id="ai-train-category" style="

                            width:100%; padding:8px; border:1px solid #444; border-radius:6px;

                            background: rgba(255,255,255,0.08); color: white;

                        "></select>

                    </div>

                    <div style="display:grid; grid-template-columns: 1fr; gap: 4px;">

                        <label style="font-size:12px; color:#aaa;">Objet cible</label>

                        <select id="ai-train-target" style="

                            width:100%; padding:8px; border:1px solid #444; border-radius:6px;

                            background: rgba(255,255,255,0.08); color: white;

                        "></select>

                    </div>

                </div>



                <div style="padding: 10px; border: 1px solid rgba(255,255,255,0.10); border-radius: 8px; background: rgba(255,255,255,0.04); margin-bottom: 10px;">

                    <div style="font-weight:700; color:#ddd; margin-bottom:6px;">Importer un .glb</div>

                    <input type="file" id="ai-train-glb" accept=".glb" style="width:100%;" />

                    <button id="ai-train-glb-btn" style="

                        margin-top: 8px; width:100%; padding: 10px; border: none; border-radius: 8px; cursor: pointer;

                        background: rgba(46,204,113,0.18); color: #2ecc71; font-weight: 700;

                    ">Importer & entraîner</button>

                </div>



                <div id="ai-train-admin-status" style="margin-top:8px; font-size:12px; color:#aaa;"></div>

            </div>



            <div style="margin-bottom: 10px;">

                <div style="display:flex; justify-content: space-between; align-items:center; gap: 10px; margin-bottom: 8px;">

                    <div style="font-weight: 700;">🎛️ Paramètres (10 selects)</div>

                    <div style="display:flex; gap: 6px;">

                        <button id="ai-selects-auto" style="padding:6px 10px; border-radius: 8px; border:1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.06); color: #ddd; cursor:pointer;">Tout Auto</button>

                        <button id="ai-selects-rand" style="padding:6px 10px; border-radius: 8px; border:1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.06); color: #ddd; cursor:pointer;">Tout Aléatoire</button>

                    </div>

                </div>

                <div id="ai-selects-container" style="display:grid; grid-template-columns: 1fr; gap: 8px;"></div>

            </div>



            <div style="margin-bottom: 12px;">

                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">

                    <input type="checkbox" id="ai-animate" />

                    <span style="color: #aaa; font-size: 14px;">Animations (on s'en occupe plus tard)</span>

                </label>

                <label style="display: flex; align-items: center; gap: 8px;">

                    <input type="checkbox" id="ai-textures" checked />

                    <span style="color: #aaa; font-size: 14px;">Textures procédurales</span>

                </label>

            </div>

        `;

        this.panel.appendChild(configSection);



        // Boutons d'action

        const actions = document.createElement('div');

        actions.style.cssText = 'display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;';



        const generateBtn = document.createElement('button');

        generateBtn.id = 'ai-generate-btn';

        generateBtn.textContent = '🎨 Générer';

        generateBtn.style.cssText = `

            padding: 12px;

            background: linear-gradient(135deg, #4a9eff, #2980b9);

            border: none;

            border-radius: 8px;

            color: white;

            font-weight: bold;

            cursor: pointer;

            transition: all 0.3s;

        `;



        const autoBtn = document.createElement('button');

        autoBtn.id = 'ai-auto-btn';

        autoBtn.textContent = '⚡ Auto x3';

        autoBtn.style.cssText = `

            padding: 12px;

            background: linear-gradient(135deg, #9b59b6, #8e44ad);

            border: none;

            border-radius: 8px;

            color: white;

            font-weight: bold;

            cursor: pointer;

            transition: all 0.3s;

        `;



        actions.appendChild(generateBtn);

        actions.appendChild(autoBtn);



        this.panel.appendChild(actions);



        // Liste des objets générés + feedback

        const genList = document.createElement('div');

        genList.id = 'ai-gen-list';

        genList.style.cssText = `

            margin-top: 10px;

            padding: 12px;

            background: rgba(0,0,0,0.25);

            border-radius: 8px;

            border: 1px solid rgba(255,255,255,0.12);

        `;

        genList.innerHTML = `

            <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 8px;">

                <div style="color:#4a9eff; font-weight: 700;">🧩 Objets générés</div>

                <button id="ai-clear-list" style="

                    padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15);

                    background: rgba(255,255,255,0.06); color: #ddd; cursor: pointer;

                ">Vider</button>

            </div>

            <div id="ai-gen-items" style="display:grid; gap: 6px;"></div>

            <div style="margin-top:8px; font-size:12px; color:#888;">Clique un objet pour le sélectionner, puis 👍/👎 pour apprendre.</div>

        `;

        this.panel.appendChild(genList);



        // Stats

        const stats = document.createElement('div');

        stats.id = 'ai-stats';

        stats.style.cssText = `

            margin-top: 15px;

            padding: 15px;

            background: rgba(0,0,0,0.3);

            border-radius: 8px;

            font-size: 12px;

            color: #aaa;

        `;

        stats.innerHTML = `

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">

                <div>

                    <div style="color: #4a9eff;">Générations</div>

                    <div id="ai-stat-count">0</div>

                </div>

                <div>

                    <div style="color: #4a9eff;">Expérience</div>

                    <div id="ai-stat-exp">0</div>

                </div>

                <div>

                    <div style="color: #4a9eff;">Créativité</div>

                    <div id="ai-stat-creativity">0%</div>

                </div>

                <div>

                    <div style="color: #4a9eff;">Styles</div>

                    <div id="ai-stat-styles">0</div>

                </div>

            </div>

        `;

        this.panel.appendChild(stats);



        // État

        const status = document.createElement('div');

        status.id = 'ai-status';

        status.style.cssText = `

            margin-top: 10px;

            padding: 10px;

            background: rgba(74, 158, 255, 0.1);

            border-radius: 6px;

            font-size: 12px;

            text-align: center;

            color: #4a9eff;

            display: none;

        `;

        this.panel.appendChild(status);



        document.body.appendChild(this.panel);

        this.setupEventListeners();

        this.buildSelects();

        this.buildTrainingSelects();

        this.loadGroqConfig();

        this.updateStats();

        this.refreshAdsExportButton();

    }



    setupEventListeners() {

        // Bouton de fermeture

        document.getElementById('ai-close-btn').addEventListener('click', () => this.toggle());



        // Téléchargement gratuit avec pub

        document.getElementById('ai-free-export-ads-btn')?.addEventListener('click', async (e) => {

            e.preventDefault();

            try {

                if (typeof window.pp3StartFreeExportWithAds === 'function') {

                    await window.pp3StartFreeExportWithAds();

                } else {

                    alert('Fonction pub indisponible.');

                }

            } catch (err) {

                console.error(err);

                alert('Erreur pub/export.');

            }

        });



        // Bouton générer

        document.getElementById('ai-generate-btn').addEventListener('click', async () => {

            // Vérifier les permissions avant de générer

            if (typeof window.pp3ShouldBlockFeature === 'function' && window.pp3ShouldBlockFeature('ai_generator')) {

                return; // Bloqué par les permissions

            }

            await this.generateObject();

        });



        // Bouton auto générer

        document.getElementById('ai-auto-btn').addEventListener('click', async () => {

            // Vérifier les permissions avant de générer

            if (typeof window.pp3ShouldBlockFeature === 'function' && window.pp3ShouldBlockFeature('ai_generator')) {

                return; // Bloqué par les permissions

            }

            await this.generateMultiple();

        });



        document.getElementById('ai-selects-auto')?.addEventListener('click', (e) => {

            e.preventDefault();

            this.setAllSelectsToAuto();

        });



        document.getElementById('ai-selects-rand')?.addEventListener('click', (e) => {

            e.preventDefault();

            this.randomizeAllSelects();

        });



        document.getElementById('ai-clear-list')?.addEventListener('click', (e) => {

            e.preventDefault();

            this.generatedItems.clear();

            const box = document.getElementById('ai-gen-items');

            if (box) box.innerHTML = '';

        });



        document.getElementById('ai-groq-test-btn')?.addEventListener('click', async (e) => {

            e.preventDefault();

            await this.testGroqConfig();

        });



        document.getElementById('ai-groq-save-btn')?.addEventListener('click', async (e) => {

            e.preventDefault();

            await this.saveGroqConfig();

        });



        document.getElementById('ai-train-glb-btn')?.addEventListener('click', async (e) => {

            e.preventDefault();

            await this.importTrainingGLB();

        });



        // Raccourci clavier

        document.addEventListener('keydown', (e) => {

            if (e.ctrlKey && e.key === 'g' && e.shiftKey) {

                e.preventDefault();

                this.toggle();

            }

        });



    }



    toggle() {

        this.isOpen = !this.isOpen;

        this.panel.style.display = this.isOpen ? 'block' : 'none';



        if (this.isOpen) {

            this.updateStats();

            this.refreshAdsExportButton();

            const first = this.panel.querySelector('select, input');

            if (first && typeof first.focus === 'function') first.focus();

        }

    }



    refreshAdsExportButton() {

        const btn = document.getElementById('ai-free-export-ads-btn');

        if (!btn) return;



        // Afficher seulement si:

        // - pub activée + zones configurées

        // - l’export est bloqué pour les non-premium

        // - utilisateur non-premium

        let show = false;

        try {

            const cfg = (typeof window.pp3GetMonetagConfig === 'function') ? window.pp3GetMonetagConfig() : { enabled: false, zones: [] };

            const premiumActive = (typeof pp3IsPremiumActive === 'function') ? pp3IsPremiumActive() : false;

            const exportReq = (typeof pp3ExportRequiresPremium === 'function') ? pp3ExportRequiresPremium() : false;

            const isPremiumUser = !!(window.pp3State && window.pp3State.user && window.pp3State.user.is_premium);

            show = !!(cfg && cfg.enabled && Array.isArray(cfg.zones) && cfg.zones.length > 0 && premiumActive && exportReq && !isPremiumUser);

        } catch (_) {

            show = false;

        }

        btn.style.display = show ? 'block' : 'none';

    }



    async generateObject() {

        await this.generateWithGroq(1);

    }



    async generateWithGroq(count) {

        const animate = !!document.getElementById('ai-animate')?.checked;

        const textures = !!document.getElementById('ai-textures')?.checked;

        const selections = this.getSelections();

        const cdn = this.getCdnContext();



        const status = document.getElementById('ai-status');

        status.textContent = count > 1 ? `🧠 IA (Groq) en train de créer x${count}...` : '🧠 IA (Groq) en train de créer...';

        status.style.display = 'block';

        status.style.color = '#4a9eff';



        try {

            const plans = await this.fetchGroqPlan(count, selections, animate, textures, cdn, 'plan');

            let addedCount = 0;



            for (let i = 0; i < plans.length; i++) {

                const plan = plans[i] || {};

                const prompt = (typeof plan.prompt === 'string') ? plan.prompt : '';

                const c = (plan.constraints && typeof plan.constraints === 'object') ? plan.constraints : {};

                const constraints = {

                    complexity: Math.max(1, Math.min(5, parseInt(c.complexity || 3))),

                    styleHint: (typeof c.styleHint === 'string' && c.styleHint) ? c.styleHint : 'geometric',

                    animate: (typeof c.animate === 'boolean') ? c.animate : animate,

                    proceduralTextures: (typeof c.proceduralTextures === 'boolean') ? c.proceduralTextures : textures,

                };



                // Priorité apprentissage: si on a un GLB importé correspondant, on le réutilise comme base identique.

                let object3D = null;

                try {

                    const base = await this.tryCreateFromImportedGLB(plan, selections);

                    if (base) object3D = base;

                } catch (e) {

                    console.warn('tryCreateFromImportedGLB error', e);

                }



                if (!object3D) {

                    object3D = await this.ai.generateObject(prompt, constraints);

                }



                // Auto-ops (outil d'éditeur: couleur/rotation/déformation/texture)

                try { this.injectPostOpsFromSelections(plan, selections); } catch (_) {}

                try { this.applyAIAutoOps(object3D, plan); } catch (_) {}



                // Marquer meta IA

                object3D.userData = object3D.userData || {};

                object3D.userData.iagen = {

                    selections,

                    plan,

                    groqModel: this.lastGroqModel,

                    createdAt: new Date().toISOString(),

                };



                // Ajouter à la scène si elle existe

                if (typeof scene !== 'undefined' && scene) {

                    scene.add(object3D);

                    addedCount++;



                    if (typeof objects !== 'undefined' && Array.isArray(objects)) {

                        objects.push(object3D);



                        if (typeof updateObjectList === 'function') {

                            setTimeout(() => updateObjectList(), 100);

                        }



                        if (typeof selectObject === 'function' && i === plans.length - 1) {

                            setTimeout(() => selectObject(object3D), 150);

                        }

                    }

                }



                this.addGeneratedItem(object3D, selections);

            }



            // Feedback

            status.textContent = `✅ ${addedCount} objet(s) créé(s) via Groq!`;

            status.style.color = '#2ecc71';



            setTimeout(() => {

                status.style.display = 'none';

            }, 3000);



            // Mettre à jour les stats

            this.updateStats();



        } catch (error) {

            console.error('Erreur génération IA:', error);

            status.textContent = '❌ Erreur lors de la création: ' + error.message;

            status.style.color = '#e74c3c';

        }

    }



    async ensureTrainingItemsLoaded() {

        if (Array.isArray(this.trainingItems)) return this.trainingItems;

        try {

            const fd = new FormData();

            fd.append('pp3_action', 'iagen_glbiagen_list');

            const r = await fetch(location.href, { method: 'POST', body: fd });

            const data = await r.json();

            if (data && data.ok && Array.isArray(data.items)) {

                this.trainingItems = data.items;

                return this.trainingItems;

            }

        } catch (e) {

            console.warn('ensureTrainingItemsLoaded error', e);

        }

        this.trainingItems = [];

        return this.trainingItems;

    }



    pickTrainingItemForSelections(items, selections) {

        const cat = String(selections?.['ai-sel-1'] || '').trim();

        const tgt = String(selections?.['ai-sel-2'] || '').trim();

        if (!cat || !tgt) return null;



        const norm = (s) => String(s || '').trim().toLowerCase();

        const catN = norm(cat);

        const tgtN = norm(tgt);



        const matches = items.filter(it => {

            if (!it || it.source !== 'glb') return false;

            if (!it.glb_file) return false;

            const c = norm(it.category);

            const t = norm(it.target);

            return c === catN && t === tgtN;

        });

        if (!matches.length) return null;



        // Choix: un exemple au hasard

        return matches[Math.floor(Math.random() * matches.length)];

    }



    async loadImportedGLB(glbFile) {

        if (!glbFile) return null;

        const fd = new FormData();

        fd.append('pp3_action', 'iagen_glbiagen_download');

        fd.append('glb_file', String(glbFile));

        const r = await fetch(location.href, { method: 'POST', body: fd });

        if (!r.ok) throw new Error('Download GLB HTTP ' + r.status);

        const buf = await r.arrayBuffer();

        if (!gltfLoader) gltfLoader = new THREE.GLTFLoader();

        const gltf = await new Promise((resolve, reject) => {

            try { gltfLoader.parse(buf, '', resolve, reject); } catch (e) { reject(e); }

        });

        const root = gltf && (gltf.scene || (Array.isArray(gltf.scenes) ? gltf.scenes[0] : null));

        return root || null;

    }



    async tryCreateFromImportedGLB(plan, selections) {

        // Si le plan demande explicitement un fichier glb, on le respecte.

        const glbWanted = plan?.post_ops?.glb_file ? String(plan.post_ops.glb_file) : '';

        if (glbWanted) {

            const obj = await this.loadImportedGLB(glbWanted);

            if (obj) {

                obj.name = (typeof plan.name === 'string' && plan.name.trim()) ? plan.name.trim() : (obj.name || 'Imported GLB');

                obj.userData = obj.userData || {};

                obj.userData.iagen_import_base = { glb_file: glbWanted };

                try { this.indexImportedObjectParts(obj); } catch (_) {}

                return obj;

            }

        }



        // Sinon, on tente automatiquement si category+target sont renseignés

        if (!selections?.['ai-sel-1'] || !selections?.['ai-sel-2']) return null;

        const items = await this.ensureTrainingItemsLoaded();

        const pick = this.pickTrainingItemForSelections(items, selections);

        if (!pick || !pick.glb_file) return null;



        const obj = await this.loadImportedGLB(pick.glb_file);

        if (!obj) return null;

        obj.name = (typeof plan?.name === 'string' && plan.name.trim()) ? plan.name.trim() : (pick.name || obj.name || 'Imported GLB');

        obj.userData = obj.userData || {};

        obj.userData.iagen_import_base = { glb_file: pick.glb_file, category: pick.category, target: pick.target };

        try { this.indexImportedObjectParts(obj); } catch (_) {}

        return obj;

    }



    async generateMultiple() {

        await this.generateWithGroq(3);

    }



    buildSelects() {

        const container = document.getElementById('ai-selects-container');

        if (!container) return;



        const defs = this.getSelectDefinitions();

        container.innerHTML = '';



        for (const def of defs) {

            const wrap = document.createElement('div');

            wrap.style.cssText = 'display:grid; grid-template-columns: 1fr; gap: 4px;';



            const label = document.createElement('label');

            label.textContent = def.label;

            label.style.cssText = 'font-size: 12px; color: #aaa;';



            const sel = document.createElement('select');

            sel.id = def.id;

            sel.style.cssText = 'width: 100%; padding: 8px; border: 1px solid #444; border-radius: 6px; background: rgba(255,255,255,0.08); color: white;';



            for (const opt of def.options) {

                const o = document.createElement('option');

                o.value = opt;

                o.textContent = opt;

                sel.appendChild(o);

            }



            wrap.appendChild(label);

            wrap.appendChild(sel);

            container.appendChild(wrap);

        }



        // Catégorie -> Objet cible : filtrage dynamique

        try {

            const catEl = document.getElementById('ai-sel-1');

            const tgtEl = document.getElementById('ai-sel-2');

            if (catEl && tgtEl) {

                const update = () => {

                    const cat = String(catEl.value || 'Auto');

                    const opts = this.getTargetsForCategory(cat);

                    this.populateSelectOptions(tgtEl, opts, true);

                    if (tgtEl.value === 'Auto' && tgtEl.options.length > 1) tgtEl.value = tgtEl.options[1].value;

                };

                catEl.addEventListener('change', update);

                update();

            }

        } catch (e) {

            console.warn('buildSelects category->target wiring error', e);

        }



        // Modèles Groq

        const modelSel = document.getElementById('ai-groq-model');

        if (modelSel && modelSel.options.length === 0) {

            for (const m of this.getGroqModelList()) {

                const o = document.createElement('option');

                o.value = m;

                o.textContent = m;

                modelSel.appendChild(o);

            }

        }

    }



    getGroqModelList() {

        return [

            'qwen/qwen3-32b',

            'canopylabs/orpheus-arabic-saudi',

            'canopylabs/orpheus-v1-english',

            'groq/compound',

            'groq/compound-mini',

            'llama-3.1-8b-instant',

            'llama-3.3-70b-versatile',

            'meta-llama/llama-4-maverick-17b-128e-instruct',

            'meta-llama/llama-4-scout-17b-16e-instruct',

            'meta-llama/llama-guard-4-12b',

            'meta-llama/llama-prompt-guard-2-22m',

            'meta-llama/llama-prompt-guard-2-86m',

            'moonshotai/kimi-k2-instruct',

            'moonshotai/kimi-k2-instruct-0905',

            'openai/gpt-oss-120b',

            'openai/gpt-oss-20b',

            'openai/gpt-oss-safeguard-20b',

            'whisper-large-v3',

            'whisper-large-v3-turbo'

        ];

    }



    getSelectDefinitions() {

        // 10 selects, 20 options chacun (inclut "Auto").

        // Objectif: forcer des objets concrets (pas des structures abstraites).

        const map = this.getCategoryTargetMap();

        const categories = ['Auto', ...Object.keys(map).filter(k => k !== 'Auto')];

        return [

            {

                id: 'ai-sel-1',

                label: 'Catégorie',

                options: categories

            },

            {

                id: 'ai-sel-2',

                label: 'Objet cible',

                // Rempli dynamiquement selon ai-sel-1

                options: ['Auto']

            },

            {

                id: 'ai-sel-3',

                label: 'Style visuel',

                options: ['Auto','Réaliste','Lowpoly','Toon','Stylisé','Sci-fi','Médiéval','Moderne','Industriel','Rustique','Brutaliste','Art déco','Minimaliste','Cyberpunk','Steampunk','Post-apo','Fantaisie','Cartoon','Hard-surface','Organique']

            },

            {

                id: 'ai-sel-4',

                label: 'Matériau principal',

                options: ['Auto','Bois','Métal','Plastique','Pierre','Béton','Verre','Céramique','Tissu','Cuir','Caoutchouc','Feuillage','Écorce','Terre','Glace','Or','Cuivre','Chrome','Carbone','Peinture']

            },

            {

                id: 'ai-sel-5',

                label: 'Forme de base (silhouette)',

                options: ['Auto','Cube','Prisme','Cylindre','Cône','Sphère','Tore','Anneau','Arche','Dôme','Pyramide','Croix','Étoile','Spirale','Aile','Branche','Tronc','Bloc empilé','Cadre','Tube']

            },

            {

                id: 'ai-sel-6',

                label: 'Niveau de détails',

                options: ['Auto','Minimal','Simple','Moyen','Détaillé','Très détaillé','Propre','Usé','Rugueux','Lisse','Avec vis/rivets','Avec gravures','Avec nervures','Avec coutures','Avec veines','Avec fissures','Avec panneaux','Avec rainures','Avec motifs','Avec imperfections']

            },

            {

                id: 'ai-sel-7',

                label: 'Couleur dominante',

                options: ['Auto','Rouge','Orange','Jaune','Vert','Bleu','Violet','Rose','Blanc','Noir','Gris','Beige','Marron','Turquoise','Cyan','Or','Cuivre','Argent','Multicolore','Naturel']

            },

            {

                id: 'ai-sel-8',

                label: 'Preset matériau (outil peinture)',

                options: ['Auto','basic','plastic','metal','metal-reflect','matte','glossy','rubber','ceramic','chrome','gold','copper','neon','glass','velvet','metal','matte','glossy','plastic','ceramic']

            },

            {

                id: 'ai-sel-9',

                label: 'Déformation / Sculpture auto',

                options: ['Auto','Aucune','Noise léger','Noise fort','Taper (effilé)','Twist léger','Twist fort','Asymétrie','Arrondir','Anguler','Étirer X','Étirer Y','Étirer Z','Compresser','Dents/pointes','Ondulation','Bosses','Creux','Fissures','Nervures']

            },

            {

                id: 'ai-sel-10',

                label: 'Échelle / usage',

                options: ['Auto','Petit prop','Objet main','Objet table','Meuble','Grand meuble','Élément de pièce','Élément extérieur','Architecture petite','Architecture grande','Décor naturel','Décor urbain','Équipement','Machine','Module','Signalétique','Conteneur','Ornement','Pièce technique','Pièce organique']

            },

        ];

    }



    getAvailableTextureFiles() {

        const out = [];

        const addFromSelect = (id) => {

            const sel = document.getElementById(id);

            if (!sel) return;

            for (const opt of Array.from(sel.options || [])) {

                const v = String(opt.value || '').trim();

                if (v) out.push(v);

            }

        };

        // Réutilise les listes existantes (textureplan)

        addFromSelect('brushTextureSelect');

        addFromSelect('paintTextureSelect');

        addFromSelect('paintReliefTextureSelect');

        return Array.from(new Set(out)).filter((x) => x && x !== '');

    }



    populateSelectOptions(selectEl, options, tryKeepValue = true) {

        if (!selectEl) return;

        const prev = tryKeepValue ? String(selectEl.value || '') : '';

        selectEl.innerHTML = '';

        for (const v of (options || [])) {

            const o = document.createElement('option');

            o.value = String(v);

            o.textContent = String(v);

            selectEl.appendChild(o);

        }

        if (tryKeepValue && prev) {

            const ok = Array.from(selectEl.options || []).some((o) => String(o.value) === prev);

            if (ok) selectEl.value = prev;

        }

    }



    getCategoryTargetMap() {

        // Catégories regroupées + compat anciennes valeurs.

        // Note: la liste "Vivant" inclut Homme/Femme + 300 animaux.

        const animalsBase = [

            'Chien','Chat','Cheval','Vache','Mouton','Chèvre','Cochon','Lapin','Souris','Rat','Hamster','Furet','Hérisson','Écureuil','Castor','Loutre','Renard','Loup','Ours brun','Ours polaire','Panda','Blaireau','Lynx','Tigre','Lion','Léopard','Guépard','Jaguar','Puma','Éléphant','Rhinocéros','Hippopotame','Girafe','Zèbre','Bison','Antilope','Gazelle','Cerf','Élan','Renne','Sanglier','Chameau','Dromadaire','Âne','Lama','Alpaga','Kangourou','Koala','Ornithorynque','Paresseux','Tatou','Fourmilier','Morse','Phoque','Otarie','Dauphin','Orque','Baleine','Requin','Raie manta','Pieuvre','Calmar','Crabe','Homard','Crevette','Méduse','Étoile de mer','Hippocampe','Saumon','Truite','Carpe','Brochet','Anguille','Crocodile','Alligator','Tortue','Iguane','Caméléon','Dragon de Komodo','Serpent','Cobra','Vipère','Python','Grenouille','Crapaud','Salamandre','Aigle','Faucon','Hibou','Chouette','Corbeau','Moineau','Pigeon','Perroquet','Toucan','Flamant rose','Cygne','Canard','Oie','Poulet','Coq','Dinde','Paon','Autruche','Pingouin','Héron','Cigogne','Mouette','Goéland','Albatros','Papillon','Abeille','Guêpe','Fourmi','Termite','Mante religieuse','Coccinelle','Scarabée','Luciole','Criquet','Libellule','Moustique','Araignée','Scorpion'

        ];

        const animalVariants = ['', ' (bébé)', ' (adulte)'];

        const animals300 = [];

        for (const a of animalsBase) {

            for (const v of animalVariants) {

                animals300.push(a + v);

                if (animals300.length >= 300) break;

            }

            if (animals300.length >= 300) break;

        }



        const listVegetal = ['Herbe','Touffe d’herbe','Fougère','Cactus en pot','Plante grasse','Bonsaï','Fleur en vase','Bouquet','Arbre (jeune)','Arbre (vieux)','Buisson','Liane','Champignon','Tronc coupé','Souche','Feuille géante','Fruit (pomme)','Fruit (banane)','Fruit (citron)','Graine','Gousse','Algue','Corail'];

        const listMineral = ['Rocher','Galets','Cristal','Géode','Stalagmite','Stalactite','Bloc de pierre','Bloc de marbre','Bloc de glace','Météorite'];

        const listEau = ['Eau (surface)','Eau (volume)','Rivière','Ruisseau','Canal','Lac','Étang','Mer','Océan','Cascade','Vagues','Bassin','Fontaine (eau)','Marécage'];

        const listFurniture = ['Chaise','Tabouret','Table','Table basse','Bureau','Étagère','Armoire','Commode','Lit','Canapé','Fauteuil','Banc','Lampe de bureau','Lampadaire','Suspension','Applique','Miroir','Tapis (relief)','Cadre photo','Paravent'];

        const listKitchen = ['Assiette','Bol','Tasse','Mug','Verre','Carafe','Bouteille','Casserole','Poêle','Couverts','Planche à découper','Théière','Cafetière','Grille-pain','Mixeur','Robot cuisine','Boîte hermétique','Panier','Bocal','Saladier'];

        const listTools = ['Marteau','Tournevis','Clé plate','Clé à molette','Pince','Scie','Perceuse','Vis','Boulon','Écrou','Établi','Caisse à outils','Mètre ruban','Cutter','Lime','Pelle','Râteau','Arrosoir','Sécateur','Brouette'];

        const listElectronics = ['Téléphone','Tablette','Ordinateur portable','Écran','Clavier','Souris','Casque audio','Enceinte','Manette','Routeur','Caméra','Micro','Drone','Console','Batterie','Chargeur','Ampoule','Ventilateur','Antenne','Boîtier'];

        const listSport = ['Ballon','Haltère','Kettlebell','Raquette','Skate','Trottinette','Vélo (simple)','Tapis yoga','Cône d’entraînement','Sac de sport','Gants','Casque sport','Gourde','Corde à sauter','Planche','Trophée','Médaille','Cible'];

        const listMedical = ['Trousse de secours','Boîte de médicaments','Seringue (stylisée)','Stéthoscope','Thermomètre','Masque médical','Béquille','Fauteuil roulant (simplifié)','Bouteille oxygène','Pansement'];

        const listContainers = ['Caisse','Coffre','Valise','Sac','Carton','Conteneur','Baril','Bidon','Seau','Poubelle','Boîte','Cagette','Coffret','Caisse en bois','Caisse métal','Tonneau','Mallette','Coffre-fort','Casier','Bac'];

        const listArchitecture = ['Porte','Fenêtre','Arche','Colonne','Pilier','Escalier','Rampe','Balcon','Toit','Dôme','Tour','Maison','Cabane','Mur','Muret','Grille','Portail','Pont','Passerelle','Garde-corps'];

        const listEnvironment = ['Lampadaire urbain','Poteau','Panneau','Feu tricolore','Banc urbain','Borne','Boîte aux lettres','Abribus','Barrière','Cônes de chantier','Palette','Poubelle urbaine','Fontaine','Statue','Brique','Pavé','Caniveau','Grille d’égout'];

        const listIndustrial = ['Module sci-fi','Console sci-fi','Porte sci-fi','Panneau sci-fi','Réacteur','Générateur','Antenne sci-fi','Capsule','Cryotube','Bras robot','Tourelle (stylisée)','Caisson','Ventilation','Tuyau','Valve','Réservoir'];

        const listVehicles = ['Vélo','Moto (stylisée)','Voiture (stylisée)','Camion (stylisé)','Bus (stylisé)','Tracteur','Chariot élévateur','Avion (stylisé)','Hélicoptère (stylisé)','Bateau (stylisé)','Sous-marin (stylisé)'];

        const listRobots = ['Robot (petit)','Robot (moyen)','Robot (humanoïde)','Robot (tête)','Robot (bras)','Mecha (stylisé)','Droid (stylisé)'];

        const listProps = ['Totem géométrique','Sphère décorative','Cube décoratif','Anneau','Arc','Prisme','Cristal stylisé','Portail (anneau)','Nuage (stylisé)','Éclat','Fragment','Plateforme','Socle','Ornement','Stèle','Statue (stylisée)'];

        const listToys = ['Ours en peluche','Poupée','Voiture jouet','Avion jouet','Robot jouet','Cube puzzle','Bille','Toupie','Figurine','Jeu de blocs'];

        const listSignal = ['Panneau stop','Panneau direction','Panneau interdit','Panneau attention','Panneau information','Borne signalétique','Totem signalétique'];



        const listVivant = ['Homme','Femme', ...animals300];

        const listFantastique = ['Dragon','Troll','Sprite','Monstre','Insecte géant','Golem','Gargouille','Démon','Fée','Hydre','Wyverne'];

        const listUndead = ['Squelette','Zombie','Vampire','Fantôme','Liche','Goule'];



        return {

            'Auto': ['Auto'],

            'Vivant': ['Auto', ...listVivant],

            'Créature/Fantastique': ['Auto', ...listFantastique],

            'Mort-vivant': ['Auto', ...listUndead],

            'Végétal': ['Auto', ...listVegetal],

            'Eau': ['Auto', ...listEau],

            'Minéral/Roche': ['Auto', ...listMineral],

            'Mobilier': ['Auto', ...listFurniture],

            'Cuisine': ['Auto', ...listKitchen],

            'Outil': ['Auto', ...listTools],

            'Électronique': ['Auto', ...listElectronics],

            'Sport': ['Auto', ...listSport],

            'Médical': ['Auto', ...listMedical],

            'Contenant': ['Auto', ...listContainers],

            'Architecture': ['Auto', ...listArchitecture],

            'Environnement': ['Auto', ...listEnvironment],

            'Industriel': ['Auto', ...listIndustrial],

            'Véhicule': ['Auto', ...listVehicles],

            'Robot': ['Auto', ...listRobots],

            'Décor/Prop': ['Auto', ...listProps],

            'Jouet': ['Auto', ...listToys],

            'Signalétique': ['Auto', ...listSignal],

            // Compat: anciennes catégories (toujours présentes)

            'Jardin': ['Auto', ...listVegetal, ...listTools],

            'Animal (stylisé)': ['Auto', ...animals300.slice(0, 120)],

        };

    }



    getTargetsForCategory(category) {

        const map = this.getCategoryTargetMap();

        const cat = String(category || 'Auto');

        if (map[cat]) return map[cat];

        return ['Auto'];

    }



    getNodePath(node, stopAt = null) {

        try {

            const names = [];

            let cur = node;

            let guard = 0;

            while (cur && cur !== stopAt && guard < 64) {

                const n = (cur.name && String(cur.name).trim()) ? String(cur.name).trim() : (cur.type || 'Node');

                names.push(n);

                cur = cur.parent;

                guard++;

            }

            return names.reverse().join('/');

        } catch (_) {

            return (node && node.name) ? String(node.name) : '';

        }

    }



    indexImportedObjectParts(object3D) {

        if (!object3D) return null;

        const parts = [];

        const lights = [];

        const hex = (c) => {

            try {

                const col = new THREE.Color(c);

                return '#' + col.getHexString();

            } catch (_) {

                return null;

            }

        };



        let meshIndex = 0;

        object3D.traverse((o) => {

            if (!o) return;

            if (o.isLight) {

                lights.push({ type: o.type || 'Light', name: o.name || '', path: this.getNodePath(o, object3D) });

                return;

            }

            if (!o.isMesh) return;

            const path = this.getNodePath(o, object3D);

            const mat = Array.isArray(o.material) ? o.material[0] : o.material;

            parts.push({

                part_id: `${meshIndex}:${path}`,

                name: o.name || '',

                path,

                material_name: (mat && mat.name) ? String(mat.name) : '',

                material_type: (mat && mat.type) ? String(mat.type) : '',

                colors: {

                    color: (mat && mat.color) ? hex(mat.color) : null,

                    emissive: (mat && mat.emissive) ? hex(mat.emissive) : null,

                },

                maps: (mat ? {

                    map: !!mat.map,

                    normalMap: !!mat.normalMap,

                    roughnessMap: !!mat.roughnessMap,

                    metalnessMap: !!mat.metalnessMap,

                    emissiveMap: !!mat.emissiveMap,

                    aoMap: !!mat.aoMap,

                    alphaMap: !!mat.alphaMap,

                    bumpMap: !!mat.bumpMap,

                } : null),

                mesh_uuid: o.uuid || null,

            });

            meshIndex++;

        });



        object3D.userData = object3D.userData || {};

        object3D.userData.iagen_parts = { parts, lights, indexed_at: new Date().toISOString() };

        return object3D.userData.iagen_parts;

    }



    applyAIPartOps(object3D, plan) {

        if (!object3D || !plan || typeof plan !== 'object') return;

        const partOps = Array.isArray(plan.part_ops) ? plan.part_ops : [];

        if (!partOps.length) return;



        const textureFiles = this.getAvailableTextureFiles();

        const pickTexture = (hint, file) => {

            const forced = (typeof file === 'string' && file.trim()) ? file.trim() : '';

            if (forced) return forced;

            const h = (typeof hint === 'string' && hint.trim()) ? hint.trim().toLowerCase() : '';

            if (!h) return null;

            const match = textureFiles.find((t) => t.toLowerCase().includes(h));

            return match || null;

        };



        const meshes = [];

        object3D.traverse((o) => { if (o && o.isMesh) meshes.push(o); });



        for (const rule of partOps) {

            if (!rule || typeof rule !== 'object') continue;

            const match = (rule.match && typeof rule.match === 'object') ? rule.match : {};

            const ops = (rule.ops && typeof rule.ops === 'object') ? rule.ops : {};



            const partId = (typeof match.part_id === 'string') ? match.part_id.trim() : '';

            const nameInc = (typeof match.nameIncludes === 'string') ? match.nameIncludes.trim().toLowerCase() : '';

            const matInc = (typeof match.materialNameIncludes === 'string') ? match.materialNameIncludes.trim().toLowerCase() : '';

            const limit = Math.max(1, Math.min(50, parseInt(match.limit || 6)));



            const colorHex = (typeof ops.colorHex === 'string' && ops.colorHex.trim()) ? ops.colorHex.trim() : null;

            const preset = (typeof ops.materialPreset === 'string' && ops.materialPreset.trim()) ? ops.materialPreset.trim() : null;

            const textureHint = (typeof ops.textureHint === 'string' && ops.textureHint.trim()) ? ops.textureHint.trim() : '';

            const textureFile = pickTexture(textureHint, ops.textureFile);



            let applied = 0;

            for (let i = 0; i < meshes.length; i++) {

                const m = meshes[i];

                const path = this.getNodePath(m, object3D);

                const myPartId = `${i}:${path}`;



                if (partId && myPartId !== partId) continue;

                if (nameInc && !String(m.name || '').toLowerCase().includes(nameInc) && !path.toLowerCase().includes(nameInc)) continue;

                const mat = Array.isArray(m.material) ? m.material[0] : m.material;

                if (matInc) {

                    const mn = String(mat?.name || '').toLowerCase();

                    if (!mn.includes(matInc)) continue;

                }



                // preset

                if (preset && typeof applyPaintMaterialPresetToMesh === 'function') {

                    try { applyPaintMaterialPresetToMesh(m, preset); } catch (_) {}

                }



                // Opacité / transparence (règle globale)

                try {

                    const mat2 = Array.isArray(m.material) ? m.material[0] : m.material;

                    if (mat2 && typeof mat2.opacity === 'number') {

                        if (preset === 'glass') {

                            mat2.transparent = true;

                            mat2.opacity = Math.min(0.6, Math.max(0.2, mat2.opacity || 0.35));

                        } else {

                            mat2.transparent = false;

                            mat2.opacity = 1.0;

                        }

                        mat2.needsUpdate = true;

                    }

                } catch (_) {}



                // couleur

                if (colorHex) {

                    const mat2 = Array.isArray(m.material) ? m.material[0] : m.material;

                    if (mat2 && mat2.color && typeof mat2.color.set === 'function') {

                        try { mat2.color.set(colorHex); } catch (_) {}

                        mat2.needsUpdate = true;

                    }

                    if (mat2 && mat2.isMeshStandardMaterial && preset === 'neon' && mat2.emissive) {

                        try { mat2.emissive.set(colorHex); } catch (_) {}

                    }

                }



                // texture (uniquement si hint/file fourni)

                if (textureFile) {

                    try {

                        const mat2 = Array.isArray(m.material) ? m.material[0] : m.material;

                        if (mat2 && mat2.isMeshStandardMaterial) {

                            const tl = new THREE.TextureLoader();

                            const tex = tl.load('textureplan/' + encodeURIComponent(textureFile));

                            tex.wrapS = THREE.RepeatWrapping;

                            tex.wrapT = THREE.RepeatWrapping;

                            tex.repeat.set(1, 1);

                            mat2.map = tex;

                            mat2.needsUpdate = true;

                        }

                    } catch (_) {}

                }



                applied++;

                if (applied >= limit) break;

            }

        }

    }



    applyAIAutoOps(object3D, plan) {

        if (!object3D || !plan || typeof plan !== 'object') return;

        const ops = (plan.post_ops && typeof plan.post_ops === 'object') ? plan.post_ops : {};



        const hasPartOps = Array.isArray(plan.part_ops) && plan.part_ops.length > 0;

        const isImported = !!object3D?.userData?.iagen_import_base;



        // 1) Appliquer les opérations ciblées (parties précises)

        try { this.applyAIPartOps(object3D, plan); } catch (e) { console.warn('applyAIPartOps error', e); }



        // Rotation

        if (Array.isArray(ops.rotateDeg) && ops.rotateDeg.length >= 3) {

            const rx = (Number(ops.rotateDeg[0]) || 0) * Math.PI / 180;

            const ry = (Number(ops.rotateDeg[1]) || 0) * Math.PI / 180;

            const rz = (Number(ops.rotateDeg[2]) || 0) * Math.PI / 180;

            object3D.rotation.set(rx, ry, rz);

        }



        // Couleur + preset matériau (outil peinture)

        const colorHex = (typeof ops.colorHex === 'string' && ops.colorHex.trim()) ? ops.colorHex.trim() : null;

        const preset = (typeof ops.materialPreset === 'string' && ops.materialPreset.trim()) ? ops.materialPreset.trim() : null;



        // Texture hint: si une texture existe, on l'applique comme map

        const textureHint = (typeof ops.textureHint === 'string' && ops.textureHint.trim()) ? ops.textureHint.trim().toLowerCase() : '';

        const textureFiles = this.getAvailableTextureFiles();

        let textureFile = null;

        if (textureFiles.length) {

            // Choix simple: match par sous-chaîne. Sur GLB importé: pas d'aléatoire (cohérence).

            const match = textureHint ? textureFiles.find((t) => t.toLowerCase().includes(textureHint)) : null;

            if (match) textureFile = match;

            else if (!isImported) textureFile = textureFiles[Math.floor(Math.random() * textureFiles.length)];

        }



        // Si part_ops est utilisé sur un GLB importé, on évite de recolorer/retexturer TOUT l'objet.

        const applyGlobalAppearance = !(isImported && hasPartOps);



        object3D.traverse((o) => {

            if (!o || !o.isMesh) return;



            // preset (global)

            if (applyGlobalAppearance && preset && typeof applyPaintMaterialPresetToMesh === 'function') {

                try { applyPaintMaterialPresetToMesh(o, preset); } catch (_) {}

            }



            // Fix bug visuel: par défaut les objets IA doivent être opaques et visibles.

            // On autorise la transparence seulement si preset==glass.

            try {

                const mat = (Array.isArray(o.material) ? o.material[0] : o.material);

                if (mat && typeof mat.opacity === 'number') {

                    if (preset === 'glass') {

                        mat.transparent = true;

                        mat.opacity = Math.min(0.6, Math.max(0.2, mat.opacity || 0.35));

                    } else {

                        mat.transparent = false;

                        mat.opacity = 1.0;

                    }

                    mat.needsUpdate = true;

                }

            } catch (_) {}



            // couleur (global)

            if (applyGlobalAppearance && colorHex) {

                const mat = (Array.isArray(o.material) ? o.material[0] : o.material);

                if (mat && mat.color && typeof mat.color.set === 'function') {

                    try { mat.color.set(colorHex); } catch (_) {}

                    mat.needsUpdate = true;

                }

                // neon: aussi emissive

                if (mat && mat.isMeshStandardMaterial && preset === 'neon' && mat.emissive) {

                    try { mat.emissive.set(colorHex); } catch (_) {}

                }

            }



            // texture (global)

            if (applyGlobalAppearance && textureFile) {

                try {

                    const mat = (Array.isArray(o.material) ? o.material[0] : o.material);

                    if (mat && mat.isMeshStandardMaterial) {

                        const tl = new THREE.TextureLoader();

                        const tex = tl.load('textureplan/' + encodeURIComponent(textureFile));

                        tex.wrapS = THREE.RepeatWrapping;

                        tex.wrapT = THREE.RepeatWrapping;

                        tex.repeat.set(1, 1);

                        mat.map = tex;

                        mat.needsUpdate = true;

                    }

                } catch (_) {}

            }



            // Déformation simple (noise) sur BufferGeometry

            const deform = (ops.deform && typeof ops.deform === 'object') ? ops.deform : null;

            if (deform && o.geometry && o.geometry.attributes && o.geometry.attributes.position) {

                const type = String(deform.type || 'none');

                const strength = Math.max(0, Math.min(1, Number(deform.strength) || 0));

                if (strength > 0 && type !== 'none') {

                    try {

                        const g = o.geometry;

                        const pos = g.attributes.position;

                        const nrm = g.attributes.normal;

                        // seed léger basé sur uuid

                        let seed = 0;

                        const u = String(o.uuid || '');

                        for (let i = 0; i < u.length; i++) seed = (seed + u.charCodeAt(i) * 97) % 997;



                        for (let i = 0; i < pos.count; i++) {

                            const x = pos.getX(i);

                            const y = pos.getY(i);

                            const z = pos.getZ(i);

                            const s = strength * 0.25;



                            // bruit pseudo stable

                            const n = Math.sin((x * 3.1 + y * 1.7 + z * 2.3 + seed) * 1.3)

                                + Math.sin((x * 1.3 + y * 2.7 + z * 1.1 + seed) * 2.1);

                            const k = (0.5 + 0.5 * (n * 0.5));



                            if (type === 'noise') {

                                if (nrm) {

                                    pos.setXYZ(i,

                                        x + nrm.getX(i) * s * k,

                                        y + nrm.getY(i) * s * k,

                                        z + nrm.getZ(i) * s * k

                                    );

                                } else {

                                    pos.setXYZ(i, x + s * (k - 0.5), y + s * (k - 0.5), z + s * (k - 0.5));

                                }

                            } else if (type === 'twist') {

                                // twist autour de Y

                                const ang = (k - 0.5) * strength * 1.5;

                                const ca = Math.cos(ang);

                                const sa = Math.sin(ang);

                                pos.setXYZ(i, x * ca - z * sa, y, x * sa + z * ca);

                            } else if (type === 'taper') {

                                // effile en hauteur

                                const t = 0.6 + 0.4 * (1 - Math.min(1, Math.abs(y) * 0.3));

                                const f = 1 - strength * (1 - t);

                                pos.setXYZ(i, x * f, y, z * f);

                            }

                        }

                        pos.needsUpdate = true;

                        try { g.computeVertexNormals(); } catch (_) {}

                    } catch (_) {}

                }

            }

        });

    }



    injectPostOpsFromSelections(plan, selections) {

        if (!plan || typeof plan !== 'object') return;

        const sel = selections && typeof selections === 'object' ? selections : {};

        plan.post_ops = (plan.post_ops && typeof plan.post_ops === 'object') ? plan.post_ops : {};



        // Couleur dominante (select #7)

        const col = String(sel['ai-sel-7'] || '').toLowerCase();

        const map = {

            'rouge':'#ff3b30','orange':'#ff9500','jaune':'#ffd60a','vert':'#34c759','bleu':'#0a84ff','violet':'#bf5af2','rose':'#ff2d55',

            'blanc':'#ffffff','noir':'#111111','gris':'#8e8e93','beige':'#d2b48c','marron':'#8b5a2b','turquoise':'#30d5c8','cyan':'#00ffff',

            'or':'#d4af37','cuivre':'#b87333','argent':'#c0c0c0'

        };

        for (const k of Object.keys(map)) {

            if (col.includes(k)) { plan.post_ops.colorHex = map[k]; break; }

        }



        // Preset matériau (select #8)

        const preset = String(sel['ai-sel-8'] || '').trim();

        if (preset && preset !== 'Auto') {

            plan.post_ops.materialPreset = preset;

        }



        // Déformation (select #9)

        const def = String(sel['ai-sel-9'] || '').toLowerCase();

        const mk = (type, strength) => ({ type, strength });

        if (def.includes('noise')) plan.post_ops.deform = mk('noise', def.includes('fort') ? 0.9 : 0.45);

        else if (def.includes('twist')) plan.post_ops.deform = mk('twist', def.includes('fort') ? 0.9 : 0.45);

        else if (def.includes('taper') || def.includes('effil')) plan.post_ops.deform = mk('taper', 0.6);

        else if (def.includes('aucune')) plan.post_ops.deform = mk('none', 0);

    }



    setAllSelectsToAuto() {

        for (const def of this.getSelectDefinitions()) {

            const el = document.getElementById(def.id);

            if (el) el.value = 'Auto';

        }

        // déclenche le refresh catégorie -> cible

        try {

            const catEl = document.getElementById('ai-sel-1');

            if (catEl) catEl.dispatchEvent(new Event('change'));

        } catch (_) {}

    }



    randomizeAllSelects() {

        const defs = this.getSelectDefinitions();



        // 1) Catégorie d'abord

        const catEl = document.getElementById('ai-sel-1');

        if (catEl) {

            const opts = Array.from(catEl.options || []).map(o => String(o.value));

            const start = Math.random() < 0.5 ? 1 : 0;

            const idx = start + Math.floor(Math.random() * Math.max(1, (opts.length - start)));

            catEl.value = opts[Math.max(0, Math.min(opts.length - 1, idx))];

            try { catEl.dispatchEvent(new Event('change')); } catch (_) {}

        }



        // 2) Objet cible parmi les options filtrées

        const tgtEl = document.getElementById('ai-sel-2');

        if (tgtEl) {

            const opts = Array.from(tgtEl.options || []).map(o => String(o.value));

            const start = Math.random() < 0.5 ? 1 : 0;

            const idx = start + Math.floor(Math.random() * Math.max(1, (opts.length - start)));

            tgtEl.value = opts[Math.max(0, Math.min(opts.length - 1, idx))];

        }



        // 3) Les autres selects

        for (const def of defs) {

            if (def.id === 'ai-sel-1' || def.id === 'ai-sel-2') continue;

            const el = document.getElementById(def.id);

            if (!el) continue;

            const opts = def.options;

            const start = Math.random() < 0.5 ? 1 : 0;

            const idx = start + Math.floor(Math.random() * (opts.length - start));

            el.value = opts[Math.max(0, Math.min(opts.length - 1, idx))];

        }

    }



    getSelections() {

        const selections = {};

        for (const def of this.getSelectDefinitions()) {

            const el = document.getElementById(def.id);

            if (!el) continue;

            const v = String(el.value || 'Auto');

            if (v !== 'Auto') {

                selections[def.id] = v;

            }

        }

        return selections;

    }



    getCdnContext() {

        const scripts = Array.from(document.querySelectorAll('script[src]')).map(s => s.getAttribute('src')).filter(Boolean);

        const styles = Array.from(document.querySelectorAll('link[rel="stylesheet"][href]')).map(l => l.getAttribute('href')).filter(Boolean);

        return { scripts, styles };

    }



    async loadGroqConfig() {

        try {

            const fd = new FormData();

            fd.append('pp3_action', 'iagen_get_cfg');

            const r = await fetch(location.href, { method: 'POST', body: fd });

            const data = await r.json();

            if (!data || !data.ok) return;



            const isAdmin = !!data.is_admin;

            const adminBox = document.getElementById('ai-groq-admin');

            if (adminBox) adminBox.style.display = isAdmin ? 'block' : 'none';



            const trainBox = document.getElementById('ai-train-admin');

            if (trainBox) trainBox.style.display = isAdmin ? 'block' : 'none';



            const modelSel = document.getElementById('ai-groq-model');

            if (modelSel && data.model) modelSel.value = data.model;



            if (isAdmin) {

                const keyInput = document.getElementById('ai-groq-key');

                if (keyInput && data.groq_key) keyInput.value = data.groq_key;

            }



            const s = document.getElementById('ai-groq-admin-status');

            if (s) {

                s.textContent = data.has_key ? `✅ Config Groq chargée (${data.model || 'modèle'})` : '⚠️ Groq non configuré';

            }

        } catch (e) {

            console.warn('loadGroqConfig error', e);

        }

    }



    buildTrainingSelects() {

        const defs = this.getSelectDefinitions();

        const catDef = defs.find(d => d && d.id === 'ai-sel-1');

        const catSel = document.getElementById('ai-train-category');

        const tgtSel = document.getElementById('ai-train-target');

        if (!catSel || !tgtSel) return;



        const fill = (sel, opts) => {

            sel.innerHTML = '';

            for (const v of (opts || [])) {

                const o = document.createElement('option');

                o.value = String(v);

                o.textContent = String(v);

                sel.appendChild(o);

            }

        };

        fill(catSel, (catDef && Array.isArray(catDef.options)) ? catDef.options : ['Auto']);



        const update = () => {

            const cat = String(catSel.value || 'Auto');

            fill(tgtSel, this.getTargetsForCategory(cat));

            try {

                if (tgtSel.value === 'Auto' && tgtSel.options.length > 1) tgtSel.value = tgtSel.options[1].value;

            } catch (_) {}

        };



        catSel.addEventListener('change', update);

        update();



        // Defaults: éviter Auto

        try {

            if (catSel.value === 'Auto' && catSel.options.length > 1) catSel.value = catSel.options[1].value;

            if (tgtSel.value === 'Auto' && tgtSel.options.length > 1) tgtSel.value = tgtSel.options[1].value;

        } catch (_) {}

    }



    getTrainingCategoryAndTarget() {

        const category = String(document.getElementById('ai-train-category')?.value || '').trim();

        const target = String(document.getElementById('ai-train-target')?.value || '').trim();

        return { category, target };

    }



    async importTrainingGLB() {

        const status = document.getElementById('ai-train-admin-status');

        const { category, target } = this.getTrainingCategoryAndTarget();

        const fileInput = document.getElementById('ai-train-glb');

        const file = fileInput && fileInput.files ? fileInput.files[0] : null;



        if (!category || category === 'Auto' || !target || target === 'Auto') {

            if (status) status.textContent = '❌ Choisis Catégorie + Objet cible.';

            return;

        }

        if (!file) {

            if (status) status.textContent = '❌ Sélectionne un fichier .glb.';

            return;

        }

        if (!String(file.name || '').toLowerCase().endsWith('.glb')) {

            if (status) status.textContent = '❌ Format invalide: .glb uniquement.';

            return;

        }



        if (status) status.textContent = '⏳ Analyse GLB (morphologie, matériaux, textures, lumières)...';



        try {

            const buf = await file.arrayBuffer();

            if (!gltfLoader) gltfLoader = new THREE.GLTFLoader();

            const gltf = await new Promise((resolve, reject) => {

                try { gltfLoader.parse(buf, '', resolve, reject); } catch (e) { reject(e); }

            });



            const features = this.extractTrainingFeaturesFromGLTF(gltf);

            features.name = String(file.name || '').replace(/\.glb$/i, '');

            features.category = category;

            features.target = target;



            if (status) status.textContent = '⏳ Upload vers iagenauto/glbiagen...';



            const fd = new FormData();

            fd.append('pp3_action', 'iagen_glbiagen_import');

            fd.append('category', category);

            fd.append('target', target);

            fd.append('features_json', JSON.stringify(features));

            fd.append('glb', new Blob([buf], { type: 'model/gltf-binary' }), file.name || 'model.glb');



            const res = await fetch(location.href, { method: 'POST', body: fd });

            const data = await res.json();

            if (!data || !data.ok) throw new Error(data?.error || 'Import échoué');



            if (status) status.textContent = '✅ Import OK: ' + (data.json || 'json');

            try { fileInput.value = ''; } catch (_) {}

        } catch (e) {

            console.warn('importTrainingGLB error', e);

            if (status) status.textContent = '❌ ' + (e?.message || 'Erreur import GLB');

        }

    }



    extractTrainingFeaturesFromGLTF(gltf) {

        const root = gltf && (gltf.scene || (Array.isArray(gltf.scenes) ? gltf.scenes[0] : null));

        const features = {

            name: '',

            bbox: null,

            bbox_ratio: null,

            mesh_count: 0,

            vertex_count: 0,

            triangle_count: 0,

            material_count: 0,

            texture_count: 0,

            dominant_colors: [],

            lights: { count: 0, types: {} },

            lights_list: [],

            has_animations: !!(gltf && Array.isArray(gltf.animations) && gltf.animations.length),

            parts: [],

        };

        if (!root) return features;



        root.updateMatrixWorld(true);

        const bbox = new THREE.Box3().setFromObject(root);

        const size = new THREE.Vector3();

        bbox.getSize(size);

        features.bbox = { x: +size.x.toFixed(5), y: +size.y.toFixed(5), z: +size.z.toFixed(5) };

        const max = Math.max(size.x, size.y, size.z, 1e-9);

        features.bbox_ratio = {

            x: +((size.x / max) || 0).toFixed(5),

            y: +((size.y / max) || 0).toFixed(5),

            z: +((size.z / max) || 0).toFixed(5),

        };



        const mats = new Set();

        const tex = new Set();

        const colorBins = {};

        const parts = [];

        const capParts = 220;

        const addColor = (c) => {

            try {

                const col = new THREE.Color(c);

                const hex = '#' + col.getHexString();

                colorBins[hex] = (colorBins[hex] || 0) + 1;

            } catch (_) {}

        };



        root.traverse((o) => {

            if (!o) return;



            if (o.isLight) {

                features.lights.count++;

                const t = o.type || 'Light';

                features.lights.types[t] = (features.lights.types[t] || 0) + 1;

                if (features.lights_list.length < 40) {

                    features.lights_list.push({

                        type: t,

                        name: o.name || '',

                        path: this.getNodePath(o, root),

                    });

                }

            }



            if (!o.isMesh) return;

            features.mesh_count++;



            // Parties: mesh + material (compact) pour édition ciblée

            if (parts.length < capParts) {

                const path = this.getNodePath(o, root);

                const mat0 = Array.isArray(o.material) ? o.material[0] : o.material;

                const toHex = (c) => {

                    try {

                        const col = new THREE.Color(c);

                        return '#' + col.getHexString();

                    } catch (_) {

                        return null;

                    }

                };

                const maps = ['map','normalMap','roughnessMap','metalnessMap','emissiveMap','aoMap','alphaMap','bumpMap'];

                const mapsObj = {};

                const texInfo = {};

                if (mat0) {

                    for (const k of maps) {

                        mapsObj[k] = !!mat0[k];

                        try {

                            const t = mat0[k];

                            if (t && t.image && t.image.width && t.image.height) {

                                texInfo[k] = [t.image.width, t.image.height];

                            }

                        } catch (_) {}

                    }

                }

                parts.push({

                    part_id: `${parts.length}:${path}`,

                    name: o.name || '',

                    path,

                    material_name: (mat0 && mat0.name) ? String(mat0.name) : '',

                    material_type: (mat0 && mat0.type) ? String(mat0.type) : '',

                    colors: {

                        color: (mat0 && mat0.color) ? toHex(mat0.color) : null,

                        emissive: (mat0 && mat0.emissive) ? toHex(mat0.emissive) : null,

                    },

                    maps: mapsObj,

                    texture_info: Object.keys(texInfo).length ? texInfo : null,

                });

            }



            const g = o.geometry;

            if (g && g.attributes && g.attributes.position) {

                features.vertex_count += (g.attributes.position.count || 0);

                if (g.index && g.index.count) {

                    features.triangle_count += Math.floor((g.index.count || 0) / 3);

                } else {

                    features.triangle_count += Math.floor((g.attributes.position.count || 0) / 3);

                }

            }



            const matArr = Array.isArray(o.material) ? o.material : [o.material];

            for (const m of matArr) {

                if (!m) continue;

                mats.add(m);

                if (m.color) addColor(m.color);

                if (m.emissive) addColor(m.emissive);

                const maps = ['map','normalMap','roughnessMap','metalnessMap','emissiveMap','aoMap','alphaMap','bumpMap'];

                for (const k of maps) {

                    if (m[k]) tex.add(m[k]);

                }

            }

        });



        features.material_count = mats.size;

        features.texture_count = tex.size;

        const entries = Object.entries(colorBins).sort((a,b)=> (b[1]-a[1]));

        features.dominant_colors = entries.slice(0, 6).map(e => e[0]);

        features.parts = parts;

        features.vertex_count = Math.min(50_000_000, features.vertex_count);

        features.triangle_count = Math.min(50_000_000, features.triangle_count);

        return features;

    }



    async testGroqConfig() {

        const out = document.getElementById('ai-groq-admin-status');

        if (out) out.textContent = 'Test en cours...';

        const key = document.getElementById('ai-groq-key')?.value?.trim() || '';

        try {

            const fd = new FormData();

            fd.append('pp3_action', 'iagen_test_cfg');

            fd.append('groq_key', key);

            const r = await fetch(location.href, { method: 'POST', body: fd });

            const data = await r.json();

            if (!data.ok) throw new Error(data.error || 'Test échoué');

            if (out) out.textContent = '✅ Clé Groq valide';

        } catch (e) {

            if (out) out.textContent = '❌ ' + (e?.message || 'Erreur');

        }

    }



    async saveGroqConfig() {

        const out = document.getElementById('ai-groq-admin-status');

        if (out) out.textContent = 'Sauvegarde en cours...';



        const key = document.getElementById('ai-groq-key')?.value?.trim() || '';

        const model = document.getElementById('ai-groq-model')?.value || 'llama-3.3-70b-versatile';



        try {

            const fd = new FormData();

            fd.append('pp3_action', 'iagen_save_cfg');

            fd.append('groq_key', key);

            fd.append('groq_model', model);

            const r = await fetch(location.href, { method: 'POST', body: fd });

            const data = await r.json();

            if (!data.ok) throw new Error(data.error || 'Save échoué');

            if (out) out.textContent = '✅ Enregistré (cf iagenauto/cfgrq.js)';

        } catch (e) {

            if (out) out.textContent = '❌ ' + (e?.message || 'Erreur');

        }

    }



    async fetchGroqPlan(count, selections, animate, textures, cdn, mode = 'plan') {

        const fd = new FormData();

        fd.append('pp3_action', 'iagen_groq_plan');

        fd.append('count', String(count || 1));

        fd.append('mode', String(mode || 'plan'));

        fd.append('selections_json', JSON.stringify(selections || {}));

        fd.append('animate', animate ? '1' : '0');

        fd.append('textures', textures ? '1' : '0');

        fd.append('cdn_json', JSON.stringify(cdn || {}));



        const r = await fetch(location.href, { method: 'POST', body: fd });

        const data = await r.json();

        if (!data || !data.ok) {

            throw new Error(data?.error || 'Plan Groq indisponible');

        }

        this.lastGroqModel = data.model || null;



        const plan = data.plan;

        if (Array.isArray(plan)) return plan;

        return [plan];

    }



    addGeneratedItem(object3D, selections) {

        try {

            const list = document.getElementById('ai-gen-items');

            if (!list || !object3D) return;



            const id = object3D.userData?.id || object3D.uuid || ('ai_' + Date.now());

            this.generatedItems.set(id, object3D);



            const row = document.createElement('div');

            row.style.cssText = 'display:grid; grid-template-columns: 1fr auto auto; gap: 6px; align-items:center; padding: 8px; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);';



            const title = document.createElement('button');

            title.textContent = object3D.name || id;

            title.style.cssText = 'text-align:left; background:none; border:none; color:#ddd; cursor:pointer; font-weight: 700; overflow:hidden; text-overflow: ellipsis; white-space: nowrap;';

            title.addEventListener('click', (e) => {

                e.preventDefault();

                if (typeof selectObject === 'function') {

                    selectObject(object3D);

                }

            });



            const up = document.createElement('button');

            up.textContent = '👍';

            up.title = 'J\'aime';

            up.style.cssText = 'padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); background: rgba(46,204,113,0.18); color: #2ecc71; cursor:pointer; font-weight:700;';

            up.addEventListener('click', async (e) => {

                e.preventDefault();

                await this.saveFeedback(true, object3D, selections, row);

            });



            const down = document.createElement('button');

            down.textContent = '👎';

            down.title = 'Je n\'aime pas';

            down.style.cssText = 'padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); background: rgba(231,76,60,0.18); color: #e74c3c; cursor:pointer; font-weight:700;';

            down.addEventListener('click', async (e) => {

                e.preventDefault();

                await this.saveFeedback(false, object3D, selections, row);

            });



            row.appendChild(title);

            row.appendChild(up);

            row.appendChild(down);

            list.prepend(row);

        } catch (e) {

            console.warn('addGeneratedItem error', e);

        }

    }



    async saveFeedback(liked, object3D, selections, rowEl = null) {

        const status = document.getElementById('ai-status');

        try {

            // Si c'est un vrai objet 3D, on inclut aussi le plan/metadata (apprentissage plus utile)

            const iagen = object3D?.userData?.iagen || object3D?.iagen || null;

            const minimalObject = {

                name: object3D?.name || null,

                uuid: object3D?.uuid || null,

                userData: object3D?.userData || null,

                iagen: iagen,

            };

            const fd = new FormData();

            fd.append('pp3_action', 'iagen_save_feedback');

            fd.append('liked', liked ? '1' : '0');

            fd.append('selections_json', JSON.stringify(selections || {}));

            fd.append('object_json', JSON.stringify(minimalObject));

            const r = await fetch(location.href, { method: 'POST', body: fd });

            const data = await r.json();

            if (!data.ok) throw new Error(data.error || 'Feedback non sauvegardé');



            // Exigence: après like/dislike, l'item ne doit plus être visible

            if (rowEl && rowEl.parentElement) {

                try { rowEl.remove(); } catch (_) {}

            }



            if (status) {

                status.textContent = '✅ Feedback enregistré (apprentissage temps réel)';

                status.style.display = 'block';

                status.style.color = '#9b59b6';

                setTimeout(() => { status.style.display = 'none'; }, 1800);

            }

        } catch (e) {

            if (status) {

                status.textContent = '❌ Feedback: ' + (e?.message || 'Erreur');

                status.style.display = 'block';

                status.style.color = '#e74c3c';

                setTimeout(() => { status.style.display = 'none'; }, 2500);

            }

        }

    }



    async updateStats() {

        try {

            const stats = await this.ai.getStats();



            document.getElementById('ai-stat-count').textContent = stats.totalGenerations;

            document.getElementById('ai-stat-exp').textContent = stats.experiencePoints;

            document.getElementById('ai-stat-creativity').textContent =

                Math.round(stats.creativityLevel * 100) + '%';

            document.getElementById('ai-stat-styles').textContent = stats.stylesCount;

        } catch (error) {

            console.warn('Erreur mise à jour stats:', error);

        }

    }

}



// ============================================

// INTÉGRATION AVEC L'ÉDITEUR

// ============================================



let aiSystem = null;

let aiInterface = null;



// Initialiser l'IA quand l'éditeur est prêt

function initAIGenerativeSystem() {

    console.log('🚀 Initialisation du système IA générative...');



    // Créer le système IA

    aiSystem = new AIGenerative3D();



    // Créer l'interface

    aiInterface = new AIInterface(aiSystem);



    // Ajouter le bouton de lancement dans l'interface

    addAIToEditorInterface();



    console.log('✅ Système IA générative prêt!');

    console.log('🎯 Raccourci: Ctrl + Shift + G pour ouvrir');



    // IMPORTANT: ne rien générer automatiquement au chargement.

}



function addAIToEditorInterface() {

    // Trouver le bouton de rigging pour s'insérer après lui

    const rigBtn = document.getElementById('rig-btn');

    if (!rigBtn || !rigBtn.parentElement) {

        // Réessayer après un délai

        setTimeout(addAIToEditorInterface, 1000);

        return;

    }



    // Le conteneur parent (la grille d'icônes des outils)

    const toolbar = rigBtn.parentElement;



    // Créer le bouton IA

    const aiButton = document.createElement('button');

    aiButton.id = 'ai-launcher-btn';

    aiButton.className = 'icon-btn';

    aiButton.title = 'IA Générative 3D (Ctrl+Shift+G)';

    // Note: suppression des styles inline width/height forcés sur le SVG qui pourraient conflire

    aiButton.innerHTML = `

        <svg viewBox="0 0 24 24">

            <path fill="currentColor" d="M21 6h-2v9H5v2c0 .55.45 1 1 1h12l4 4V7c0-.55-.45-1-1-1zm-4 6V3c0-.55-.45-1-1-1H3c-.55 0-1 .45-1 1v14l4-4h10c.55 0 1-.45 1-1z"/>

        </svg>

        <span class="sr-only">IA Générative</span>

    `;



    // Style spécial pour l'IA (garder le gradient mais s'assurer de la taille)

    aiButton.style.background = 'linear-gradient(135deg, #4a9eff, #9b59b6)';

    aiButton.style.border = '1px solid rgba(255,255,255,0.3)';



    aiButton.addEventListener('click', (e) => {

        e.preventDefault();

        e.stopPropagation();

        if (aiInterface) {

            aiInterface.toggle();

        }

    });



    // Insérer juste APRÈS le bouton rig-btn

    if (rigBtn.nextSibling) {

        toolbar.insertBefore(aiButton, rigBtn.nextSibling);

    } else {

        toolbar.appendChild(aiButton);

    }



    // Ajouter aussi au panneau des formes

    const shapePanel = document.getElementById('shape-panel');

    if (shapePanel) {

        const btnGroup = shapePanel.querySelector('.btn-group');

        if (btnGroup) {

            const aiShapeBtn = document.createElement('button');

            aiShapeBtn.textContent = '🤖 IA Avancée';

            aiShapeBtn.setAttribute('data-shape', 'ai_advanced');

            aiShapeBtn.title = 'Création IA avancée avec apprentissage';

            aiShapeBtn.style.cssText = `

                background: linear-gradient(135deg, #4a9eff, #9b59b6);

                color: white;

                border: 2px solid rgba(255,255,255,0.3);

                padding: 12px 8px;

                border-radius: 8px;

                cursor: pointer;

                font-size: 14px;

                font-weight: bold;

                transition: all 0.3s;

                flex: 1;

                min-width: 120px;

                text-align: center;

                box-shadow: 0 4px 15px rgba(74, 158, 255, 0.3);

            `;



            aiShapeBtn.addEventListener('click', async () => {

                if (aiSystem) {

                    try {

                        const obj = await aiSystem.generateObject(

                            'objet IA avancé avec textures et animations'

                        );

                        if (typeof scene !== 'undefined' && scene) {

                            scene.add(obj);

                            if (typeof objects !== 'undefined') {

                                objects.push(obj);

                                if (typeof selectObject === 'function') {

                                    setTimeout(() => selectObject(obj), 100);

                                }

                            }

                        }

                    } catch (error) {

                        console.error('Erreur génération IA avancée:', error);

                        alert('Erreur lors de la génération IA: ' + error.message);

                    }

                }

            });



            btnGroup.appendChild(aiShapeBtn);

        }

    }

}



// Démarrer l'IA quand la page est chargée

if (document.readyState === 'loading') {

    document.addEventListener('DOMContentLoaded', function() {

        setTimeout(initAIGenerativeSystem, 2000);

    });

} else {

    setTimeout(initAIGenerativeSystem, 2000);

}



// Exporter les fonctions pour utilisation globale

window.AIGenerator = {

    generate: async (prompt) => {

        if (!aiSystem) return null;

        return await aiSystem.generateObject(prompt);

    },



    generateMultiple: async (count) => {

        if (!aiSystem) return [];

        return await aiSystem.generateFromLearning(count);

    },



    getStats: async () => {

        if (!aiSystem) return {};

        return await aiSystem.getStats();

    },



    toggleUI: () => {

        if (aiInterface) aiInterface.toggle();

    },



    // Méthodes utilitaires

    enhanceObject: (mesh) => {

        if (!mesh || !aiSystem) return mesh;



        // Améliorer un objet existant avec l'IA

        mesh.userData.aiEnhanced = true;

        mesh.userData.enhancedAt = new Date().toISOString();



        // Ajouter des animations IA s pas déjà présentes

        if (!mesh.userData.animation) {

            const patterns = aiSystem.knowledgeGraph.get('animation_patterns');

            const pattern = _.sample(Object.keys(patterns));

            mesh.userData.animation = { type: pattern, active: true };

        }



        // Améliorer le matériau

        if (mesh.material && mesh.material.isMeshStandardMaterial) {

            mesh.material.metalness += (Math.random() - 0.5) * 0.2;

            mesh.material.roughness += (Math.random() - 0.5) * 0.2;

            mesh.material.needsUpdate = true;

        }



        return mesh;

    }

};



console.log('🤖 Module IA générative chargé - Utilisez window.AIGenerator');

</script>



</body>

</html>

<?php

// Dossier de textures (créé automatiquement à côté de ce fichier)

$textureDir = __DIR__ . DIRECTORY_SEPARATOR . 'textureplan';

