<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2020 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge;

use DateTime;
use GuzzleHttp\Client;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\HostState;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Model\ServiceState;
use RuntimeException;

abstract class AbstractRestApi
{
    protected Client $client;
    protected $baseUrl;
    protected array $auth = [];

    protected bool $useCache = false;
    protected bool $useFilters = false;
    protected string $cachePath = '/tmp/icingabridge-api-cache';

    public abstract function __construct($baseUrl, $username, $password, $hash = false, $verifyByHash = false);

    public function setCachePath($cachePath)
    {
        $this->cachePath = $this->safeDirectoryPath($cachePath);
    }

    public function enableFilters()
    {
        $this->useFilters = true;
    }

    public function enableCache()
    {
        $this->useCache = true;
    }

    protected function cleanupCache(): void
    {
        if (!is_dir($this->cachePath)) {
            return;
        }

        $cutoff = time() - 120;

        foreach (glob($this->cachePath . '/*.json') as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    protected function safeDirectoryPath(string $path): string
    {
        $path = trim($path);

        // Allow letters, numbers, dot, underscore, dash and slash
        $path = preg_replace('/[^a-zA-Z0-9._\/-]+/', '_', $path);

        return $path !== '' ? $path : '_';
    }

    protected function request(string $method, string $path, array $options = []): array
    {
        if (!empty($this->auth)) {
            $options['auth'] = $this->auth;
        }

        $url = $this->baseUrl . $path;


        $hash = hash('sha256', serialize([$method, $url, $options]));
        $cacheFile = $this->cachePath . '/' . $hash . '_' . date('Y-m-d-H-i') . '.json';

        if ($this->useCache && is_file($cacheFile)) {
            $decoded = json_decode(file_get_contents($cacheFile), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $options['curl'][CURLOPT_URL] = $url;

        $response = $this->client->request($method, $url, $options);

        $content = $response->getBody()->getContents();
        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON returned from Icinga 2 API');
        }

        if ($this->useCache) {
            if (!is_dir($this->cachePath)) {
                mkdir($this->cachePath, 0777, true);

            }

            file_put_contents($cacheFile, $content);
            chmod($cacheFile, 0777);
            $this->cleanupCache();
        }

        return $decoded;
    }

    public abstract function login();

    public abstract function fetchHosts(string $query, $sort): array;

    public abstract function fetchHost(string $hostname): ?Host;

    public abstract function fetchService(string $hostname, string $servicename): ?Service;

    public abstract function fetchServices(string $query, $sort): array;

    public abstract function fetchServicegroups(string $query, $sort): array;

    public abstract function fetchHostgroups(string $query, $sort): array;

    public abstract function fetchTactical(string $query, $hostname = false): array;

    public function generateHostObject($item): Host
    {
        $obj = new Host();

        foreach ($item as $key => $value) {
            if ($key === 'state' && is_array($value)) {
                $obj->$key = $this->generateStateObject($value, 'host');
            } else {
                $obj->$key = $value;
            }
        }

        return $obj;
    }

    public function generateServiceObject($item): Service
    {
        $obj = new Service();

        foreach ($item as $key => $value) {
            if ($key === 'state' && is_array($value)) {
                $obj->$key = $this->generateStateObject($value, 'service');
            } elseif ($key === 'host' && is_array($value)) {
                $obj->$key = $this->generateHostObject($value);
            } else {
                $obj->$key = $value;
            }
        }
        //fallback if join does not exist
        if (!isset($obj->host) || !$obj->host instanceof Host) {
            $obj->host = $this->fetchHost($item['host_name']);
        }
        return $obj;
    }

    public function generateStateObject($item, $kind)
    {
        $datetimes = [
            'last_update',
            'last_state_change',
            'last_hard_state_change',
            'next_check',
            'next_update'
        ];

        $state = $kind === 'host'
            ? new HostState()
            : new ServiceState();

        foreach ($item as $stateKey => $stateValue) {
            if (in_array($stateKey, $datetimes, true) && !empty($stateValue)) {
                $stateValue = new DateTime($stateValue);
            }

            $state->$stateKey = $stateValue;
        }

        return $state;
    }

}