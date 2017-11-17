freifunk-versions
=================

[![Build Status](https://travis-ci.org/freifunkhamburg/freifunk-versions.svg?branch=master)](https://travis-ci.org/freifunkhamburg/freifunk-versions)

Wordpress plugin to render latest (Hamburg-flavoured)
[gluon firmware](https://github.com/freifunkhamburg/gluon) version table.

Provides shortcode `[ff_hh_versions]` to display table.
Input data is read from i.e.
https://updates.hamburg.freifunk.net/ffhh/stable/sysupgrade/stable.manifest

Output looks like this:

![shortcode output example](http://mschuette.name/wp/wp-upload/freifunk_versions.png)

Arguments
---------
The optional argument `domain` (default: `ffhh`) allows you to choose the
firmware's domain.

Example:

```
    Hamburg-Süd Firmware: [ff_hh_versions domain="ffhh-sued"]
```

The optional argument `branch` (default: `stable`) allows you to choose the
firmware's branch.

Example:

```
    Experimental Firmware: [ff_hh_versions branch="experimental"]
```

The optional argument `grep` allows you show a subset of hardware versions.

Example:

```
    TP-Link Firmware: [ff_hh_versions grep="tp-link"]

    Ubiquiti Firmware: [ff_hh_versions grep="ubiquiti"]
```
