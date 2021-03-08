<?php namespace Kanban\Custom\Models;

use Event;
use October\Rain\Database\Model;
use October\Rain\Database\Traits\Nullable;
use October\Rain\Database\Traits\Validation;

class UrlMap extends Model
{
    use Validation, Nullable;

    public static $autoClearCache = true;

    protected static $cacheKey = 'kanban.custom.url_map_v2';

    protected static $mapCache = null;

    public $table = 'kb_url_map';

    public $rules = [
        'source_url' => 'required',
        'target_url' => 'required',
    ];

    public $nullable =  ['domain'];

    protected $fillable = ['source_url', 'target_url', 'domain', 'is_active'];

    public function afterSave()
    {
        if(static::$autoClearCache) {
            static::clearCache();
        }
    }

    public function afterDelete()
    {
        if(static::$autoClearCache) {
            static::clearCache();
        }
    }

    public static function clearCache()
    {
        cache()->forget(static::$cacheKey);
    }

    public static function defaultDomain()
    {
        return str_replace(['http://', 'https://'], '', config('app.url'));
    }

    public static function getMap()
    {
        $cached = static::$mapCache ?: cache(static::$cacheKey);

        if($cached) {
            return $cached;
        }

        $all = static::where('is_active', 1)->get();

        $sourceToTarget = $all->keyBy('source_hash')->map(function($item) {
            return [
                'url' => $item->target_url,
                'domain' => $item->domain
            ];
        })->all();

        $targetToSource = $all->keyBy('target_hash')->map(function($item) {
            return [
                'url' => $item->source_url,
                'domain' => $item->domain
            ];
        })->all();

        static::$mapCache = [
            'source_to_target' => $sourceToTarget,
            'target_to_source' => $targetToSource
        ];

        cache()->forever(static::$cacheKey, static::$mapCache);

        return static::$mapCache;
    }

    public static function targetToSource($url, $domain = null)
    {
        return static::mapUrl($url, $domain, 'target_to_source');
    }

    public static function sourceToTarget($url, $domain = null)
    {
        return static::mapUrl($url, $domain, 'source_to_target');
    }

    protected static function mapUrl($url, $domain, $key)
    {
        $map = static::getMap();
        $hash = ($domain ?? static::defaultDomain()) . '::' . $url;

        if(isset($map[$key][$hash])) {
            return $map[$key][$hash];
        }

        return null;
    }

    public function getSourceHashAttribute()
    {
        return ($this->domain ?? static::defaultDomain()) . '::' .$this->source_url;
    }

    public function getTargetHashAttribute()
    {
        return ($this->domain ?? static::defaultDomain()) . '::' . $this->target_url;
    }
}
