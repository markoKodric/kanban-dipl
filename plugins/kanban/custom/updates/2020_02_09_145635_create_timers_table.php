<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateTimersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kb_timers', function ($table) {
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->integer('timeable_id');
            $table->string('timeable_type');
            $table->timestamp('started_at');
            $table->timestamp('stopped_at')->default(null)->nullable();
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
        Schema::dropIfExists('kb_timers');
    }
}
