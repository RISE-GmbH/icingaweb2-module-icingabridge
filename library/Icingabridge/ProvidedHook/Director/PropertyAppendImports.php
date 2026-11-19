<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge\ProvidedHook\Director;

use Exception;
use Icinga\Application\Config;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyAppendImports extends PropertyModifierHook
{


    public function getName()
    {
        return 'Appendimports';
    }

    public function hasArraySupport()
    {
        return true;
    }

    /** @var Db */
    private static $db;

    protected static function directordb()
    {
        if (self::$db === null) {
            $resourceName = Config::module('director')->get('db', 'resource');

            if ($resourceName) {
                self::$db = Db::fromResourceName($resourceName);
            } else {
                throw new Exception(
                    'Director is not configured correctly'
                );
            }
        }

        return self::$db;
    }

    protected static function getHosttemplates()
    {
        $db = self::directordb();
        $funcTemplates = 'enumHostTemplates';

        if (method_exists($db, $funcTemplates)) {
            $templates = $db->$funcTemplates();

            if (!empty($templates)) {
                $templates = array_combine($templates, $templates);
                natsort($templates);

                return $templates;
            }
        }

        return [];
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'add_position', array(
            'label' => $form->translate('Position'),
            'required' => true,
            'value' => 'last',
            'multiOptions' => ['first' => 'First', 'last' => 'Last'],
        ));

        $form->addElement('select', 'additional_template', array(
            'label' => $form->translate('Additional Template'),
            'required' => true,
            'value' => '',
            'multiOptions' => self::getHosttemplates(),
        ));
    }


    public function transform($value)
    {
        if (empty($value)) {
            return $this->emptyValue();
        }
        $template = $this->getSetting('additional_template');
        $db = self::directordb();
        if (IcingaHost::exists($value, $db)) {
            $host = IcingaHost::load($value, $db);
            $imports = $host->getImports();
            if ($this->getSetting('additional_template', 'last') !== 'last') {
                $imports = array_reverse($imports);
            }
            $imports[] = $template;
            $imports = array_unique($imports);

            if ($this->getSetting('additional_template', 'last') !== 'last') {
                $imports = array_reverse($imports);
            }

            return $imports;
        } else {
            return [$template];
        }
    }

    protected function emptyValue()
    {
        if ($this->getSetting('when_empty', 'empty_array') === 'empty_array') {
            return array();
        } else {
            return null;
        }
    }
}
