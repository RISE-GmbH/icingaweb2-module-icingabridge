<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2020 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Widget\Detail;

use Icinga\Module\Icingadb\Hook\ExtensionHook\ObjectDetailExtensionHook;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Widget\Detail\ObjectDetail;

class ServiceDetail extends ObjectDetail
{
    public function __construct(Service $object)
    {
        parent::__construct($object);
    }

    protected function assemble()
    {
        if (getenv('ICINGAWEB_EXPORT_FORMAT') === 'pdf') {
            $this->add($this->createPrintHeader());
        }

        $this->add(ObjectDetailExtensionHook::injectExtensions([
            1 => $this->createPluginOutput(),
        ], []));
    }
}
