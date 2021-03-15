<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateKbDocumentation extends Migration
{
    public function up()
    {
        Schema::create('kb_documentation', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->integer('project_id');
            $table->string('title');
            $table->mediumText('content')->nullable();
            $table->integer('creator_id');
            $table->integer('last_user_id');
            $table->integer('editing_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('kb_documentation');
    }
}
