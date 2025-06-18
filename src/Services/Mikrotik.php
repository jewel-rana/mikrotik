<?php

namespace Rajtika\Mikrotik\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PEAR2\Console\CommandLine\Exception;
use PEAR2\Net\RouterOS;
use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\RouterOS\Util;
use PEAR2\Net\RouterOS\Query;

class Mikrotik
{
    protected static $config;
    protected static $host;
    protected static $port;
    protected static $user;
    protected static $password;
    protected static $service;
    protected static $client;
    public static bool $customer_exist = false;
    private static bool $connected = false;

    public function __construct()
    {
        self::init();
    }

    public static function dump()
    {
//        self::connect();
//        if( self::$connected ) {
//
//            // $util = new Util(self::$client);
//            // $util->setMenu('/ppp active')->remove('ibrahim001@0001');
//            $query = new Query('/ppp/active/remove');
//            $query->add('=find=ibrahim001@0001');
//
//
//            // Send query to RouterOS
//            $response = self::$client->write($query)->read();
//            dd($response);
//        }
        dd(self::monitor('sojib'));
        dd('Dump from Mikrotik Services for Pear2');
    }

    private static function init()
    {
        $router = auth()->check() ? Auth::user()->router : null;
        if ($router !== null) {
            self::$host = $router['mktik_host'];
            self::$port = $router['mktik_port'] ?? '8728';
            self::$user = $router['mktik_user'];
            self::$password = $router['mktik_password'];
            self::$service = $router['mktik_interface'] ?? 'pppoe';
        }
    }

    public static function _set($router)
    {
        if ($router !== null) {
            self::$host = $router['mktik_host'];
            self::$port = $router['mktik_port'] ?? '8728';
            self::$user = $router['mktik_user'];
            self::$password = $router['mktik_password'];
            self::$service = $router['mktik_interface'] ?? 'pppoe';
        }
    }

    public static function connect()
    {
        if (!empty(self::$host) && !empty(self::$user) && !empty(self::$password)) {
            try {
                self::$client = new Client(self::$host, self::$user, self::$password, self::$port);
                self::$connected = true;
            } catch (\Exception $exception) {
                Log::error($exception->getMessage(), [
                    'keyword' => 'MIKROTIK_CONNECTION_EXCEPTION'
                ]);
                self::$connected = false;
            }
        } else {
            self::$connected = false;
        }
    }

    public static function getStatus()
    {
        self::connect();
        if (self::$connected) {
            $responses = self::$client->sendSync(new Request('/interface pppoe-client monitor numbers=pppoe-out1 once'));

            foreach ($responses as $item) {
                echo 'Status :', $item->getProperty('status');
            }
        }
    }

    public static function torch($name = null): object
    {
        $data = null;
        if ($name !== null) {
            self::connect();
            if (self::$connected) {
                $torchRequest = new Request('/tool torch duration=2');
                // $torchRequest->setArgument('interface', 'ether8');
                $torchRequest->setArgument('interface', $name);
                $data = self::$client->sendSync($torchRequest);
            }
        }

        return $data;
    }

    public static function monitor()
    {
        $data = null;
        self::connect();
        if (self::$connected) {
            // /interface/monitor-traffic interface=eathernet
            $responses = self::$client->sendSync(new Request('/interface pppoe-client monitor numbers=ether8'));
            $data = $responses;
        }

        return $data;
    }

    public static function resource(): object
    {
        $data = null;
        if (self::mikrotik_enabled()) {
            self::connect();
            if (!self::$connected) {
            }
            $data = self::$client->sendSync(new Request('/system/resource/print'))->getAllOfType(RouterOS\Response::TYPE_DATA);
        }
        return $data;
    }

    public static function logs(): array
    {
        $response = array('status' => false, 'msg' => '');
        if (self::mikrotik_enabled()) {
            self::connect();
            if (!self::$connected) {
                $response['msg'] = 'Could not connect to router.';
                $response['status'] = false;
                return $response;
            }
            try {
                $util = new Util(self::$client);
                $response['status'] = true;
                $response['data'] = $util->setMenu('/log')->getAll();
            } catch (Exception $e) {
                Log::error($exception->getMessage(), [
                    'keyword' => 'MIKROTIK_LOG_EXCEPTION'
                ]);

                $response['msg'] = 'An error occured to collect system user logs';
            }
        } else {
            $response['msg'] = 'Mikrotik configuration is not set.';
        }
        return $response;
    }

