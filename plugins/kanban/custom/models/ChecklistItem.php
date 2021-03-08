<?php namespace Kanban\Custom\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class ChecklistItem extends Model
{
    use Validation;

    public $table = 'kb_ticket_checklist_items';

    public $fillable = ['description', 'is_done', 'sort_order'];

    public $rules = [];

    public $belongsTo = [
        'ticket' => [Checklist::class, 'key' => 'ticket_checklist_id'],
    ];
}
