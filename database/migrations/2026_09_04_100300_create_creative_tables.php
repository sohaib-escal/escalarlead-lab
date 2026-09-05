<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creatives', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();   // human readable: PAC-W-60-69-HIGHBILL-AID-FB-001
            $table->string('name');
            $table->text('description')->nullable(); // internal description
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('creative_status_id')->constrained()->restrictOnDelete();
            $table->foreignId('landing_page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cta_option_id')->nullable()->constrained()->nullOnDelete();

            // Creative asset
            $table->string('format')->default('static_image'); // static_image|video|carousel|ugc|motion|other
            $table->string('asset_url')->nullable();
            $table->string('asset_path')->nullable();
            $table->string('asset_filename')->nullable();
            $table->string('asset_mime')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('thumbnail_path')->nullable();

            // Ad copy
            $table->text('hook')->nullable();
            $table->text('primary_text')->nullable();
            $table->string('headline')->nullable();
            $table->string('ad_description')->nullable();
            $table->text('concept')->nullable(); // what the visual communicates

            // Ops
            $table->string('performance_override')->nullable(); // winner|promising|average|poor
            $table->unsignedInteger('version')->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('duplicated_from_id')->nullable()->constrained('creatives')->nullOnDelete();
            $table->timestamps();
        });

        // Relational persona storage so we can query "PAC + women + 60-69 + high heating bill".
        Schema::create('creative_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parameter_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parameter_value_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['creative_id', 'parameter_value_id']);
            $table->index(['parameter_category_id', 'parameter_value_id']);
        });

        Schema::create('channel_creative', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->unique(['creative_id', 'channel_id']);
        });

        Schema::create('campaign_creative', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creative_id')->constrained()->cascadeOnDelete();
            $table->unique(['campaign_id', 'creative_id']);
        });

        Schema::create('utm_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creative_id')->constrained()->cascadeOnDelete();
            $table->string('base_url')->nullable(); // defaults to the landing page URL
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->boolean('auto_sync')->default(true);
            $table->timestamps();

            $table->unique('creative_id');
        });

        Schema::create('creative_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');

            $table->decimal('spend', 12, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);

            $table->unsignedInteger('leads')->default(0);
            $table->unsignedInteger('qualified_leads')->default(0);
            $table->unsignedInteger('contacted')->default(0);
            $table->unsignedInteger('phone_qualified')->default(0);
            $table->unsignedInteger('appointments')->default(0);
            $table->unsignedInteger('confirmed')->default(0);
            $table->unsignedInteger('sales')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['creative_id', 'period_start']);
        });

        Schema::create('creative_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('creative_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');       // created | updated | status_changed | asset_uploaded | ...
            $table->string('description');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['creative_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_history');
        Schema::dropIfExists('creative_notes');
        Schema::dropIfExists('creative_metrics');
        Schema::dropIfExists('utm_configurations');
        Schema::dropIfExists('campaign_creative');
        Schema::dropIfExists('channel_creative');
        Schema::dropIfExists('creative_parameters');
        Schema::dropIfExists('creatives');
    }
};
