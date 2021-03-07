<?php namespace Kanban\Custom\Models;

use Model;
use October\Rain\Database\Traits\Validation;
use RainLab\User\Models\User;

class Swimlane extends Model
{
    use Validation;

    public $table = 'kanban_custom_swimlanes';

    public $fillable = ['name', 'project_id', 'sort_order'];

    public $rules = [];

    public $belongsTo = [
        'project' => Project::class
    ];

    public $hasMany = [
        'sections' => [FlowSection::class]
    ];
}
