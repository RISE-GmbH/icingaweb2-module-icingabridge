<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2019 Icinga GmbH<https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Controllers;

use Icinga\Exception\NotFoundError;

use Icinga\Module\Icingadb\Common\HostLinks;
use Icinga\Module\Icingadb\Common\Links;

use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingabridge\Web\Controller;
use Icinga\Module\Icingabridge\Widget\Detail\HostDetail;
use Icinga\Module\Icingadb\Model\ServicestateSummary;
use Icinga\Module\Icingadb\Widget\Detail\HostMetaInfo;
use Icinga\Module\Icingadb\Widget\Detail\ObjectHeader;

use Icinga\Module\Icingadb\Widget\ItemList\ObjectList;
use ipl\Html\Html;
use ipl\Stdlib\Filter;
use ipl\Web\Filter\QueryString;
use ipl\Web\Widget\Tabs;
use Throwable;

class HostController extends Controller
{

    /** @var Host The host object */
    protected $host;

    public function init(): void
    {
        parent::init();
        $this->hostInit($this->params->shiftRequired('name'));
    }

    protected function hostInit($name)
    {
        $host = $this->helper->fetchHost($name);


        if ($host === null) {
            throw new NotFoundError($this->translate('Host not found'));
        }

        $this->host = $host;
        //$this->loadTabsForObject($host);

        if ($this->view->compact) {
            $this->addContent(new ObjectHeader($host));
            $this->addContent(Html::tag('h1', []));
        } else {
            $this->addControl(new ObjectHeader($host));
        }

        $this->setTitleTab($this->getRequest()->getActionName());
        $this->setTitle($host->display_name);
    }

    public function indexAction(): void
    {
        try {
            $filter = Filter::equal('host.name', $this->host->name);
            $query = QueryString::render($filter);

            list(, $serviceSummary) = $this->helper->fetchTactical($query, $this->host->name);
        } catch (Throwable) {
            $serviceSummary = new ServicestateSummary();
            $serviceSummary->services_total = 0;
        }


        if ($this->host->state->is_overdue) {
            $this->controls->addAttributes(['class' => 'overdue']);
        }

        $this->addControl(new HostMetaInfo($this->host));
        $hostDetails = new HostDetail($this->host, $serviceSummary);
        $this->addContent($hostDetails);

        $hostDetails->add(Html::tag('h2', null, "Remote Instance"));
        $url = Links::host($this->host)->getRelativeUrl();

        $hostDetails->add(
            Html::tag(
                'a',
                [
                    'target' => '_blank',
                    'href' => trim($this->instanceObject->weburl, "/") . "/" . $url
                ],
                $this->instance
            )
        );


        $this->setAutorefreshInterval(10);
    }

    public function servicesAction()
    {
        if ($this->host->state->is_overdue) {
            $this->controls->addAttributes(['class' => 'overdue']);
        }
        $filter = Filter::equal('host.name', $this->host->name);
        $query = QueryString::render($filter);

        $services = $this->helper->fetchServices($query, "service.state.severity desc");

        $serviceList = (new ObjectList($services));


        $this->addContent($serviceList);

        $this->setAutorefreshInterval(10);
    }

    protected function createTabs(): Tabs
    {
        return $this->getTabs()
            ->add('index', [
                'label' => $this->translate('Host'),
                'url' => Links::host($this->host)
            ])
            ->add('services', [
                'label' => $this->translate('Services'),
                'url' => HostLinks::services($this->host)
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
