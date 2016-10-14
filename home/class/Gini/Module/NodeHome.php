<?php

namespace Gini\Module;

class NodeHome
{

    public static function setup()
    {
        \Gini\Gapper\Client::init();

        isset($_GET['locale']) and $_SESSION['locale'] = $_GET['locale'];
        isset($_SESSION['locale']) and \Gini\Config::set('system.locale', $_SESSION['locale']);
        \Gini\I18N::setup();

        setlocale(LC_MONETARY, (\Gini\Config::get('system.locale') ?: 'en_US').'.UTF-8');
    }

}