    public static function interfaces(): array
    {
        $response = array('status' => false, 'msg' => '', 'data' => []);
        if (self::mikrotik_enabled()) {
            self::connect();
            if (!self::$connected) {
                $response['msg'] = 'Could not connect to router.';
                $response['status'] = false;
                return $response;
            }
            $response['data'] = self::$client->sendSync(new Request('/interface/print'))->getAllOfType(RouterOS\Response::TYPE_DATA);
            $response['status'] = true;
        } else {
            $response['msg'] = 'Mikrotik configuration is not set.';
        }
        return $response;
    }

    /*
    * Get All connections with type ['secret', 'active']
    */
    public static function getAll($type = 'inactive'): array
    {
        $response = array('status' => false, 'msg' => '', 'data' => []);
        if (self::mikrotik_enabled()) {
            self::connect();
            if (!self::$connected) {
                $response['msg'] = 'Could not connect to router.';
                $response['status'] = false;
                return $response;
            }
            $response['data'] = self::$client->sendSync(new Request('/ppp/' . $type . '/print detail=""'))->getAllOfType(RouterOS\Response::TYPE_DATA);
            $response['status'] = true;
        } else {
            $response['msg'] = 'Mikrotik configuration is not set.';
        }
        return $response;
    }

    /*
    * Get All connections with type ['secret', 'active']
    */
    public static function getAll2($type = 'inactive'): array
    {
        $response = array('status' => false, 'msg' => '', 'data' => []);
        if (self::mikrotik_enabled()) {
            self::connect();
            if (!self::$connected) {
                $response['msg'] = 'Could not connect to router.';
                $response['status'] = false;
                return $response;
            }
            $response['data'] = self::$client->sendSync(new Request('/interface/monitor-traffic'))->getAllOfType(RouterOS\Response::TYPE_DATA);
            $response['status'] = true;
        } else {
            $response['msg'] = 'Mikrotik configuration is not set.';
        }
        return $response;
    }

    public static function get($id = null): array
    {
        $response = ['status' => false, 'msg' => ''];
        if (self::mikrotik_enabled()) {
            self::connect();
            if (!self::$connected) {
                $response['msg'] = 'Could not connect to router.';
                return $response;
            }
            if ($id != null) {
                $customer = new Request('/ppp/secret/getall detail=""');
                $customer->setQuery(Query::where('.id', $id));
                $info = self::$client->sendSync($customer);

                Log::error('MIKROTIK_GET_USER_INFO', [
                    'user-id' => $id,
                    'response' => $info
                ]);
                if (!empty($info[0])) :
                    $response['status'] = true;
                    $response['data'] = $info[0];
                endif;
            } else {
                $response['msg'] = 'Mikrotik ID not provided!';
            }
        } else {
            $response['msg'] = 'Mikrotik configuration is not set.';
        }

        return $response;
    }


    public static function getActive($name): array
    {
        $response = ['status' => false, 'msg' => ''];
        if (self::mikrotik_enabled()) {
            self::connect();
            if (!self::$connected) {
                $response['msg'] = 'Could not connect to router.';
                return $response;
            }
            if ($name != null) {
                $customer = new Request('/ppp/active/print');
                $customer->setQuery(Query::where('name', $name));
                $info = self::$client->sendSync($customer);
                Log::error('MIKROTIK_GET_ACTIVE_USER_INFO', [
                    'user-name' => $name,
                    'response' => $info
                ]);
                if (!empty($info[0])) :
                    $response['status'] = true;
                    $response['data'] = $info[0];
                endif;
            } else {
                $response['msg'] = 'Mikrotik name not provided!';
            }
        } else {
            $response['msg'] = 'Mikrotik configuration is not set.';
        }

        return $response;
    }

