# Installation <a id="module-icingabridge-installation"></a>

## Requirements <a id="module-icingabridge-installation-requirements"></a>

* Icinga Web 2 (&gt;= 2.10.3)
* Icinga Director (&gt;= 1.9.1)
* PHP (&gt;= 7.3)

The Icinga Web 2 `icingadb` module needs to be configured and enabled.
The Icinga Web 2 `cpl` module needs to be enabled to replay checkresults.

## Installation from .tar.gz <a id="module-icingabridge-installation-manual"></a>

Download the latest version and extract it to a folder named `icingabridge`
in one of your Icinga Web 2 module path directories.

## Enable the newly installed module <a id="module-icingabridge-installation-enable"></a>

Enable the `icingabridge` module either on the CLI by running

```sh
icingacli module enable icingabridge
```
## Replay checkresults
In order to replay checkresults add your nagios/icinga user to group icingaweb2

```sh
usermod -aG icingaweb2 nagios
```

Or go to your Icinga Web 2 frontend, choose `Configuration` -&gt; `Modules`, chose the `icingabridge` module and
`enable` it.

It might afterward be necessary to refresh your web browser to be sure that
newly provided styling is loaded.
