freifunk-versions
=================

Wordpress plugin to render latest (Hamburg-flavoured)
[gluon firmware](https://github.com/freifunkhamburg/gluon) version table.

Provides shortcode `[ff_hh_versions]` to display table.
Input data is read from http://updates.hamburg.freifunk.net/stable/sysupgrade/manifest

Output looks like this:

![shortcode output example](http://mschuette.name/wp/wp-upload/freifunk_versions.png)

Arguments
---------
An optional argument `grep` allows you show a subset of hardware versions,
Example:

```
    TP-Link Firmware: [ff_hh_versions grep="tp-link"]

    Ubiquiti Firmware: [ff_hh_versions grep="ubiquiti"]
```

