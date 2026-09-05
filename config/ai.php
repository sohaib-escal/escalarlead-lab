<?php

return [
    /*
     | LLM providers that can turn a creative idea into a generation prompt.
     | Which concrete models are offered is admin-managed (`ai_models` table);
     | this only describes how to talk to each provider.
     */
    'providers' => [
        'anthropic' => [
            'label' => 'Claude (Anthropic)',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'docs' => 'https://docs.anthropic.com',
        ],
        'gemini' => [
            'label' => 'Gemini (Google)',
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'docs' => 'https://ai.google.dev/gemini-api/docs',
        ],
        'openai' => [
            'label' => 'OpenAI',
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'docs' => 'https://platform.openai.com/docs',
        ],
    ],

    'max_output_tokens' => 4000,

    'timeout' => 120,
];
