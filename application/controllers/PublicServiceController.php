<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Controllers;

use Icinga\Module\Icingadb\Model\Service;

class PublicServiceController extends ServiceController
{

    /** @var Service The service object */
    protected $service;
    protected $requiresAuthentication = false;

    public function init(): void
    {
        $this->initForPublic();
        $this->serviceInit(
            $this->params->shiftRequired('host.name'),
            $this->params->shiftRequired('name')
        );
    }

}
