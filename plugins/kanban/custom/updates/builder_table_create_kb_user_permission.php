<?php namespace Kanban\Custom\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateKbUserPermission extends Migration
{
    public function up()
    {
        Schema::create('kb_user_permission', function($table)
        {
            $table->engine = 'InnoDB';
            $table->integer('user_id')->unsigned();
            $table->integer('permission_id')->unsigned();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('kb_user_permission');
    }
}
