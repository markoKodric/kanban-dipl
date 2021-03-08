<?php namespace Kanban\Custom\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class Tag extends Model
{
    use Validation;

    public $table = 'kb_tags';

    public $fillable = ['code', 'title', 'color'];

    public $rules = [];

    public $belongsTo = [
        'project' => Project::class,
    ];

    public $belongsToMany = [
        'tickets' => [Ticket::class, 'table' => 'kb_tickets_tags'],
    ];
}
