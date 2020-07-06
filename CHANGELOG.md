# Changelog
All notable changes to this project will be documented in [this file](../CHANGELOG.md)
The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic
Versioning](http://semver.org/).

## [1.0.0] - 2020-07-06
### Add
- Fork from [https://github.com/Galillei/magento-sqs](https://github.com/Galillei/magento-sqs)
- Support for PHP 7.2 and 7.3
- System configs
- Queues names mapping
- Workaround for unknown connection name sqs error if there is no deployment config in env.php
- Serialized array parameter for queues names
- Edit the getQueuesListByConnection function to accept more than one connection
 
### Fix
- Fix queue name underscore replacement
- Fix recurring data - removed deprecated functions
- Fix check for empty data and comment
- Fix encryption of API secret
- Fix and css