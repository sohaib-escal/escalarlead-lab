<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversations and the leads they produce.
 *
 * Named for the conversation, not for WhatsApp: the agent must run the same way
 * whatever carries the messages. `channel` says where a conversation came from
 * ('test' for the internal console, 'whatsapp' once the transport is wired), and
 * nothing in the agent branches on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->default('whatsapp');     // whatsapp | test
            $table->string('external_id')->nullable();          // wa_id, or a console session
            $table->string('status')->default('active');        // active | awaiting_human | closed
            $table->string('locale', 8)->default('fr');

            // What the conversation turned out to be about — the single
            // strongest input to knowledge retrieval.
            $table->string('problem_family')->nullable();

            $table->text('summary')->nullable();                // rolling, not regenerated every turn
            $table->timestamp('consent_at')->nullable();

            // Acquisition context. Resolved where possible, raw payload always kept.
            $table->foreignId('creative_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->json('referral')->nullable();

            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status']);
            $table->unique(['channel', 'external_id']);
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction');                        // inbound | outbound
            $table->string('external_id')->nullable()->unique(); // provider message id — idempotency key
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->json('raw')->nullable();
            $table->string('status')->default('received');

            // Why the agent said what it said: retrieved passages, tokens, model.
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone_e164')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('department', 3)->nullable();        // derived from the postal code

            $table->string('qualification_status')->default('new');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->json('missing_required_fields')->nullable();
            $table->json('details')->nullable();                 // enrichment that has no taxonomy value
            $table->text('raw_notes')->nullable();               // what the homeowner actually said
            $table->text('summary')->nullable();                 // the fifteen-second brief

            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('handed_off_at')->nullable();
            $table->timestamps();

            $table->index('qualification_status');
        });

        // The mirror of `creative_parameters`: this is what makes "which branch
        // of the tree produces qualified leads" a real query.
        Schema::create('lead_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parameter_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parameter_value_id')->constrained()->cascadeOnDelete();
            $table->string('confidence')->default('stated');     // stated | inferred
            $table->foreignId('source_message_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lead_id', 'parameter_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_parameters');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
