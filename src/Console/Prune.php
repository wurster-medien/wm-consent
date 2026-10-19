<?php

namespace WursterMedien\Consent\Console;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use WursterMedien\Consent\Settings;

/**
 * Gespeicherte Werte (content/wm-consent.yaml) entfernen, die der Voreinstellung aus Config und Agentur gleichen, damit
 * spätere Vorgaben der Agentur wieder greifen (z. B. nach dem Ausrollen von 1.2 auf Seiten mit gespeicherter Position).
 */
class Prune extends Command
{
    use RunsInPlease;

    protected $signature = 'wm-consent:prune';

    protected $description = 'Cookie-Banner: gespeicherte Werte entfernen, die der Voreinstellung gleichen';

    public function handle(Settings $settings): int
    {
        $removed = $settings->prune();
        $this->info($removed === 0 ? 'Nichts zu entfernen.' : ($removed === 1 ? 'Ein Wert entfernt.' : "{$removed} Werte entfernt."));

        return self::SUCCESS;
    }
}
