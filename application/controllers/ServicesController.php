<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Controllers;

use Icinga\Module\Icingabridge\Web\Controller;
use Icinga\Module\Icingadb\Model\ServicestateSummary;
use Icinga\Module\Icingadb\Widget\ItemList\ObjectList;
use Icinga\Module\Icingadb\Widget\ServiceStatusBar;
use ipl\Web\Filter\QueryString;
use Throwable;

class ServicesController extends Controller
{

    public function indexAction()
    {
        $this->addTitleTab(t('Services'));
        $compact = $this->view->compact;

        $sort = $this->params->shift('sort', 'service.state.severity DESC');


        $query = QueryString::render($this->getFilter());

        if (!$compact) {
            try {
                list(, $servicesSummary) = $this->helper->fetchTactical($query);
            } catch (Throwable) {
                $servicesSummary = new ServicestateSummary();
                $servicesSummary->services_total;
            }

            $this->addFooter((new ServiceStatusBar($servicesSummary))->setBaseFilter($this->getFilter()));
        }

        $results = $this->helper->fetchServices($query, $sort);
        $serviceList = (new ObjectList($results));

        $this->addContent($serviceList);


        $this->setAutorefreshInterval(10);
    }


}
