<?php namespace Kanban\Custom\Models;

use Model;
use October\Rain\Database\Traits\Validation;
use RainLab\User\Models\User;

class FlowSection extends Model
{
    use Validation;

    public $table = 'kanban_custom_flow_sections';

    public $fillable = ['name', 'flow_id', 'parent_section_id', 'sort_order', 'wip_limit', 'mark_tickets_complete', 'swimlane_id', 'original_section_id'];

    public $rules = [];

    public $belongsTo = [
        'flow'   => Flow::class,
        'parent' => [FlowSection::class, 'key' => 'parent_section_id']
    ];

    public $hasMany = [
        'subsections' => [FlowSection::class, 'key' => 'parent_section_id', 'order' => 'sort_order asc'],
        'tickets'     => [Ticket::class, 'scope' => 'unarchived'],
    ];

    public $belongsToMany = [
        'restrictions' => [User::class, 'table' => 'kanban_custom_flow_section_restrictions']
    ];

    public function scopeParents($query)
    {
        $query->whereNull('parent_section_id');
    }

    public function columnWidth()
    {
        $columnWidths = [
            0 => '22.5',
            1 => '22.5',
            2 => '45',
            3 => '67.5'
        ];

        return $columnWidths[$this->subsections()->count()];
    }

    public function cleanDelete()
    {
        $this->tickets->each(function ($ticket) {
            $ticket->comments()->delete();
            $ticket->files()->delete();
            $ticket->checklists->each(function($checklist) {
                $checklist->items()->delete();
            });
            $ticket->checklists()->delete();
            $ticket->timers()->delete();
            $ticket->users()->detach();
            $ticket->tags()->detach();

            $ticket->delete();
        });

        $this->delete();
    }
}
