<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\Web;

use Icinga\Exception\Http\HttpMethodNotAllowedException;
use Icinga\Exception\Http\HttpNotFoundException;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Icingabridge\AccessUrlIniRepository;
use Icinga\Module\Icingabridge\CustomUrlAction;
use Icinga\Module\Icingabridge\Icinga2RestApi;
use Icinga\Module\Icingabridge\IcingadbFileabased;
use Icinga\Module\Icingabridge\IcingadbRestApi;
use Icinga\Module\Icingabridge\InstanceIniRepository;
use Icinga\Module\Icingabridge\PermissionHelper;
use Icinga\Module\Icingabridge\Web\Compat\ViewRenderer;
use Icinga\Web\UrlParams;
use ipl\Web\Compat\CompatController;
use ipl\Web\Filter\QueryString;
use ipl\Stdlib\Filter;

class Controller extends CompatController
{

    protected $helper = null;
    protected $filter = null;
    protected $instance = null;
    protected $instanceObject = null;

    protected function prepareInit()
    {
        parent::prepareInit();
        ViewRenderer::inject();
    }

    public function getModuleName()
    {
        return 'icingadb';
    }

    public function getFilter(): Filter\Rule
    {
        if ($this->filter === null) {
            $this->filter = QueryString::parse((string)$this->params);
        }

        return $this->filter;
    }

    protected function initForPublic()
    {
        $apikey = $this->params->shift('accesskey');
        $repo = new AccessUrlIniRepository();
        $entry = $repo->select()->where('apikey', $apikey)->fetchRow();

        if ($entry === false) {
            throw new HttpNotFoundException("Apikey not found");
        }


        $query = parse_url($entry->local_url, PHP_URL_QUERY);
        $params = UrlParams::fromQueryString($query);
        $this->instance = $params->shiftRequired('instance');

        $this->params = $params;
        $this->view->compact = true;
        $this->view->showFullscreen = true;

        $this->helper = $this->getInstanceHelper($this->instance);
        $this->helper->login();
    }

    public function init()
    {
        $ext = new CustomUrlAction();
        $ext->setUrl("icingabridge/accessurl/new");

        $ext->setLabel('Publish');
        $ext->setName('icingbridge-publish');
        $ext->setIcon('pencil');

        $this->getTabs()->extend($ext);

        // parent::init(); // we dont need that
        $this->instance = $this->params->shiftRequired('instance');
        $permissionHelper = new PermissionHelper();

        if (!$permissionHelper->hasPermissionForInstance($this->instance)) {
            throw new HttpMethodNotAllowedException("You are not allowed to access this resource.");
        }
        $this->params->shift('format');

        $this->helper = $this->getInstanceHelper($this->instance);
        $this->helper->login();
    }

    protected function getInstanceHelper($instance)
    {
        $repo = new InstanceIniRepository();
        $instanceObject = $repo->select()->where('name', $instance)->where('enabled', '1')->fetchRow();
        if ($instanceObject === false) {
            throw new NotFoundError("Instance {$instance} not found");
        }
        $this->instanceObject = $instanceObject;

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
        } elseif ($instanceObject->connection == 'icingadbfilebased') {
            $helper = new IcingadbFileabased(
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


}
