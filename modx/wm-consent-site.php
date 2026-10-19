<?php

/**
 * Einbau des Cookie-Banners in eine MODX-Seite (2 und 3) von der Kommandozeile, ohne Manager.
 * Liegt im Paket neben core/ und assets/ und wird als Benutzer der Seite ausgeführt:
 *
 *   php wm-consent-site.php --root=/home/kunde/kunde.de info
 *   php wm-consent-site.php --root=… install [--config=datei.json]   Dateien, Snippet, Plugin, Chunk wmConsentConfig
 *   php wm-consent-site.php --root=… head [--only=chunk:START,…] [--apply]  [[!wmConsent]] in jeden <head> (ohne --apply nur Vorschau; Mail-Vorlagen auslassen!)
 *   php wm-consent-site.php --root=… get chunk|template NAME          Inhalt ausgeben (statische Elemente aus der Datei)
 *   php wm-consent-site.php --root=… put chunk|template NAME DATEI    Inhalt ersetzen (DB und ggf. statische Datei)
 *   php wm-consent-site.php --root=… grep MUSTER                      Muster (Regex, ohne Begrenzer) in Chunks/Templates suchen
 *   php wm-consent-site.php --root=… restore SICHERUNG [chunk|template NAME]   aus einer Sicherung zurück
 *   php wm-consent-site.php --root=… uninstall                        Snippet, Plugin, Chunk und alle [[!wmConsent]] entfernen
 *   php wm-consent-site.php --root=… clean [--apply]                  CookieFirst und altes Universal Analytics entfernen
 *   php wm-consent-site.php --root=… auto --config=datei.json        install, head --only=chunk:START, clean in einem Lauf
 *   php wm-consent-site.php --root=… clear-cache
 *
 * Vor jeder Änderung sichert es alle Chunks und Templates (mit statischen Dateien) nach ~/wm-consent-backup/<zeit>/.
 */

/* Ohne SSH (z. B. Hosting beim Kunden): eine kleine Datei setzt WM_CONSENT_WEB und $argv und bindet das Skript ein. */
if (PHP_SAPI !== 'cli' && ! defined('WM_CONSENT_WEB')) {
    exit(1);
}

if (! defined('STDERR')) {
    define('STDERR', fopen('php://output', 'w'));
}

$args = [];
$options = [];

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
        $options[$m[1]] = $m[2] ?? true;
    } else {
        $args[] = $arg;
    }
}

$root = rtrim((string) ($options['root'] ?? getcwd()), '/');
$command = $args[0] ?? 'info';

if (! is_file($root.'/config.core.php')) {
    fwrite(STDERR, "Keine MODX-Seite unter $root (config.core.php fehlt).\n");
    exit(1);
}

define('MODX_API_MODE', true);
require $root.'/config.core.php';

$v3 = is_file(MODX_CORE_PATH.'src/Revolution/modX.php');

if ($v3) {
    require MODX_CORE_PATH.'vendor/autoload.php';
    $modx = new \MODX\Revolution\modX;
} else {
    require MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx = new modX;
}

$modx->initialize('mgr');
$modx->getService('error', 'error.modError');
$modx->setLogLevel(1);
$modx->setLogTarget('ECHO');

/** Klassenname für MODX 2 bzw. 3. */
$cls = function (string $name) use ($v3): string {
    return $v3 ? 'MODX\\Revolution\\'.$name : $name;
};

$typeClass = function (string $type) use ($cls): string {
    if ($type !== 'chunk' && $type !== 'template') {
        throw new InvalidArgumentException("Art $type unbekannt (chunk oder template).");
    }

    return $cls($type === 'chunk' ? 'modChunk' : 'modTemplate');
};

$nameField = function (string $type): string {
    return $type === 'template' ? 'templatename' : 'name';
};

/** Inhalt eines Elements, bei statischen aus der Datei. */
$contentOf = function ($element): string {
    if ($element->get('static')) {
        $file = $element->getSourceFile();

        if ($file && is_file($file)) {
            return (string) file_get_contents($file);
        }
    }

    return (string) $element->get('content');
};

