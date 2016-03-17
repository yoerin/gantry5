<?php
/**
 * @package   Gantry 5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2016 RocketTheme, LLC
 * @license   GNU/GPLv2 and later
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */
defined('_JEXEC') or die;

class plgSystemGantry5 extends JPlugin
{
    /**
     * @var JApplicationCms
     */
    protected $app;
    protected $styles;
    protected $modules;

    public function __construct(&$subject, $config = array())
    {
        $this->_name = isset($config['name']) ? $config['name'] : 'gantry5';
        $this->_type = isset($config['type']) ? $config['type'] : 'system';

        $this->app = JFactory::getApplication();

        $this->loadLanguage('plg_system_gantry5.sys');

        JLoader::register('Gantry5\Loader', JPATH_LIBRARIES . '/gantry5/Loader.php');

        // Detect Gantry Framework or fail gracefully.
        if (!class_exists('Gantry5\Loader')) {
            if ($this->app->isAdmin()) {
                $this->app->enqueueMessage(
                    JText::sprintf('PLG_SYSTEM_GANTRY5_LIBRARY_MISSING', JText::_('PLG_SYSTEM_GANTRY5')),
                    'warning'
                );
            }
            return;
        }

        parent::__construct($subject, $config);
    }

    /**
     * Return global configuration for Gantry5.
     */
    public function onGantryGlobalConfig(&$global)
    {
        $global = $this->params->toArray();
    }

    /**
     * Re-route Gantry templates to Gantry Administration component.
     */
    public function onAfterRoute()
    {
        if ($this->app->isSite()) {
             $this->onAfterRouteSite();

        } elseif ($this->app->isAdmin()) {
            $this->onAfterRouteAdmin();
        }
    }

    /**
     * Convert links in com_templates to point into Gantry Administrator component.
     */
    public function onAfterRender()
    {
        if (!$this->app->isAdmin()) {
            return;
        }

        $document = JFactory::getDocument();
        $type   = $document->getType();

        $option = $this->app->input->getString('option');
        $view   = $this->app->input->getString('view', 'g5');
        $task   = $this->app->input->getString('task');

        if (in_array($option, array('com_templates', 'com_advancedtemplates')) && ($view == 'g5' || $view == 'styles') && !$task && $type == 'html') {
            $this->styles = $this->getStyles();

            $body = preg_replace_callback('/(<a\s[^>]*href=")([^"]*)("[^>]*>)(.*)(<\/a>)/siU', array($this, 'appendHtml'), $this->app->getBody());

            $this->app->setBody($body);
        }

        if (($option == 'com_modules' || $option == 'com_advancedmodules') && (($view == 'g5' || $view == 'modules') || empty($view)) && $type == 'html') {
            $db    = JFactory::getDBO();
            $query = $db->getQuery(true);
            $query->select('id, title, params');
            $query->from('#__modules');
            $query->where('module = ' . $db->quote('mod_gantry5_particle'));
            $db->setQuery($query);
            $data = $db->loadObjectList();

            if (sizeof($data) > 0) {
                $this->modules = array();
                $body = $this->app->getBody();

                foreach ($data as $module) {
                    $params   = json_decode($module->params);
                    $particle = isset($params->particle) ? json_decode($params->particle) : '';
                    $title = isset($particle->title) ? $particle->title : (isset($particle->particle) ? $particle->particle : '');

                    $this->modules[$module->id] = $particle;

                    $body = preg_replace_callback('/(<a\s[^>]*href=")([^"]*)("[^>]*>)(.*)(<\/a>)/siU', function($matches) use ($title) {
                        return $this->appendHtml($matches, $title);
                    }, $body);
                }


                $this->app->setBody($body);
            }
        }
    }

    /**
     * Load Gantry framework before dispatching to the component.
     */
    private function onAfterRouteSite()
    {
        $template = $this->app->getTemplate();

        if (!file_exists(JPATH_THEMES . "/{$template}/gantry/theme.yaml")) {
            return;
        }

        $gantryPath = JPATH_THEMES . "/{$template}/includes/gantry.php";

        if (is_file($gantryPath)) {
            // Manually setup Gantry 5 Framework from the template.
            $gantry = include $gantryPath;

            if (!$gantry) {
                throw new \RuntimeException(
                    JText::sprintf("GANTRY5_THEME_LOADING_FAILED", $template, JText::_('GANTRY5_THEME_INCLUDE_FAILED')),
                    500
                );
            }

        } else {

            // Setup Gantry 5 Framework or throw exception.
            Gantry5\Loader::setup();

            // Get Gantry instance.
            $gantry = Gantry\Framework\Gantry::instance();

            // Initialize the template.
            $gantry['theme.path'] = JPATH_THEMES . "/{$template}";
            $gantry['theme.name'] = $template;

            $themePath = $gantry['theme.path'] . '/includes/theme.php';

            include_once $themePath;
        }

        /** @var Gantry\Framework\Theme $theme */
        $theme = $gantry['theme'];

        /** @var \Gantry\Framework\Outlines $configurations */
        $configurations = $gantry['configurations'];

        $theme->setLayout($configurations->current());

        if (!$this->params->get('production', 0) || $this->params->get('asset_timestamps', 1)) {
            $age = (int) ($this->params->get('asset_timestamps_period', 7) * 86400);
            Gantry\Framework\Document::$timestamp_age = $age > 0 ? $age : PHP_INT_MAX;
        } else {
            Gantry\Framework\Document::$timestamp_age = 0;
        }
    }

