<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later
// AI-assisted: OpenAI ChatGPT (GPT-5.6 Sol)

namespace Icinga\Module\Icingabridge;

use DateTimeInterface;
use Exception;
use GuzzleHttp\Client;
use Icinga\Application\Logger;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Hostgroupsummary;
use Icinga\Module\Icingadb\Model\HoststateSummary;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Model\ServicegroupSummary;
use Icinga\Module\Icingadb\Model\ServicestateSummary;
use ipl\Stdlib\Filter;
use ipl\Web\Filter\QueryString;

class Icinga2RestApi extends AbstractRestApi
{
    use SSLHashUtil;

    protected $baseUrl = null;
    protected $username = null;
    protected $password = null;
    protected $verifyByHash = null;
    protected $hash;
    protected $scheme = null;

    private IcingadbQueryToIcinga2Filter $filterCompiler;
    private Icinga2ApiObjectToIcingadbWebObject $objectMapper;

    private array $hostAttrs = [
        'name',
        'display_name',
        'address',
        'address6',
        'groups',
        'vars',
        'check_command',
        'max_check_attempts',
        'check_period',
        'check_timeout',
        'check_interval',
        'retry_interval',
        'enable_active_checks',
        'enable_passive_checks',
        'enable_event_handler',
        'enable_notifications',
        'enable_flapping',
        'enable_perfdata',
        'event_command',
        'volatile',
        'zone',
        'command_endpoint',
        'notes',
        'notes_url',
        'action_url',
        'icon_image',
        'icon_image_alt',
        'state',
        'state_type',
        'last_state',
        'last_hard_state',
        'last_check_result',
        'last_state_change',
        'last_hard_state_change',
        'check_attempt',
        'next_check',
        'last_check',
        'next_update',
        'acknowledgement',
        'acknowledgement_expiry',
        'downtime_depth',
        'flapping',
        'problem',
        'handled',
        'severity'
    ];
    private array $hostgroupAttrs = [
        'name',
        'display_name',
        'groups',
        'vars',
        'notes',
        'notes_url',
        'action_url',
        'zone'
    ];
    private array $serviceAttrs = [
        'name',
        'host_name',
        'display_name',
        'groups',
        'vars',
        'check_command',
        'max_check_attempts',
        'check_period',
        'check_timeout',
        'check_interval',
        'retry_interval',
        'enable_active_checks',
        'enable_passive_checks',
        'enable_event_handler',
        'enable_notifications',
        'enable_flapping',
        'enable_perfdata',
        'event_command',
        'volatile',
        'zone',
        'command_endpoint',
        'notes',
        'notes_url',
        'action_url',
        'icon_image',
        'icon_image_alt',
        'state',
        'state_type',
        'last_state',
        'last_hard_state',
        'last_check_result',
        'last_state_change',
        'last_hard_state_change',
        'check_attempt',
        'next_check',
        'last_check',
        'next_update',
        'acknowledgement',
        'acknowledgement_expiry',
        'downtime_depth',
        'flapping',
        'problem',
        'handled',
        'severity'
    ];
    private array $servicegroupAttrs = [
        'name',
        'display_name',
        'groups',
        'vars',
        'notes',
        'notes_url',
        'action_url',
        'zone'
    ];

    public function __construct($baseUrl, $username, $password, $hash = false, $verifyByHash = false)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->baseUrl = preg_replace('#/v1(/objects)?/?$#', '', $this->baseUrl);

        $urlParts = parse_url($this->baseUrl);

        $this->scheme = $urlParts['scheme'] ?? 'https';
        $this->username = $username;
        $this->password = $password;
        $this->verifyByHash = $verifyByHash;
        $this->hash = $hash;

        if (!empty($username) && !empty($password)) {
            $this->auth = [$username, $password];
        }

        $this->client = new Client([
            'verify' => !$verifyByHash,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);

        $this->filterCompiler = new IcingadbQueryToIcinga2Filter();
        $this->objectMapper = new Icinga2ApiObjectToIcingadbWebObject();
    }

