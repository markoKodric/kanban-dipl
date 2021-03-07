<?php namespace Kanban\Custom\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class Flow extends Model
{
    use Validation;

    public $table = 'kanban_custom_flows';

    public $fillable = ['name', 'project_id'];

    public $rules = [];

    public $belongsTo = [
        'project' => Project::class,
    ];

    public $hasMany = [
        'sections' => [FlowSection::class],
    ];

    public function parentSections()
    {
        return $this->sections()->parents()->orderBy('sort_order')->get();
    }

    public function sectionsToJson()
    {
        $sections = [];

        $parentSections = $this->sections()->with('subsections')->whereNull('parent_section_id')->whereNull('swimlane_id')->get();

        foreach ($parentSections as $key => $section) {
            if ($section->subsections->isEmpty()) {
                $sections[] = [
                    'id'           => $section->id,
                    'title'        => $section->name,
                    'wipLimit'     => $subsection->wip_limit ?? '-',
                    'markComplete' => $subsection->mark_tickets_complete ?? false,
                    'subsections'  => [],
                ];
            } else {
                $subsections = [];

                foreach ($section->subsections as $subkey => $subsection) {
                    $subsections[] = [
                        'id'           => $section->id,
                        'title'        => $section->name,
                        'wipLimit'     => $subsection->wip_limit ?? '-',
                        'markComplete' => $subsection->mark_tickets_complete ?? false,
                    ];
                }

                $sections[] = [
                    'id'           => $section->id,
                    'title'        => $section->name,
                    'markComplete' => $section->mark_tickets_complete ?? false,
                    'subsections'  => $subsections,
                ];
            }
        }

        return json_encode($sections);
    }
}
