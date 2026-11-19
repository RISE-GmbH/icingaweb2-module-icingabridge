<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Controllers;

class PublicHostController extends HostController
{


    protected $requiresAuthentication = false;

    public function init(): void
    {
        $this->initForPublic();
        $this->hostInit($this->params->shiftRequired('name'));
    }


}
