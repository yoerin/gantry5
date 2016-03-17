<?php
/**
 * @package   Gantry5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2016 RocketTheme, LLC
 * @license   GNU/GPLv2 and later
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Gantry\Joomla;

use Gantry\Admin\ThemeList;
use Gantry\Component\File\CompiledYamlFile;
use Gantry\Component\Filesystem\Folder;
use Gantry\Component\Layout\Layout;
use Gantry\Framework\Gantry;
use Gantry\Joomla\TemplateInstaller;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Joomla manifest file modifier.
 */
class StyleHelper
{
    public static function getStyle($id)
    {
        \JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_templates/tables');

        $style = \JTable::getInstance('Style', 'TemplatesTable');
        $style->load($id);

        return $style;
    }

    public static function getDefaultStyle()
    {
        return static::getStyle(['home' => 1, 'client_id' => 0]);
    }

    public static function copy($style, $old, $new)
    {
        $gantry = Gantry::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $gantry['locator'];

        $oldPath = $locator->findResource('gantry-config://' . $old, true, true);
        $newPath = $locator->findResource('gantry-config://' . $new, true, true);

        if (file_exists($oldPath)) {
            Folder::copy($oldPath, $newPath);
        }

        $extension = !empty($style->extension_id) ? $style->extension_id : $style->template;

        $installer = new TemplateInstaller($extension);
        $installer->updateStyle($new, ['configuration' => $new]);
    }

    public static function update($id, $preset)
    {
        $style = static::getStyle($id);

        $extension = !empty($style->extension_id) ? $style->extension_id : $style->template;

        $installer = new TemplateInstaller($extension);
        $installer->updateStyle($id, ['configuration' => $id, 'preset' => $preset]);
    }

    public static function delete($id)
    {
        $gantry = Gantry::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $gantry['locator'];

        $path = $locator->findResource('gantry-config://' . $id, true, true);

        if (is_dir($path)) {
            Folder::delete($path, true);
        }
    }

    /**
     * @return \TemplatesModelStyle
     */
    public static function loadModel()
    {
        static $model;

        if (!$model) {
            $path = JPATH_ADMINISTRATOR . '/components/com_templates/';

            \JTable::addIncludePath("{$path}/tables");
            require_once "{$path}/models/style.php";

            // Load language strings.
            $lang = \JFactory::getLanguage();
            $lang->load('com_templates');

            $model = new \TemplatesModelStyle;
        }

        return $model;
    }
}
