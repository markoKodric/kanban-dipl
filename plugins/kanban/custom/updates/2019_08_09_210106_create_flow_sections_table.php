<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateFlowSectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kanban_custom_flow_sections', function ($table) {
            $table->bigIncrements('id');
            $table->integer('flow_id');
            $table->integer('parent_section_id')->nullable();
            $table->string('name');
            $table->integer('wip_limit')->nullable();
            $table->boolean('mark_tickets_complete')->nullable();
            $table->integer('sort_order')->default(10);
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
        Schema::dropIfExists('kanban_custom_flow_sections');
    }
}
