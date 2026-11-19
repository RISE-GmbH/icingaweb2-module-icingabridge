<?php

use Icinga\Module\Icingabridge\ProvidedHook\Director\PropertyAppendImports;

$this->provideHook('director/ImportSource');
$this->provideHook('director/PropertyModifier', PropertyAppendImports::class);