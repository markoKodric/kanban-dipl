<?php namespace Kanban\Custom\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class FlowSectionUpdate extends Model
{
    use Validation;

    public $table = 'kanban_custom_flow_section_updates';

    public $rules = [];

    public $fillable = ['ticket_id', 'flow_section_id'];

    public $belongsTo = [
        'ticket' => Ticket::class,
        'section' => [FlowSection::class, 'key' => 'flow_section_id'],
    ];
}
