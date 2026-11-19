<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later
// AI-assisted: OpenAI ChatGPT (GPT-5.6 Sol)

namespace Icinga\Module\Icingabridge;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Icinga\Application\Logger;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Hostgroupsummary;
use Icinga\Module\Icingadb\Model\HoststateSummary;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Model\ServicegroupSummary;
use Icinga\Module\Icingadb\Model\ServicestateSummary;
use ipl\Stdlib\Filter;
use ipl\Web\Filter\QueryString;

class IcingadbRestApi extends AbstractRestApi
{
    use SSLHashUtil;

    protected $baseUrl = null;
    protected $username = null;
    protected $password = null;
    protected $verifyByHash = null;
    protected $hash;
    protected $scheme = null;
    protected $cookieJar;


    public function __construct($baseUrl, $username, $password, $hash = false, $verifyByHash = false)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $urlParts = parse_url($this->baseUrl);

        $this->scheme = $urlParts['scheme'] ?? "https";
        $this->username = $username;
        $this->password = $password;
        $this->verifyByHash = $verifyByHash;
        $this->hash = $hash;

        $this->cookieJar = new CookieJar(); // in-memory session
        if ($verifyByHash) {
            $this->client = new Client([
                'cookies' => $this->cookieJar,
                'allow_redirects' => true,
                'verify' => false,
                'headers' => [
                    'Accept' => 'application/json',
                ],

            ]);
        } else {
            $this->client = new Client([
                'cookies' => $this->cookieJar,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        }
    }

    public function login(): void
    {
        if ($this->hash !== false && $this->scheme === "https" && $this->verifyByHash) {
            if ($this->getSSLHash($this->baseUrl) !== $this->hash) {
                throw new Exception("Invalid hash for ssl certificate");
            }
        }
        if (!empty($this->username) && !empty($this->password)) {
            $this->auth = [$this->username, $this->password];
            $this->client->request('GET', $this->baseUrl . "/dashboard", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'auth' => $this->auth, // Basic Auth
            ]);
        } else {
            //assume it is proxy auth
            $this->client->request('GET', $this->baseUrl . "/dashboard", [
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        }
    }


    /**
     * Sum all services and unique embedded hosts in an Icinga DB Web
     * service-search export.
     *
     * Every valid service row is counted.
     * Hosts are counted only once, using host id as the unique key.
     *
     * @param array<int, array<string, mixed>> $services
     * @return array{
     *     services: array<string, int>,
     *     hosts: array<string, int>
     * }
     */
    function getGroupSummary(array $services): array
    {
        $serviceSummary = [
            'services_acknowledged' => 0,
            'services_active_checks_enabled' => 0,
            'services_passive_checks_enabled' => 0,
            'services_critical_handled' => 0,
            'services_critical_unhandled' => 0,
            'services_event_handler_enabled' => 0,
            'services_flapping_enabled' => 0,
            'services_notifications_enabled' => 0,
            'services_ok' => 0,
            'services_pending' => 0,
            'services_problems_unacknowledged' => 0,
            'services_total' => 0,
            'services_unknown_handled' => 0,
            'services_unknown_unhandled' => 0,
            'services_warning_handled' => 0,
            'services_warning_unhandled' => 0,
        ];

        $hostSummary = [
            'hosts_acknowledged' => 0,
            'hosts_active_checks_enabled' => 0,
            'hosts_passive_checks_enabled' => 0,
            'hosts_down_handled' => 0,
            'hosts_down_unhandled' => 0,
            'hosts_event_handler_enabled' => 0,
            'hosts_flapping_enabled' => 0,
            'hosts_notifications_enabled' => 0,
            'hosts_pending' => 0,
            'hosts_problems_unacknowledged' => 0,
            'hosts_total' => 0,
            'hosts_unreachable_handled' => 0,
            'hosts_unreachable_unhandled' => 0,
            'hosts_up' => 0,
        ];

        $seenHostIds = [];

        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }

            if (!empty($service['id'])) {
                $state = is_array($service['state'] ?? null)
                    ? $service['state']
                    : [];

                $softState = (int)($state['soft_state'] ?? 99);
                $handled = $this->icingaBool($state['is_handled'] ?? false);
                $reachable = $this->icingaBool($state['is_reachable'] ?? true);
                $acknowledged = $this->icingaBool($state['is_acknowledged'] ?? false);
                $problem = $this->icingaBool($state['is_problem'] ?? false);

                ++$serviceSummary['services_total'];
                $serviceSummary['services_acknowledged'] += (int)$acknowledged;
                $serviceSummary['services_active_checks_enabled']
                    += (int)$this->icingaBool($service['active_checks_enabled'] ?? false);
                $serviceSummary['services_passive_checks_enabled']
                    += (int)$this->icingaBool($service['passive_checks_enabled'] ?? false);
                $serviceSummary['services_event_handler_enabled']
                    += (int)$this->icingaBool($service['event_handler_enabled'] ?? false);
                $serviceSummary['services_flapping_enabled']
                    += (int)$this->icingaBool($service['flapping_enabled'] ?? false);
                $serviceSummary['services_notifications_enabled']
                    += (int)$this->icingaBool($service['notifications_enabled'] ?? false);
                $serviceSummary['services_problems_unacknowledged']
                    += (int)($problem && !$acknowledged);

                switch ($softState) {
                    case 0:
                        ++$serviceSummary['services_ok'];
                        break;
                    case 1:
                        ++$serviceSummary[$handled || !$reachable
                            ? 'services_warning_handled'
                            : 'services_warning_unhandled'];
                        break;
                    case 2:
                        ++$serviceSummary[$handled || !$reachable
                            ? 'services_critical_handled'
                            : 'services_critical_unhandled'];
                        break;
                    case 3:
                        ++$serviceSummary[$handled || !$reachable
                            ? 'services_unknown_handled'
                            : 'services_unknown_unhandled'];
                        break;
                    case 99:
                    default:
                        ++$serviceSummary['services_pending'];
                        break;
                }
            }

            $host = is_array($service['host'] ?? null)
                ? $service['host']
                : null;

            $hostId = is_array($host)
                ? (string)($host['id'] ?? '')
                : '';

            if ($hostId === '' || isset($seenHostIds[$hostId])) {
                continue;
            }

            $seenHostIds[$hostId] = true;

            $state = is_array($host['state'] ?? null)
                ? $host['state']
                : [];

            $softState = (int)($state['soft_state'] ?? 99);
            $handled = $this->icingaBool($state['is_handled'] ?? false);
            $reachable = $this->icingaBool($state['is_reachable'] ?? true);
            $acknowledged = $this->icingaBool($state['is_acknowledged'] ?? false);
            $problem = $this->icingaBool($state['is_problem'] ?? false);

            ++$hostSummary['hosts_total'];
            $hostSummary['hosts_acknowledged'] += (int)$acknowledged;
            $hostSummary['hosts_active_checks_enabled']
                += (int)$this->icingaBool($host['active_checks_enabled'] ?? false);
            $hostSummary['hosts_passive_checks_enabled']
                += (int)$this->icingaBool($host['passive_checks_enabled'] ?? false);
            $hostSummary['hosts_event_handler_enabled']
                += (int)$this->icingaBool($host['event_handler_enabled'] ?? false);
            $hostSummary['hosts_flapping_enabled']
                += (int)$this->icingaBool($host['flapping_enabled'] ?? false);
            $hostSummary['hosts_notifications_enabled']
                += (int)$this->icingaBool($host['notifications_enabled'] ?? false);
            $hostSummary['hosts_problems_unacknowledged']
                += (int)($problem && !$acknowledged);

            switch ($softState) {
                case 0:
                    ++$hostSummary['hosts_up'];
                    break;
                case 1:
                    ++$hostSummary[$handled || !$reachable
                        ? 'hosts_down_handled'
                        : 'hosts_down_unhandled'];
                    break;
                case 2:
                    ++$hostSummary[$handled || !$reachable
                        ? 'hosts_unreachable_handled'
                        : 'hosts_unreachable_unhandled'];
                    break;
                case 99:
                default:
                    ++$hostSummary['hosts_pending'];
                    break;
            }
        }

        return [
            'services' => $serviceSummary,
            'hosts' => $hostSummary,
        ];
    }