    /**
     * Re-route Gantry templates to Gantry Administration component.
     */
    private function onAfterRouteAdmin()
    {
        $input = $this->app->input;

        $option = $input->getCmd('option');
        $task   = $input->getCmd('task');

        if (in_array($option, array('com_templates', 'com_advancedtemplates')) && $task && strpos($task, 'style') === 0) {
            // Get all ids.
            $cid = $input->post->get('cid', (array) $input->getInt('id'), 'array');

            if ($cid) {
                $styles = $this->getStyles();
                $selected = array_intersect_key($styles, array_flip($cid));

                // If no Gantry templates were selected, just let com_templates deal with the request.
                if (!$selected) {
                    return;
                }

                // Special handling for tasks coming from com_template.
                if ($task == 'style.edit') {
                    $item = each($selected);
                    $id = $item['key'];
                    $theme = $item['value'];
                    $token = JSession::getFormToken();
                    $this->app->redirect("index.php?option=com_gantry5&view=configurations/{$id}/styles&theme={$theme}&{$token}=1");
                }
            }
        }
    }

    /**
     * Save plugin parameters and trigger the save events.
     *
     * @param array $data
     * @return bool
     * @see JModelAdmin::save()
     */
    public function onGantry5SaveConfig(array $data)
    {
        $name = 'plg_' . $this->_type . '_' . $this->_name;

        // Initialise variables;
        $dispatcher = JEventDispatcher::getInstance();
        $table = JTable::getInstance('Extension');

        // Include the content plugins for the on save events.
        JPluginHelper::importPlugin('extension');

        // Load the row if saving an existing record.
        $table->load(array('type'=>'plugin', 'folder'=>$this->_type, 'element'=>$this->_name));

        $params = new Joomla\Registry\Registry($table->params);
        $params->loadArray($data);

        $table->params = $params->toString();

        // Check the data.
        if (!$table->check()) {
            throw new RuntimeException($table->getError());
        }

        // Trigger the onContentBeforeSave event.
        $result = $dispatcher->trigger('onExtensionBeforeSave', array($name, &$table, false));
        if (in_array(false, $result, true)) {
            throw new RuntimeException($table->getError());
        }

        // Store the data.
        if (!$table->store()) {
            throw new RuntimeException($table->getError());
        }

        // Clean the cache.
        \Gantry\Joomla\CacheHelper::cleanPlugin();

        // Trigger the onExtensionAfterSave event.
        $dispatcher->trigger('onExtensionAfterSave', array($name, &$table, false));

        return true;
    }

    public function onContentBeforeSave($context, $table, $isNew)
    {
        if ($context !== 'com_menus.item') {
            return;
        }
    }

    public function onContentAfterSave($context, $table, $isNew)
    {
        if ($context !== 'com_menus.item') {
            return;
        }
    }

    public function onContentBeforeDelete($context, $table)
    {
        if ($context !== 'com_menus.item') {
            return;
        }
    }

    public function onContentAfterDelete($context, $table)
    {
        if ($context !== 'com_menus.item') {
            return;
        }
    }

    public function onExtensionAfterSave($context, $table, $isNew)
    {
        if ($context !== 'com_templates.style' || $table->client_id || !$this->isGantryTemplate($table->template)) {
            return;
        }

        if (!$isNew) {
            return;
        }

        $template = $table->template;

        $gantry = $this->load($template);
        $registry = new Joomla\Registry\Registry($table->params);
        $old = (int) $registry->get('configuration', 0);
        $new = (int) $table->id;

        if ($old && $old !== $new) {
            Gantry\Joomla\StyleHelper::copy($table, $old, $new);
        }
    }

    public function onExtensionAfterDelete($context, $table)
    {
        if ($context !== 'com_templates.style' || $table->client_id || !$this->isGantryTemplate($table->template)) {
            return;
        }

        $template = $table->template;

        $gantry = $this->load($template);

        Gantry\Joomla\StyleHelper::delete($table->id);
    }

