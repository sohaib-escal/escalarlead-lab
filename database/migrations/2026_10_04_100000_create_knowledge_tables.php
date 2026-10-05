<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The WhatsApp agent's knowledge system.
 *
 * Two tables, deliberately different in kind:
 *
 *  - `knowledge_passages` is retrieved. Educational content, FAQ, objection
 *    handling, process — open-ended, long-tail, worth ranking.
 *  - `agent_guardrails` is never retrieved. Persona, tone and the never-claim
 *    list go into the system prompt on every single turn, because a retrieval
 *    that misses a compliance rule is an incident, not a quality dip.
 *
 * Retrieval is Postgres French full-text search rather than embeddings: the
 * corpus is ~100 short passages, so ranking quality is not the bottleneck, and
 * this keeps homeowner messages away from a third-party embedding API.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // `unaccent()` is only STABLE, so Postgres refuses it inside a generated
        // column or an index. This IMMUTABLE wrapper pins the dictionary and makes
        // it usable in both. Without it the search_vector column below fails.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION f_unaccent(text)
            RETURNS text
            LANGUAGE sql
            IMMUTABLE PARALLEL SAFE STRICT
            AS $$ SELECT public.unaccent('public.unaccent', $1) $$
        SQL);

        Schema::create('knowledge_passages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('body');

            // Extra retrieval surface: the words a homeowner actually types
            // ("buée", "taches noires", "ça sent le renfermé") which may not
            // appear in a well-written body.
            $table->text('keywords')->nullable();

            // Tag-first filtering happens on these before anything is ranked.
            $table->string('problem_family')->nullable();   // humidite | chauffage | ...
            $table->string('content_type')->default('education'); // education|faq|objection|process|coverage
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audience')->default('homeowner');

            // What the agent must not say when using this passage.
            $table->text('never_claim')->nullable();

            $table->string('source')->nullable(); // where the content came from, for the admin
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['problem_family', 'content_type', 'is_active']);
        });

        // Weighted so a title match outranks a passing mention in the body.
        DB::statement(<<<'SQL'
            ALTER TABLE knowledge_passages
            ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('french', f_unaccent(coalesce(title, ''))), 'A') ||
                setweight(to_tsvector('french', f_unaccent(coalesce(keywords, ''))), 'B') ||
                setweight(to_tsvector('french', f_unaccent(coalesce(body, ''))), 'C')
            ) STORED
        SQL);

        DB::statement('CREATE INDEX knowledge_passages_search_idx ON knowledge_passages USING GIN (search_vector)');

        // Trigram fallback for heavy typos, where stemming gives up.
        DB::statement(<<<'SQL'
            CREATE INDEX knowledge_passages_trgm_idx
            ON knowledge_passages
            USING GIN (f_unaccent(title || ' ' || coalesce(keywords, '')) gin_trgm_ops)
        SQL);

        Schema::create('agent_guardrails', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('kind')->default('rule'); // persona | tone | rule | never_claim | disclosure
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_guardrails');
        Schema::dropIfExists('knowledge_passages');
        DB::statement('DROP FUNCTION IF EXISTS f_unaccent(text)');
    }
};
