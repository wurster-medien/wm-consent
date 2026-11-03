<?php

namespace WursterMedien\Consent\Console;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use WursterMedien\Consent\Content\ContentMap;
use WursterMedien\Consent\Content\DemoStand;

/**
 * „Demo-Stand merken“ (Simon, 3.11.2026): vor der Präsentation, solange nur Demo- und Branding-Inhalte von uns auf der
 * Seite stehen. Danach gilt jeder Text, der sich nicht ändert, als Platzhalter; Bilder aus einem Ordner demo/ gelten
 * immer als Platzhalter. --vergessen löscht den gemerkten Stand (keine Platzhalter mehr aus Texten).
 */
class RememberDemo extends Command
{
    use RunsInPlease;

    protected $signature = 'wm-consent:demo-stand {--vergessen : Gemerkten Demo-Stand löschen}';

    protected $description = 'Kundenbereich: Demo-Stand der Texte merken (Platzhalter zum Prüfen)';

    public function handle(ContentMap $content, DemoStand $demo): int
    {
        if ($this->option('vergessen')) {
            $demo->forget();
            $this->info('Demo-Stand gelöscht.');

            return self::SUCCESS;
        }

        $prints = $content->allTextFingerprints();
        $demo->remember($prints);
        $this->info(count($prints).' Texte als Demo-Stand gemerkt.');

        return self::SUCCESS;
    }
}
