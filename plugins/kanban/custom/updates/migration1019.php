<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class Migration1019 extends Migration
{
    public function up()
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function($table)
            {
                $table->integer('team_id')->after('id')->nullable();
                $table->string('picture')->after('name')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('users')) {
            if (Schema::hasColumn('users', 'team_id')) {
                Schema::table('users', function($table)
                {
                    $table->dropColumn('team_id');
                });
            }
            if (Schema::hasColumn('users', 'picture')) {
                Schema::table('users', function($table)
                {
                    $table->dropColumn('picture');
                });
            }
        }
    }
}