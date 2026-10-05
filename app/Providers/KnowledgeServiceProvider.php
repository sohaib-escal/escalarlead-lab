<?php

namespace App\Providers;

use App\Services\Knowledge\KnowledgeRetriever;
use App\Services\Knowledge\PostgresKnowledgeRetriever;
use Illuminate\Support\ServiceProvider;

class KnowledgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Rebind this one line to move to embeddings later.
        $this->app->bind(KnowledgeRetriever::class, PostgresKnowledgeRetriever::class);
    }
}
