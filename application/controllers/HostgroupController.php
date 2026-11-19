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

class HostgroupController extends Controller
{
    /** @var string */
    protected $hostgroupName;

    public function init()
    {
        parent::init();
        $this->hostgroupName = $this->params->shiftRequired('name');
    }

    public function indexAction()
    {
        $filter = $this->getFilter();

        $newFilter = Filter::all($filter, Filter::equal('hostgroup.name', $this->hostgroupName));
        $query = QueryString::render($newFilter);
        $hosts = $this->helper->fetchHosts($query, "host.state.severity desc");


        $hostList = (new ObjectList($hosts));
        $subject = Html::tag('span', ['class' => 'subject'], $this->hostgroupName);
        $title = Html::tag('div', ['class' => 'title'], $subject);
        $main = Html::tag('div', ['class' => 'main'], $title);
        $this->addControl($main);

        $this->addContent($hostList);


        $this->addTitleTab(t('Hostgroup Group'));
        $this->setTitle($this->hostgroupName);
        $this->setAutorefreshInterval(10);
    }
}
