<?php

/**
 * @package   Gantry5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2015 RocketTheme, LLC
 * @license   Dual License: MIT or GNU/GPLv2 and later
 *
 * http://opensource.org/licenses/MIT
 * http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Gantry Framework code that extends GPL code is considered GNU/GPLv2 and later
 */

namespace Gantry\Admin\Controller\Html;

use Gantry\Component\Controller\HtmlController;
use Gantry\Component\Request\Request;
use Gantry\Component\Response\HtmlResponse;
use Gantry\Component\Response\JsonResponse;
use Gantry\Component\Response\Response;
use Gantry\Framework\Outlines as OutlinesObject;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Configurations extends HtmlController
{
    protected $httpVerbs = [
        'GET' => [
            '/'   => 'index',
            '/**' => 'forward',
        ],
        'POST' => [
            '/'            => 'undefined',
            '/*'           => 'undefined',
            '/create'      => 'create',
            '/*/rename'    => 'rename',
            '/*/duplicate' => 'duplicate',
            '/*/**'        => 'forward',
        ],
        'PUT'    => [
            '/'   => 'undefined',
            '/**' => 'forward'
        ],
        'PATCH'  => [
            '/'   => 'undefined',
            '/**' => 'forward'
        ],
        'DELETE' => [
            '/'     => 'undefined',
            '/*'    => 'delete',
            '/*/**' => 'forward'
        ]
    ];

    public function index()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->container['locator'];

        $finder = new \Gantry\Component\Config\ConfigFileFinder();
        $files = $finder->getFiles($locator->findResources('gantry-layouts://'));
        $layouts = array_keys($files);
        sort($layouts);

        $layouts_user = array_filter($layouts, function($val) { return strpos($val, 'presets/') !== 0 && substr($val, 0, 1) !== '_'; });
        $layouts_core = array_filter($layouts, function($val) { return strpos($val, 'presets/') !== 0 && substr($val, 0, 1) === '_'; });
        $this->params['layouts'] = ['user' => $layouts_user, 'core' => $layouts_core];

        return $this->container['admin.theme']->render('@gantry-admin/pages/configurations/configurations.html.twig', $this->params);
    }

    public function create()
    {
        /** @var OutlinesObject $outlines */
        $outlines = $this->container['outlines'];

        $outlines->create($this->request->post['title'], $this->request->post['preset']);

        return new JsonResponse(['html' => 'Configuration created.']);
    }

    public function rename($outline)
    {
        /** @var OutlinesObject $outlines */
        $outlines = $this->container['outlines'];
        $list = $outlines->user();

        if (!isset($list[$outline])) {
            $this->forbidden();
        }

        $outlines->rename($outline, $this->request->post['title']);

        return new JsonResponse(['html' => 'Configuration renamed.']);
    }

    public function duplicate($outline)
    {
        /** @var OutlinesObject $outlines */
        $outlines = $this->container['outlines'];

        // Handle special case on duplicating a preset.
        if ($outline && $outline[0] == '_') {
            $preset = $outlines->preset($outline);
            if (empty($preset)) {
                throw new \RuntimeException('Preset not found');
            }
            $outlines->create(ucwords(trim(str_replace('_', ' ', $outline))), $outline);

            return new JsonResponse(['html' => 'System configuration duplicated.']);
        }

        $list = $outlines->user();

        if (!isset($list[$outline])) {
            $this->forbidden();
        }

        $outlines->duplicate($outline);

        return new JsonResponse(['html' => 'Configuration duplicated.']);
    }

    public function delete($outline)
    {
        /** @var OutlinesObject $outlines */
        $outlines = $this->container['outlines'];
        $list = $outlines->user();

        if (!isset($list[$outline])) {
            $this->forbidden();
        }

        $outlines->delete($outline);

        return new JsonResponse(['html' => 'Configuration deleted.', 'outline' => $outline]);
    }

    public function forward()
    {
        $path = func_get_args();

        $outlines = $this->container['outlines']->toArray();

        $outline = isset($outlines[$path[0]]) ? array_shift($path) : 'default';

        $this->container['outline'] = $outline;
        // TODO: configuration is deprecated; remove.
        $this->container['configuration'] = $outline;

        $method = $this->params['method'];
        $page = (array_shift($path) ?: 'styles');
        $resource = $this->params['location'] . '/'. $page;

        $this->params['outline'] = $outline;
        // TODO: configuration is deprecated; remove.
        $this->params['configuration'] = $outline;
        $this->params['location'] = $resource;
        $this->params['configuration_page'] = $page;
        $this->params['navbar'] = !empty($this->request->get['navbar']);

        return $this->executeForward($resource, $method, $path, $this->params);
    }

    protected function executeForward($resource, $method = 'GET', $path, $params = [])
    {
        $class = '\\Gantry\\Admin\\Controller\\Html\\' . strtr(ucwords(strtr($resource, '/', ' ')), ' ', '\\');
        if (!class_exists($class)) {
            throw new \RuntimeException('Outline not found', 404);
        }

        /** @var HtmlController $controller */
        $controller = new $class($this->container);

        // Execute action.
        $response = $controller->execute($method, $path, $params);

        if (!$response instanceof Response) {
            $response = new HtmlResponse($response);
        }

        return $response;
    }
}
