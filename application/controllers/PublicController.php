<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Controllers;


use Icinga\Exception\Http\HttpNotFoundException;
use Icinga\Module\Icingabridge\AccessUrlIniRepository;
use ipl\Web\Compat\CompatController;
use ipl\Web\Widget\Tabs;

class PublicController extends CompatController
{
    protected $requiresAuthentication = false;

    protected $innerLayout = 'otherBody';

    public function getModuleName()
    {
        return "icingadb";
    }

    public function indexAction()
    {
        $this->setAutorefreshInterval(10);
        $apikey = $this->params->getRequired('accesskey');
        $repo = new AccessUrlIniRepository();
        $entry = $repo->select()->where('apikey', $apikey)->fetchRow();

        if ($entry === false) {
            throw new HttpNotFoundException("Apikey not found");
        }
        $tmp = explode("?", $entry->local_url);
        array_pop($tmp);

        $path = array_pop($tmp);
        $tmp = explode("/", $path);
        if (count($tmp) === 2) {
            $tmp[] = "index";
        }

        $action = array_pop($tmp);
        $controller = array_pop($tmp);
        $module = array_pop($tmp);

        if($module !== "icingabridge"){
            throw new \Exception("Invalid module name");
        }
        // from now on we can access every Public*Controller
        $html = $this->view->action(
            $action,
            "public-" . $controller,
            $module,
        );


        $this->getResponse()->setBody($html);

        // Prevent automatic rendering of public.phtml
        $this->_helper->viewRenderer->setNoRender();
        // Usually prevent wrapping the captured action in another layout


    }

    protected function createTabs(): Tabs
    {
        return $this->getTabs()
            ->add('Public', [
                'label' => $this->translate('Public')
            ]);
    }

    protected function setTitleTab(string $name): void
    {
        $tab = $this->createTabs()->get($name);

        if ($tab !== null) {
            $this->getTabs()->activate($name);
        }
    }
}
