<?php

namespace App\Services\Knowledge;

/**
 * Swapping Postgres full-text search for embeddings later is a one-class
 * change: implement this interface again and rebind it in the container.
 * Do not leak SQL, vectors or ranking details past this boundary.
 */
interface KnowledgeRetriever
{
    public function retrieve(RetrievalQuery $query): RetrievalResult;
}
