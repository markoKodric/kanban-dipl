<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateKanbanCustomUserPermission extends Migration
{
    public function up()
    {
        Schema::create('kanban_custom_user_permission', function($table)
        {
            $table->engine = 'InnoDB';
            $table->integer('user_id')->unsigned();
            $table->integer('permission_id')->unsigned();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('kanban_custom_user_permission');
    }
}
