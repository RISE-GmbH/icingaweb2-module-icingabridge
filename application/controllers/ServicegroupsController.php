<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Controllers;

use Icinga\Module\Icingadb\View\ServicegroupRenderer;
use Icinga\Module\Icingadb\Widget\ItemTable\ObjectTable;
use Icinga\Module\Icingabridge\Web\Controller;
use ipl\Web\Filter\QueryString;


class ServicegroupsController extends Controller
{


    public function indexAction()
    {
        $this->addTitleTab(t('Service Groups'));
        $sort = $this->params->shift('sort');

        $query = QueryString::render($this->getFilter());


        $all = $this->helper->fetchServicegroups($query, $sort);

        $content = new ObjectTable($all, (new ServicegroupRenderer())->setBaseFilter($this->getFilter()));

        $this->addContent($content);


        $this->setAutorefreshInterval(30);
    }


}
