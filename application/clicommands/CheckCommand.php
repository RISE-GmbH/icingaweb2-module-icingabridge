<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Clicommands;


use Icinga\Cli\Command;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Cpl\Check\Check;
use Icinga\Module\Cpl\Check\CheckPackage;
use Icinga\Module\Cpl\Check\ScreenWrapper;


use Icinga\Module\Icingabridge\AbstractRestApi;
use Icinga\Module\Icingabridge\Icinga2RestApi;
use Icinga\Module\Icingabridge\IcingadbRestApi;
use Icinga\Module\Icingabridge\InstanceIniRepository;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\ServiceState;

use IMEdge\RrdGraph\Rpn\Log;
use ipl\Stdlib\Filter;
use ipl\Web\Filter\QueryString;

use function Symfony\Component\String\b;

class CheckCommand extends Command
{

    protected $icingaState = [0 => "OK", 1 => "WARNING", 2 => "CRITICAL", 3 => "UNKNOWN"];

    protected function bailOut($code){
        if($code === 404){
            echo "[UNKNOWN] Endpoint not valid for icingadbridge. ($code)\n";
        }elseif($code === 401){
            echo "[UNKNOWN] Invalid credentials for icingabridge. ($code)\n";
        }else{
            echo "[UNKNOWN] Something went wrong using icingabridge. ($code)\n";
        }

        exit(3);
    }
    /**
     * Build the REST API helper for the given Instance
     *
     * Depending on the connection type this returns an IcingaDB Web or an
     * Icinga 2 API helper. Caching and filters are enabled based on the
     * instance configuration.
     */
    public function getHelper($instanceObject): AbstractRestApi
    {
        if ($instanceObject->connection == 'icingadb') {
            $helper = new IcingadbRestApi(
                $instanceObject->apiurl,
                $instanceObject->username,
                $instanceObject->password,
                $instanceObject->apiurl_hash ?? false,
                $instanceObject->apiurl_verify_by_hash_only === "1",
            );
        } elseif ($instanceObject->connection == 'icinga2api') {
            $helper = new Icinga2RestApi(
                $instanceObject->apiurl,
                $instanceObject->username,
                $instanceObject->password,
                $instanceObject->apiurl_hash ?? false,
                $instanceObject->apiurl_verify_by_hash_only === "1"
            );
        } else {
            throw new NotFoundError("Connection unsupported");
        }
        if ($instanceObject->use_cache === "1") {
            $helper->enableCache();
            $helper->setCachePath('/tmp/icingabridge-api-cache/' . $instanceObject->name);
        }
        if ($instanceObject->use_filters === "1") {
            $helper->enableFilters();
        }

        return $helper;
    }

    /**
     * Check the State of a single Remote Host
     *
     * USAGE
     *
     * icingacli icingabridge check host --host <hostname> --instance <instance-name>
     *
     * OPTIONS
     *   --host <hostname>              Name of the remote host (required)
     *   --instance <instance-name>     Name of the bridged instance (required)
     *   --
     */
    public function hostAction()
    {
        $host = $this->params->getRequired("host");
        $instance = $this->params->getRequired("instance");

        $repo = new InstanceIniRepository();
        $instanceObject = $repo->select()->where('name', $instance)->where('enabled', '1')->fetchRow();
        if ($instanceObject === false) {
            throw new NotFoundError("Instance {$instance} not found");
        }

        $helper = $this->getHelper($instanceObject);
        try {
            $helper->login();
            $host = $helper->fetchHost($host);
        }catch (\Throwable $e){
            $this->bailOut($e->getCode());
        }


        $check = new Check(
            'State', $host->state->hard_state,
            false, "", 0, null, null, strtolower("xx"),
            '', ''
        );
        $output = $host->state->output . "\n" . $host->state->long_output;
        if (!preg_match('/^\[(OK|WARNING|CRITICAL|UNKNOWN|UP|DOWN|INFO)\]/', $output)) {
            $output = "[" . $this->icingaState[$host->state->hard_state] . "] " . $output;
        }
        $check->setCustomCheckOutput($output);

        $check->setCustomPerfData($host->state->performance_data);

        $check->setCustomCheckResult($host->state->hard_state);
        $check->evaluate(true);
    }

    /**
     * Check the State of a single Remote Service
     *
     * USAGE
     *
     * icingacli icingabridge check service --host <hostname> --service <service-name> --instance <instance-name>
     *
     * OPTIONS
     *   --host <hostname>              Name of the remote host (required)
     *   --service <service-name>       Name of the remote service (required)
     *   --instance <instance-name>     Name of the bridged instance (required)
     */
    public function serviceAction()
    {
        $host = $this->params->getRequired("host");
        $service = $this->params->getRequired("service");
        $instance = $this->params->getRequired("instance");

        $repo = new InstanceIniRepository();
        $instanceObject = $repo->select()->where('name', $instance)->fetchRow();
        if ($instanceObject === false) {
            throw new NotFoundError("Instance {$instance} not found");
        }


        $helper = $this->getHelper($instanceObject);

        try {
            $helper->login();
            $service = $helper->fetchService($host, $service);
        }catch (\Throwable $e){
            $this->bailOut($e->getCode());
        }

        $check = new Check(
            'State', $service->state->hard_state,
            false, "", 0, null, null, strtolower("xx"),
            '', ''
        );

        $check->setCustomPerfData($service->state->performance_data);

        $output2 = $service->state->output . "\n" . $service->state->long_output;
        if (!preg_match('/^\[(OK|WARNING|CRITICAL|UNKNOWN|UP|DOWN|INFO)\]/', $output2)) {
            $output2 = "[" . $this->icingaState[$service->state->hard_state] . "] " . $output2;
        }
        $check->setCustomCheckResult($service->state->hard_state);
        $check->setCustomCheckOutput($output2);

        $check->evaluate(true);
    }

