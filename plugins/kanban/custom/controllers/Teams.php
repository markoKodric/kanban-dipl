<?php namespace Kanban\Custom\Controllers;

use Backend\Behaviors\FormController;
use Backend\Behaviors\ListController;
use Backend\Behaviors\RelationController;
use Backend\Classes\Controller;
use BackendMenu;

class Teams extends Controller
{
    public $implement = [
        ListController::class,
        FormController::class,
        RelationController::class,
    ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $relationConfig = 'config_relation.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Kanban.Custom', 'kanban-menu-item', 'teams-side-menu-item');
    }
}
