<?php

return [
    /*
     | Minimum spend before the automatic performance rating means anything.
     | Below this a creative is still "in test".
     */
    'rating_min_spend' => 100,

    /*
     | Cost per qualified lead (EUR) thresholds used to rate a creative.
     | Cheap leads are not automatically good leads, so the rating is driven by
     | the qualified lead cost rather than the raw CPL.
     */
    'rating_thresholds' => [
        'winner' => 12,
        'promising' => 18,
        'average' => 30,
    ],

    'formats' => [
        'static_image' => 'Image statique',
        'video' => 'Vidéo',
        'carousel' => 'Carrousel',
        'ugc' => 'UGC',
        'motion' => 'Motion design',
        'other' => 'Autre',
    ],

    'ratings' => [
        'winner' => 'WINNER',
        'promising' => 'PROMISING',
        'average' => 'AVERAGE',
        'poor' => 'POOR',
        'testing' => 'IN TEST',
        'no_data' => 'NO DATA',
    ],

    /*
     | Default ordering of the creative tree axes (parameter category slugs).
     | Users can reorder them in the UI; admins can change which categories are
     | available through the `in_tree` flag on parameter_categories.
     */
    'default_tree_axes' => ['product', 'specific-problem', 'gender', 'age', 'motivation', 'channel'],
];