    public static function getByName($name = ''): array
    {
        $response = array('status' => false, 'msg' => '');
        if (self::mikrotik_enabled()) {
            self::connect();
            if (!self::$connected) {
                $response['msg'] = 'Could not connect to router.';
                return $response;
            }

            if ($name) :
                $customer = new Request('/ppp/secret/getall');
                $customer->setArgument('.proplist', '.id,name,profile,disabled');
                $customer->setQuery(Query::where('name', $name));
                $info = self::$client->sendSync($customer);
                if (!empty($info[0])) :
                    Log::error('MIKROTIK_GET_BY_NAME_USER_INFO', [
                        'user-name' => $name,
                        'response' => $info
                    ]);
                    $response['status'] = true;
                    $response['data'] = $info[0];
                endif;
            else :
                $response['msg'] = 'Customer username is empty.';
            endif;
        } else {
            $response['msg'] = 'Mikrotik configuration is not set.';
        }
        return $response;
    }


    /*
    * Reference
    * $service = $client->sendSync(new Request('/interface/pppoe-server/print', RouterOS\Query::where('name', $ppp('name')))->getArgument('service');
    */
    public static function getServerByName($name = ''): array
    {
        $response = array('status' => false, 'msg' => '');
        if (self::mikrotik_enabled()) {
            self::connect();
            if (!self::$connected) {
                $response['msg'] = 'Could not connect to router.';
                return $response;
            }

            if ($name) :
                $customer = new Request('/interface/pppoe-server/print');
                $customer->setArgument('.proplist', '.id,name,profile,disabled,service');
                $customer->setQuery(Query::where('name', $name));
                $info = self::$client->sendSync($customer);
                if (!empty($info[0])) :
                    Log::error('MIKROTIK_GET_SERVER_NAME', [
                        'name' => $name,
                        'response' => $info
                    ]);
                    $response['status'] = true;
                    $response['data'] = $info[0];
                endif;
            else :
                $response['msg'] = 'Mikrotik ID not found is empty.';
            endif;
        } else {
            $response['msg'] = 'Mikrotik configuration is not set.';
        }
        return $response;
    }

    public static function create($customer): array
    {
        $response = ['status' => false, 'msg' => '', 'data' => []];
        //check mikrotik enabled
        if (self::mikrotik_enabled() && $customer) {
            self::connect();
            if (!self::$connected) {
                throw new Exception("Router not connected", 1);
            }
            $user = new Request('/ppp/secret/add');
            $user->setArgument('name', $customer->customerID);
            $user->setArgument('profile', $customer->package['code']);
            $user->setArgument('password', $customer->password);
            $user->setArgument('service', self::$service);
            // if ($customer->remote_ip) {
            //     $user->setArgument('remote_address', $customer->remote_ip);
            // }
            // if ($customer->remote_mac) {
            //     $user->setArgument('physical_address', $customer->remote_mac);
            // }
            $user->setArgument('comment', 'Via api [pkg - ' . $customer->package->name . ', price- ' . $customer->package['price'] . 'Tk., date- ' . date('d/m/Y'));
            $user->setArgument('disabled', 'no');

            $requestResponse = self::$client->sendSync($user);
            Log::error('MIKROTIK_GET_SERVER_NAME', [
                'customer-id' => $customer->customerID,
                'response' => $requestResponse
            ]);
            if ($requestResponse->getType() !== RouterOS\Response::TYPE_FINAL) {
                $response['msg'] = 'Sorry! cannot create customer';
            } else {
//                self::$client->loop();
                $customerAcc = self::getByName($customer->customerID);
                $response['data'] = $customerAcc['status'] ? $customerAcc['data'] : null;
                $response['status'] = true;
                $response['msg'] = 'Customer has been successfully created';
            }
        } else {
            $response['status'] = true;
            $response['msg'] = 'Mikrotik configuration is not set.';
        }
        return $response;
    }

