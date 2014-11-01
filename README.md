Rock PHP Framework
=================

**Framework is not ready for production use yet.**

[![Latest Stable Version](https://poser.pugx.org/romeOz/rock/v/stable.svg)](https://packagist.org/packages/romeOz/rock)
[![Total Downloads](https://poser.pugx.org/romeOz/rock/downloads.svg)](https://packagist.org/packages/romeOz/rock)
[![Build Status](https://travis-ci.org/romeOz/rock.svg?branch=master)](https://travis-ci.org/romeOz/rock)
[![Coverage Status](https://coveralls.io/repos/romeOz/rock/badge.png?branch=master)](https://coveralls.io/r/romeOz/rock?branch=master)
[![License](https://poser.pugx.org/romeOz/rock/license.svg)](https://packagist.org/packages/romeOz/rock)

[Rock Framework on Packagist](https://packagist.org/packages/romeOz/rock)

Features
-------------------

 * MVC
 * DI
 * Route
 * Template engine (in an independent project [Rock Template](https://github.com/romeOz/rock-template))
    * Snippets (ListView, Pagination,...)
    * HTML Builder (fork by [Yii2](https://github.com/yiisoft/yii2))
    * Widgets (fork by [Yii2](https://github.com/yiisoft/yii2))
 * ORM/DBAL (fork by [Yii2](https://github.com/yiisoft/yii2))
 * DataProviders (DB, Thumb)
 * Events (Pub/Sub)
 * Many different helpers (String, Numeric, ArrayHelper, File, Pagination...)
 * Url Builder
 * DateTime Builder
 * FileManager (abstraction over the [thephpleague/flysystem](https://github.com/thephpleague/flysystem))
 * Sanitize (in an independent project [Rock Sanitize](https://github.com/romeOz/rock-sanitize))
 * Request
 * Response + Formatters (HtmlResponseFormatter, JsonResponseFormatter, XmlResponseFormatter, RssResponseFormatter, SitemapResponseFormatter)
 * Session
 * Cookie
 * i18n
 * Validation (in an independent project [Rock Validate](https://github.com/romeOz/rock-validate))
 * Cache (in an independent project [Rock Cache](https://github.com/romeOz/rock-cache))
 * Behaviors + Filters (AccessFilter, ContentNegotiatorFilter, EventFilter, RateLimiter, SanitizeFilter, ValidationFilters, VerbFilter, TimestampBehavior)
 * Mail
 * Security + Tokenization
 * Markdown (abstraction over the [cebe/markdown](https://github.com/cebe/markdown))
 * RBAC (local or DB)
 * Exception + Logger + Tracing
 * Extensions
    * Sphinx (search engine)
    * phpMorphy (morphological analyzer library for search and other)
    * OAuth/OAuth2 clients (abstraction over the [Lusitanian/PHPoAuthLib](https://github.com/Lusitanian/PHPoAuthLib))
    * Message queue services (ZeroMQ, RabbitMQ, Gearman)


Installation
-------------------

From the Command Line:

`composer create-project --prefer-dist romeoz/rock:1.0.0-beta.3`

Then, to create the structure of the application you must to run `/path/to/framework/rock.sh`.
if you want to create tables `Users` and `Access`, then run with parameter `/path/to/framework/rock.sh -u <username> -p <password>`.

###Configure server

For a single entry point.

####Apache

Security via "white list":

```
RewriteCond %{REQUEST_URI} ^\/(?!index\.php|robots\.txt|500\.html|favicon\.ico||assets\b\/.+\.(?:js|ts|css|ico|xml|swf|flv|pdf|xls|htc|gif|jpg|png|jpeg)$).*$ [NC]
RewriteRule ^.*$ index.php [L]
```

####Nginx

Security via "white list":

```
location ~ ^\/(?!index\.php|robots\.txt|favicon\.ico|500\.html|assets\b\/.+\.(?:js|ts|css|ico|xml|swf|flv|pdf|xls|htc|gif|jpg|png|jpeg)$).*$
{
    rewrite ^.*$ /index.php;
}
```

Demo & Tests
-------------------

Use a specially prepared environment (Vagrant + Ansible) with preinstalled and configured storages.

###Out of the box:

 * Ubuntu 14.04 64 bit

> If you need to use 32 bit of Ubuntu, then uncomment `config.vm.box_url` the appropriate version in the file `/path/to/Vagrantfile`.

 * Nginx 1.6
 * PHP-FPM 5.5
 * Composer
 * MySQL 5.5
 * For caching
    * Couhbase 3.0.0 + pecl couchbase-1.2.2 (**option**)
    * Redis 2.8 + php5-redis (**option**)
    * Memcached 1.4.14 + php5_memcached, php5_memcache
 * For message queue
    * ZeroMQ 4.0.4 + php5-zmq 1.1.2 (**option**)
    * RabbitMQ 3.2.4 (**option**)
    * Gearman 1.0.6 + php5-gearman 1.1.2 (**option**)
 * Local IP loop on Host machine /etc/hosts and Virtual hosts in Nginx already set up!

> To run all services marked `option` you should to uncomment them in the file `/path/to/provisioning/main.yml`.

###Installation:

1. [Install Composer](https://getcomposer.org/doc/00-intro.md#globally)
2. `composer create-project --prefer-dist romeoz/rock:1.0.0-beta.3`
3. [Install Vagrant](https://www.vagrantup.com/downloads), and additional Vagrant plugins `vagrant plugin install vagrant-hostsupdater vagrant-vbguest vagrant-cachier`
4. `vagrant up`
5. Open demo [http://rock/](http://rock/) or [http://192.168.33.37/](http://192.168.33.37/)

> Work/editing the project can be done via ssh:
```bash
vagrant ssh
cd /var/www/
```

Requirements
-------------------
 * **PHP 5.4+**
 * **MySQL 5.5+**

License
-------------------

The Rock PHP Framework is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).