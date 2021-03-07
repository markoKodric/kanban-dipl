<?php namespace Kanban\Custom\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Permissions extends Controller
{
    public $implement = [        'Backend\Behaviors\ListController',        'Backend\Behaviors\FormController'    ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Kanban.Custom', 'kanban-menu-item', 'permissions-side-menu-item');
    }
}
