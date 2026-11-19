<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later
// AI-assisted: OpenAI ChatGPT (GPT-5.6 Sol)

namespace Icinga\Module\Icingabridge;

use DateTime;

class Icinga2ApiObjectToIcingadbWebObject
{
    public function mapHost(array $item): array
    {
        $attrs = $item['attrs'] ?? [];

        $name = $attrs['name'] ?? $item['name'] ?? null;

        return [
            'id' => null,
            'environment_id' => null,
            'name_checksum' => null,
            'properties_checksum' => null,

            'name' => $name,
            'name_ci' => $name !== null ? strtolower($name) : null,
            'display_name' => $attrs['display_name'] ?? $name,

            'address' => $attrs['address'] ?? '',
            'address6' => $attrs['address6'] ?? '',
            'address_bin' => null,
            'address6_bin' => null,

            'checkcommand_name' => $attrs['check_command'] ?? null,
            'checkcommand_id' => null,

            'max_check_attempts' => $attrs['max_check_attempts'] ?? null,
            'check_timeperiod_name' => $attrs['check_period'] ?? '',
            'check_timeperiod_id' => null,

            // Icinga 2 API uses seconds, Icinga DB Web JSON often exposes seconds too
            'check_timeout' => $attrs['check_timeout'] ?? null,
            'check_interval' => $attrs['check_interval'] ?? null,
            'check_retry_interval' => $attrs['retry_interval'] ?? null,

            'active_checks_enabled' => $attrs['enable_active_checks'] ?? null,
            'passive_checks_enabled' => $attrs['enable_passive_checks'] ?? null,
            'event_handler_enabled' => $attrs['enable_event_handler'] ?? null,
            'notifications_enabled' => $attrs['enable_notifications'] ?? null,
            'flapping_enabled' => $attrs['enable_flapping'] ?? null,
            'perfdata_enabled' => !empty($attrs['enable_perfdata']) ? 'y' : 'n',

            'eventcommand_name' => $attrs['event_command'] ?? '',
            'eventcommand_id' => null,

            'is_volatile' => $attrs['volatile'] ?? false,

            'action_url_id' => null,
            'notes_url_id' => null,
            'notes' => $attrs['notes'] ?? '',
            'action_url' => $attrs['action_url'] ?? null,
            'notes_url' => $attrs['notes_url'] ?? null,

            'icon_image_id' => null,
            'icon_image_alt' => $attrs['icon_image_alt'] ?? '',
            'icon_image' => [
                'id' => null,
                'icon_image' => $attrs['icon_image'] ?? null,
                'environment_id' => null,
            ],

            'zone_name' => $attrs['zone'] ?? null,
            'zone_id' => null,

            'command_endpoint_name' => $attrs['command_endpoint'] ?? '',
            'command_endpoint_id' => null,

            'groups' => $attrs['groups'] ?? [],
            'vars' => $attrs['vars'] ?? [],

            'total_children' => null,

            'state' => $this->mapState($attrs, 'host'),
        ];
    }

    public function mapService(array $item): array
    {
        $attrs = $item['attrs'] ?? [];

        $objectName = $item['name'] ?? '';
        [$hostName, $serviceName] = $this->splitServiceObjectName($objectName);

        $serviceName = $attrs['name'] ?? $serviceName;

        return [
            'id' => null,
            'environment_id' => null,
            'name_checksum' => null,
            'properties_checksum' => null,

            'name' => $serviceName,
            'name_ci' => $serviceName !== null ? strtolower($serviceName) : null,
            'display_name' => $attrs['display_name'] ?? $serviceName,

            'host_name' => $attrs['host_name'] ?? $hostName,
            'host_id' => null,

            'checkcommand_name' => $attrs['check_command'] ?? null,
            'checkcommand_id' => null,

            'max_check_attempts' => $attrs['max_check_attempts'] ?? null,
            'check_timeperiod_name' => $attrs['check_period'] ?? '',
            'check_timeperiod_id' => null,

            'check_timeout' => $attrs['check_timeout'] ?? null,
            'check_interval' => $attrs['check_interval'] ?? null,
            'check_retry_interval' => $attrs['retry_interval'] ?? null,

            'active_checks_enabled' => $attrs['enable_active_checks'] ?? null,
            'passive_checks_enabled' => $attrs['enable_passive_checks'] ?? null,
            'event_handler_enabled' => $attrs['enable_event_handler'] ?? null,
            'notifications_enabled' => $attrs['enable_notifications'] ?? null,
            'flapping_enabled' => $attrs['enable_flapping'] ?? null,
            'perfdata_enabled' => !empty($attrs['enable_perfdata']) ? 'y' : 'n',

            'eventcommand_name' => $attrs['event_command'] ?? '',
            'eventcommand_id' => null,

            'is_volatile' => $attrs['volatile'] ?? false,

            'action_url_id' => null,
            'notes_url_id' => null,
            'notes' => $attrs['notes'] ?? '',
            'action_url' => $attrs['action_url'] ?? null,
            'notes_url' => $attrs['notes_url'] ?? null,

            'icon_image_id' => null,
            'icon_image_alt' => $attrs['icon_image_alt'] ?? '',
            'icon_image' => [
                'id' => null,
                'icon_image' => $attrs['icon_image'] ?? null,
                'environment_id' => null,
            ],

            'zone_name' => $attrs['zone'] ?? null,
            'zone_id' => null,

            'command_endpoint_name' => $attrs['command_endpoint'] ?? '',
            'command_endpoint_id' => null,

            'groups' => $attrs['groups'] ?? [],
            'vars' => $attrs['vars'] ?? [],

            'state' => $this->mapState($attrs, 'service'),
        ];
    }