    public static function enable($customer = ''): array
    {
        $response = ['status' => false, 'msg' => '', 'data' => []];
        if (self::mikrotik_enabled()) {
            self::connect();
            if (!self::$connected) {
                $response['msg'] = 'Could not connect to router';
                return $response;
            }
            if ($customer) {
                $mktikId = '';
                if (empty($mktikId)) {
                    $customerAcc = self::getByName($customer['customerID']);
                    if ($customerAcc['status']) {
                        $mktikId = $customerAcc['data']->getProperty('.id');
                    }
                }
                if (!empty($mktikId)) {
                    $user = new Request('/ppp/secret/set');
                    $user->setArgument('.id', $mktikId);
                    $user->setArgument('disabled', 'no');

                    $requestResponse = self::$client->sendSync($user);
                    Log::error('MIKROTIK_ENABLE_CUSTOMER', [
                        'customer-id' => $customer['customerID'],
                        'response' => $requestResponse
                    ]);
                    if ($requestResponse->getType() !== RouterOS\Response::TYPE_FINAL) {
                        $response['status'] = true;
                        $response['msg'] = 'Mikrotik! Customer has been enabled.';
                    } else {
                        $response['msg'] = 'Mikrotik! Customer cannot be enabled.';
                    }
                } else {
                    $response['msg'] = 'Could not find customer into router';
                }
            } else {
                $response['msg'] = 'Customer not found.';
            }
        } else {
            $response['status'] = true;
            $response['msg'] = 'Mikrotik configuration not set.';
        }
        return $response;
    }

    public static function disable(?array $customer): array
    {
        $response = ['status' => true, 'msg' => 'Cannot disable customer'];
        if (self::mikrotik_enabled()) {
            self::connect();
            if (!self::$connected) {
                $response['msg'] = 'Could not connect to router';
                return $response;
            }
            if ($customer) :
                $user = self::getByName($customer['customerID']);
                $mktikId = '';
                if (!empty($user['data'])) {
                    foreach ($user['data'] as $key => $v) {
                        if ($key == ".id") {
                            $mktikId = $v;
                        }
                    }
                }

                if (!empty($mktikId)) {
                    $userAcc = new Request('/ppp/secret/set');
                    $userAcc->setArgument('.id', $mktikId);
                    $userAcc->setArgument('disabled', 'yes');
                    $userAcc->setArgument('.proplist', '.id,name,profile,service');

                    $requestResponse = self::$client->sendSync($user);
                    Log::error('MIKROTIK_DISABLE_CUSTOMER', [
                        'customer-id' => $customer['customerID'],
                        'response' => $requestResponse
                    ]);
                    if ($requestResponse->getType() !== RouterOS\Response::TYPE_FINAL) {
                        $activeCon = self::getActive($customer['customerID']);
                        // dd( $activeCon['data']['.id']);
                        if (!empty($activeCon['data'])) {
                            foreach ($activeCon['data'] as $key => $v) {
                                if ($key == ".id") {
                                    $mktikId = $v;
                                    $userAcc = new Request('/ppp/active/remove');
                                    $userAcc->setArgument('.id', $mktikId);
                                    self::$client->sendSync($userAcc);
                                }
                            }
                        }

                        $response['status'] = true;
                        $response['msg'] = 'Customer successfully disabled';

                    } else {
                        $response['msg'] = 'Sorry! cannot disable customer';
                    }
                } else {
                    $response['msg'] = 'Customer not found in router.';
                }
            else :
                $response['msg'] = 'Mikrotik ID not found.';
            endif;
        } else {
            $response['status'] = true;
            $response['msg'] = 'Mikrotik configuration not set';
        }

        return $response;
    }

    public static function changeName($params = array()): array
    {
        $response = ['status' => false, 'msg' => 'Cannot change customerID'];

        if (self::mikrotik_enabled()) {
            self::connect();
            if (!self::$connected) {
                $response['msg'] = 'Could not connect to router';
                return $response;
            }
            if ($params['name'] != '') {
                $mktikId = '';
                $user = self::getByName($params['customerID']);
                if ($user['status']) {
                    $mktikId = $user['data']->getProperty('.id');
                }

                if (!empty($mktikId)) {
                    $customer = new Request('/ppp/secret/set');
                    $customer->setArgument('.id', $mktikId);
                    $customer->setArgument('name', $params['name']);

                    $requestResponse = self::$client->sendSync($user);
                    Log::error('MIKROTIK_CHANGE_CUSTOMER_NAME', [
                        'customer-id' => $customer['customerID'],
                        'response' => $requestResponse
                    ]);
                    if ($requestResponse->getType() !== RouterOS\Response::TYPE_FINAL) {
                        $response['status'] = true;
                        $response['msg'] = 'Your CustomerID has been changed';
                    }
                } else {
                    $response['msg'] = 'Mikrotik account not found';
                }
            } else {
                $response['msg'] = 'Customer name not provided';
            }
        } else {
            $response['status'] = true;
            $response['msg'] = 'Mikrotik configuration not set.';
        }

        return $response;
    }

