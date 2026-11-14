<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 2048);
            $table->string('primary_url', 2048)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'is_primary']);
        });

        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('status', 20)->default('queued'); // queued|running|completed|failed
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('pages_crawled')->default(0);
            $table->unsignedInteger('pages_failed')->default(0);
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->decimal('evidence_coverage', 5, 2)->nullable();
            $table->unsignedInteger('critical_count')->default(0);
            $table->unsignedInteger('high_count')->default(0);
            $table->unsignedInteger('medium_count')->default(0);
            $table->unsignedInteger('low_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('normalized_url', 2048);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('title', 512)->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->string('html_hash', 64)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('load_time_ms')->nullable();
            $table->unsignedInteger('html_bytes')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['scan_id', 'normalized_url'], 'pages_scan_norm_unique');
            $table->index(['scan_id', 'depth']);
        });

        Schema::create('page_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['page_id', 'key']);
        });

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule_id', 60);
            $table->string('category', 40);
            $table->string('rule_name', 200);
            $table->string('severity', 12); // critical|high|medium|low
            $table->unsignedTinyInteger('confidence')->default(3); // 1-5
            $table->text('evidence')->nullable();
            $table->text('explanation')->nullable();
            $table->text('recommended_action')->nullable();
            $table->text('implementation_guidance')->nullable();
            $table->text('limitations')->nullable();
            $table->unsignedTinyInteger('impact')->default(3);
            $table->unsignedTinyInteger('effort')->default(3);
            $table->unsignedTinyInteger('reach')->default(3);
            $table->decimal('priority_score', 6, 2)->default(0);
            $table->string('priority_label', 12)->default('low');
            $table->string('status', 12)->default('open'); // open|resolved
            $table->timestamps();
            $table->index(['scan_id', 'category']);
            $table->index(['scan_id', 'severity']);
            $table->index(['scan_id', 'rule_id']);
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finding_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 300);
            $table->text('body');
            $table->string('type', 20)->default('rule_based'); // rule_based|ai
            $table->string('priority_label', 12)->default('low');
            $table->decimal('priority_score', 6, 2)->default(0);
            $table->unsignedTinyInteger('impact')->default(3);
            $table->unsignedTinyInteger('confidence')->default(3);
            $table->unsignedTinyInteger('effort')->default(3);
            $table->unsignedTinyInteger('reach')->default(3);
            $table->string('source', 60)->nullable();
            $table->timestamps();
            $table->index(['scan_id', 'priority_score']);
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->decimal('score', 5, 2)->default(0);
            $table->decimal('weight', 5, 2)->default(0);
            $table->unsignedInteger('issues_count')->default(0);
            $table->decimal('evidence_coverage', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['scan_id', 'category']);
        });

        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 2048);
            $table->timestamps();
            $table->index('project_id');
        });

        Schema::create('competitor_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scan_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->json('category_scores')->nullable();
            $table->json('cta_summary')->nullable();
            $table->json('trust_summary')->nullable();
            $table->json('content_summary')->nullable();
            $table->json('form_summary')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
            $table->index(['competitor_id', 'captured_at']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('path');
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'created_at']);
        });

        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('level', 12)->default('info');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['scan_id', 'created_at']);
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30); // ga4|gtm|crm
            $table->text('credentials')->nullable(); // encrypted cast
            $table->json('config')->nullable();
            $table->string('status', 20)->default('disconnected');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'provider']);
        });

        Schema::create('visitor_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name', 100);
            $table->string('source', 40)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'occurred_at']);
        });

        Schema::create('conversion_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->decimal('value', 12, 2)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'occurred_at']);
        });

        Schema::create('experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('hypothesis')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('variant_a', 200)->nullable();
            $table->string('variant_b', 200)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach ([
            'experiments', 'conversion_events', 'visitor_events', 'integrations',
            'scan_logs', 'reports', 'competitor_snapshots', 'competitors', 'scores',
            'recommendations', 'findings', 'page_metrics', 'pages', 'scans', 'projects',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