    private function icingaBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }

        return in_array(
            strtolower(trim((string)$value)),
            ['1', 'true', 'yes', 'y', 'on'],
            true
        );
    }

    public function fetchTacticalFallback(string $query): array
    {
        $data = [];
        try {
            $data = $this->request('GET', $this->buildUrl("services", $query, ""));
        } catch (Exception $e) {
            Logger::error($e->getCode(), $e->getMessage());
        }
        $obj = $this->getGroupSummary($data);

        $hoststateSummary = new HoststateSummary();
        $servicestateSummary = new ServicestateSummary();
        foreach ($obj as $summary) {
            foreach ($summary as $key => $value) {
                if (strpos($key, "hosts_") === 0) {
                    if (!isset($hoststateSummary->$key)) {
                        $hoststateSummary->$key = 0;
                    }
                    $hoststateSummary->$key += $value;
                }
                if (strpos($key, "services_") === 0) {
                    if (!isset($servicestateSummary->$key)) {
                        $servicestateSummary->$key = 0;
                    }
                    $servicestateSummary->$key += $value;
                }
            }
        }

        return [$hoststateSummary, $servicestateSummary];
    }

    protected function validTactical($array)
    {

        foreach ($array as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'services_')) {
                return true;
            }

            if (is_array($value) && $this->validTactical($value)) {
                return true;
            }
        }

        return false;

    }
    public function fetchTactical(string $query, $hostname = false): array
    {
        $obj = null;
        try {
            $obj = $this->request('GET', $this->buildUrl("tactical", $query, ""));
        } catch (Exception $e) {
            //Logger::error($e->getCode(), $e->getMessage());
        }

        if ($obj == null || !$this->validTactical($obj)) {
            try {
                $obj = $this->fetchTacticalFallback($query);
            } catch (Exception $e) {
                Logger::error($e->getCode(), $e->getMessage());
                throw new Exception('Something went wrong while fetching tactical via workaround');
            }
        }


        $hoststateSummary = new HoststateSummary();
        $servicestateSummary = new ServicestateSummary();
        foreach ($obj as $summary) {
            foreach ($summary as $key => $value) {
                if (strpos($key, "hosts_") === 0) {
                    if (!isset($hoststateSummary->$key)) {
                        $hoststateSummary->$key = 0;
                    }
                    $hoststateSummary->$key += $value;
                }
                if (strpos($key, "services_") === 0) {
                    if (!isset($servicestateSummary->$key)) {
                        $servicestateSummary->$key = 0;
                    }
                    $servicestateSummary->$key += $value;
                }
            }
        }

        return [$hoststateSummary, $servicestateSummary];
    }

    public function fetchServicegroups(string $query, $sort): array
    {
        $obj = $this->request('GET', $this->buildUrl("servicegroups", $query, $sort));

        $all = [];
        foreach ($obj as $item) {
            $dummy = new ServicegroupSummary();
            foreach ($item as $key => $value) {
                $dummy->$key = $value;
            }
            $all[] = $dummy;
        }
        return $all;
    }

    public function fetchHostgroups(string $query, $sort): array
    {
        $obj = $this->request('GET', $this->buildUrl("hostgroups", $query, $sort));

        $all = [];
        foreach ($obj as $item) {
            $dummy = new Hostgroupsummary();
            foreach ($item as $key => $value) {
                $dummy->$key = $value;
            }
            $all[] = $dummy;
        }
        return $all;
    }


    public function buildUrl($controller, $query, $sort)
    {
        $url = sprintf("/icingadb/%s", $controller);

        if (!empty($sort)) {
            if (empty($query)) {
                $query .= sprintf("sort=%s", rawurlencode($sort));
            } else {
                $query .= sprintf("&sort=%s", rawurlencode($sort));
            }
        }

        $uri = new PatchedUri($url);
        return $uri->withQuery($query);
    }

    public function fetchHosts(string $query, $sort): array
    {
        $obj = $this->request('GET', $this->buildUrl("hosts", $query, $sort));

        $all = [];
        foreach ($obj as $item) {
            $all[] = $this->generateHostObject($item);
        }
        return $all;
    }

    public function fetchHost(string $hostname): ?Host
    {
        $query = "";
        if ($this->useFilters) {
            $filter = Filter::equal('host.name', $hostname);
            $query = QueryString::render($filter);
        }
        $obj = $this->request('GET', $this->buildUrl("hosts", $query, ""));

        foreach ($obj as $item) {
            if ($item['name'] == $hostname) {
                return $this->generateHostObject($item);
            }
        }
        return null;
    }

    public function fetchService(string $hostname, string $servicename): ?Service
    {
        $query = "";
        if ($this->useFilters) {
            $filter = Filter::all(
                Filter::equal('service.name', $servicename),
                Filter::equal('host.name', $hostname)
            );
            $query = QueryString::render($filter);
        }

        $obj = $this->request('GET', $this->buildUrl("services", $query, ""));

        foreach ($obj as $item) {
            if ($item['name'] == $servicename && $item['host']['name'] == $hostname) {
                return $this->generateServiceObject($item);
            }
        }
        return null;
    }

    public function fetchServices(string $query, $sort): array
    {
        $obj = $this->request('GET', $this->buildUrl("services", $query, $sort));
        $all = [];
        foreach ($obj as $item) {
            $all[] = $this->generateServiceObject($item);
        }
        return $all;
    }

}
