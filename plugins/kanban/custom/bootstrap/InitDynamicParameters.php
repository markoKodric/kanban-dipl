<?php namespace Kanban\Custom\Bootstrap;

use Cms\Classes\ComponentBase;
use October\Rain\Router\Helper;
use Kanban\Custom\Models\UrlMap;
use RainLab\Pages\Classes\Controller;
use Illuminate\Support\Facades\Event;
use Kanban\Custom\Traits\DynamicParameters;
use October\Rain\Exception\ApplicationException;

class InitDynamicParameters
{
    protected $params = [];

    protected $throw404 = false;

    public function init()
    {
        $this->parseDynamicParams();
        $this->matchDynamicParams();
        //$this->throw404IfParamsDontMatch();
    }

    protected function parseDynamicParams()
    {
        Event::listen('cms.router.beforeRoute', function ($url, $router) {
            $url = Helper::normalizeUrl($url);

            if($mappedUrl = UrlMap::targetToSource('/' . app()->getLocale() . $url, request()->getHttpHost())) {
                $url = str_replace('/' . app()->getLocale() . '/', '/', $mappedUrl['url']);
            } elseif($mappedUrl = UrlMap::targetToSource($url, request()->getHttpHost())) {
                $url = $mappedUrl['url'];
            }

            // Remove one URL segment in each loop and check whether
            // we have a match each time. This essentially removes
            // the parameters and stacks them on a separate array
            while (($pos = strrpos($url, '/')) > 0) {

                array_unshift($this->params, substr($url, $pos + 1));
                $url = substr($url, 0, $pos);

                if ($page = Controller::instance()->initCmsPage($url)) {

                    // Update path in october router
                    $router->findByUrl($url);

                    return $page;
                }
            }

            return null;
        }, -1);
    }

    protected function matchDynamicParams()
    {
        Event::listen('cms.page.initComponents', function ($controller, $page) {
            if (!isset($page->apiBag['staticPage'])) {
                return;
            }

            $definedParams = null;

            // Here we will check for dynamic parameters defined in components and check:
            // a.) do any component's parameter definitions conflict one another
            // b.) do defined parameters match with what was received in the URL
            foreach ($controller->vars as $component) {
                if (! $this->componentUsesDynamicParams($component)) continue;

                $expectedParams = $component->getExpectedParameters();

                if(!empty($definedParams)) {
                    if ($expectedParams !== $definedParams) {
                        throw new ApplicationException('At least two components have conflicting parameter definitions.');
                    } else {
                        continue;
                    }
                }

                $tooLittleParams = count($expectedParams['required']) > count($this->params);
                $tooManyParams = count($this->params) > count(array_flatten($expectedParams));

                if ($tooLittleParams || $tooManyParams) {
                    $this->throw404 = true;
                    return;
                }

                $actualParams = $component->mapActualParameters($this->params);
                $controller->getRouter()->setParameters($actualParams);
                $definedParams = $expectedParams;
            }

            if(!empty($this->params) && empty($expectedParams)) {
                $this->throw404 = true;
            }
        }, -1);
    }

    protected function componentUsesDynamicParams($component)
    {
        if(!($component instanceof ComponentBase)) return false;

        $usedTraits = class_uses_recursive(get_class($component));

        return in_array(DynamicParameters::class, $usedTraits);
    }

    protected function throw404IfParamsDontMatch()
    {
        Event::listen('cms.page.init', function($controller) {
            if($this->throw404) {
                $this->throw404 = false;
                return $controller->run('404');
            }
        });
    }
}