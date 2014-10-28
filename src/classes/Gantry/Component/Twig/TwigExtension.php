<?php
namespace Gantry\Component\Twig;

use Gantry\Framework\Document;
use Gantry\Framework\Gantry;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class TwigExtension extends \Twig_Extension
{
    /**
     * Returns extension name.
     *
     * @return string
     */
    public function getName()
    {
        return 'UrlExtension';
    }

    /**
     * Return a list of all filters.
     *
     * @return array
     */
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('base64', 'base64_encode')
        );
    }

    /**
     * Return a list of all functions.
     *
     * @return array
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('url', [$this, 'urlFunc']),
            new \Twig_SimpleFunction('parseHtmlHeader', [$this, 'parseHtmlHeaderFunc'])
        );
    }

    /**
     * Return URL to the resource.
     *
     * @param  string $input
     * @param  bool $domain
     * @return string
     */
    public function urlFunc($input, $domain = false)
    {
        $gantry = Gantry::instance();
        /** @var UniformResourceLocator $locator */
        $locator = $gantry['locator'];

        $resource = $locator->findResource($input, false);

        return $resource ? Document::rootUri() .'/'. $resource : false;
    }

    /**
     * Move supported document head elements into platform document object, return all
     * unsupported tags in a string.
     *
     * @param $input
     * @return string
     */
    public function parseHtmlHeaderFunc($input, $in_footer = false)
    {
        $doc = new \DOMDocument();
        $doc->loadHTML('<html><head>' . $input . '</head><body></body></html>');
        $raw = [];
        /** @var \DomElement $element */
        foreach ($doc->getElementsByTagName('head')->item(0)->childNodes as $element) {
            $result = ['tag' => $element->tagName, 'content' => $element->textContent];
            foreach ($element->attributes as $attribute) {
                $result[$attribute->name] = $attribute->value;
            }
            $success = Document::addHeaderTag($result, $in_footer);
            if (!$success) {
                $raw[] = $doc->saveHTML($element);
            }
        }

        return implode("\n", $raw);
    }
}
