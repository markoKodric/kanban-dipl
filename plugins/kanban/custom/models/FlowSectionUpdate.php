<?php namespace Kanban\Custom\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class FlowSectionUpdate extends Model
{
    use Validation;

    public $table = 'kb_flow_section_updates';

    public $rules = [];

    public $fillable = ['ticket_id', 'flow_section_id', 'old_flow_section_id', 'project_id', 'user_id', 'description'];

    public $belongsTo = [
        'ticket' => Ticket::class,
        'section' => [FlowSection::class, 'key' => 'flow_section_id'],
    ];
}
