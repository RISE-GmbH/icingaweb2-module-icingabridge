<?php

/** @var Module $this */

use Icinga\Application\Icinga;
use Icinga\Application\Modules\Module;
use Icinga\Authentication\Auth;
use Icinga\Module\Icingabridge\InstanceIniRepository;
use Icinga\Module\Icingabridge\PermissionHelper;


$this->providePermission('config/icingabridge', $this->translate('allow access to icingabridge configuration'));


$this->provideRestriction(
    'icingabridge/instance/filter',
    $this->translate('allow access to instances that matche the filter')
);
$this->provideRestriction('icingabridge/instance/show-menu', $this->translate('render the instances in the sidebar'));

$this->provideConfigTab('config/instance', array(
    'title' => $this->translate('Instance'),
    'label' => $this->translate('Instance'),
    'url' => 'instance'
));
$this->provideConfigTab('config/accessurl', array(
    'title' => $this->translate('Access URL'),
    'label' => $this->translate('Access URL'),
    'url' => 'accessurl'
));


$this->provideConfigTab('config/moduleconfig', array(
    'title' => $this->translate('Module Configuration'),
    'label' => $this->translate('Module Configuration'),
    'url' => 'moduleconfig'
));

if (Auth::getInstance()->isAuthenticated() && Icinga::app()->isWeb()) {
    $repo = new InstanceIniRepository();
    foreach ($repo->select()->where('enabled', '1')->fetchAll() as $entry) {
        $permissionHelper = new PermissionHelper();

        if (!$permissionHelper->hasPermissionForMenu($entry->name)) {
            continue;
        }

        /** @var Module $this */
        $section = $this->menuSection($entry->name, [
            'icon' => 'database',
            'priority' => 30
        ]);
        $section->add(N_('Tactical Overview'), [
            'url' => 'icingabridge/tactical?instance=' . $entry->name,

            'priority' => 10,
            'description' => $this->translate('Open tactical overview'),
            'icon' => 'chart-pie'
        ]);

        $section->add(N_('Hosts'), [
            'priority' => 20,
            'description' => $this->translate('List hosts'),
            'url' => 'icingabridge/hosts?instance=' . $entry->name,
            'icon' => 'server'
        ]);
        $section->add(N_('Services'), [
            'priority' => 30,
            'description' => $this->translate('List services'),
            'url' => 'icingabridge/services?instance=' . $entry->name,
            'icon' => 'cog'
        ]);
        $section->add(N_('Host Groups'), [
            'url' => 'icingabridge/hostgroups?instance=' . $entry->name,
            'priority' => 50,
            'description' => $this->translate('List host groups'),
            'icon' => 'network-wired'
        ]);


        $section->add(N_('Service Groups'), [
            'url' => 'icingabridge/servicegroups?instance=' . $entry->name,
            'priority' => 60,
            'description' => $this->translate('List service groups'),
            'icon' => 'cogs'
        ]);
    }
}


?>
