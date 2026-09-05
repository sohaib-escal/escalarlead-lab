<?php

return [
    /*
     | Creative generation providers.
     |
     | google_veo  — Veo through the Gemini API. This is the officially supported
     |               programmatic path and is fully implemented here.
     | google_flow — flow.google, the consumer app built on Veo. It has no public
     |               generation API, so the integration is an explicit manual
     |               handoff: the app hands over the validated prompt and records
     |               the asset the admin brings back. Nothing is ever faked.
     */
    'generation' => [
        'google_veo' => [
            'label' => 'Google Veo (Gemini API)',
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model' => env('VEO_MODEL', 'veo-3.1-generate-preview'),
            'timeout' => 120,
        ],
        'google_flow' => [
            'label' => 'Google Flow',
            'url' => env('GOOGLE_FLOW_URL', 'https://flow.google'),
        ],
    ],

    /*
     | Where performance numbers come from. Manual entry is the only implemented
     | source today; the Meta provider is a declared, unimplemented placeholder so
     | the rest of the app can already be written against the interface.
     */
    'performance' => [
        'manual' => ['label' => 'Saisie manuelle'],
        'meta' => ['label' => 'Meta Ads', 'planned' => true],
    ],
];
