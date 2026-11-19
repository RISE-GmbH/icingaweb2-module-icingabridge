<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Controllers;


use Icinga\Application\Logger;
use Icinga\Module\Icingadb\Model\HoststateSummary;
use Icinga\Module\Icingabridge\Web\Controller;
use Icinga\Module\Icingadb\Widget\HostStatusBar;
use Icinga\Module\Icingadb\Widget\ItemList\ObjectList;


use ipl\Web\Filter\QueryString;
use Throwable;


class HostsController extends Controller
{


    public function indexAction()
    {
        $this->addTitleTab(t('Hosts'));

        $compact = $this->view->compact;

        $sort = $this->params->shift('sort', 'host.state.severity desc,host.state.last_state_change desc');
        $query = QueryString::render($this->getFilter());
        try {
            if (!$compact) {
                list($hostsSummary,) = $this->helper->fetchTactical($query);
                /** @var HoststateSummary $hostsSummary */
                $this->addFooter((new HostStatusBar($hostsSummary))->setBaseFilter($this->getFilter()));
            }
        } catch (Throwable $e) {
            Logger::error($e->getMessage() . "\n" . $e->getTraceAsString());
        }

        $results = $this->helper->fetchHosts($query, $sort);


        $hostList = (new ObjectList($results));
        $this->addContent($hostList);


        $this->setAutorefreshInterval(10);
    }


}
