# CLI Checks <a id="module-icingabridge-cli-checks"></a>

The module exposes an `icingacli` command that runs a check against a bridged
remote instance. It is intended to be used as a **remote check command**:
The check result is fetched from the remote instance via its API and fed and rendered out including
performance data, so it flows seamlessly into the local dashboard, reporting and allows to trigger notifications.

The command base is:

```sh
icingacli icingabridge check <action> [options]
```

## Actions

### host

Fetches the state of a single remote host.

| Option | Required | Description |
|--------|----------|-------------|
| `--host <hostname>` | **yes** | Name of the remote host. |
| `--instance <instance-name>` | **yes** | Name of the bridged instance. |

### service

Fetches the state of a single remote service.

| Option | Required | Description |
|--------|----------|-------------|
| `--host <hostname>` | **yes** | Name of the remote host. |
| `--service <service-name>` | **yes** | Name of the remote service. |
| `--instance <instance-name>` | **yes** | Name of the bridged instance. |

### objects

Fetches multiple hosts (and optionally their services) by query and aggregates
them into a single check package.

| Option | Required | Description |
|--------|----------|-------------|
| `--instance <instance-name>` | **yes** | Name of the bridged instance. |
| `--hostquery <query>` | no | IcingaDB compatible host query. |
| `--servicequery <query>` | no | Additional service filter applied per host. |
| `--with-services` | no | Include the services of the matched hosts. |
| `--no-hostalive` | no | Skip the synthetic hostalive check. |
| `--prefix-perfdata` | no | Prefix performance data keys with the host/service name. |

## Examples

Check a single remote host:

```sh
icingacli icingabridge check host \
    --host 'monitoring-node02.prod.riseops.example.at' \
    --instance 'Icinga Demo'
```

Check a single remote service:

```sh
icingacli icingabridge check service \
    --host 'monitoring-node02.prod.riseops.example.at' \
    --instance 'Icinga Demo' \
    --service 'check_postgres_replication'
```

Check multiple objects by query, including services:

```sh
icingacli icingabridge check objects \
    --hostquery 'name=monitoring-node02.prod.riseops.example.at' \
    --instance 'Icinga Demo' \
    --with-services
```

Check multiple hosts with an additional service filter and prefixed performance
data:

```sh
icingacli icingabridge check objects \
    --hostquery 'host.groups~*production*' \
    --servicequery 'service.name~check_*' \
    --instance 'Icinga Demo' \
    --with-services \
    --prefix-perfdata
```

## Notes

* The instance must be configured and enabled (see [Instance](03-Configuration.md#module-icingabridge-configuration-instance)).
* The `--hostquery` uses an IcingaDB compatible query string.
* Performance data is rendered out and so it can be used in the local monitoring. 
* Use `--prefix-perfdata`
  to avoid key collisions when several hosts are checked in parallel.
* The command can also be used as a notification command if required.