$writeContent = function ($element, string $content): void {
    if ($element->get('static')) {
        $file = $element->getSourceFile();

        if (! $file || ! is_file($file) || ! is_writable($file)) {
            throw new RuntimeException('Statische Datei nicht schreibbar: '.$file);
        }

        file_put_contents($file, $content);
    }

    /* Direkt in die Tabelle: das Element ist nur mit einigen Spalten geladen (siehe $query) und lässt sich nicht speichern. */
    $modx = $element->xpdo;
    $isTemplate = $element->get('templatename') !== null;
    $statement = $modx->prepare('UPDATE '.$modx->getTableName($element->_class).' SET '.($isTemplate ? 'content' : 'snippet').' = ? WHERE id = ?');

    if (! $statement || ! $statement->execute([$content, $element->get('id')])) {
        throw new RuntimeException('Speichern fehlgeschlagen.');
    }

    $element->set($isTemplate ? 'content' : 'snippet', $content);
};

/**
 * Chunks bzw. Templates nur mit den Spalten, die das Skript braucht (halb aktualisierte Seiten haben nicht jede
 * Spalte, die das Modell des Kerns kennt, z. B. preview_file).
 *
 * @param  array<string, mixed>  $criteria
 */
$query = function (string $type, array $criteria = []) use ($modx, $typeClass, $nameField): array {
    $class = $typeClass($type);
    $q = $modx->newQuery($class, $criteria);
    $q->select($modx->getSelectColumns($class, $type === 'template' ? 'modTemplate' : 'modChunk', '', ['id', $nameField($type), $type === 'template' ? 'content' : 'snippet', 'static', 'static_file', 'source', 'category', 'description']));

    return array_values($modx->getCollection($class, $q));
};

$elements = function () use ($query): array {
    $list = [];

    foreach (['chunk', 'template'] as $type) {
        foreach ($query($type) as $element) {
            $list[] = [$type, $element];
        }
    }

    return $list;
};

$backupDir = null;

