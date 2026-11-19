<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Controllers;


use Icinga\Module\Icingabridge\Web\Controller;
use Icinga\Module\Icingadb\Widget\ItemList\ObjectList;
use ipl\Html\Html;
use ipl\Stdlib\Filter;
use ipl\Web\Filter\QueryString;


class ServicegroupController extends Controller
{
    /** @var string */
    protected $servicegroupName;

    public function init()
    {
        parent::init();
        $this->servicegroupName = $this->params->shiftRequired('name');
    }


    public function indexAction()
    {
        $filter = $this->getFilter();

        $newFilter = Filter::all($filter, Filter::equal('servicegroup.name', $this->servicegroupName));
        $query = QueryString::render($newFilter);
        $services = $this->helper->fetchServices($query, "service.state.severity desc");


        $serviceList = (new ObjectList($services));

        $this->addContent($serviceList);
        $subject = Html::tag('span', ['class' => 'subject'], $this->servicegroupName);
        $title = Html::tag('div', ['class' => 'title'], $subject);
        $main = Html::tag('div', ['class' => 'main'], $title);
        $this->addControl($main);

        $this->addTitleTab(t('Service Group'));
        $this->setTitle($this->servicegroupName);
        $this->setAutorefreshInterval(10);
    }

}
