<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateKanbanCustomActivityLog extends Migration
{
    public function up()
    {
        Schema::create('kanban_custom_activity_log', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->integer('user_id');
            $table->integer('project_id');
            $table->string('description');
            $table->text('data')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('kanban_custom_activity_log');
    }
}