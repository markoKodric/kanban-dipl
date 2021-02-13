<?php namespace Kanban\Custom\Components;

use Cms\Classes\ComponentBase;
use Kanban\Custom\Traits\MenuHelpers;
use Kanban\Custom\Traits\RenderingHelpers;
use Kanban\Custom\Traits\DynamicParameters;

class Analytics extends ComponentBase
{
    use RenderingHelpers, MenuHelpers, DynamicParameters;

    protected $parameters = ['project'];

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - Analytics',
            'description' => 'Display analytics on page.'
        ];
    }

    public function defineProperties()
    {
        return [
            'projectPage' => [
                'title' => 'Select project page',
                'type' => 'dropdown',
            ]
        ];
    }

    public function getProjectPageOptions()
    {
        return ['' => '---'] + $this->getAllPages();
    }
}
