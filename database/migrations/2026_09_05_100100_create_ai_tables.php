<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which LLM turns a creative idea into a generation prompt. Admin-managed.
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');                  // "Claude Opus 5"
            $table->string('provider');              // anthropic | gemini | openai | other
            $table->string('model_id');              // claude-opus-5
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('target_format')->default('any'); // video | image | any
            $table->text('description')->nullable();
            $table->text('system_prompt');
            $table->text('user_template');           // supports {{placeholders}}
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // One row per generated prompt: the creative brief the admin reviews and validates.
        Schema::create('creative_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_model_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prompt_template_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('outcome')->nullable();     // the structured "creative outcome" it was built from
            $table->text('body');
            $table->string('status')->default('draft'); // draft | validated
            $table->string('target_format')->default('video');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->json('meta')->nullable();        // provider name, token usage, errors
            $table->timestamps();

            $table->index(['creative_id', 'version']);
        });

        // One row per attempt to turn a validated prompt into an actual asset.
        Schema::create('creative_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creative_prompt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');              // google_veo | google_flow_manual | ...
            $table->string('model')->nullable();     // veo-3.1-generate-preview
            $table->string('format')->default('video');
            $table->string('status')->default('queued'); // queued|generating|completed|failed|awaiting_manual
            $table->string('external_id')->nullable();   // provider operation / generation id
            $table->string('asset_url')->nullable();     // where the asset lives (provider-hosted)
            $table->string('asset_reference')->nullable(); // provider file id / Drive id
            $table->string('asset_mime')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['creative_id', 'status']);
        });

        Schema::table('creatives', function (Blueprint $table) {
            // Where the current asset came from: upload | link | google_veo | google_flow
            $table->string('asset_source')->nullable()->after('asset_path');
            $table->foreignId('creative_generation_id')->nullable()->after('asset_source')
                ->constrained('creative_generations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creative_generation_id');
            $table->dropColumn('asset_source');
        });

        Schema::dropIfExists('creative_generations');
        Schema::dropIfExists('creative_prompts');
        Schema::dropIfExists('prompt_templates');
        Schema::dropIfExists('ai_models');
    }
};