    /**
     * Check multiple Remote Objects by Query
     *
     * Fetches hosts by query and optionally their services, then aggregates
     * them into a single check package.
     *
     * USAGE
     *
     * icingacli icingabridge check objects --instance <instance-name> [options]
     *
     * OPTIONS
     *   --instance <instance-name>     Name of the bridged instance (required)
     *   --hostquery <query>            IcingaDB compatible host query
     *   --servicequery <query>         Additional service filter applied per host
     *   --with-services                Include the services of the matched hosts
     *   --no-hostalive                 Skip the synthetic hostalive check
     *   --prefix-perfdata              Prefix performance data keys with the host/service name
     */
    public function objectsAction()
    {
        $hostquery = $this->params->get("hostquery", "");
        $servicequery = $this->params->get("servicequery", "");
        $instance = $this->params->getRequired("instance");
        $withServices = $this->params->shift("with-services");
        $noHostalive = $this->params->shift("no-hostalive");
        $prefixPerfdata = $this->params->shift("prefix-perfdata");

        $repo = new InstanceIniRepository();
        $instanceObject = $repo->select()->where('name', $instance)->fetchRow();
        if ($instanceObject === false) {
            throw new NotFoundError("Instance {$instance} not found");
        }


        $helper = $this->getHelper($instanceObject);

        try {
            $helper->login();
            $hosts = $helper->fetchHosts($hostquery, "host.state.severity desc");
        }catch (\Throwable $e){
            $this->bailOut($e->getCode());
        }


        $package = new CheckPackage('Objects on ' . $instance, "AND", true, false, new ScreenWrapper($this->screen));


        foreach ($hosts as $host) {
            $hostPackage = new CheckPackage($host->name, "AND", true, false, new ScreenWrapper($this->screen));

            if (!$noHostalive) {
                $hostAlivePackage = new CheckPackage("Hostalive", "AND", true, false, new ScreenWrapper($this->screen));

                /* @var $host Host */
                $check = new Check(
                    'State', $host->state->hard_state,
                    false, "", 0, null, null, strtolower("xx"),
                    '', ''
                );
                $output = $host->state->output;
                if (!preg_match('/^\[(OK|WARNING|CRITICAL|UNKNOWN|UP|DOWN)\]/', $output)) {
                    $output = "[" . $this->icingaState[$host->state->hard_state] . "] " . $output;
                }
                $check->setCustomCheckOutput($output);
                if ($prefixPerfdata) {
                    $check->setCustomPerfData(
                        $this->prefixPerfdata($host->state->performance_data, $host->name . " " . "hostalive")
                    );
                } else {
                    $check->setCustomPerfData($host->state->performance_data);
                }
                $check->setCustomCheckResult($host->state->hard_state);
                if ($withServices) {
                    $hostAlivePackage->add($check);
                    $hostPackage->add($hostAlivePackage);
                } else {
                    $hostPackage->add($check);
                }
            }


            if ($withServices) {
                $filter = QueryString::parse((string)$servicequery);
                $currentFilter = Filter::all($filter, Filter::equal("host.name", $host->name));
                $hostServiceFilter = QueryString::render($currentFilter);
                $servicePackage = new CheckPackage(
                    "Services on " . $host->name,
                    "AND",
                    true,
                    true,
                    new ScreenWrapper($this->screen)
                );
                try {
                    $services = $helper->fetchServices($hostServiceFilter, "service.state.severity desc");
                }catch (\Throwable $e){
                    $this->bailOut($e->getCode());
                }
                $hostPackage->add($servicePackage);

                foreach ($services as $service) {
                    /* @var $service ServiceState */
                    $check = new Check(
                        'State', $service->state->hard_state,
                        false, "", 0, null, null, strtolower("xx"),
                        '', ''
                    );
                    if ($prefixPerfdata) {
                        $check->setCustomPerfData(
                            $this->prefixPerfdata($service->state->performance_data, $host->name . " " . $service->name)
                        );
                    } else {
                        $check->setCustomPerfData($service->state->performance_data);
                    }
                    $output2 = $service->state->output;
                    if (!preg_match('/^\[(OK|WARNING|CRITICAL|UNKNOWN|UP|DOWN)\]/', $output2)) {
                        $output2 = "[" . $this->icingaState[$service->state->hard_state] . "] " . $output2;
                    }
                    $check->setCustomCheckResult($service->state->hard_state);
                    $check->setCustomCheckOutput($output2);
                    $servicePackage->add($check);
                    if (count($hosts) === 1 && count($services) === 1 && $noHostalive) {
                        $check->evaluate(true);
                    }
                }
                if (count($hosts) === 1 && $noHostalive) {
                    $servicePackage->evaluate(true);
                }
            } else {
                if (count($hosts) === 1) {
                    $check->evaluate(true);
                }
            }


            $package->add($hostPackage);
        }


        $package->evaluate(true);
    }

    protected function prefixPerfdata($perfdata, $prefix)
    {
        $prefix = str_replace(" ", "_", $prefix);
        $prefix = str_replace("__", "_", $prefix);

        $prefix = $prefix . "_";
        return preg_replace_callback(
            '/(?<=^|\s|\|)(\'?)([^\'=]+)\1=/',
            function ($m) use ($prefix) {
                if (str_starts_with($m[2], $prefix)) {
                    return $m[0]; // already prefixed
                }

                return $m[1] . $prefix . $m[2] . $m[1] . '=';
            },
            $perfdata
        );
    }


}
