<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateFlowSectionUpdates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kanban_custom_flow_section_updates', function ($table) {
            $table->bigIncrements('id');
            $table->integer('ticket_id');
            $table->integer('flow_section_id');
            $table->integer('old_flow_section_id')->nullable();
            $table->integer('project_id');
            $table->integer('user_id');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kanban_custom_flow_section_updates');
    }
}
