<?php namespace Kanban\Custom\Traits;

use App;
use Cms\Classes\Theme;
use October\Rain\Exception\ApplicationException;

/**
 * Enables previews and extended templates
 */
trait RenderingHelpers
{
    public $originalAlias;

    public function init()
    {
        $this->originalAlias = $this->alias;

        $this->alias = $this->resolveAlias();
    }

    protected function resolveAlias()
    {
        return strtolower(class_basename($this));
    }

    public function onRender($withPreview = true)
    {
        if (property_exists($this, 'overridable')) {
            $result = $this->renderOverridable($this->overridable);
        } else {
            $result = $this->renderPartial('@default');
        }

        return $result ?: ' ';
    }

    protected function renderOverridable($overridable)
    {
        $template = $this->property($overridable);

        if ($template) {
            return $this->renderPartial($template);
        }

        return $this->renderPartial('@default');
    }

    public function withLayoutOptions(array $options = [])
    {
        return array_merge($options, [
            'layout' => [
                'title'       => 'Layout',
                'type'        => 'dropdown',
                'description' => 'Select a layout',
            ]
        ]);
    }

    public function getLayoutOptions()
    {
        $folder = strtolower(class_basename($this));
        $overrides = $this->findTemplateOverrides($folder);

        return $overrides ?: ['' => 'Default'];
    }

    public function findTemplateOverrides($folder)
    {
        $theme = Theme::getActiveTheme()->getDirName();
        $path = Theme::getActiveTheme()->getPath($theme . '/partials/' . $folder);

        if (!file_exists($path)) {
            return [];
        }

        $files = scandir($path);
        $result = [];

        foreach($files as $file) {
            if (starts_with($file, '.')) {
                continue;
            }

            $fileName = substr($file, 0, strrpos($file, '.'));

            if (!starts_with($fileName, '_')) {
                $result['@' . $fileName] = ucfirst(str_replace('_', ' ', $fileName));
            }
        }

        return $result;
    }

    public function __toString()
    {
        return $this->originalAlias;
    }
}
