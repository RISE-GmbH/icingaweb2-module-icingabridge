# IcingaBridge

An [Icinga Web 2](https://github.com/icinga/icingaweb2) module that bridges
independent Icinga instances into a single frontend.

`icingabridge` connects to a remote Icinga installation either through the full
**IcingaDB Web REST API** or, as a limited fallback, through the **Icinga 2 REST
API (v1)**. This allows the seamless integration of separate instances, for
example between production and test environments or between RISE and customer
systems.

## Features

- **Remote instance bridging** – tactical overview, hosts, services and groups
  of a remote instance, shown directly in your local Icinga Web 2.
- **Two backends** – the primary backend is the IcingaDB Web API; the limited
  Icinga 2 API backend also serves as an **emergency dashboard** when the main
  database is not available.
- **Performance data** – performance data is carried over and flows seamlessly
  into the local monitoring.
- **Director integration** – import hosts/services from a remote instance via an
  Icinga Director import source and run checks against the remote check result.
- **Public views via API key** – publish a single view for a wallboard. API key
  access is restricted to one URL and never logs the user into the system.
- **Database independent** – keeps working even when the database is down.

## Requirements

- Icinga Web 2 (>= 2.10.3)
- Icinga Director (>= 1.9.1)
- PHP (>= 7.3)
- The Icinga Web 2 `monitoring` or `icingadb` module configured and enabled

## Installation

1. Extract the module to a folder named `icingabridge` in one of your Icinga Web
   2 module path directories.
2. Enable the module on the CLI:

   ```sh
   icingacli module enable icingabridge
   ```

   Or enable it via the web UI under `Configuration` → `Modules`.
3. Refresh your browser to load the new styling.

## Quick Start

1. Go to `Configuration` → `Modules` → **icingabridge** → **Instance** and add
   a remote instance (API URL, credentials, backend type).
2. The instance appears in the sidebar with its views.
3. (Optional) Import objects into the Icinga Director using the **Icingabridge**
   import source.
4. (Optional) Publish a view with the **Access URL** config for a wallboard.

## Configuration

See the documentation for details on module settings, instance fields, access
URLs and the Director hooks:

- [About](doc/01-About.md)
- [Installation](doc/02-Installation.md)
- [Configuration](doc/03-Configuration.md)
- [Usage](doc/04-Usage.md)
- [Commands](05-Commands.md)

## Permissions & Restrictions

The module provides the following permissions and restrictions:

| Name | Type | Description |
|------|------|-------------|
| `config/icingabridge` | permission | Allow access to the icingabridge configuration. |
| `icingabridge/instance/filter` | restriction | Allow access to instances matching the filter. |
| `icingabridge/instance/show-menu` | restriction | Render the instances in the sidebar. |

## License

Released under the terms of the **GNU General Public License v3.0 or later**.
See [LICENSE](LICENSE).
