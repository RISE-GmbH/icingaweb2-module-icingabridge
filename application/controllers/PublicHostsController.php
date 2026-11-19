<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingabridge\Controllers;


use Icinga\Exception\Http\HttpNotFoundException;


class PublicHostsController extends HostsController
{
    protected $requiresAuthentication = false;

    public function init(): void
    {
        $this->initForPublic();
    }


}
