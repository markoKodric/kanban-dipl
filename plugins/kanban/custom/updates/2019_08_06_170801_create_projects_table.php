<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateProjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kb_projects', function ($table) {
            $table->bigIncrements('id');
            $table->integer('team_id');
            $table->string('title');
            $table->string('slug');
            $table->text('description');
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_company')->nullable();
            $table->date('start_date');
            $table->date('due_date')->nullable();
            $table->integer('sort_order')->default(1);
            $table->boolean('is_started')->nullable();
            $table->boolean('is_active')->nullable();
            $table->boolean('is_completed')->nullable();
            $table->boolean('is_default')->nullable();
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
        Schema::dropIfExists('kb_projects');
    }
}