    public static function changePassword($params = array()): array
    {
        $response = ['status' => false, 'msg' => ''];
        if (self::mikrotik_enabled()) {
            self::connect();
            if (!self::$connected) {
                $response['msg'] = 'Could not connect to router';
                return $response;
            }
            if ($params['password'] != '') {
                $mktikId = '';
                $user = self::getByName($params['customerID']);
                if ($user['status']) {
                    $mktikId = $user['data']->getProperty('.id');
                }
                if (!empty($mktikId)) {
                    $customer = new Request('/ppp/secret/set');
                    $customer->setArgument('.id', $mktikId);
                    $customer->setArgument('password', $params['password']);

                    $requestResponse = self::$client->sendSync($user);
                    Log::error('MIKROTIK_CHANGE_PASSWORD', [
                        'customer-id' => $customer['customerID'],
                        'response' => $requestResponse
                    ]);
                    if ($requestResponse->getType() !== RouterOS\Response::TYPE_FINAL) {
                        $response['status'] = true;
                    } else {
                        $response['msg'] = 'Sorry! cannot change password';
                    }
                } else {
                    $response['msg'] = 'Mikrotik account not found';
                }
            } else {
                $response['msg'] = 'Your password is empty';
            }
        } else {
            $response['status'] = true;
            $response['msg'] = 'Mikrotik configuration not set.';
        }
        return $response;
    }


    public static function changeProfile($params = array()): array
    {
        $response = ['status' => false, 'msg' => ''];
        if (self::mikrotik_enabled()) {
            self::connect();
            if (!self::$connected) {
                $response['msg'] = 'Could not connect to router';
                return $response;
            }
            if ($params['profile'] != '') {
                $customer = new Request('/ppp/secret/set');
                $customer->setArgument('.id', $params['id']);
                $customer->setArgument('profile', $params['profile']);

                $requestResponse = self::$client->sendSync($customer);
                Log::error('MIKROTIK_CHANGE_CUSTOMER_PROFILE', [
                    'customer-id' => $customer['customerID'],
                    'response' => $requestResponse
                ]);
                if ($requestResponse->getType() !== RouterOS\Response::TYPE_FINAL) {
                    $response['status'] = true;
                    $response['msg'] = 'Customer package has been successfully changed!';
                } else {
                    $response['msg'] = 'Sorry! cannot change package';
                }
            } else {
                $response['msg'] = 'Customer package not selected.';
            }
        } else {
            $response['status'] = true;
            $response['msg'] = 'Mikrotik configuration not set.';
        }

        return $response;
    }

    private static function mikrotik_enabled(): bool
    {
        if (getOption('mikrotik_access')) {
            return true;
        }
        return false;
    }

    public static function _exist($params): bool
    {
        self::connect();
        if (!self::$connected) {
            return false;
        }
        if ($params['name']) {
            $customer = new Request('/ppp/secret/getall');
            $customer->setArgument('.proplist', '.id,name,profile,service');
            $customer->setQuery(Query::where('name', $params['name']));
            $requestResponse = self::$client->sendSync($customer)->getProperty('.id');
            if (!empty($requestResponse) || is_array($requestResponse)) {
                Log::error('MIKROTIK_CHECK_CUSTOMER_EXIST', [
                    'params' => $params,
                    'response' => $requestResponse
                ]);
                self::$customer_exist = true;
                return true;
            }
        }
        return false;
    }

    public static function reboot()
    {
        self::connect();

        if (self::$connected) {
            $request = new Request(
                '/system scheduler add name=REBOOT interval=2s
                on-event="/system scheduler remove REBOOT;/system reboot"'
            );
            $requestResponse = self::$client->sendSync($request);

            Log::error('MIKROTIK_REBOOT', [
                'response' => $requestResponse
            ]);
        }
    }

    public function __destruct()
    {
        self::$client = null;
    }
}
