<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Controllers;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Icingadb\Common\Links;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingabridge\Web\Controller;
use Icinga\Module\Icingadb\Widget\Detail\ObjectHeader;
use Icinga\Module\Icingabridge\Widget\Detail\ServiceDetail;
use Icinga\Module\Icingadb\Widget\Detail\ServiceMetaInfo;

use ipl\Html\Html;
use ipl\Web\Widget\Tabs;

class ServiceController extends Controller
{
    /** @var Service The service object */
    protected $service;

    public function init(): void
    {
        parent::init();
        $this->serviceInit(
            $this->params->shiftRequired('host.name'),
            $this->params->shiftRequired('name')
        );
    }

    protected function serviceInit($hostName, $name)
    {
        $service = $this->helper->fetchService($hostName, $name);
        if ($service === null) {
            throw new NotFoundError($this->translate('Service not found'));
        }

        $this->service = $service;
        if ($this->view->compact) {
            $this->addContent(new ObjectHeader($service));
            $this->addContent(Html::tag('h1', []));
        } else {
            $this->addControl(new ObjectHeader($service));
        }

        $this->setTitleTab($this->getRequest()->getActionName());
        $this->setTitle(
            $this->translate('%s on %s', '<service> on <host>'),
            $service->display_name,
            $service->host->display_name
        );
    }

    public function indexAction(): void
    {
        if ($this->service->state->is_overdue) {
            $this->controls->addAttributes(['class' => 'overdue']);
        }

        $this->addControl(new ServiceMetaInfo($this->service));
        $serviceDetails = new ServiceDetail($this->service);
        $this->addContent($serviceDetails);
        $serviceDetails->add(Html::tag('h2', null, "Remote Instance"));
        $url = Links::service($this->service, $this->service->host)->getRelativeUrl();

        $serviceDetails->add(
            Html::tag(
                'a',
                [
                    'target' => '_blank',
                    'href' => trim($this->instanceObject->weburl, "/") . "/" . $url
                ],
                $this->instance
            )
        );
#
        $this->setAutorefreshInterval(10);
    }

    protected function createTabs(): Tabs
    {
        return $this->getTabs()
            ->add('index', [
                'label' => $this->translate('Service'),
                'url' => Links::service($this->service, $this->service->host)
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