    public function onContentPrepareData($context, $data)
    {
        $name = 'plg_' . $this->_type . '_' . $this->_name;

        // Check that we are manipulating a valid form.
        switch ($context) {
            // TODO: Joomla seems to be missing support for component data manipulation!
/*
            case 'com_config.component':
                // Add plugin parameters under plg_type_name.
                if ($data instanceof JRegistry) {
                    $data->set($name, $this->params->toObject());
                }
                break;
*/

            case 'com_menus.item':
                break;
        }

        return true;
    }

    public function onContentPrepareForm($form, $data)
    {
        // Check that we are manipulating a valid form.
        if (!($form instanceof JForm)) {
            $this->_subject->setError('JERROR_NOT_A_FORM');

            return false;
        }

        $name = 'plg_' . $this->_type . '_' . $this->_name;

        switch ($form->getName()) {
            // TODO: This part works, but Joomla seems to be missing support for component data manipulation!
/*
            case 'com_config.component':
                // If we are editing configuration from Gantry component, add missing fields from system plugin.
                $rules = $form->getField('rules');
                if ($rules && $rules->getAttribute('component') == 'com_gantry5') {
                    $this->loadLanguage('plg_system_gantry5.sys');
                    // Add plugin fields to the form under plg_type_name.
                    $file = file_get_contents(__DIR__."/{$this->_name}.xml");
                    $file = preg_replace('/ name="params"/', " name=\"{$name}\"", $file);
                    $form->load($file, false, '/extension/config');
                }
                break;
*/
            case 'com_menus.items.filter':
                break;

            case 'com_menus.item':
                break;
        }

        return true;
    }

    /**
     * @param array $matches
     * @return string
     */
    private function appendHtml(array $matches, $content = 'Gantry 5')
    {
        $html = $matches[0];

        if (strpos($matches[2], 'task=style.edit') || strpos($matches[2], 'task=module.edit')) {
            $uri = new JUri($matches[2]);
            $id = (int) $uri->getVar('id');

            if ($id && in_array($uri->getVar('option'), array('com_templates', 'com_advancedtemplates', 'com_modules')) && (isset($this->styles[$id]) || isset($this->modules[$id]))) {
                $html = $matches[1] . $uri . $matches[3] . $matches[4] . $matches[5];
                $html .= ' <span class="label" style="background:#439a86;color:#fff;">' . $content . '</span>';

                if ($this->modules[$id]) { unset($this->modules[$id]); }
                else { unset($this->styles[$id]); }
            }
        }

        return $html;
    }

    /**
     * @return array
     */
    private function getStyles()
    {
        $cache = JFactory::getCache('com_templates', '');
        $list = $cache->get('gantry-templates-1');

        if ($list === false) {
            // Load styles
            $db    = JFactory::getDbo();
            $query = $db
                ->getQuery(true)
                ->select('s.id, s.template')
                ->from('#__template_styles as s')
                ->where('s.client_id = 0')
                ->where('e.enabled = 1')
                ->leftJoin('#__extensions as e ON e.element=s.template AND e.type=' . $db->quote('template') . ' AND e.client_id=s.client_id');

            $db->setQuery($query);
            $templates = (array) $db->loadObjectList();

            $list = array();

            foreach ($templates as $template) {
                if ($this->isGantryTemplate($template->template)) {
                    $list[$template->id] = $template->template;
                }
            }

            $cache->store($list, 'gantry-templates-1');
        }

        return $list;
    }

    private function isGantryTemplate($name)
    {
        return file_exists(JPATH_SITE . "/templates/{$name}/gantry/theme.yaml");
    }

    protected function load($name)
    {
        Gantry5\Loader::setup();

        $gantry = \Gantry\Framework\Gantry::instance();

        if (!isset($gantry['theme.name']) || $name !== $gantry['theme.name']) {
            // Restart Gantry and initialize it.
            $gantry = Gantry\Framework\Gantry::restart();
            $gantry['theme.name'] = $name;
            $gantry['streams']->register();

            $patform = $gantry['platform'];
            $locator = $gantry['locator'];

            // Initialize theme stream.
            $details = new Gantry\Component\Theme\ThemeDetails($name);
            $locator->addPath('gantry-theme', '', $details->getPaths(), false, true);

            // Initialize theme cache stream.
            $cachePath = $patform->getCachePath() . '/' . $name;
            Gantry\Component\FileSystem\Folder::create($cachePath);
            $locator->addPath('gantry-cache', 'theme', array($cachePath), true, true);

            \Gantry\Component\File\CompiledYamlFile::$defaultCachePath = $locator->findResource('gantry-cache://theme/compiled/yaml', true, true);
            \Gantry\Component\File\CompiledYamlFile::$defaultCaching = $gantry['global']->get('compile_yaml', 1);
        }

        return $gantry;
    }
}
