<?php

require_once "../../APIclient.class.php";

$c = new burstAPI('', '');
//
//var_dump($c->SMS('6141xxxxxx', 'Hello Alex. This is a test message. [opt-out-info]', '61429705882'));

var_dump($c->getContactLists(0, 10));