<?php

namespace gateway;

class mothod
{
    private $methodClass;

    public function run()
    {
        /*
        接口允许的调用方式
            {网关}?method=tests.test1.test2
            {网关}/tests/test1/test2
        */
        $method = null;
        try {
            // 检查网关
            $this->_setupCheckGateway();

            // 检查并设置查询类
            $this->_setupCheckMothod();

            // 筛选查询数据
            $this->_getRequest();

            // 执行相关查询类
            $method = new $this->methodClass();
            $method->run();

            throw new \Exception('gateway.empty-run');
        } catch (\Exception $e) {
            self::_responseJSON($e);
        }
    }

    /*
     * 检查网关
     */
    private function _setupCheckGateway()
    {
        global $wp_query;

        $gateway = get_option('dfoxa_gateway');

        if (!isset($wp_query->query['pagename']))
            throw new \Exception('gateway.empty-gateway', -1);

        $pagename = $wp_query->query['pagename'];

        // 检测网关是否配置
        if ($gateway == '')
            throw new \Exception('gateway.empty-gateway', -1);


        // 检查是否匹配网关
        $gateway = get_option('dfoxa_gateway');
        if ($pagename != $gateway && strpos($pagename, $gateway) !== 0)
            throw new \Exception('gateway.undefined', -1);


        return true;
    }

    /**
     * 检查查询内容接口是否存在
     * 如果存在则定义 $methodClass 为所需的类
     * @throws \Exception
     */
    private function _setupCheckMothod()
    {
        // 检查请求体是否存在
        if (isset($_GET['method'])) {
            $class = explode('.', $_GET['method']);
            $num = count($class);
            if ($num < 2)
                throw new \Exception('gateway.method-undefined');

            /*
            method
                 =>     '?method=access.sign.up'
            $methodClass
                 =>     '\access\sign\up'
            */
            global $methodClass;
            $methodClass = '';
            $methodNameSpace = '';
            for ($i = 0; $i < $num; $i++) {
                $methodClass .= '\\' . $class[$i];

                if ($i < $num - 1)
                    $methodNameSpace .= $methodNameSpace == '' ? $class[$i] : '\\' . $class[$i];
            }
            if (!class_exists($methodClass)) {
                // 自加载无效,加载插件
                foreach (get_dfoxa_active_plugins() as $pluginname => $plugin) {
                    if (in_array($methodNameSpace, $plugin['Namespace'])) {
                        include_once(DFOXA_PLUGINS . DFOXA_SEP . $pluginname);
                    }
                }

                if (!class_exists($methodClass)) {
                    throw new \Exception('gateway.empty-method');
                }
            }

            $this->methodClass = $methodClass;
        } else {
            global $wp_query;
            $pagename = $wp_query->query['pagename'];
            $class = explode('/', $pagename);
            $num = count($class);
            if ($num < 3)
                throw new \Exception('gateway.method-undefined');

            $methodClass = '';
            for ($i = 1; $i < $num; $i++) {
                $methodClass .= '\\' . $class[$i];
            }

            if (!class_exists($methodClass)) {
                apply_filters('dfoxa_wpapi_method_exists_class', $pagename);
                throw new \Exception('gateway.empty-method');
            }
        }

        $this->methodClass = $methodClass;
    }

    /*
     * 检查查询数据并返回查询数组
     */
    public function _getRequest()
    {
        // OPTIONS 一律直接返回正确
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS')
            throw new \Exception('gateway.options-success');


        global $bizContent;
        // 过滤GET POST以外的请求
        $request_method = array('GET', 'POST');

        if (!in_array($_SERVER['REQUEST_METHOD'], $request_method))
            throw new \Exception('gateway.error-request');

        if (stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            $biz = file_get_contents('php://input') ? file_get_contents('php://input') : gzuncompress($GLOBALS ['HTTP_RAW_POST_DATA']);
            $bizContent = json_decode($biz);
        } else if (stripos($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded') !== false) {
            $biz = file_get_contents('php://input') ? file_get_contents('php://input') : gzuncompress($GLOBALS ['HTTP_RAW_POST_DATA']);
            $bizContent = json_decode($biz);
        } else if (stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
            $bizContent = arrayToObject($_POST);
        }

        if (!is_object($bizContent) || $bizContent === NULL)
            throw new \Exception('gateway.empty-request');

        return true;
    }

    /*
     * 输出 json 格式结果,在出错的情况下执行
     */
    private static function _responseJSON($e)
    {
        if ($e->getCode() === -1) {
            return true;
        }

        ob_clean();
        if (!empty($e->getCode())) {
            status_header($e->getCode());
        } else {
            status_header(200);
        }

        header("Access-Control-Allow-Origin: *");
        header('Access-Control-Allow-Headers:Origin, X-Requested-With, Content-Type, Accept');
        header('Content-type: application/json');

        echo json_encode(code::_e($e->getMessage()));
        exit;
    }

    /*
     * 输出 json 格式结果,在成功的情况下执行
     * $arrkey 如果填写，则拼接到code之下
     */
    public static function responseSuccessJSON($response = '', $status = '10000', $code = '200', $arrayKey = '')
    {
        ob_clean();
        status_header($code);
        header("Access-Control-Allow-Origin: *");
        header('Access-Control-Allow-Headers:Origin, X-Requested-With, Content-Type, Accept');
        header('Content-type: application/json');
        // 清理空的返回内容
        if (is_object($response))
            $response = objectToArray($response);

        if (!is_array($response))
            $response = array('res' => $response);

        if (empty($response)) {
            $response = array();
        }

        // 将用户的请求包含在返回的内容中
        global $bizContent;
        $response['request'] = $bizContent;
        if ($arrayKey == '') {
            $echo_array = array_merge(code::_e($status), $response);
        } else {
            $echo_array = array_merge(code::_e($status), array($arrayKey => $response));
        }
        echo json_encode($echo_array);
        exit;
    }

}