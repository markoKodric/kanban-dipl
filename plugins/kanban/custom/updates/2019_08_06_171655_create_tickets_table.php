<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateTicketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kanban_custom_tickets', function ($table) {
            $table->bigIncrements('id');
            $table->integer('flow_section_id')->nullable();
            $table->integer('project_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('priority')->default(1);
            $table->integer('sort_order')->default(10);
            $table->integer('time_estimation')->default(0);
            $table->timestamp('due_date')->nullable();
            $table->boolean('is_archived')->nullable();
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
        Schema::dropIfExists('kanban_custom_tickets');
    }
}
