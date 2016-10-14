<?php

namespace Gini\Controller\CGI\AJAX;

class Member extends \Gini\Controller\CGI
{
    use \Gini\Module\Gapper\Client\RPCTrait;
    use \Gini\Module\Gapper\Client\CGITrait;
    use \Gini\Module\Gapper\Client\LoggerTrait;

    private function _showAdded(array $user=[])
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'replace',
            'replace'=> $user,
            'message'=> (string)V('add-member/success')
        ]);
    }

    private function _showFillInfo($vars)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'replace',
            'message'=> (string)V('add-member/fill-info', $vars)
        ]);
    }

    private function _alert($message)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'alert',
            'message'=> $message
        ]);
    }

    /**
        * @brief 获取支持的添加新用户的类型
        *
        * @return 
     */
    public function actionGetAddTypes()
    {
        $current = \Gini\Gapper\Client::getLoginStep();
        if ($current!==\Gini\Gapper\Client::STEP_DONE) return;


        $conf = (array) \Gini\Config::get('gapper.auth');
        $data['demo'] = $conf['demo']['icon'];

        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('add-member-types', [
            'data'=> $data,
            'group'=> \Gini\Gapper\Client::getGroupID()
        ]));
    }

    /**
        * @brief 获取添加member表单
        *
        * @return 
     */
    public function actionGetAddModal()
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('add-member/modal'));
    }

    private static $identitySource = 'demo';

    /** 
        * @brief 一卡通用户信息获取
        *
        * @param $username 一卡通卡号
        *
        * @return (object)
     */
    private static function _getUserInfo($username)
    {

        try {
            $config = (array) \Gini\Config::get('app.rpc');
            $config = $config['gateway'];
            $api = $config['url'];
            $client_id = $config['client_id'];
            $client_secret = $config['client_secret'];
            $rpc = \Gini\IoC::construct('\Gini\RPC', $api);
            if ($rpc->Gateway->authorize($client_id, $client_secret)) {
                $info = (array)$rpc->Gateway->People->getUser($username);
            }
        }
        catch (\Exception $e) {
        }

        if (empty($info)) return;

        return (object)[
            'id'=> $info['uid'],
            'name'=> $info['name'],
            'ref_no'=> $info['ref_no'],
            'phone'=> $info['phone'],
            'email'=> $info['email'],
            'type' => $info['type'],
        ];
    }

    public function actionSearch()
    {
        $data = $this->form('get');
        // 被搜索的卡号
        $value = $data['value'];

        if (!$value) return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');

        try {
            $info = self::getRPC()->Gapper->User->getUserByIdentity(self::$identitySource, $value);
        } catch (\Exception $e) {
        }

        // 一卡通用户已经激活
        if ($info && $info['id']) {
            try {
                $groups = self::getRPC()->Gapper->User->getGroups((int)$info['id']);
            } catch (\Exception $e) {
            }
            $current = \Gini\Gapper\Client::getGroupID();
            // 一卡通用户已经在当前组
            if (isset($groups[$current])) {
                return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
            }
            $data = [
                'username'=> $value,
                'name'=> $info['name'],
                'initials'=> $info['initials'],
                'icon'=> $info['icon']
            ];
        }
        // 一卡通用户未激活
        else {
            $info = (array)self::_getUserInfo($value);
            $data = [
                'username'=> $value,
                'name'=> $info['name'],
                'email'=> $info['email']
            ];
        }

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', (string)V('add-member/match', $data));

    }

    public function actionAdd()
    {
        $form = $this->form('post');
        // 被搜索的卡号
        $username = $form['username'];

        if (empty($username)) return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');

        try {
            $info = (array)self::getRPC()->gapper->user->getUserByIdentity(self::$identitySource, $username);
        } catch (\Exception $e) {
        }
        $current = \Gini\Gapper\Client::getGroupID();

        // 对应的gapper用户已经存在
        if ($info['id']) {
            // 对应的gapper用户已经在当前组
            // 直接提示添加用户成功
            try {
                $groups = self::getRPC()->gapper->user->getGroups((int)$info['id']);
            } catch (\Exception $e) {
            }
            if (isset($groups[$current])) {
                return $this->_showAdded($info);
            }

            try {
                $bool = self::getRPC()->gapper->group->addMember((int)$current, (int)$info['id']);
            } catch (\Exception $e) {
            }
            if ($bool) {
                return $this->_showAdded($info);
            }
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        // 没有对用的gapper用户
        // 需要先激活该一卡通用户

        // 如果没有提交email和name, 展示确认name和email的表单
        if (empty($form['name']) || empty($form['email'])) {
            $error = [];
            if (empty($form['name'])) {
                $error['name'] = T('请补充用户姓名');
            }
            if (empty($form['email'])) {
                $error['email'] = T('请填写Email');
            }
        }

        $pattern = '/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/';
        if ($form['email'] && !preg_match($pattern, $form['email'])) {
            $error['email'] = T('请填写真实的Email');
        }

        if ($form['disable-error']) {
            return $this->_showFillInfo([
                'username'=> $username,
                'name'=> $form['name'],
                'email'=> $form['email']
            ]);
        }
        elseif (!empty($error)) {
            return $this->_showFillInfo([
                'username'=> $username,
                'name'=> $form['name'],
                'email'=> $form['email'],
                'error'=> $error
            ]);
        }

        $email = $form['email'];
        $name = $form['name'];
        try {
            $info = self::getRPC()->gapper->user->getInfo($email);
        } catch (\Exception $e) {
        }

        // email已经被占用
        if ($info['id']) {
            return $this->_showFillInfo([
                'username'=> $username,
                'name'=> $name,
                'email'=> $email,
                'error'=> [
                    'email'=> 'Email已经被占用, 请换一个试试'
                ]
            ]);
        }

        // 注册gapper用户, 以Email为用户名
        try {
            $uid = self::getRPC()->gapper->user->registerUser([
                'username'=> $email,
                'password'=> \Gini\Util::randPassword(),
                'name'=> $name,
                'email'=> $email
            ]);
        } catch (\Exception $e) {
        }

        if (!$uid) return $this->_alert(T('添加用户失败, 请重试!'));

        // 绑定identity
        try {
            $bool = self::getRPC()->gapper->user->linkIdentity((int)$uid, self::$identitySource, $username);
        } catch (\Exception $e) {
        }
        if (!$bool) return $this->_alert(T('用户添加失败, 请换一个Email试试!'));

        // 将新用户加入当前组
        try {
            $bool = self::getRPC()->gapper->group->addMember((int)$current, (int)$uid);
        } catch (\Exception $e) {
        }
        if ($bool) {
            $info = self::getRPC()->gapper->user->getInfo((int)$uid);
            return $this->_showAdded($info);
        }

        return $this->_alert(T('一卡通用户已经激活, 但是暂时无法将该用户加入当前组, 请联系网站管理员处理!'));
    }

}
