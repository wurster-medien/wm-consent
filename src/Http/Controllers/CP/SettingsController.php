<?php

namespace WursterMedien\Consent\Http\Controllers\CP;

use Illuminate\Http\Request;
use Statamic\CP\PublishForm;
use Statamic\Http\Controllers\CP\CpController;
use WursterMedien\Consent\Settings;
use WursterMedien\Consent\Support\StatamicVersion;

/**
 * Seite „Cookie-Banner“ im Control Panel (Werkzeuge, Recht „manage wm consent“): ein Formular aus dem Blueprint von
 * Settings. Statamic 6 über Statamic\CP\PublishForm (Inertia), Statamic 5 über die eingebaute Vue-Komponente
 * <publish-form> (View cp.v5). Gespeichert wird in beiden Fällen gleich.
 */
class SettingsController extends CpController
{
    public function edit(Settings $settings, StatamicVersion $statamic): mixed
    {
        $this->authorize('manage wm consent');

        if ($statamic->major() >= 6) {
            return PublishForm::make($settings->blueprint())
                ->title('Cookie-Banner')
                ->icon('lock')
                ->values($settings->formValues())
                ->submittingTo(cp_route('wm-consent.update'));
        }

        $fields = $settings->blueprint()->fields()->addValues($settings->formValues())->preProcess();

        return view('wm-consent::cp.v5', [
            'blueprint' => $settings->blueprint()->toPublishArray(),
            'values' => $fields->values(),
            'meta' => $fields->meta(),
        ]);
    }

    /**
     * @return array{message: string}
     */
    public function update(Request $request, Settings $settings): array
    {
        $this->authorize('manage wm consent');

        $fields = $settings->blueprint()->fields()->addValues($request->all());
        $fields->validate();
        $settings->saveForm($fields->process()->values()->all());

        return ['message' => 'Gespeichert'];
    }
}
