<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableKanbanCustomUrlMap extends Migration
{
    public function up()
    {
        Schema::create('kanban_custom_url_map', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->string('source_url');
            $table->string('target_url');
            $table->string('domain')->nullable();
            $table->boolean('is_active');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('kanban_custom_url_map');
    }
}