<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2020 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Widget\Detail;

use Icinga\Module\Icingadb\Hook\ExtensionHook\ObjectDetailExtensionHook;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\ServicestateSummary;
use Icinga\Module\Icingadb\Widget\Detail\ObjectDetail;
use Icinga\Module\Icingadb\Widget\Detail\ServiceStatistics;
use ipl\Html\Html;
use ipl\Stdlib\Filter;
use ipl\Web\Widget\EmptyState;

class HostDetail extends ObjectDetail
{
    protected $serviceSummary;

    public function __construct(Host $object, ServicestateSummary $serviceSummary)
    {
        parent::__construct($object);

        $this->serviceSummary = $serviceSummary;
    }

    protected function createServiceStatistics(): array
    {
        if ($this->serviceSummary->services_total > 0) {
            $services = new ServiceStatistics($this->serviceSummary);
            $services->setBaseFilter(Filter::equal('host.name', $this->object->name));
        } else {
            $services = new EmptyState(t('This host has no services'));
        }

        return [Html::tag('h2', t('Services')), $services];
    }

    protected function assemble()
    {
        if (getenv('ICINGAWEB_EXPORT_FORMAT') === 'pdf') {
            $this->add($this->createPrintHeader());
        }

        $this->add(ObjectDetailExtensionHook::injectExtensions([
            1 => $this->createPluginOutput(),
            190 => $this->createServiceStatistics(),

        ], []));
    }
}
