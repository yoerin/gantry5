<?php
/**
 * @package   Gantry5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2016 RocketTheme, LLC
 * @license   GNU/GPLv2 and later
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Gantry\Framework;

use Gantry\Component\Theme\AbstractTheme;
use Gantry\Component\Theme\ThemeTrait;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class Theme
 * @package Gantry\Framework
 */
class Theme extends AbstractTheme
{
    use ThemeTrait;

    /**
     * @var bool
     */
    protected $joomla = false;

    /**
     * If parameter is set to true, loads bootstrap. Returns true if bootstrap has been loaded.
     *
     * @param bool|null $enable
     * @return bool
     */
    public function joomla($enable = null)
    {
        if ($enable && !$this->joomla) {
            // Workaround for Joomla! not loading bootstrap when it needs it.
            \JHtml::_('bootstrap.framework');

            $this->joomla = true;
        }

        return $this->joomla;
    }

    /**
     * @see AbstractTheme::extendTwig()
     *
     * @param \Twig_Environment $twig
     * @param \Twig_LoaderInterface $loader
     * @return \Twig_Environment
     */
    public function extendTwig(\Twig_Environment $twig, \Twig_LoaderInterface $loader = null)
    {
        parent::extendTwig($twig, $loader);

        // Get user timezone and if not set, use Joomla default.
        $timezone = \JFactory::getUser()->getParam('timezone', \JFactory::getConfig()->get('offset', 'UTC'));

        $twig->getExtension('core')->setTimezone(new \DateTimeZone($timezone));

        return $twig;
    }

    /**
     * @see AbstractTheme::getContext()
     *
     * @param array $context
     * @return array
     */
    public function getContext(array $context)
    {
        $gantry = static::gantry();

        $context = parent::getContext($context);
        $context['site'] = $gantry['site'];
        $context['joomla'] = $gantry['platform'];

        return $context;
    }

    /**
     * @see AbstractTheme::init()
     */
    protected function init()
    {
        parent::init();

        $gantry = Gantry::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $gantry['locator'];

        \JPluginHelper::importPlugin('gantry5');

        // Trigger the onGantryThemeInit event.
        $dispatcher = \JEventDispatcher::getInstance();
        $dispatcher->trigger('onGantry5ThemeInit', ['theme' => $this]);

        $lang = \JFactory::getLanguage();

        // FIXME: Do not hardcode this file.
        $lang->load('files_gantry5_nucleus', JPATH_SITE);

        if (\JFactory::getApplication()->isSite()) {
            // Load our custom positions file as frontend requires the strings to be there.
            $filename = $locator("gantry-theme://language/en-GB/en-GB.tpl_{$this->name}_positions.ini");

            if ($filename) {
                $lang->load("tpl_{$this->name}_positions", dirname(dirname(dirname($filename))), 'en-GB');
            }

            // Load template language files, including overrides.
            $paths = $locator->findResources('gantry-theme://language');
            foreach (array_reverse($paths) as $path) {
                $lang->load("tpl_{$this->name}", dirname($path));
            }
        }

        $doc = \JFactory::getDocument();
        $this->language = $doc->language;
        $this->direction = $doc->direction;
        $this->url = \JUri::root(true) . '/templates/' . $this->name;
    }

    /**
     * @see AbstractTheme::setTwigLoaderPaths()
     *
     * @param \Twig_LoaderInterface $loader
     */
    protected function setTwigLoaderPaths(\Twig_LoaderInterface $loader)
    {
        if (!($loader instanceof \Twig_Loader_Filesystem)) {
            return;
        }

        $gantry = static::gantry();

        /** @var UniformResourceLocator $locator */
        $locator = $gantry['locator'];

        $loader->setPaths($locator->findResources('gantry-engine://twig'));

        parent::setTwigLoaderPaths($loader);
    }
}
