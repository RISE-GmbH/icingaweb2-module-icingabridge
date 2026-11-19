<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2022 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge;

use DateTime;
use DateTimeZone;
use ipl\Orm\Model;

trait ObjectResultSetUtils
{

    protected function formatValue(string $key, $value)
    {
        if (
            $value
            && (
                $key === 'id'
                || substr($key, -3) === '_id'
                || substr($key, -3) === '.id'
                || substr($key, -9) === '_checksum'
                || substr($key, -4) === '_bin'
            )
        ) {
            $value = bin2hex($value);
        }

        if ($value instanceof DateTime) {
            return $value->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s.vP');
        }

        return $value;
    }

    protected function createObject(Model $model): array
    {
        $keysAndValues = [];
        foreach ($model as $key => $value) {
            if ($key === "state") {
                continue;
            }
            if ($value instanceof Model) {
                $keysAndValues[$key] = $this->createObject($value);
            } else {
                $keysAndValues[$key] = $this->formatValue($key, $value);
            }
        }

        return $keysAndValues;
    }


}
