<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateProjectUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kanban_custom_project_user', function ($table) {
            $table->integer('user_id');
            $table->integer('project_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kanban_custom_project_user');
    }
}
