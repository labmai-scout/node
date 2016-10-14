<?php

namespace Gini\Controller\CGI;

class Index extends Layout\Common
{
    public function __index()
    {
        $this->redirect('home');
    }

    public function actionLogout()
    {
        \Gini\Gapper\Client::logout();
        $this->redirect('home');
    }

    public function actionLogin()
    {
        if (\Gini\Gapper\Client::getLoginStep() !== \Gini\Gapper\Client::STEP_DONE) {
            \Gini\Gapper\Client::goLogin();
        } else {
            $this->redirect('home');
        }
    }

    public function actionResetGroup()
    {
        \Gini\Gapper\Client::resetGroup();
        $this->redirect('home');
    }

    public function actionBadBrowser()
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('bad-browser'));
    }
}
