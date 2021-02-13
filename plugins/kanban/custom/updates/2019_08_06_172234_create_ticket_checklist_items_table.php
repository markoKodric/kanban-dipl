<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateTicketChecklistItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kanban_custom_ticket_checklist_items', function ($table) {
            $table->bigIncrements('id');
            $table->integer('ticket_checklist_id');
            $table->text('description');
            $table->boolean('is_done');
            $table->integer('sort_order');
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
        Schema::dropIfExists('kanban_custom_ticket_checklist_items');
    }
}
