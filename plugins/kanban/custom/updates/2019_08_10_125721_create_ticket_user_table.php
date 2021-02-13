<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateTicketUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kanban_custom_ticket_user', function ($table) {
            $table->integer('ticket_id');
            $table->integer('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kanban_custom_ticket_user');
    }
}
