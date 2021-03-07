<?php namespace Kanban\Custom\Updates;

use Kanban\Custom\Models\Permission;
use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateKanbanCustomPermissions extends Migration
{
    public function up()
    {
        Schema::create('kanban_custom_permissions', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->string('title');
            $table->string('code');
        });

        $permissions = [
            'projects-manage-projects' => 'Projects - Manage projects',
            'board-edit-workflow'      => 'Board - Edit workflow',
            'board-manage-users'       => 'Board - Manage users',
            'board-add-tickets'        => 'Board - Add tickets',
            'board-reorder-tickets'    => 'Board - Reorder tickets',
            'ticket-manage-users'      => 'Ticket - Manage users',
            'ticket-manage-tags'       => 'Ticket - Manage tags',
            'tickets-edit-ticket'      => 'Tickets - Edit ticket',
            'tickets-delete-ticket'    => 'Tickets - Delete ticket',
        ];

        foreach ($permissions as $code => $permission) {
            Permission::forceCreate([
                'title' => $permission,
                'code'  => $code,
            ]);
        }
    }
    
    public function down()
    {
        Schema::dropIfExists('kanban_custom_permissions');
    }
}
