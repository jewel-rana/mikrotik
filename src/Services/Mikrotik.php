<?php

namespace Rajtika\Mikrotik\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PEAR2\Console\CommandLine\Exception;
use PEAR2\Net\RouterOS\TrapException;
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
                Log::debug('MIKROTIK_CLIENT', [
                    'client' => self::$client
                ]);
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
            $data = self::$client->sendSync(new Request('/system/resource/print'))->getAllOfType(Response::TYPE_DATA);
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
            $response['data'] = self::$client->sendSync(new Request('/interface/print'))->getAllOfType(Response::TYPE_DATA);
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
            $response['data'] = self::$client->sendSync(new Request('/ppp/' . $type . '/print detail=""'))->getAllOfType(Response::TYPE_DATA);
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
            $response['data'] = self::$client->sendSync(new Request('/interface/monitor-traffic'))->getAllOfType(Response::TYPE_DATA);
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
                if ($info instanceof ResponseCollection && $info->getType() === Response::TYPE_DATA) {
                    $response['status'] = true;
                    $response['data'] = $info[0];
                }
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
            try {
                $profile = trim($customer['package']['code']);
                $profile = mb_convert_encoding($profile, 'UTF-8');

                self::connect();
                if (!self::$connected) {
                    throw new \Exception("Router not connected", 1);
                }
                $user = new Request('/ppp/secret/add');
                $user->setArgument('name', $customer['customerID']);
                $user->setArgument('profile', $profile);
                $user->setArgument('password', $customer['password']);
                $user->setArgument('service', self::$service);
                // if ($customer->remote_ip) {
                //     $user->setArgument('remote_address', $customer->remote_ip);
                // }
                // if ($customer->remote_mac) {
                //     $user->setArgument('physical_address', $customer->remote_mac);
                // }
                $user->setArgument('comment', 'Via api [pkg - ' . $customer['package']['name'] . ', price- ' . $customer['package']['price'] . 'Tk., date- ' . date('d/m/Y'));
                $user->setArgument('disabled', 'no');

                $requestResponse = self::$client->sendSync($user, true);
                Log::error('MIKROTIK_GET_SERVER_NAME', [
                    'package' => $customer['package']['code'],
                    'service' => self::$service,
                    'customer-id' => $customer['customerID'],
                    'response' => $requestResponse,
                    'response-type' => $requestResponse->getType(),
                    'final-type' => Response::TYPE_FINAL
                ]);

                $response['data'] = $requestResponse;

                if ($requestResponse->getType() !== Response::TYPE_FINAL) {
                    $response['msg'] = 'Sorry! cannot create customer';
                } else {
                    self::$client->loop();
                    $customerAcc = self::getByName($customer['customerID']);
                    $response['data'] = $customerAcc['status'] ? $customerAcc['data'] : null;
                    $response['status'] = true;
                    $response['msg'] = 'Customer has been successfully created';
                }
            } catch (TrapException $e) {
                $response['msg'] = $e->getMessage();
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
                    if ($requestResponse->getType() == Response::TYPE_FINAL) {
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
        $response = ['status' => false, 'msg' => 'Cannot disable customer'];

        if (!self::mikrotik_enabled()) {
            return ['status' => true, 'msg' => 'Mikrotik configuration not set'];
        }

        self::connect();

        if (!self::$connected) {
            return ['status' => false, 'msg' => 'Could not connect to router'];
        }

        if (!$customer || empty($customer['customerID'])) {
            return ['status' => false, 'msg' => 'Customer data missing'];
        }

        $user = self::getByName($customer['customerID']);
        $mktikId = !empty($user['data']) ? $user['data']->getProperty('.id') : null;

        if (empty($mktikId)) {
            return ['status' => false, 'msg' => 'Customer not found in router.'];
        }

        try {
            // Disable the PPP secret
            $disableReq = new Request('/ppp/secret/set');
            $disableReq->setArgument('.id', $mktikId);
            $disableReq->setArgument('disabled', 'yes');

            $disableRes = self::$client->sendSync($disableReq);

            Log::info('MIKROTIK_DISABLE_SECRET', [
                'customer-id' => $customer['customerID'],
                'response' => $disableRes,
            ]);

            if (!($disableRes instanceof \PEAR2\Net\RouterOS\ResponseCollection)) {
                $response['msg'] = 'Unrecognized response from router: ' . $customer['router_id'];
            } else {

                if ($disableRes->getType() === Response::TYPE_FINAL) {
                    // Attempt to remove from active sessions (if connected)
                    $activeCon = self::getActive($customer['customerID']);
                    $activeId = !empty($activeCon['data']) ? $activeCon['data']->getProperty('.id') : null;

                    if (!empty($activeId) && str_starts_with($activeId, '*')) {
                        try {
                            $remove = new Request('/ppp/active/remove');
                            $remove->setArgument('.id', $activeId);

                            $activeRes = self::$client->sendSync($remove);

                            Log::info('MIKROTIK_REMOVE_ACTIVE', [
                                'customer-id' => $customer['customerID'],
                                'response' => $activeRes,
                            ]);

                            if (!($activeRes instanceof \PEAR2\Net\RouterOS\ResponseCollection)) {
                                Log::warning('Unrecognized response from /ppp/active/remove', [
                                    'customer-id' => $customer['customerID'],
                                    'class' => is_object($activeRes) ? get_class($activeRes) : gettype($activeRes),
                                ]);
                            }
                        } catch (\Throwable $e) {
                            Log::error('Exception during /ppp/active/remove', [
                                'customer-id' => $customer['customerID'],
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    } else {
                        Log::info('Customer is not actively connected; skipping /ppp/active/remove', [
                            'customer-id' => $customer['customerID'],
                            'active-id' => $activeId,
                        ]);
                    }

                    $response['status'] = true;
                    $response['msg'] = 'Customer successfully disabled';
                } else {
                    $response['msg'] = 'Mikrotik responded with an unexpected type.';
                    foreach ($disableRes as $r) {
                        if ($r->getType() === '!trap') {
                            $response['msg'] .= ' Error: ' . ($r->getProperty('message') ?? 'Unknown');
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Exception during disable()', [
                'customer-id' => $customer['customerID'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $response['msg'] = 'Exception: ' . $e->getMessage();
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
                    $request = new Request('/ppp/secret/set');
                    $request->setArgument('.id', $mktikId);
                    $request->setArgument('name', $params['name']);

                    $requestResponse = self::$client->sendSync($request);
                    Log::error('MIKROTIK_CHANGE_CUSTOMER_NAME', [
                        'customer-id' => $customer['customerID'],
                        'response' => $requestResponse
                    ]);
                    if ($requestResponse->getType() !== Response::TYPE_FINAL) {
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
                    if ($requestResponse->getType() !== Response::TYPE_FINAL) {
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

    if (!self::mikrotik_enabled()) {
        $response['status'] = true;
        $response['msg'] = 'Mikrotik configuration not set.';
        return $response;
    }

    self::connect();
    if (!self::$connected) {
        $response['msg'] = 'Could not connect to router';
        return $response;
    }

    if (!empty($params['profile']) && !empty($params['id'])) {
        $request = new Request('/ppp/secret/set');
        $request->setArgument('.id', $params['id']);
        $request->setArgument('profile', $params['profile']);

        $requestResponse = self::$client->sendSync($request);

        Log::error('MIKROTIK_CHANGE_CUSTOMER_PROFILE', [
            'customer-id' => $params['customerID'] ?? null,
            'response' => $requestResponse
        ]);

        if ($requestResponse->getType() === Response::TYPE_FINAL) {
            $response['status'] = true;
            $response['msg'] = 'Customer package has been successfully changed!';
        } else {
            $response['msg'] = 'Sorry! cannot change package';
        }
    } else {
        $response['msg'] = 'Customer package or ID not provided.';
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
            Log::info("MIKROTIK_NOT_CONNECTED");
            return false;
        }
        if ($params['name']) {
            $customer = new Request('/ppp/secret/getall');
            $customer->setArgument('.proplist', '.id,name,profile,service');
            $customer->setQuery(Query::where('name', $params['name']));
            $requestResponse = self::$client->sendSync($customer)->getProperty('.id');
            Log::error('MIKROTIK_CHECK_CUSTOMER_EXIST_RESPONSE', [
                'params' => $params,
                'response' => $requestResponse,
                'router' => self::$host
            ]);
            if (!empty($requestResponse) || is_array($requestResponse)) {
                self::$customer_exist = true;
                return true;
            }
        }
        return false;
    }

    public static function ping(string $address, int $count = 4): array
    {
        $response = ['status' => false, 'msg' => '', 'data' => []];

        if (!self::mikrotik_enabled()) {
            $response['msg'] = 'Mikrotik configuration not set.';
            return $response;
        }

        self::connect();

        if (!self::$connected) {
            $response['msg'] = 'Could not connect to router';
            return $response;
        }

        try {
            $request = new Request('/ping');
            $request->setArgument('address', $address);
            $request->setArgument('count', $count);

            $pingResponse = self::$client->sendSync($request)->getAllOfType(Response::TYPE_DATA);

            $response['status'] = true;
            $response['data'] = $pingResponse;
        } catch (\Throwable $e) {
            $response['msg'] = 'Ping failed: ' . $e->getMessage();
            Log::error('MIKROTIK_PING_ERROR', [
                'address' => $address,
                'error' => $e->getMessage()
            ]);
        }

        return $response;
    }

    public static function getHotspotUsers(): array
    {
        $response = ['status' => false, 'msg' => '', 'data' => []];

        if (!self::mikrotik_enabled()) {
            $response['msg'] = 'Mikrotik configuration not set.';
            return $response;
        }

        self::connect();

        if (!self::$connected) {
            $response['msg'] = 'Could not connect to router';
            return $response;
        }

        try {
            $request = new Request('/ip/hotspot/user/print');
            $hotspotUsers = self::$client->sendSync($request)->getAllOfType(Response::TYPE_DATA);

            $response['status'] = true;
            $response['data'] = $hotspotUsers;
        } catch (\Throwable $e) {
            $response['msg'] = 'Error fetching hotspot users: ' . $e->getMessage();
            Log::error('MIKROTIK_HOTSPOT_USERS_ERROR', [
                'error' => $e->getMessage()
            ]);
        }

        return $response;
    }

    public static function addHotspotUser(string $name, string $password, string $profile = 'default'): array
    {
        $response = ['status' => false, 'msg' => ''];

        if (!self::mikrotik_enabled()) {
            $response['msg'] = 'Mikrotik configuration not set.';
            return $response;
        }

        self::connect();

        if (!self::$connected) {
            $response['msg'] = 'Could not connect to router';
            return $response;
        }

        try {
            $request = new Request('/ip/hotspot/user/add');
            $request->setArgument('name', $name);
            $request->setArgument('password', $password);
            $request->setArgument('profile', $profile);

            $result = self::$client->sendSync($request);

            $response['status'] = true;
            $response['msg'] = 'Hotspot user added successfully';
        } catch (\Throwable $e) {
            $response['msg'] = 'Error adding hotspot user: ' . $e->getMessage();
            Log::error('MIKROTIK_ADD_HOTSPOT_USER_ERROR', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
        }

        return $response;
    }

    public static function removeHotspotUser(string $name): array
    {
        $response = ['status' => false, 'msg' => ''];

        if (!self::mikrotik_enabled()) {
            $response['msg'] = 'Mikrotik configuration not set.';
            return $response;
        }

        self::connect();

        if (!self::$connected) {
            $response['msg'] = 'Could not connect to router';
            return $response;
        }

        try {
            $request = new Request('/ip/hotspot/user/print');
            $request->setQuery(Query::where('name', $name));
            $result = self::$client->sendSync($request);

            if (!empty($result[0])) {
                $id = $result[0]->getProperty('.id');

                $remove = new Request('/ip/hotspot/user/remove');
                $remove->setArgument('.id', $id);
                self::$client->sendSync($remove);

                $response['status'] = true;
                $response['msg'] = 'Hotspot user removed';
            } else {
                $response['msg'] = 'Hotspot user not found';
            }
        } catch (\Throwable $e) {
            $response['msg'] = 'Error removing hotspot user: ' . $e->getMessage();
            Log::error('MIKROTIK_REMOVE_HOTSPOT_USER_ERROR', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
        }

        return $response;
    }

    public static function traceroute(string $address): array
    {
        $response = ['status' => false, 'msg' => '', 'data' => []];

        if (!self::mikrotik_enabled()) {
            $response['msg'] = 'Mikrotik configuration not set.';
            return $response;
        }

        self::connect();

        if (!self::$connected) {
            $response['msg'] = 'Could not connect to router';
            return $response;
        }

        try {
            $request = new Request('/tool/traceroute');
            $request->setArgument('address', $address);

            $data = self::$client->sendSync($request)->getAllOfType(Response::TYPE_DATA);

            $response['status'] = true;
            $response['data'] = $data;
        } catch (\Throwable $e) {
            $response['msg'] = 'Traceroute failed: ' . $e->getMessage();
            Log::error('MIKROTIK_TRACEROUTE_ERROR', [
                'address' => $address,
                'error' => $e->getMessage()
            ]);
        }

        return $response;
    }

    public static function monitorInterface(string $interface): array
    {
        $response = ['status' => false, 'msg' => '', 'data' => []];

        if (!self::mikrotik_enabled()) {
            $response['msg'] = 'Mikrotik configuration not set.';
            return $response;
        }

        self::connect();

        if (!self::$connected) {
            $response['msg'] = 'Could not connect to router';
            return $response;
        }

        try {
            $request = new Request('/interface/monitor-traffic');
            $request->setArgument('interface', $interface);
            $request->setArgument('once', ''); // prevents continuous streaming

            $data = self::$client->sendSync($request)->getAllOfType(Response::TYPE_DATA);

            $response['status'] = true;
            $response['data'] = $data;
        } catch (\Throwable $e) {
            $response['msg'] = 'Traffic monitor failed: ' . $e->getMessage();
            Log::error('MIKROTIK_MONITOR_INTERFACE_ERROR', [
                'interface' => $interface,
                'error' => $e->getMessage()
            ]);
        }

        return $response;
    }

    public static function getDhcpLeases(): array
    {
        $response = ['status' => false, 'msg' => '', 'data' => []];

        if (!self::mikrotik_enabled()) {
            $response['msg'] = 'Mikrotik configuration not set.';
            return $response;
        }

        self::connect();

        if (!self::$connected) {
            $response['msg'] = 'Could not connect to router';
            return $response;
        }

        try {
            $request = new Request('/ip/dhcp-server/lease/print');
            $leases = self::$client->sendSync($request)->getAllOfType(Response::TYPE_DATA);

            $response['status'] = true;
            $response['data'] = $leases;
        } catch (\Throwable $e) {
            $response['msg'] = 'Failed to fetch DHCP leases: ' . $e->getMessage();
            Log::error('MIKROTIK_DHCP_LEASE_ERROR', [
                'error' => $e->getMessage()
            ]);
        }

        return $response;
    }

    public static function getRouterIdentity(): array
    {
        $response = ['status' => false, 'msg' => '', 'identity' => ''];

        if (!self::mikrotik_enabled()) {
            $response['msg'] = 'Mikrotik configuration not set.';
            return $response;
        }

        self::connect();

        if (!self::$connected) {
            $response['msg'] = 'Could not connect to router';
            return $response;
        }

        try {
            $request = new Request('/system/identity/print');
            $data = self::$client->sendSync($request)->getAllOfType(Response::TYPE_DATA);

            $response['status'] = true;
            $response['identity'] = $data[0]->getProperty('name') ?? '';
        } catch (\Throwable $e) {
            $response['msg'] = 'Failed to fetch identity: ' . $e->getMessage();
            Log::error('MIKROTIK_IDENTITY_ERROR', [
                'error' => $e->getMessage()
            ]);
        }

        return $response;
    }

    public static function getIpAddresses(): array
    {
        $response = ['status' => false, 'msg' => '', 'data' => []];

        if (!self::mikrotik_enabled()) {
            $response['msg'] = 'Mikrotik configuration not set.';
            return $response;
        }

        self::connect();

        if (!self::$connected) {
            $response['msg'] = 'Could not connect to router';
            return $response;
        }

        try {
            $request = new Request('/ip/address/print');
            $addresses = self::$client->sendSync($request)->getAllOfType(Response::TYPE_DATA);

            $response['status'] = true;
            $response['data'] = $addresses;
        } catch (\Throwable $e) {
            $response['msg'] = 'Failed to fetch IP addresses: ' . $e->getMessage();
            Log::error('MIKROTIK_IP_ADDRESS_ERROR', [
                'error' => $e->getMessage()
            ]);
        }

        return $response;
    }

    public static function diagnose(string $address): array
    {
        $response = [
            'status' => false,
            'msg' => '',
            'data' => [
                'ping' => [],
                'traceroute' => [],
            ],
        ];

        if (!self::mikrotik_enabled()) {
            $response['msg'] = 'Mikrotik configuration not set.';
            return $response;
        }

        self::connect();

        if (!self::$connected) {
            $response['msg'] = 'Could not connect to router';
            return $response;
        }

        try {
            // Run ping
            $pingReq = new Request('/ping');
            $pingReq->setArgument('address', $address);
            $pingReq->setArgument('count', 4);
            $pingResult = self::$client->sendSync($pingReq)->getAllOfType(Response::TYPE_DATA);

            // Run traceroute
            $traceReq = new Request('/tool/traceroute');
            $traceReq->setArgument('address', $address);
            $traceResult = self::$client->sendSync($traceReq)->getAllOfType(Response::TYPE_DATA);

            $pingData = array_map(function ($item) {
                $time = floatval(str_replace('ms', '', $item->getProperty('time')));
                return [
                    'host' => $item->getProperty('host') ?? '',
                    'resolved' => gethostbyaddr($item->getProperty('host')) ?? '',
                    'time' => $item->getProperty('time') ?? '',
                    'time_level' => $time < 50 ? 'good' : ($time < 100 ? 'warning' : 'high'),
                    'ttl' => $item->getProperty('ttl') ?? '',
                    'bytes' => $item->getProperty('bytes') ?? '',
                ];
            }, $pingResult);

            $traceData = array_map(function ($item) {
                $time = floatval(str_replace('ms', '', $item->getProperty('time')));
                $host = $item->getProperty('host') ?? '';
                return [
                    'hop' => $item->getProperty('hop') ?? '',
                    'host' => $host,
                    'resolved' => $host ? gethostbyaddr($host) : '',
                    'time' => $item->getProperty('time') ?? '',
                    'time_level' => $time < 50 ? 'good' : ($time < 100 ? 'warning' : 'high'),
                ];
            }, $traceResult);

            $response['status'] = true;
            $response['msg'] = 'Diagnostics complete';
            $response['data']['ping'] = $pingData;
            $response['data']['traceroute'] = $traceData;
        } catch (\Throwable $e) {
            $response['msg'] = 'Diagnostic failed: ' . $e->getMessage();
            Log::error('MIKROTIK_DIAGNOSE_ERROR', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
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
