<?php namespace Kanban\Custom;

use App;
use Auth;
use Cms\Classes\Controller;
use Event;
use Illuminate\Support\Str;
use Kanban\Custom\Components\TicketModal;
use System\Classes\PluginBase;
use Kanban\Custom\Models\Project;
use Kanban\Custom\Components\Login;
use Kanban\Custom\Components\Archive;
use Kanban\Custom\Components\Settings;
use Kanban\Custom\Components\Analytics;
use Kanban\Custom\Bootstrap\ExtendUser;
use Kanban\Custom\Components\ActivityLog;
use Kanban\Custom\Components\UserProfile;
use Kanban\Custom\Components\ProjectList;
use Kanban\Custom\Components\ProjectMedia;
use Kanban\Custom\Components\TicketSingle;
use Kanban\Custom\Components\ProjectSingle;
use Kanban\Custom\Components\ProjectUpdate;
use Kanban\Custom\Bootstrap\InitSocketFunctions;
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
        (new InitSocketFunctions())->init();
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
            ActivityLog::class   => 'activityLog',
            ProjectList::class   => 'projectList',
            UserProfile::class   => 'userProfile',
            TicketModal::class   => 'ticketModal',
            TicketSingle::class  => 'ticketSingle',
            ProjectMedia::class  => 'projectMedia',
            ProjectSingle::class => 'projectSingle',
            ProjectUpdate::class => 'projectUpdate',
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
                'file_type' => function ($value) {
                    return pathinfo($value, PATHINFO_EXTENSION);
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
                'dd' => function () {
                    dd(...func_get_args());
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
                },
                'charts' => function () {
                    $components = Controller::getController()->getPage()->components;

                    foreach ($components as $key => $component) {
                        if (get_class($component) == 'Kanban\\Custom\\Components\\Analytics') {
                            return true;
                        }
                    }

                    return false;
                },
                'ticketModal' => function () {
                    $components = Controller::getController()->getPage()->components;

                    foreach ($components as $key => $component) {
                        if (get_class($component) == 'Kanban\\Custom\\Components\\TicketModal') {
                            return true;
                        }
                    }

                    return false;
                },
                'ping' => function ($url) {
                    if ($url == null) return false;

                    if (cache()->has('url_available_' . md5($url))) {
                        return cache()->get('url_available_' . md5($url));
                    }

                    $curl = curl_init($url);

                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

                    curl_exec($curl);

                    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                    curl_close($curl);

                    return cache()->remember('url_available_' . md5($url), 15, function () use ($httpCode) {
                        return $httpCode >= 200 && $httpCode < 300;
                    });
                },
                'str_random' => function ($length = 32) {
                    return Str::random($length);
                }
            ]
        ];
    }
}
