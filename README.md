php-hs100-smartplug
===================

Basic connector to manipulate Tp-Link HS100 Smartplug directly by it's hostname
and port.

Useful in case of problems with Tp-Link Cloud connection.

HS100 by default operates on port 9999. If you wish to access your local HS100
from external network be sure to forward that port thru your router!

sample
======

```php
$c = (new Connector())
     ->setHost('mysmartplug.ddns.net')
     ->setPort(19999)
     ->turnOn
     ;

var_dump($c->isOn());
```