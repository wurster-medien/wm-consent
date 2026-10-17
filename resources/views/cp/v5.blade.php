@extends('statamic::layout')
@section('title', 'Cookie-Banner')

{{-- Statamic 5: eingebaute Komponente <publish-form> (Statamic 6 nimmt Statamic\CP\PublishForm, siehe SettingsController). --}}

@section('content')
    <publish-form
        title="Cookie-Banner"
        action="{{ cp_route('wm-consent.update') }}"
        method="patch"
        :blueprint='@json($blueprint)'
        :meta='@json($meta)'
        :values='@json($values)'
    ></publish-form>
@stop
