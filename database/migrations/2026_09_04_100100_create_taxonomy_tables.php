<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code', 16)->unique();
            $table->string('color', 32)->default('teal');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code', 16)->unique();
            $table->string('default_utm_source')->nullable();
            $table->string('default_utm_medium')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Admin-managed dimensions of the creative tree (Gender, Age, Problem, ...).
        Schema::create('parameter_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group')->default('persona'); // persona | property | financial | energy | problem
            $table->text('description')->nullable();
            $table->boolean('is_multi')->default(false);   // several values allowed on one creative
            $table->boolean('in_tree')->default(false);    // available as a creative-tree axis
            $table->boolean('in_naming')->default(false);  // contributes to the generated creative ID
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('parameter_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parameter_category_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('slug');
            $table->string('code', 24);          // short token used in creative IDs, e.g. W, 60-69, HIGHBILL
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete(); // scope value to a product
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->unique(['parameter_category_id', 'slug']);
        });

        Schema::create('creative_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 32)->default('slate');
            $table->boolean('counts_as_live')->default(false);
            $table->boolean('is_archived_state')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cta_options', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('slug')->unique();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('landing_page_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_types');
        Schema::dropIfExists('cta_options');
        Schema::dropIfExists('creative_statuses');
        Schema::dropIfExists('parameter_values');
        Schema::dropIfExists('parameter_categories');
        Schema::dropIfExists('channels');
        Schema::dropIfExists('products');
    }
};
