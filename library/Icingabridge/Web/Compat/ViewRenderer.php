<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2019 Icinga GmbH ipl <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Web\Compat;

use Icinga\Web\View;
use ipl\Html\HtmlDocument;
use Zend_Controller_Action_Helper_ViewRenderer as Zf1ViewRenderer;
use Zend_Controller_Action_HelperBroker as Zf1HelperBroker;

class ViewRenderer extends Zf1ViewRenderer
{
    /** @var View */
    public $view;
    public $instance;

    /**
     * Inject the view renderer
     */
    public static function inject()
    {
        /** @var Zf1ViewRenderer $viewRenderer */
        $viewRenderer = Zf1HelperBroker::getStaticHelper('ViewRenderer');
        $inject = new static();

        foreach (get_object_vars($viewRenderer) as $property => $value) {
            if ($property === '_inflector') {
                continue;
            }

            $inject->$property = $value;
        }

        Zf1HelperBroker::removeHelper('ViewRenderer');
        Zf1HelperBroker::addHelper($inject);
    }

    public function getName()
    {
        return 'ViewRenderer';
    }

    /**
     * Render the view w/o using a view script
     *
     * {@inheritdoc}
     */
    public function render($action = null, $name = null, $noController = null)
    {
        $this->instance = $this->getRequest()->getParam('instance');

        /** @var HtmlDocument $document */
        $document = $this->view->document;

        if ($document->isEmpty() || $this->getRequest()->getParam('error_handler') !== null) {
            parent::render($action, $name, $noController);

            return;
        }

        if ($name === null) {
            $name = $this->getResponseSegment();
        }

        //
        $content = $document->render();
        $content = preg_replace_callback(
            '/(<a\s+[^>]*href=["\'])([^"\']+)(["\'])/i',
            function ($matches) {
                $prefix = $matches[1];   // <a ... href="
                $url = $matches[2];   // actual URL
                $suffix = $matches[3];   // closing quote

                if (strpos($url, 'http://') !== false || strpos($url, 'https://') !== false) {
                    // do not change abolute urls
                    return $prefix . $url . $suffix;
                }
                // 1. Replace path
                $url = str_replace('/icingadb/', '/icingabridge/', $url);
                if (strpos($prefix, 'refresh-container-control') !== false) {
                    return $prefix . $url . $suffix;
                }
                if (strpos($url, '#') !== false || strpos($url, 'navigation/add') !== false || strpos(
                        $url,
                        'dashboard/new'
                    ) !== false) {
                    return $prefix . $url . $suffix;
                }

                // 2. Append instance parameter correctly
                if (strpos($url, '?') !== false) {
                    $url .= '&instance=' . $this->instance;
                } else {
                    $url .= '?instance=' . $this->instance;
                }

                return $prefix . $url . $suffix;
            },
            $content
        );
        //

        $this->getResponse()->appendBody($content, $name);

        $this->setNoRender();
    }
}
