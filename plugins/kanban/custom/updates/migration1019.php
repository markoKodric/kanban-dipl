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
                $table->integer('team_id')->after('id');
                $table->string('picture')->after('name')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function($table)
            {
                $table->dropColumn('team_id');
                $table->dropColumn('picture');
            });
        }
    }
}