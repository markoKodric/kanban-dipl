<?php namespace Kanban\Custom\Traits;

use App;
use Response;
use RainLab\Pages\Classes\MenuItem;

trait MenuHelpers
{
    protected function getCmsPages()
    {
        $cmsPages = MenuItem::getTypeInfo('cms-page');

        return $this->createHierarchy($cmsPages['references']);
    }

    protected function getStaticPages()
    {
        $staticPages = MenuItem::getTypeInfo('static-page');

        return $this->createHierarchy($staticPages['references']);
    }

    protected function getAllPages($staticPrefix = 'static:', $cmsPrefix = 'cms:')
    {
        // Speed optimization -> no need to retrieve pages on front-end
        if(!App::runningInBackend()) return [];

        $cmsPages = MenuItem::getTypeInfo('cms-page');
        $staticPages = MenuItem::getTypeInfo('static-page');

        return array_merge(
            $this->createHierarchy($cmsPages['references'], $cmsPrefix),
            $this->createHierarchy($staticPages['references'], $staticPrefix)
        );
    }

    protected function createHierarchy($items, $codePrefix = '', $prefix = '')
    {
        $flatItems = [];

        if(!$items) return [];

        foreach($items as $code => $title) {
            if(is_string($title)) {
                $flatItems[$codePrefix . $code] = $prefix . $title;
                continue;
            }

            $flatItems[$codePrefix . $code] = $prefix . $title['title'];
            $indentation = $prefix . '&nbsp;&nbsp;&nbsp;';

            $flatItems = $flatItems + $this->createHierarchy($title['items'], $codePrefix, $indentation);
        }

        return $flatItems;
    }

    protected function pageOptions()
    {
        return ['' => '- Select page -'] + $this->getAllPages();
    }
}