    public function login(): void
    {
        if ($this->hash !== false && $this->scheme === 'https' && $this->verifyByHash) {
            if ($this->getSSLHash($this->baseUrl) !== $this->hash) {
                throw new Exception('Invalid hash for ssl certificate');
            }
        }

        $this->request('GET', '/v1');
    }

    private function extractTacticalHostQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }

        /*
         * If the tactical query contains service-state conditions, do not apply
         * them to hosts. Keep only host-level restrictions if they exist.
         *
         * This intentionally removes:
         *   service.state.*
         *   service.name
         *   servicegroup.*
         */
        return $this->stripQueryParts($query, [
            'service.',
            'servicegroup.',
            'servicegroups.'
        ]);
    }

    private function extractTacticalServiceQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }

        /*
         * For services we may keep service filters and host filters because the
         * service endpoint can filter on joined host fields.
         */
        return $query;
    }

    private function stripQueryParts(string $query, array $prefixes): string
    {
        $parts = explode('&', $query);
        $kept = [];

        foreach ($parts as $part) {
            $decoded = urldecode($part);
            $trimmed = trim($decoded, '() ');

            $skip = false;

            foreach ($prefixes as $prefix) {
                if (strpos($trimmed, $prefix) === 0) {
                    $skip = true;
                    break;
                }
            }

            if (!$skip) {
                $kept[] = $part;
            }
        }

        return implode('&', $kept);
    }

    public function fetchTactical(string $query, $hostname = false): array
    {
        $hostQuery = $this->extractTacticalHostQuery($query);
        $serviceQuery = $this->extractTacticalServiceQuery($query);

        if ($hostname !== false) {
            $hosts = $this->fetchHostsByHostname($hostname);
            $services = $this->fetchServicesByHostname($hostname);
        } else {
            $hosts = $this->fetchHosts($hostQuery);
            $services = $this->fetchServices($serviceQuery);
        }


        $hoststateSummary = new HoststateSummary();
        $servicestateSummary = new ServicestateSummary();

        $hostFields = [
            'hosts_acknowledged',
            'hosts_active_checks_enabled',
            'hosts_passive_checks_enabled',
            'hosts_down_handled',
            'hosts_down_unhandled',
            'hosts_event_handler_enabled',
            'hosts_flapping_enabled',
            'hosts_notifications_enabled',
            'hosts_pending',
            'hosts_problems_unacknowledged',
            'hosts_total',
            'hosts_up',
        ];

        $serviceFields = [
            'services_acknowledged',
            'services_active_checks_enabled',
            'services_passive_checks_enabled',
            'services_critical_handled',
            'services_critical_unhandled',
            'services_event_handler_enabled',
            'services_flapping_enabled',
            'services_notifications_enabled',
            'services_ok',
            'services_pending',
            'services_problems_unacknowledged',
            'services_total',
            'services_unknown_handled',
            'services_unknown_unhandled',
            'services_warning_handled',
            'services_warning_unhandled',
        ];

        $hoststateSummary->type = 'host_state';
        $servicestateSummary->type = 'service_state';

        foreach ($hostFields as $field) {
            $hoststateSummary->$field = 0;
        }

        foreach ($serviceFields as $field) {
            $hoststateSummary->$field = null;
        }

        foreach ($hostFields as $field) {
            $servicestateSummary->$field = null;
        }

        foreach ($serviceFields as $field) {
            $servicestateSummary->$field = 0;
        }

        foreach ($hosts as $host) {
            $state = $host->state->hard_state ?? null;

            $hoststateSummary->hosts_total++;

            if ($state === null || $state === 99) {
                $hoststateSummary->hosts_pending++;
            } elseif ((int)$state === 0) {
                $hoststateSummary->hosts_up++;
            } elseif ((int)$state === 1) {
                if (!empty($host->state->is_handled)) {
                    $hoststateSummary->hosts_down_handled++;
                } else {
                    $hoststateSummary->hosts_down_unhandled++;
                }
            }

            if (!empty($host->state->is_acknowledged)) {
                $hoststateSummary->hosts_acknowledged++;
            }

            if (!empty($host->active_checks_enabled)) {
                $hoststateSummary->hosts_active_checks_enabled++;
            }

            if (!empty($host->passive_checks_enabled)) {
                $hoststateSummary->hosts_passive_checks_enabled++;
            }

            if (!empty($host->event_handler_enabled)) {
                $hoststateSummary->hosts_event_handler_enabled++;
            }

            if (!empty($host->flapping_enabled)) {
                $hoststateSummary->hosts_flapping_enabled++;
            }

            if (!empty($host->notifications_enabled)) {
                $hoststateSummary->hosts_notifications_enabled++;
            }

            if (!empty($host->state->is_problem) && empty($host->state->is_acknowledged)) {
                $hoststateSummary->hosts_problems_unacknowledged++;
            }
        }

        foreach ($services as $service) {
            $state = $service->state->hard_state ?? null;

            $servicestateSummary->services_total++;

            if ($state === null || $state === 99) {
                $servicestateSummary->services_pending++;
            } elseif ((int)$state === 0) {
                $servicestateSummary->services_ok++;
            } elseif ((int)$state === 1) {
                if (!empty($service->state->is_handled)) {
                    $servicestateSummary->services_warning_handled++;
                } else {
                    $servicestateSummary->services_warning_unhandled++;
                }
            } elseif ((int)$state === 2) {
                if (!empty($service->state->is_handled)) {
                    $servicestateSummary->services_critical_handled++;
                } else {
                    $servicestateSummary->services_critical_unhandled++;
                }
            } elseif ((int)$state === 3) {
                if (!empty($service->state->is_handled)) {
                    $servicestateSummary->services_unknown_handled++;
                } else {
                    $servicestateSummary->services_unknown_unhandled++;
                }
            }

            if (!empty($service->state->is_acknowledged)) {
                $servicestateSummary->services_acknowledged++;
            }

            if (!empty($service->active_checks_enabled)) {
                $servicestateSummary->services_active_checks_enabled++;
            }

            if (!empty($service->passive_checks_enabled)) {
                $servicestateSummary->services_passive_checks_enabled++;
            }

            if (!empty($service->event_handler_enabled)) {
                $servicestateSummary->services_event_handler_enabled++;
            }

            if (!empty($service->flapping_enabled)) {
                $servicestateSummary->services_flapping_enabled++;
            }

            if (!empty($service->notifications_enabled)) {
                $servicestateSummary->services_notifications_enabled++;
            }

            if (!empty($service->state->is_problem) && empty($service->state->is_acknowledged)) {
                $servicestateSummary->services_problems_unacknowledged++;
            }
        }

        return [$hoststateSummary, $servicestateSummary];
    }

    public function fetchHosts(string $query, $sort = null): array
    {
        $payload = $this->buildIcinga2Payload($query, $this->hostAttrs, $sort);

        $result = $this->request('POST', '/v1/objects/hosts', [
            'headers' => [
                'X-HTTP-Method-Override' => 'GET'
            ],
            'json' => $payload
        ]);

        $all = [];

        foreach ($result['results'] ?? [] as $item) {
            $mapped = $this->objectMapper->mapHost($item);
            $all[] = $this->generateHostObject($mapped);
        }

        return $this->sortObjects($all, $sort);
    }

    public function fetchHostsByHostname($hostname): array #this will of course only return one host
    {
        $payload = $this->buildIcinga2Payload('', $this->hostAttrs);
        $payload['host'] = $hostname;
        $result = $this->request('POST', '/v1/objects/hosts', [
            'headers' => [
                'X-HTTP-Method-Override' => 'GET'
            ],
            'json' => $payload
        ]);

        $all = [];

        foreach ($result['results'] ?? [] as $item) {
            $mapped = $this->objectMapper->mapHost($item);
            $all[] = $this->generateHostObject($mapped);
        }

        return $this->sortObjects($all, null);
    }

    public function fetchHost(string $hostname): ?Host
    {
        $payload = $this->buildIcinga2Payload('', $this->hostAttrs);
        if ($this->useFilters) {
            $payload['host'] = $hostname;
        }

        $result = $this->request('POST', '/v1/objects/hosts', [
            'headers' => [
                'X-HTTP-Method-Override' => 'GET'
            ],
            'json' => $payload
        ]);
        foreach ($result['results'] ?? [] as $item) {
            if ($item['attrs']['name'] === $hostname) {
                $mapped = $this->objectMapper->mapHost($item);
                return $this->generateHostObject($mapped);
            }
        }
        return new Host();
    }

    public function fetchServicesByHostname($hostname): array
    {
        $payload = $this->buildIcinga2Payload('', $this->serviceAttrs);
        $payload['joins'] = [
            'host'
        ];

        $result = $this->request('POST', '/v1/objects/services', [
            'headers' => [
                'X-HTTP-Method-Override' => 'GET'
            ],
            'json' => $payload
        ]);

        $all = [];
        foreach ($result['results'] ?? [] as $item) {
            $mapped = $this->objectMapper->mapService($item);

            if (isset($item['joins']['host'])) {
                $mapped['host'] = $this->objectMapper->mapHost(['attrs' => $item['joins']['host']]);
            } else {
                $mapped['host'] = [
                    'name' => $mapped['host_name'] ?? null,
                    'name_ci' => isset($mapped['host_name']) ? strtolower($mapped['host_name']) : null,
                    'display_name' => $mapped['host_name'] ?? null,
                    'state' => null
                ];
            }
            if ($mapped['host_name'] == $hostname) {
                $all[] = $this->generateServiceObject($mapped);
            }
        }

        return $this->sortObjects($all, null);
    }


    public function fetchService(string $hostname, string $servicename): ?Service
    {
        $payload = $this->buildIcinga2Payload('', $this->serviceAttrs);
        if ($this->useFilters) {
            $payload['service'] = $hostname . "!" . $servicename;
        }
        $payload['joins'] = [
            'host'
        ];

        $result = $this->request('POST', '/v1/objects/services', [
            'headers' => [
                'X-HTTP-Method-Override' => 'GET',
            ],
            'json' => $payload
        ]);

        foreach ($result['results'] ?? [] as $item) {
            if ($item['attrs']['name'] == $servicename && $item['attrs']['host_name'] == $hostname) {
                $mapped = $this->objectMapper->mapService($item);

                if (isset($item['joins']['host'])) {
                    $mapped['host'] = $this->objectMapper->mapHost(['attrs' => $item['joins']['host']]);
                } else {
                    $mapped['host'] = [
                        'name' => $mapped['host_name'] ?? null,
                        'name_ci' => isset($mapped['host_name']) ? strtolower($mapped['host_name']) : null,
                        'display_name' => $mapped['host_name'] ?? null,
                        'state' => null
                    ];
                }
                return $this->generateServiceObject($mapped);
            }
        }
        return new Service();
    }

    public function fetchServices(string $query, $sort = null): array
    {
        $payload = $this->buildIcinga2Payload($query, $this->serviceAttrs, $sort);

        $payload['joins'] = [
            'host'
        ];

        $result = $this->request('POST', '/v1/objects/services', [
            'headers' => [
                'X-HTTP-Method-Override' => 'GET'
            ],
            'json' => $payload
        ]);

        $all = [];
        foreach ($result['results'] ?? [] as $item) {
            $mapped = $this->objectMapper->mapService($item);

            if (isset($item['joins']['host'])) {
                $mapped['host'] = $this->objectMapper->mapHost(['attrs' => $item['joins']['host']]);
            } else {
                $mapped['host'] = [
                    'name' => $mapped['host_name'] ?? null,
                    'name_ci' => isset($mapped['host_name']) ? strtolower($mapped['host_name']) : null,
                    'display_name' => $mapped['host_name'] ?? null,
                    'state' => null
                ];
            }
            $all[] = $this->generateServiceObject($mapped);
        }

        return $this->sortObjects($all, $sort);
    }

    private function buildIcinga2Payload(string $query, array $attrs, $sort = null): array
    {
        $payload = [
            'attrs' => $attrs

        ];

        if ($query !== '') {
            $compiled = $this->filterCompiler->fromQueryString($query);

            if (!empty($compiled['filter'])) {
                $payload['filter'] = $compiled['filter'];
            }

            if (!empty($compiled['filter_vars'])) {
                $payload['filter_vars'] = $compiled['filter_vars'];
            }
        }

        if (!empty($sort)) {
            Logger::debug('Ignoring IcingaDB sort for Icinga 2 API backend: ' . $sort);
        }

        return $payload;
    }

    public function fetchHostgroups(string $query, $sort): array
    {
        $payload = [
            'attrs' => $this->hostgroupAttrs
        ];

        $result = $this->request('POST', '/v1/objects/hostgroups', [
            'headers' => [
                'X-HTTP-Method-Override' => 'GET'
            ],
            'json' => $payload
        ]);

        $all = [];

        foreach ($result['results'] ?? [] as $item) {
            $name = $item['attrs']['name'] ?? $item['name'];
            $groupQuery = QueryString::render(Filter::equal('hostgroup.name', $name));

            $hosts = $this->fetchHosts($groupQuery);
            $services = $this->fetchServices($groupQuery);

            $dummy = new Hostgroupsummary();

            $dummy->id = sha1('hostgroup:' . $name);
            $dummy->name = $name;
            $dummy->name_ci = $name;
            $dummy->display_name = $item['attrs']['display_name'] ?? $name;

            $dummy->hosts_down_handled = 0;
            $dummy->hosts_down_unhandled = 0;
            $dummy->hosts_pending = 0;
            $dummy->hosts_total = 0;
            $dummy->hosts_up = 0;
            $dummy->hosts_severity = 0;

            $dummy->services_critical_handled = 0;
            $dummy->services_critical_unhandled = 0;
            $dummy->services_ok = 0;
            $dummy->services_pending = 0;
            $dummy->services_total = 0;
            $dummy->services_unknown_handled = 0;
            $dummy->services_unknown_unhandled = 0;
            $dummy->services_warning_handled = 0;
            $dummy->services_warning_unhandled = 0;

            foreach ($hosts as $host) {
                $state = $host->state->hard_state ?? null;
                $severity = $host->state->severity ?? 0;

                $dummy->hosts_total++;
                $dummy->hosts_severity += (int)$severity;

                if ($state === null || (int)$state === 99) {
                    $dummy->hosts_pending++;
                } elseif ((int)$state === 0) {
                    $dummy->hosts_up++;
                } elseif ((int)$state === 1 || (int)$state === 2) {
                    if ($this->isHandled($host)) {
                        $dummy->hosts_down_handled++;
                    } else {
                        $dummy->hosts_down_unhandled++;
                    }
                }
            }

            foreach ($services as $service) {
                $state = $service->state->hard_state ?? null;

                $dummy->services_total++;

                if ($state === null || (int)$state === 99) {
                    $dummy->services_pending++;
                } elseif ((int)$state === 0) {
                    $dummy->services_ok++;
                } elseif ((int)$state === 1) {
                    if ($this->isHandled($service)) {
                        $dummy->services_warning_handled++;
                    } else {
                        $dummy->services_warning_unhandled++;
                    }
                } elseif ((int)$state === 2) {
                    if ($this->isHandled($service)) {
                        $dummy->services_critical_handled++;
                    } else {
                        $dummy->services_critical_unhandled++;
                    }
                } elseif ((int)$state === 3) {
                    if ($this->isHandled($service)) {
                        $dummy->services_unknown_handled++;
                    } else {
                        $dummy->services_unknown_unhandled++;
                    }
                }
            }

            $all[] = $dummy;
        }

        return $all;
    }

    private function isHandled($object): bool
    {
        $ack = false;
        $downtime = false;

        if (isset($object->acknowledgement)) {
            $ack = (int)$object->acknowledgement !== 0;
        } elseif (isset($object->state->is_acknowledged)) {
            $ack = (bool)$object->state->is_acknowledged;
        }

        if (isset($object->downtime_depth)) {
            $downtime = (int)$object->downtime_depth > 0;
        } elseif (isset($object->state->in_downtime)) {
            $downtime = (bool)$object->state->in_downtime;
        }

        return $ack || $downtime;
    }

    public function fetchServicegroups(string $query, $sort): array
    {
        $payload = [
            'attrs' => $this->servicegroupAttrs
        ];

        $result = $this->request('POST', '/v1/objects/servicegroups', [
            'headers' => [
                'X-HTTP-Method-Override' => 'GET'
            ],
            'json' => $payload
        ]);

        $all = [];

        foreach ($result['results'] ?? [] as $item) {
            $name = $item['attrs']['name'] ?? $item['name'];

            $services = $this->fetchServices(
                QueryString::render(Filter::equal('servicegroup.name', $name))
            );

            $dummy = new ServicegroupSummary();

            $dummy->name = $name;
            $dummy->name_ci = strtolower($name);
            $dummy->display_name = $item['attrs']['display_name'] ?? $name;

            $dummy->services_total = count($services);
            $dummy->services_ok = 0;
            $dummy->services_warning_handled = 0;
            $dummy->services_warning_unhandled = 0;
            $dummy->services_critical_handled = 0;
            $dummy->services_critical_unhandled = 0;
            $dummy->services_unknown_handled = 0;
            $dummy->services_unknown_unhandled = 0;
            $dummy->services_pending = 0;

            foreach ($services as $service) {
                $state = $service->state->hard_state ?? null;

                if ($state === null || (int)$state === 99) {
                    $dummy->services_pending++;
                } elseif ((int)$state === 0) {
                    $dummy->services_ok++;
                } elseif ((int)$state === 1) {
                    if ($this->isHandled($service)) {
                        $dummy->services_warning_handled++;
                    } else {
                        $dummy->services_warning_unhandled++;
                    }
                } elseif ((int)$state === 2) {
                    if ($this->isHandled($service)) {
                        $dummy->services_critical_handled++;
                    } else {
                        $dummy->services_critical_unhandled++;
                    }
                } elseif ((int)$state === 3) {
                    if ($this->isHandled($service)) {
                        $dummy->services_unknown_handled++;
                    } else {
                        $dummy->services_unknown_unhandled++;
                    }
                }
            }

            $all[] = $dummy;
        }

        return $all;
    }

    private function sortObjects(array $objects, ?string $sort): array
    {
        if (empty($sort) || count($objects) < 2) {
            return $objects;
        }

        $sortParts = array_map('trim', explode(',', $sort));

        usort($objects, function ($a, $b) use ($sortParts) {
            foreach ($sortParts as $part) {
                $direction = 1;

                if (preg_match('/\s+desc$/i', $part)) {
                    $direction = -1;
                    $part = preg_replace('/\s+desc$/i', '', $part);
                }

                $av = $this->getSortValue($a, $part);
                $bv = $this->getSortValue($b, $part);

                if ($av == $bv) {
                    continue;
                }

                return ($av <=> $bv) * $direction;
            }

            return 0;
        });

        return $objects;
    }

    private function getSortValue($object, string $field)
    {
        $parts = explode('.', $field);

        /*
         * host.display_name
         * service.state.severity
         * service.state.last_state_change
         */

        if ($parts[0] === 'host' && property_exists($object, 'host')) {
            $current = $object->host;
            array_shift($parts);
        } else {
            $current = $object;
            if ($parts[0] === 'host' || $parts[0] === 'service') {
                array_shift($parts);
            }
        }

        foreach ($parts as $part) {
            if ($current === null) {
                return null;
            }

            if ($current instanceof DateTimeInterface) {
                return $current->getTimestamp();
            }

            if (is_object($current) && isset($current->$part)) {
                $current = $current->$part;
                continue;
            }

            return null;
        }

        if ($current instanceof DateTimeInterface) {
            return $current->getTimestamp();
        }

        return $current;
    }
}