<?php

namespace Gini\Controller\CGI;

class Index extends Layout\Board
{
    public function __index()
    {
        $this->view->body = V('home');
    }

    public function actionGo($type)
    {
        $clients = (array)\Gini\Config::get('app.clients');
        if (!isset($clients[$type])) {
            return;
        }
        $user = \Gini\Gapper\Client::getUserName();
        $clientID = $clients[$type];
        $rpc = \Gini\Gapper\Client::getRPC();
        $appInfo = $rpc->gapper->app->getInfo($clientID);
        $url = $appInfo['url'];
        if ($user) {
            $token = $rpc->gapper->user->getLoginToken($user, $clientID);
            $url = \Gini\URI::url($url, [
                'gapper-token'=> $token
            ]);
        }
        $this->redirect($url);
    }
}

