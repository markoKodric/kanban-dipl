<?php namespace Kanban\Custom;

use App;
use Auth;
use Event;
use System\Classes\PluginBase;
use Kanban\Custom\Models\Project;
use Kanban\Custom\Components\Login;
use Kanban\Custom\Components\Archive;
use Kanban\Custom\Components\Settings;
use Kanban\Custom\Components\Analytics;
use Kanban\Custom\Bootstrap\ExtendUser;
use Kanban\Custom\Components\ProjectList;
use Kanban\Custom\Components\TicketSingle;
use Kanban\Custom\Components\ProjectSingle;
use Kanban\Custom\Bootstrap\InitGlobalScopes;
use Kanban\Custom\Bootstrap\InitDynamicParameters;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Plugin extends PluginBase
{
    public function register()
    {
        require_once __DIR__ . '/helpers.php';
    }

    public function boot()
    {
        (new ExtendUser())->init();
        (new InitGlobalScopes())->init();
        (new InitDynamicParameters())->init();

        App::error(function(HttpException $exception) {
            if ($exception->getStatusCode() == 403) {
                return redirect('/403');
            }
        });

        Event::listen('rainlab.user.logout', function () {
            session()->forget('currentProject');
        });

        Event::listen('cms.page.init', function ($controller, $page) {
            if (isset($page->apiBag['staticPage']) && $page->apiBag['staticPage']->id == 'project' &&
                array_last(explode('/', request()->url())) != session()->get('currentProject') &&
                is_numeric(array_last(explode('/', request()->url()))) && Auth::check()
            ) {
                session()->put('currentProject', array_last(explode('/', request()->url())));

                $page->viewBag['project'] = Project::find(session()->get('currentProject'));
            }

            if ($controller->getStatusCode() === 404 && request()->path() != '404') {
                return redirect('/404');
            }
        });
    }

    public function registerPageSnippets()
    {
        return $this->registerComponents();
    }

    public function registerComponents()
    {
        return [
            Login::class         => 'login',
            Archive::class       => 'archive',
            Settings::class      => 'settings',
            Analytics::class     => 'analytics',
            ProjectList::class   => 'projectList',
            TicketSingle::class  => 'ticketSingle',
            ProjectSingle::class => 'projectSingle',
        ];
    }

    public function registerMarkupTags()
    {
        return [
            'functions' => [
                'format_bytes' => function ($size, $precision = 2) {
                    if ($size > 0) {
                        $size = (int)$size;
                        $base = log($size) / log(1024);
                        $suffixes = array(' B', ' KB', ' MB', ' GB', ' TB');

                        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
                    } else {
                        return $size;
                    }
                },
                'hex2rgb' => function ($hex) {
                    $hex = str_replace("#", "", $hex);

                    if (strlen($hex) == 3) {
                        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
                        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
                        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
                    } else {
                        $r = hexdec(substr($hex, 0, 2));
                        $g = hexdec(substr($hex, 2, 2));
                        $b = hexdec(substr($hex, 4, 2));
                    }
                    $rgb = array($r, $g, $b);

                    return implode(",", $rgb);
                },
                'user' => function () {
                    return Auth::getUser();
                },
                'dd' => function (...$args) {
                    dd($args);
                },
                'request' => function () {
                    return request();
                },
                'session' => function () {
                    return session()->all();
                },
                'is_array' => function ($value) {
                    return is_array($value);
                },
                'debugEnabled' => function () {
                    return config('app.debug');
                }
            ]
        ];
    }
}
