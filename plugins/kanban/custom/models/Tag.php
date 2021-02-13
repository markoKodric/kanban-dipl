<?php namespace Kanban\Custom\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class Tag extends Model
{
    use Validation;

    public $table = 'kanban_custom_tags';

    public $fillable = ['code', 'title', 'color'];

    public $rules = [];

    public $belongsTo = [
        'project' => Project::class,
    ];

    public $belongsToMany = [
        'tickets' => [Ticket::class, 'table' => 'kanban_custom_tickets_tags'],
    ];
}
