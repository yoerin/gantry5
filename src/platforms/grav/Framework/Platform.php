<?php
/**
 * @package   Gantry5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2016 RocketTheme, LLC
 * @license   MIT
 *
 * http://opensource.org/licenses/MIT
 */

namespace Gantry\Framework;

use Gantry\Component\Position\Module;
use Gantry\Component\Position\Position;
use Gantry\Framework\Base\Platform as BasePlatform;
use Grav\Common\Grav;
use Grav\Common\Utils;
use RocketTheme\Toolbox\DI\Container;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * The Platform Configuration class contains configuration information.
 *
 * @author RocketTheme
 * @license MIT
 */

class Platform extends BasePlatform
{
    protected $name = 'grav';

    public function __construct(Container $container)
    {
        parent::__construct($container);

        // Initialize custom streams for Prime.
        $this->items['streams'] += [
            'gantry-positions' => [
                'type' => 'ReadOnlyStream',
                'prefixes' => [
                    '' => ['gantry-theme://positions']
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    public function getCachePath()
    {
        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        return $locator->findResource('cache://gantry5', true, true);
    }

    /**
     * @return array
     */
    public function getThemesPaths()
    {
        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        return $locator->getPaths('themes');
    }

    public function getMediaPaths()
    {
        return ['' => ['gantry-theme://images', 'page://images', 'user://gantry5/media']];
    }

    public function getEnginesPaths()
    {
        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        if (is_link($locator('plugin://gantry5/engines'))) {
            // Development environment.
            return ['' => ["plugin://gantry5/engines/{$this->name}", 'plugin://gantry5/engines/common']];
        }
        return ['' => ['plugin://gantry5/engines']];
    }

    public function getAssetsPaths()
    {
        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        if (is_link($locator('plugin://gantry5/assets'))) {
            // Development environment.
            return ['' => ['gantry-theme://', "plugin://gantry5/assets/{$this->name}", 'plugin://gantry5/assets/common']];
        }

        return ['' => ['gantry-theme://', 'plugin://gantry5/assets']];
    }


    public function countModules($position)
    {
        return count($this->getModules($position));
    }

    public function getModules($position)
    {
        return (new Position($position))->listModules();
    }

    public function displayModule($id, $attribs = [])
    {
        $module = is_array($id) ? $id : $this->getModule($id);

        // Make sure that module really exists.
        if (!$module || !is_array($module)) {
            return '';
        }

        if (isset($module['assignments'])) {
            $assignments = $module['assignments'];
            if (is_array($assignments)) {
                // TODO: move Assignments to DI to speed it up.
                if (!(new Assignments)->matches(['test' => $assignments])) {
                    return '';
                }
            } elseif ($assignments !== 'all') {
                return '';
            }
        }

        /** @var Theme $theme */
        $theme = $this->container['theme'];

        $html = trim($theme->render('@nucleus/partials/module.html.twig', $attribs + ['segment' => $module]));

        return $html;
    }

    public function displayModules($position, $attribs = [])
    {
        $html = '';
        foreach ($this->getModules($position) as $module) {
            $html .= $this->displayModule($module, $attribs);
        }

        return $html;
    }

    protected function getModule($id)
    {
        list($position, $module) = explode('/', $id, 2);

        return (new Module($module, $position))->toArray();
    }

    /**
     * Get preview url for individual theme.
     *
     * @param string $theme
     * @return null
     */
    public function getThemePreviewUrl($theme)
    {
        return null;
    }

    /**
     * Get administrator url for individual theme.
     *
     * @param string $theme
     * @return string|null
     */
    public function getThemeAdminUrl($theme)
    {
        $grav = Grav::instance();
        return $grav['gantry5_plugin']->base . '/' . $theme;
    }

    public function finalize()
    {
        Document::registerAssets();
    }


    public function settings()
    {
        $grav = Grav::instance();
        return $grav['base_url_relative'] . $grav['admin']->base . '/plugins/gantry5';
    }

    public function truncate($text, $length, $html = false)
    {
        if ($html) {
            return Utils::truncate($text, $length);
        } else {
            return Utils::truncateHtml($text, $length);
        }
    }
}
