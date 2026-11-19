<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge;

use Icinga\Application\Modules\Module;
use Icinga\Authentication\Auth;
use Icinga\Util\StringHelper;

class PermissionHelper
{
    public function __construct()
    {
    }

    public function hasPermissionForMenu($instancename)
    {
        return $this->hasPermission($instancename, 'icingabridge/instance/show-menu');
    }

    public function hasPermissionForInstance($instancename)
    {
        return $this->hasPermission($instancename, 'icingabridge/instance/filter');
    }

    public function hasPermission($instance, $type)
    {
        $unrestricted = (Module::get('icingabridge')->getConfig()->get(
                'settings',
                'show_all_for_unrestricted'
            ) ?? "0") == "1";

        foreach ($this->getAuth()->getUser()->getRoles() as $role) {
            if ($this->getAuth()->getUser()->isUnrestricted() && $unrestricted) {
                return true;
            }

            if (($restriction = $role->getRestrictions($type))) {
                if ($restriction !== null) {
                    $multiple = StringHelper::trimSplit($restriction);
                    foreach ($multiple as $partialMatch) {
                        if (fnmatch($partialMatch, $instance)) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    public function getAuth()
    {
        return Auth::getInstance();
    }
}