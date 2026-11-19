# Icinga Web 2 Icingabridge Module Changelog

## What's New

### What's New in Version 0.2.8
* permission fix for running in cli
* added docs for groupmembership requirement of nagios / icinga user
* 
### What's New in Version 0.2.7
* return unknown if there is anything wrong with fetching data via icingabridge

### What's New in Version 0.2.6
* improvements after review

### What's New in Version 0.2.5
* Docs generated with the help of AI, and adapted

### What's New in Version 0.2.4
* auto key name -> object_name for director sync
* code cleanup
* added copyright headers

### What's New in Version 0.2.3

* one minute cache for all requests, in order to reduce api requests.
* additional property modifier to add template to existing one

### What's New in Version 0.2.2

* allow to sync icinga api objects to director
* public access via api key to predefined urls

### What's New in Version 0.2.1

* workarounds for missing filter-expression permission
    * basic gui works with the missing permission
        * what doesn't work
            * Filtering
            * Hostgroups
            * Servicegroups
            * clicking on anything filter related
            * check aggregation for a service query

### What's New in Version 0.2.0

* basic permissions
* basic restrictions
* icingadb tactical fetch fallback using a service query
* experimental icinga2 backend support

### What's New in Version 0.1.0

* Initial release
* Basic functionality
    * Tactical Overview
    * Hosts
    * Services
    * Servicegroups
    * Hostgroups
* Rendering Instances in menu
* PatchedUri for not changing the Icingadb searchquery
* Director Sync rules
* fix for unpatched icingadb

## Feature Backlog
* create access url permission
* check command creation
* ssl hash on store and on update
* handling of unknown
* check for unreachable instance