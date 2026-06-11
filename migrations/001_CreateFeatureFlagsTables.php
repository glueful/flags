<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Database\Migrations;

use Glueful\Database\Migrations\MigrationInterface;
use Glueful\Database\Schema\Interfaces\SchemaBuilderInterface;

final class CreateFeatureFlagsTables implements MigrationInterface
{
    public function up(SchemaBuilderInterface $schema): void
    {
        if (!$schema->hasTable('feature_flags')) {
            $schema->createTable('feature_flags', function ($table): void {
                $table->bigInteger('id')->primary()->autoIncrement();
                $table->string('uuid', 12);
                $table->string('key', 160);
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->boolean('enabled')->default(false);
                $table->boolean('default_value')->default(false);
                $table->string('status', 20)->default('active');
                $table->string('created_by', 12)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('uuid');
                $table->unique('key');
                $table->index('status');
                $table->index('created_by');
            });
        }

        if (!$schema->hasTable('feature_flag_rules')) {
            $schema->createTable('feature_flag_rules', function ($table): void {
                $table->bigInteger('id')->primary()->autoIncrement();
                $table->string('uuid', 12);
                $table->string('flag_uuid', 12);
                $table->integer('priority')->default(0);
                $table->string('type', 40);
                $table->string('operator', 40);
                $table->json('value')->nullable();
                $table->integer('percentage')->nullable();
                $table->string('subject', 20)->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('uuid');
                $table->index('flag_uuid');
                $table->index(['flag_uuid', 'enabled', 'priority'], 'idx_feature_flag_rule_eval');
            });
        }

        if (!$schema->hasTable('feature_flag_audits')) {
            $schema->createTable('feature_flag_audits', function ($table): void {
                $table->bigInteger('id')->primary()->autoIncrement();
                $table->string('uuid', 12);
                $table->string('flag_uuid', 12);
                $table->string('action', 40);
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->string('actor_uuid', 12)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->unique('uuid');
                $table->index('flag_uuid');
                $table->index('action');
            });
        }
    }

    public function down(SchemaBuilderInterface $schema): void
    {
        $schema->dropTableIfExists('feature_flag_audits');
        $schema->dropTableIfExists('feature_flag_rules');
        $schema->dropTableIfExists('feature_flags');
    }

    public function getDescription(): string
    {
        return 'Create feature flag, rule, and audit tables.';
    }
}