    protected function toIcingaPerfdata($perfdata): string
    {
        // Already valid/formatted perfdata
        if (is_string($perfdata)) {
            return $perfdata;
        }

        if (!is_array($perfdata)) {
            return '';
        }

        $result = [];

        foreach ($perfdata as $item) {
            // Inner element is already perfdata
            if (is_string($item)) {
                $result[] = $item;
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            // Parsed PerfdataValue
            if (array_key_exists('value', $item)) {
                $label = str_replace("'", "''", $item['label'] ?? '');

                if ($label === '') {
                    continue;
                }

                $value = $item['value'];
                $unit = $item['unit'] ?? '';
                $warn = $item['warn'] ?? '';
                $crit = $item['crit'] ?? '';
                $min = $item['min'] ?? '';
                $max = $item['max'] ?? '';

                $result[] = sprintf(
                    "'%s'=%s%s;%s;%s;%s;%s",
                    $label,
                    $value,
                    $unit,
                    $warn,
                    $crit,
                    $min,
                    $max
                );
            }
        }

        return implode(' ', $result);
    }

    private function mapState(array $attrs, string $type): array
    {
        $result = $attrs['last_check_result'] ?? [];

        return [
            $type . '_id' => null,
            'environment_id' => null,

            'state_type' => $this->mapStateType($attrs['state_type'] ?? null),

            'soft_state' => $attrs['state'] ?? null,
            'hard_state' => $attrs['last_hard_state'] ?? $attrs['state'] ?? null,

            'previous_soft_state' => $attrs['last_state'] ?? 99,
            'previous_hard_state' => $attrs['last_hard_state'] ?? 99,

            'check_attempt' => $attrs['check_attempt'] ?? null,
            'severity' => $attrs['severity'] ?? 0,

            'output' => $result['output'] ?? null,
            'long_output' => $result['long_output'] ?? null,
            'performance_data' => $this->toIcingaPerfdata($result['performance_data'] ?? ''),
            'normalized_performance_data' => null,

            'check_commandline' => $result['command'] ?? null,

            'is_problem' => $attrs['problem'] ?? false,
            'is_handled' => $attrs['handled'] ?? false,
            'is_reachable' => true,
            'is_flapping' => $attrs['flapping'] ?? false,
            'is_overdue' => false,

            'is_acknowledged' => !empty($attrs['acknowledgement']),
            'acknowledgement_comment_id' => null,

            'last_comment_id' => null,
            'in_downtime' => !empty($attrs['downtime_depth']),

            'execution_time' => $result['execution_time'] ?? 0,
            'latency' => $result['latency'] ?? 0,

            'check_timeout' => isset($attrs['check_timeout'])
                ? (int)($attrs['check_timeout'] * 1000)
                : null,

            'check_source' => $result['check_source'] ?? null,
            'scheduling_source' => null,

            'last_update' => $this->formatTime($result['execution_end'] ?? null),
            'last_state_change' => $this->formatTime($attrs['last_state_change'] ?? null),
            'next_check' => $this->formatTime($attrs['next_check'] ?? null),
            'next_update' => $this->formatTime($attrs['next_update'] ?? null),

            'affects_children' => false,
            'is_sticky_acknowledgement' => false,

            'last_comment' => [
                'id' => null,
                'environment_id' => null,
                'object_type' => null,
                'host_id' => null,
                'service_id' => null,
                'name_checksum' => null,
                'properties_checksum' => null,
                'name' => null,
                'author' => null,
                'text' => null,
                'entry_type' => null,
                'entry_time' => null,
                'is_persistent' => null,
                'is_sticky' => null,
                'expire_time' => null,
                'zone_id' => null,
            ],
        ];
    }

    private function splitServiceObjectName(string $objectName): array
    {
        if (strpos($objectName, '!') === false) {
            return [null, $objectName ?: null];
        }

        return explode('!', $objectName, 2);
    }

    private function mapStateType($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return strtolower($value);
        }

        return ((int)$value) === 1 ? 'hard' : 'soft';
    }

    private function formatTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (new DateTime('@' . (int)$value))
                ->format('Y-m-d\TH:i:s.vP');
        }

        return (new DateTime((string)$value))
            ->format('Y-m-d\TH:i:s.vP');
    }
}