$backup = function () use (&$backupDir, $elements, $contentOf, $nameField): string {
    if ($backupDir) {
        return $backupDir;
    }

    $home = getenv('HOME') ?: dirname(__DIR__);
    $backupDir = $home.'/wm-consent-backup/'.date('Ymd-His').'-'.getmypid();
    @mkdir($backupDir, 0700, true);
    $data = [];

    foreach ($elements() as [$type, $element]) {
        $data[] = [
            'type' => $type,
            'id' => $element->get('id'),
            'name' => $element->get($nameField($type)),
            'static' => (bool) $element->get('static'),
            'static_file' => $element->get('static') ? $element->getSourceFile() : null,
            'content' => $contentOf($element),
        ];
    }

    file_put_contents($backupDir.'/elements.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo "Sicherung: $backupDir\n";

    return $backupDir;
};

$find = function (string $type, string $name) use ($query, $nameField) {
    $element = $query($type, [$nameField($type) => $name])[0] ?? null;

    if (! $element) {
        throw new RuntimeException("$type $name nicht gefunden.");
    }

    return $element;
};

$clearCache = function () use ($modx): void {
    $modx->cacheManager->refresh();
    echo "Cache geleert.\n";
};

/** Fügt [[!wmConsent]] nach dem Charset (sonst direkt nach <head>) ein; null, wenn kein <head> oder schon drin. */
$insertHead = function (string $content): ?string {
    if (strpos($content, '[[!wmConsent') !== false ||! preg_match('/<head\b[^>]*>/i', $content, $head, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    $eol = strpos($content, "\r\n") !== false ? "\r\n" : "\n";
    $start = $head[0][1] + strlen($head[0][0]);
    $position = $start;

    if (preg_match('/<meta\b[^>]*(charset|Content-Type)[^>]*>/i', $content, $meta, PREG_OFFSET_CAPTURE, $start)) {
        $headEnd = stripos($content, '</head>', $start);

        if ($headEnd === false || $meta[0][1] < $headEnd) {
            $position = $meta[0][1] + strlen($meta[0][0]);
        }
    }

    return substr($content, 0, $position).$eol.'[[!wmConsent]]'.substr($content, $position);
};

$bannerPattern = '/cookiefirst|cookiebot|consentmanager|cookie-?script|ccm19|borlabs|usercentrics|cookieyes|klaro|cookie-?byte|cookieconsent|cookie-?notice|cookie-?banner|cookie-?hinweis|googletagmanager|gtag\(|google-analytics|analytics\.js|fbq\(|_paq|matomo|etracker/i';

/**
 * Entfernt das Skript von CookieFirst und altes Universal Analytics (analytics.js, ga.js, gtag mit UA-… oder dem
 * Platzhalter GA_TRACKING_ID); GA4 (G-…) bleibt, das übernimmt der Banner über google_analytics_id.
 */
$clean = function (string $content): string {
    $script = '[ \t]*<script\b[^>]*>(?:(?!<\/script>).)*?<\/script>';
    $content = (string) preg_replace('#\R?[ \t]*<script\b[^>]*src="[^"]*consent\.cookiefirst\.com[^"]*"[^>]*>\s*</script>#i', '', $content);
    $content = (string) preg_replace('#\R?[ \t]*<script\b[^>]*src="[^"]*gtag/js\?id=(UA-|GA_TRACKING_ID)[^"]*"[^>]*>\s*(</script>)?#i', '', $content);

    return (string) preg_replace_callback('#\R?'.$script.'#is', function (array $match): string {
        $old = preg_match('#google-analytics\.com/(analytics|ga)\.js|_gaq\.push|gtag\(\s*.config.\s*,\s*.(UA-|GA_TRACKING_ID)#i', $match[0]) === 1;

        return $old && preg_match('#[\'"]G-[A-Z0-9]+#', $match[0]) !== 1 ? '' : $match[0];
    }, $content);
};

/* auto: alles für eine Seite wie die bisherigen (Kopf im Chunk START), z. B. über die Datei für den Browser. */
if ($command === 'auto') {
    $options['apply'] = true;
    $options['only'] = $options['only'] ?? 'chunk:START';
}

try {
    foreach ($command === 'auto' ? ['install', 'head', 'clean'] : [$command] as $command) {
        switch ($command) {
            case 'info':
                echo 'MODX '.$modx->getVersionData()['full_version'].' (PHP '.PHP_VERSION.")\n";
                echo 'site_url '.$modx->getOption('site_url').', base_path '.MODX_BASE_PATH."\n\n";

                echo "Kontexte:\n";
                foreach ($modx->getCollection($cls('modContext'), ['key:!=' => 'mgr']) as $context) {
                    $settings = [];
                    foreach ($modx->getCollection($cls('modContextSetting'), ['context_key' => $context->get('key'), 'key:IN' => ['site_url', 'cultureKey', 'site_start', 'http_host']]) as $setting) {
                        $settings[] = $setting->get('key').'='.$setting->get('value');
                    }
                    echo '  '.$context->get('key').': '.implode(', ', $settings)."\n";
                }

                echo "\nPlugins auf OnWebPagePrerender/OnWebPageComplete:\n";
                foreach ($modx->getCollection($cls('modPluginEvent'), ['event:IN' => ['OnWebPagePrerender', 'OnWebPageComplete', 'OnLoadWebDocument']]) as $pluginEvent) {
                    $plugin = $modx->getObject($cls('modPlugin'), $pluginEvent->get('pluginid'));
                    if ($plugin) {
                        echo '  '.$pluginEvent->get('event').': '.$plugin->get('name').($plugin->get('disabled') ? ' (aus)' : '')."\n";
                    }
                }

                $installed = $modx->getObject($cls('modSnippet'), ['name' => 'wmConsent']) ? 'ja' : 'nein';
                echo "\nwmConsent installiert: $installed\n";

                echo "\nTemplates:\n";
                foreach ($query('template') as $template) {
                    echo '  '.$template->get('id').' '.$template->get('templatename').($template->get('static') ? ' [statisch '.$template->getSourceFile().']' : '')."\n";
                }

                echo "\nElemente mit <head>, </body> oder Treffern (Banner, Tracker, Datenschutz-Link):\n";
                foreach ($elements() as [$type, $element]) {
                    $content = $contentOf($element);
                    $lines = [];
                    foreach (preg_split('/\R/', $content) as $number => $line) {
                        if (preg_match('/<head\b|<\/head>|<\/body>|\[\[!?wmConsent/i', $line) || preg_match($bannerPattern, $line) || preg_match('/datenschutz|privacy|impressum/i', $line) && strpos($line, '<a') !== false) {
                            $lines[] = '    '.($number + 1).': '.mb_strimwidth(trim($line), 0, 220, '…');
                        }
                    }
                    if ($lines) {
                        echo '  '.$type.' '.$element->get($nameField($type)).($element->get('static') ? ' [statisch '.$element->getSourceFile().']' : '')."\n".implode("\n", $lines)."\n";
                    }
                }
                break;

            case 'install':
                $backup();
                $package = __DIR__;

                foreach (['core/components/wm-consent' => MODX_CORE_PATH.'components/wm-consent', 'assets/components/wm-consent' => $modx->getOption('assets_path').'components/wm-consent'] as $from => $to) {
                    $copy = function (string $source, string $target) use (&$copy): void {
                        if (is_dir($source)) {
                            @mkdir($target, 0755, true);
                            foreach (scandir($source) as $entry) {
                                if ($entry !== '.' && $entry !== '..') {
                                    $copy($source.'/'.$entry, $target.'/'.$entry);
                                }
                            }

                            return;
                        }
                        copy($source, $target);
                    };
                    $copy($package.'/'.$from, $to);
                    echo "Dateien: $to\n";
                }

                $category = $modx->getObject($cls('modCategory'), ['category' => 'wmConsent']);
                if (! $category) {
                    $category = $modx->newObject($cls('modCategory'));
                    $category->set('category', 'wmConsent');
                    $category->save();
                }

                $snippet = $modx->getObject($cls('modSnippet'), ['name' => 'wmConsent']) ?: $modx->newObject($cls('modSnippet'));
                $snippet->fromArray([
                    'name' => 'wmConsent',
                    'description' => 'Cookie-Banner von Wurster Medien, ungecacht in den <head>: [[!wmConsent]]',
                    'category' => $category->get('id'),
                    'snippet' => "return include MODX_CORE_PATH.'components/wm-consent/elements/snippets/wmconsent.snippet.php';",
                    'static' => 0,
                ]);
                $snippet->save();
                echo "Snippet wmConsent.\n";

                $plugin = $modx->getObject($cls('modPlugin'), ['name' => 'wmConsentFilter']) ?: $modx->newObject($cls('modPlugin'));
                $plugin->fromArray([
                    'name' => 'wmConsentFilter',
                    'description' => 'Blockt Tracker und Einbettungen bis zur Zustimmung (Cookie-Banner von Wurster Medien)',
                    'category' => $category->get('id'),
                    'plugincode' => "include MODX_CORE_PATH.'components/wm-consent/elements/plugins/wmconsent.plugin.php';",
                    'static' => 0,
                    'disabled' => 0,
                ]);
                $plugin->save();

                if (! $modx->getObject($cls('modPluginEvent'), ['pluginid' => $plugin->get('id'), 'event' => 'OnWebPagePrerender'])) {
                    $event = $modx->newObject($cls('modPluginEvent'));
                    $event->set('pluginid', $plugin->get('id'));
                    $event->set('event', 'OnWebPagePrerender');
                    $event->set('priority', 0);
                    $event->set('propertyset', 0);
                    $event->save();
                }
                echo "Plugin wmConsentFilter (OnWebPagePrerender).\n";

                $chunk = $modx->getObject($cls('modChunk'), ['name' => 'wmConsentConfig']);
                if (isset($options['config'])) {
                    $json = (string) file_get_contents((string) $options['config']);
                    if (! is_array(json_decode($json, true))) {
                        throw new RuntimeException('Einstellungen sind kein gültiges JSON.');
                    }
                    $chunk = $chunk ?: $modx->newObject($cls('modChunk'));
                    $chunk->fromArray(['name' => 'wmConsentConfig', 'description' => 'Einstellungen des Cookie-Banners (JSON)', 'category' => $category->get('id'), 'static' => 0]);
                    $chunk->set('snippet', $json);
                    $chunk->save();
                    echo "Chunk wmConsentConfig gesetzt.\n";
                } elseif (! $chunk) {
                    echo "Hinweis: Chunk wmConsentConfig fehlt noch (--config=…), Banner läuft mit Standardwerten.\n";
                }

                $clearCache();
                break;

            case 'head':
                $changes = [];
                $only = isset($options['only']) ? explode(',', (string) $options['only']) : null;
                foreach ($elements() as [$type, $element]) {
                    if ($only !== null && ! in_array($type.':'.$element->get($nameField($type)), $only, true)) {
                        continue;
                    }
                    $new = $insertHead($contentOf($element));
                    if ($new !== null) {
                        $changes[] = [$type, $element, $new];
                        echo ($options['apply'] ?? false ? 'Ändere ' : 'Würde ändern: ').$type.' '.$element->get($nameField($type))."\n";
                    }
                }
                if (! $changes) {
                    echo "Nichts zu tun (kein <head> ohne [[!wmConsent]]).\n";
                }
                if (($options['apply'] ?? false) && $changes) {
                    $backup();
                    foreach ($changes as [$type, $element, $new]) {
                        $writeContent($element, $new);
                    }
                    $clearCache();
                }
                break;

            case 'get':
                echo $contentOf($find($args[1] ?? '', $args[2] ?? ''));
                break;

            case 'put':
                $element = $find($args[1] ?? '', $args[2] ?? '');
                $file = $args[3] ?? '';
                if (! is_file($file)) {
                    throw new RuntimeException("Datei $file fehlt.");
                }
                $backup();
                $writeContent($element, (string) file_get_contents($file));
                echo 'Gespeichert: '.($args[1]).' '.($args[2])."\n";
                $clearCache();
                break;

            case 'grep':
                $pattern = '/'.str_replace('/', '\/', $args[1] ?? '').'/i';
                foreach ($elements() as [$type, $element]) {
                    foreach (preg_split('/\R/', $contentOf($element)) as $number => $line) {
                        if (preg_match($pattern, $line)) {
                            echo $type.' '.$element->get($nameField($type)).':'.($number + 1).': '.mb_strimwidth(trim($line), 0, 300, '…')."\n";
                        }
                    }
                }
                break;

            case 'restore':
                $data = json_decode((string) file_get_contents(rtrim($args[1] ?? '', '/').'/elements.json'), true);
                if (! is_array($data)) {
                    throw new RuntimeException('Sicherung nicht lesbar.');
                }
                $backup();
                foreach ($data as $item) {
                    if (isset($args[2]) && ($item['type'] !== $args[2] || $item['name'] !== ($args[3] ?? ''))) {
                        continue;
                    }
                    $element = $query($item['type'], ['id' => $item['id']])[0] ?? null;
                    if ($element && $contentOf($element) !== $item['content']) {
                        $writeContent($element, $item['content']);
                        echo 'Zurück: '.$item['type'].' '.$item['name']."\n";
                    }
                }
                $clearCache();
                break;

            case 'uninstall':
                $backup();
                foreach ($elements() as [$type, $element]) {
                    $content = $contentOf($element);
                    $new = preg_replace('/\R?\[\[!wmConsent\]\]/', '', $content);
                    if ($new !== $content) {
                        $writeContent($element, $new);
                        echo 'Entfernt aus '.$type.' '.$element->get($nameField($type))."\n";
                    }
                }
                if ($plugin = $modx->getObject($cls('modPlugin'), ['name' => 'wmConsentFilter'])) {
                    $modx->removeCollection($cls('modPluginEvent'), ['pluginid' => $plugin->get('id')]);
                    $plugin->remove();
                }
                if ($snippet = $modx->getObject($cls('modSnippet'), ['name' => 'wmConsent'])) {
                    $snippet->remove();
                }
                echo "Snippet und Plugin entfernt (Chunk wmConsentConfig und Dateien bleiben).\n";
                $clearCache();
                break;

            case 'clean':
                $changes = [];
                foreach ($elements() as [$type, $element]) {
                    $content = $contentOf($element);
                    $new = $clean($content);
                    if ($new !== $content) {
                        $changes[] = [$type, $element, $new];
                        echo ($options['apply'] ?? false ? 'Bereinige ' : 'Würde bereinigen: ').$type.' '.$element->get($nameField($type))."\n";
                    }
                }
                if (! $changes) {
                    echo "Kein CookieFirst und kein altes Analytics gefunden.\n";
                }
                if (($options['apply'] ?? false) && $changes) {
                    $backup();
                    foreach ($changes as [$type, $element, $new]) {
                        $writeContent($element, $new);
                    }
                    $clearCache();
                }
                break;

            case 'clear-cache':
                $clearCache();
                break;

            default:
                throw new InvalidArgumentException("Befehl $command unbekannt.");
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Fehler: '.$e->getMessage()."\n");
    exit(1);
}
