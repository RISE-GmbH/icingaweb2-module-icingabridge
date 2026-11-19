<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later
// AI-assisted: OpenAI ChatGPT (GPT-5.6 Sol)

namespace Icinga\Module\Icingabridge;


use Exception;
use Icinga\Application\Logger;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\HoststateSummary;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Model\ServicestateSummary;
use ipl\Stdlib\Filter;
use ipl\Web\Filter\QueryString;

class IcingadbFileabased extends IcingadbRestApi
{

    protected $baseUrl = null;
    protected $username = null;
    protected $password = null;
    protected $insecure = null;
    protected $hash;
    protected $scheme = null;
    protected $cookieJar;


    public function __construct($baseUrl, $username, $password, $hash = false, $insecure = false)
    {
        $this->baseUrl = $baseUrl;
    }

    public function login(): void
    {
    }

    public function get($url)
    {
        if (str_contains($url, 'icingadb/services')) {
            return file_get_contents($this->baseUrl . DIRECTORY_SEPARATOR . 'services.json');
        }
        if (str_contains($url, 'icingadb/hosts')) {
            return file_get_contents($this->baseUrl . DIRECTORY_SEPARATOR . 'hosts.json');
        }

        return '';
    }

    public function fetchTacticalFallback(string $query): array
    {
        $data = [];
        try {
            parse_str($query, $params);

            $hostname = $params['host_name'] ?? null;
            $data = json_decode($this->get($this->buildUrl("services", $query, "")), true);
            foreach ($data as $key => $service) {
                if ($hostname === null) {
                    continue;
                }
                if ($hostname !== null && $hostname !== $service['host']['name']) {
                    unset($data[$key]);
                }
            }
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

    public function fetchTactical(string $query, $hostname = false): array
    {
        $obj = $this->fetchTacticalFallback($query);

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


    public function fetchHosts(string $query, $sort): array
    {
        $obj = json_decode($this->get($this->buildUrl("hosts", $query, $sort)), true);

        parse_str($query, $params);

        $hostname = $params['host_name'] ?? null;


        $all = [];
        foreach ($obj as $item) {
            $host = $this->generateHostObject($item);
            if ($hostname === null || $hostname === $host->name) {
                $all[] = $host;
            }
        }

        return $this->sortByKey($all, 'name');
    }

    protected function sortByKey(array $array, string $key): array
    {
        usort($array, function ($a, $b) use ($key) {
            return $a[$key] <=> $b[$key];
        });

        return $array;
    }

    public function fetchHost(string $hostname): ?Host
    {
        $filter = Filter::equal('host.name', $hostname);
        $query = QueryString::render($filter);
        $obj = json_decode($this->get($this->buildUrl("hosts", $query, "")), true);

        foreach ($obj as $item) {
            $host = $this->generateHostObject($item);
            if ($host->name === $hostname) {
                return $host;
            }
        }
        return null;
    }

    public function fetchService($hostname, string $servicename): ?Service
    {
        $filter = Filter::all(
            Filter::equal('service.name', $servicename),
            Filter::equal('host.name', $hostname)
        );
        $query = QueryString::render($filter);
        $obj = json_decode($this->get($this->buildUrl("services", $query, "")), true);

        foreach ($obj as $item) {
            $service = $this->generateServiceObject($item);
            if ($service->name === $servicename && $hostname === $service->host->name) {
                return $service;
            }
        }
        return null;
    }


    public function fetchServices(string $query, $sort): array
    {
        $obj = json_decode($this->get($this->buildUrl("services", $query, $sort)), true);
        $all = [];
        parse_str($query, $params);

        $hostname = $params['host_name'] ?? null;

        foreach ($obj as $item) {
            $service = $this->generateServiceObject($item);
            if ($hostname === null || $hostname === $service->host->name) {
                $all[] = $service;
            }
        }
        return $this->sortByKey($all, 'name');
    }

}
