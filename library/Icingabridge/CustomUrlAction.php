<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Icingabridge;


use Icinga\Web\Url;
use Icinga\Web\Widget\Tabextension\Tabextension;
use Icinga\Web\Widget\Tabs;

/**
 * Tabextension that allows to add the current URL as menu entry
 *
 * Displayed as a dropdown field in the tabs
 */
class CustomUrlAction implements Tabextension
{
    protected $name;
    protected $url;
    protected $icon;
    protected $label;

    /**
     * Applies the menu actions to the provided tabset
     *
     * @param Tabs $tabs The tabs object to extend with
     */
    public function apply(Tabs $tabs)
    {
        $tabs->addAsDropdown(
            $this->getName(),
            array(
                'icon' => $this->getIcon(),
                'label' => t($this->getLabel()),
                'url' => Url::fromPath($this->getUrl()),
                'urlParams' => array(
                    'local_url' => base64_encode(Url::fromRequest()->getRelativeUrl())
                )
            )
        );
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name): Tabextension
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param mixed $label
     */
    public function setLabel($label): Tabextension
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @param mixed $icon
     */
    public function setIcon($icon): Tabextension
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param mixed $url
     */
    public function setUrl($url): Tabextension
    {
        $this->url = $url;
        return $this;
    }

}
