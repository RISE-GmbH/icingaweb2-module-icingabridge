<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\ProvidedHook\Director;

use Exception;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Icingabridge\Icinga2RestApi;
use Icinga\Module\Icingabridge\IcingadbRestApi;
use Icinga\Module\Icingabridge\InstanceIniRepository;
use Icinga\Module\Icingabridge\ObjectResultSetUtils;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;

class ImportSource extends ImportSourceHook
{
    use ObjectResultSetUtils;

    public function getHelper($instanceObject)
    {
        if ($instanceObject->connection == 'icingadb') {
            return new IcingadbRestApi(
                $instanceObject->apiurl,
                $instanceObject->username,
                $instanceObject->password,
                $instanceObject->apiurl_hash ?? false,
                $instanceObject->apiurl_verify_by_hash_only === "1",
            );
        } elseif ($instanceObject->connection == 'icinga2api') {
            return new Icinga2RestApi(
                $instanceObject->apiurl,
                $instanceObject->username,
                $instanceObject->password,
                $instanceObject->apiurl_hash ?? false,
                $instanceObject->apiurl_verify_by_hash_only === "1"
            );
        } else {
            throw new NotFoundError("Connection unsupported");
        }
    }

    protected function getData()
    {
        $mode = $this->getSetting('mode');
        $instance = $this->getSetting('instance');
        $query = $this->getSetting('query');
        $repo = new InstanceIniRepository();
        $instanceObject = $repo->select()->where('name', $instance)->fetchRow();


        if ($instanceObject !== false) {
            $helper = $this->getHelper($instanceObject);

            $helper->login();
            if ($mode === "Hosts") {
                return $helper->fetchHosts($query, "");
            } elseif ($mode === "Services") {
                return $helper->fetchServices($query, "");
            } else {
                throw new Exception("Invalid mode");
            }
        }
        throw new Exception("Invalid instance");
    }

    public function listColumns()
    {
        $mode = $this->getSetting('mode');
        $additional = ['object_name'];
        if ($mode === "Hosts") {
            $model = new Host();
        } elseif ($mode === "Services") {
            $model = new Service();
            $additional[] = 'host';
        }


        return array_merge($model->getColumns(), $additional);
    }

    public function getName()
    {
        return 'Icingabridge';
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->getElement('key_column')->setValue('object_name');

        $repo = new InstanceIniRepository();
        $entries = $repo->select(['name']);
        $multiOptions = [];
        foreach ($entries as $entry) {
            $multiOptions[$entry->name] = $entry->name;
        }
        $form->addElement('select', 'instance', array(
            'label' => $form->translate('Instance name'),
            'required' => true,
            'multiOptions' =>
                $multiOptions

        ));
        $form->addElement('text', 'query', array(
            'label' => $form->translate('Query'),
            'description' => $form->translate('An IcingaDb comaptible search query'),

        ));

        $form->addElement('select', 'mode', array(
                'label' => $form->translate('Object type to import'),
                'description' => $form->translate('Not all object types are supported'),
                'required' => true,
                'multiOptions' =>
                    ['Hosts' => 'Hosts', 'Services' => 'Services'],
            )
        );
    }

    public function fetchData()
    {
        $output = [];
        $mode = $this->getSetting('mode');

        foreach ($this->getData() as $exportable) {
            $arrayElement = $this->createObject($exportable);
            if ($mode === "Hosts") {
                $object_name = $exportable->name;
            } elseif ($mode === "Services") {
                $object_name = $exportable->host->name . "!" . $exportable->name;
            }
            $arrayElement['object_name'] = $object_name;
            $output[] = (object)$arrayElement;
        }

        return $output;
    }
}
