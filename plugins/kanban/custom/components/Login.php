<?php namespace Kanban\Custom\Components;

use Auth;
use Kanban\Custom\Models\Team;
use RainLab\User\Components\Account;
use Kanban\Custom\Traits\RenderingHelpers;

class Login extends Account
{
    use RenderingHelpers;

    public $teams;

    public function componentDetails()
    {
        return [
            'name'        => 'Login',
            'description' => 'Display login on page.'
        ];
    }

    public function defineProperties()
    {
        return $this->withLayoutOptions(parent::defineProperties());
    }

    public function onRun()
    {
        if (Auth::check()) {
            return redirect('/');
        }

        parent::onRun();

        $this->teams = Team::lists('name', 'id');
    }
}
