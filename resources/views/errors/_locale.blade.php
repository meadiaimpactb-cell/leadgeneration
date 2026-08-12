@php
    /*
     * Which language this visitor was reading.
     *
     * A 404 never matches the locale-prefixed route group, so SetLocale never
     * runs and the app is still on its default — which served an Arabic error
     * page to anyone who mistyped an /en address. The first URL segment is the
     * only thing left that knows.
     *
     * Included at the TOP of each error view rather than in the layout because
     * `@section('heading', __('…'))` is evaluated in the child, before the
     * layout renders — setting the locale there would be too late for the very
     * strings it is meant to choose.
     */
    $segment = (string) request()->segment(1);

    if (array_key_exists($segment, config('site.locales'))) {
        app()->setLocale($segment);
    }
@endphp
