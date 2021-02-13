<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateKanbanCustomFlowSectionRestrictions extends Migration
{
    public function up()
    {
        Schema::create('kanban_custom_flow_section_restrictions', function($table)
        {
            $table->engine = 'InnoDB';
            $table->integer('user_id')->unsigned();
            $table->integer('flow_section_id')->unsigned();
            $table->integer('project_id')->unsigned();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('kanban_custom_flow_section_restrictions');
    }
}
