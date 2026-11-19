<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Controllers;


use Icinga\Module\Icingabridge\Web\Controller;
use Icinga\Module\Icingadb\Widget\ServiceSummaryDonut;
use ipl\Web\Filter\QueryString;


class ServiceTacticalController extends Controller
{

    public function indexAction()
    {
        $this->addTitleTab(t('Service Summary'));


        $this->params->shift('sort');

        $query = QueryString::render($this->getFilter());

        list(, $servicestateSummary) = $this->helper->fetchTactical($query);

        $this->addContent(
            ((new ServiceSummaryDonut($servicestateSummary))->setBaseFilter($this->getFilter()))
        );

        $this->setAutorefreshInterval(10);
    }


}
