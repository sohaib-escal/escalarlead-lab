<?php

return [
    /*
     | What a lead must have before the commercial team can act on it.
     | Everything else is enrichment. Keep this list short — each required field
     | is a question the homeowner has to answer before anyone calls them back.
     */
    'required_fields' => ['first_name', 'last_name', 'postal_code', 'problem'],

    /*
     | Understand the problem before asking who they are.
     |
     | A homeowner who has just described mould in their bedroom and is asked
     | for their surname feels processed, not heard — and on this audience that
     | is where conversations die. So the agent earns the identity questions by
     | first showing it understood the problem.
     */
    'context_fields' => ['is_homeowner', 'property_type', 'problem_duration'],

    // How many context facts to gather before asking for name and postcode.
    'context_before_identity' => 2,

    'identity_fields' => ['first_name', 'last_name', 'postal_code'],

    /*
     | Nice to have, once the lead is already usable.
     */
    'enrichment_fields' => [
        'city', 'heating_system', 'energy_source', 'property_age',
        'estimated_bill', 'previous_work', 'availability',
    ],

    'reply' => [
        // A WhatsApp reply to a 65-year-old homeowner. Two short sentences.
        'max_chars' => 240,

        // A photo earns a little more room: say what is visible, give possible
        // causes in the plural, then ask the next question. Still not a wall.
        'max_chars_with_image' => 420,
        'max_questions' => 1,
    ],

    /*
     | The seam the WhatsApp gateway (zailer) calls. The agent knows nothing
     | about WhatsApp; the gateway knows nothing about qualification.
     */
    'api' => [
        // Shared secret for the HMAC signature. No secret, no API — the route
        // refuses rather than running unauthenticated.
        'secret' => env('AGENT_API_SECRET'),

        // How far a signed request may be out of date, in seconds. Narrow
        // enough to make a captured request useless, wide enough for clock drift.
        'tolerance' => 300,

        // Fetching an image the caller only gave us a URL for.
        'fetch_timeout' => 15,

        // Posting the finished reply back to the gateway.
        'callback_timeout' => 15,

        // Where to send replies when the caller does not name a callback itself.
        'callback_url' => env('AGENT_CALLBACK_URL'),
    ],

    'images' => [
        'max_per_message' => 3,
        'max_bytes' => 5 * 1024 * 1024,
    ],

    // Turns kept verbatim; everything older lives in the rolling summary.
    'history_turns' => 8,

    // Stop asking questions once we have this much — a conversation that keeps
    // going after it is useful is just friction.
    'max_enrichment_after_qualified' => 3,
];
