<?php

return [
    /*
     | Problem families. These are the tag the conversation state filters on
     | before anything is ranked — the single biggest lever on retrieval quality.
     | Keep them coarse: a homeowner's opening message rarely distinguishes more
     | finely than this.
     */
    'families' => [
        'humidite' => 'Humidité, moisissures, condensation',
        'infiltration' => 'Infiltration',
        'isolation' => 'Isolation, maison froide',
        'chauffage' => 'Chauffage',
        'facture' => 'Factures d\'énergie',
        'fenetres' => 'Fenêtres, courants d\'air, bruit',
        'solaire' => 'Solaire',
        'general' => 'Général',
    ],

    'content_types' => [
        'education' => 'Explication',
        'faq' => 'Question fréquente',
        'objection' => 'Objection',
        'process' => 'Déroulement',
        'coverage' => 'Zone d\'intervention',
    ],

    /*
     | Retrieval budget. Three passages is enough to ground a 25-word reply, and
     | the token cap is what actually protects the per-turn cost — a passage that
     | grows in the admin must not silently grow every prompt.
     */
    'retrieval' => [
        'max_passages' => 3,
        'max_tokens' => 700,

        // Below this ts_rank the match is noise; return nothing rather than
        // grounding a reply in an unrelated passage.
        'min_rank' => 0.02,

        // Trigram similarity floor for the typo fallback.
        'min_similarity' => 0.3,

        // How much the conversation's known problem family lifts a passage of
        // that family above a general one.
        'family_boost' => 1.5,

        // Keep only passages scoring at least this fraction of the best match.
        'relative_floor' => 0.5,

        // French runs ~3.6 characters per token. Deliberately rough: this is a
        // budget guard, not an accounting figure.
        'chars_per_token' => 3.6,
    ],
];
