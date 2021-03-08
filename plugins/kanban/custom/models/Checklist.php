<?php namespace Kanban\Custom\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class Checklist extends Model
{
    use Validation;

    public $table = 'kb_ticket_checklists';

    public $fillable = ['title', 'sort_order'];

    public $rules = [];

    public $belongsTo = [
        'ticket' => Ticket::class,
    ];

    public $hasMany = [
        'items' => [ChecklistItem::class, 'key' => 'ticket_checklist_id'],
    ];

    public $with = ['items'];
}
