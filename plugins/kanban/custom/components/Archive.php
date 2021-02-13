<?php namespace Kanban\Custom\Components;

use Cms\Classes\ComponentBase;
use Kanban\Custom\Traits\RenderingHelpers;

class Archive extends ComponentBase
{
    use RenderingHelpers;

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - Archive',
            'description' => 'Display archive on page.'
        ];
    }

    public function defineProperties()
    {
        return [];
    }
}
