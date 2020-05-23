<?php
namespace Rajtika\Mikrotik\Services;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
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
    /**
     * @var bool
    */
    private static $connected = false;

    public function __construct()
    {
        self::init();
    }

    public static function dump() {
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
        dd( self::monitor('sojib'));
        dd( 'Dump from Mikrotik Services for Pear2');
    }

    private static function init()
    {
        $router = Auth::user()->router;
        if( $router !== null ) {
            self::$host =  $router['mktik_host'];
            self::$port = $router['mktik_port'] ?? '8728';
            self::$user = $router['mktik_user'];
            self::$password = $router['mktik_password'];
            self::$service = $router['mktik_interface'] ?? 'pppoe';
        }
    }

    public static function _set( $router )
    {
        if( $router !== null ) {
            self::$host =  $router['mktik_host'];
            self::$port = $router['mktik_port'] ?? '8728';
            self::$user = $router['mktik_user'];
            self::$password = $router['mktik_password'];
            self::$service = $router['mktik_interface'] ?? 'pppoe';
        }
    }

    public static function connect()
    {
        // dd( self::$host . '-' . self::$user . '-' . self::$port);
        if( !empty( self::$host ) && !empty( self::$user ) && !empty( self::$password ) ) {
            try {
                // dd( self::$host . '-' . self::$user . '-' . self::$port);
                self::$client = new Client(self::$host, self::$user, self::$password, self::$port);
                self::$connected = true;
            } catch (Exception $e) {
                dd( $e->getMessage() );
                self::$connected = false;
            }
        } else {
            self::$connected = false;
        }
    }

    public static function getStatus()
    {
        self::connect();
        if( self::$connected ) {
            $responses = self::$client->sendSync(new Request('/interface pppoe-client monitor numbers=pppoe-out1 once'));

            foreach ($responses as $item){
                echo 'Status :', $item->getProperty('status');
            }
        }
    }

    public static function torch( $name = null )
    {
        $data = [];
        if( $name !== null ) {
            self::connect();
            if( self::$connected ) {
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
        $data = [];
        self::connect();
        if( self::$connected ) {
            // /interface/monitor-traffic interface=eathernet
            $responses = self::$client->sendSync(new Request('/interface pppoe-client monitor numbers=ether8'));
            $data = $responses;
        }

        return $data;
    }

    public static function resource()
    {
        $data = [];
        if( self::mikrotik_enabled() ) {
            self::connect();
            if( self::$connected == false ) {
            }
            $data = self::$client->sendSync(new Request('/system/resource/print'))->getAllOfType(RouterOS\Response::TYPE_DATA);
        }
        return $data;
    }

    public static function logs()
    {
        $response = array('status' => false, 'msg' => '');
        if( self::mikrotik_enabled() ) {
            self::connect();
            if( self::$connected == false ) {
                $response['msg'] = 'Could not connect to router.';
                $response['status'] = false;
                return $response;
            }
            try{
                $util = new Util( self::$client );
                $response['status'] = true;
                $response['data'] = $util->setMenu('/log')->getAll();
            } catch (Exception $e) {
                $response['msg'] = 'An error occured to collect system user logs';
            }
        } else {
            $response['msg'] = 'Mikrotik configuration is not set.';
        }
        return $response;
    }

    /*
    * Get All connections with type ['secret', 'active']
    */
    public static function getAll( $type = 'inactive')
    {
        $response = array('status' => false, 'msg' => '', 'data' => []);
        if( self::mikrotik_enabled() ) {
            self::connect();
            if( self::$connected == false ) {
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
    public static function getAll2( $type = 'inactive')
    {
        $response = array('status' => false, 'msg' => '', 'data' => []);
        if( self::mikrotik_enabled() ) {
            self::connect();
            if( self::$connected == false ) {
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

    public static function get( $id = null )
    {
        $response = ['status' => false, 'msg' => ''];
        if( self::mikrotik_enabled() ) {
            self::connect();
            if( self::$connected == false ) {
                $response['msg'] = 'Could not connect to router.';
                return $response;
            }
            if( $id != null ) {
                $customer = new Request('/ppp/secret/getall detail=""');
                $customer->setQuery(Query::where('.id', $id));
                $info = self::$client->sendSync($customer);
                if ( !empty( $info[0] ) ) :
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

    public static function getByName( $name = '' ) {
        $response = array('status' => false, 'msg' => '');
        if( self::mikrotik_enabled() ) {
            self::connect();
            if( self::$connected == false ) {
                $response['msg'] = 'Could not connect to router.';
                return $response;
            }

            if( $name ) :
                $customer = new Request('/ppp/secret/getall');
                $customer->setArgument('.proplist', '.id,name,profile,disabled');
                $customer->setQuery(Query::where('name', $name));
                $info = self::$client->sendSync($customer);
                if ( !empty( $info[0] ) ) :
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
    public static function getServerByName( $name = '' ) {
        $response = array('status' => false, 'msg' => '');
        if( self::mikrotik_enabled() ) {
            self::connect();
            if( self::$connected == false ) {
                $response['msg'] = 'Could not connect to router.';
                return $response;
            }

            if( $name ) :
                $customer = new Request('/interface/pppoe-server/print');
                $customer->setArgument('.proplist', '.id,name,profile,disabled,service');
                $customer->setQuery(Query::where('name', $name));
                $info = self::$client->sendSync($customer);
                if ( !empty( $info[0] ) ) :
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

    public static function create( $customer ) {
        $response = ['status' => false, 'msg' => '', 'data' => []];
        //check mikrotike enabled
        if( self::mikrotik_enabled() ) {
            self::connect();
            if (self::$connected == false) {
                $response['msg'] = 'Router not connected';
                return $response;
            }
            $user = new Request('/ppp/secret/add');
            $user->setArgument('name', $customer->customerID);
            $user->setArgument('profile', $customer->package['code']);
            $user->setArgument('password', $customer->user->mobile);
            $user->setArgument('service', self::$service);
            $user->setArgument('comment', 'Via api [pkg - ' . $customer->package->name . ', price- ' . $customer->package['price'] . 'Tk., date- ' . date('d/m/Y'));
            $user->setArgument('disabled', 'no');

            if (self::$client->sendSync($user)->getType() !== RouterOS\Response::TYPE_FINAL) {
                $response['msg'] = 'Sorry! cannot create customer';
            } else {
//                self::$client->loop();
                $customerAcc = self::getByName( $customer->customerID );
                $response['data'] = ( $customerAcc['status'] == true ) ? $customerAcc['data'] : null;
                $response['status'] = true;
                $response['msg'] = 'Customer has been successfully created';
            }
        } else {
            $response['status'] = true;
            $response['msg'] = 'Mikrotik configuration is not set.';
        }
        return $response;
    }

    public static function enable( $customer = '' ) {
        $response = ['status' => false, 'msg' => '', 'data' => []];
        if( self::mikrotik_enabled() ) {
            self::connect();
            if (self::$connected == false) {
                $response['msg'] = 'Could not connect to router';
                return $response;
            }
            if ($customer !== '') {
                $mktikId = $customer['mikrotik_id'];
                if( empty( $mktikId ) ) {
                    $customerAcc = self::getByName( $customer->customerID );
                    if( $customerAcc['status'] == true ) {
                        $mktikId = $customerAcc['data']->getProperty('.id');
                    }
                }
                if (!empty( $mktikId ) ) {
                    $customer = new Request('/ppp/secret/set');
                    $customer->setArgument('.id', $mktikId);
                    $customer->setArgument('disabled', 'no');
                    $customer->setArgument('.proplist', '.id,name,profile,service');
                    if (self::$client->sendSync($customer)->getType() === Response::TYPE_FINAL) {
                        $response['status'] = true;
                    } else {
                        $response['msg'] = 'Mikrotik! Customer cannot be enabled.';
                    }
                } else {
                    $newAcc = self::create( $customer );
                    if( $newAcc['status'] == true ) {
                        $response['status'] = true;
                        $response['msg'] = 'Customer added to mikrotik';
                    } else {
                        $response['msg'] = 'Mikrotik ID not set.';
                    }
                }
            } else {
                $response['msg'] = 'Customer not found.';
            }
        } else {
            $response['status'] = true;
            $response['msg'] = 'Mikrotike configuration not set.';
        }
        return $response;
    }

    public static function disable( $customer = '' ) {
        $response = ['status' => true, 'msg' => 'Cannot disable customer'];
        if( self::mikrotik_enabled() ) {
            self::connect();
            if (self::$connected == false) {
                $response['msg'] = 'Could not connect to router';
                return $response;
            }
            if ($customer != '') :
                $mktikId = $customer['mikrotik_id'];
                if( $mktikId == '' ) {
                    $user = self::getByName($customer['customerID']);
                    if( !empty($user['.id'] ) ) {
                        $mktikId = $user['.id'];
                    }
                }

                if (!empty( $mktikId ) ) {
                    $userAcc = new Request('/ppp/secret/set');
                    $userAcc->setArgument('.id', $mktikId);
                    $userAcc->setArgument('disabled', 'yes');
                    $userAcc->setArgument('.proplist', '.id,name,profile,service');
                    if (self::$client->sendSync($userAcc)->getType() === Response::TYPE_FINAL) {
                        $remove = new RouterOs\Request("/ppp/active/remove");
                        $remove->setArgument('numbers', $mktikId);
                        self::$client->sendSync($remove);
                        // $userAcc = new Request('/ppp/active/remove');
                        // $userAcc->setArgument('.id', $mktikId);
                        // self::$client->sendSync($userAcc);
                        // $util = new Util(self::$client);
                        // $util->setMenu('/ppp active')->remove($customer->customerID);
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
            $response['msg'] = 'Mikrotike configuration not set';
        }

        return $response;
    }

    public static function changeName( $params = array() ) {
        $response = ['status' => false, 'msg' => 'Cannot change customerID'];

        if( self::mikrotik_enabled() ) {
            self::connect();
            if( self::$connected == false ) {
                $response['msg'] = 'Could not connect to router';
                return $response;
            }
            if( $params['name'] != '' ) {
                $customer = new Request('/ppp/secret/set');
                $customer->setArgument('.id', $params['id']);
                $customer->setArgument('name', $params['name']);
                if( self::$client->sendSync($customer)->getType() === Response::TYPE_FINAL ) {
                    $response['status'] = true;
                    $response['msg'] = 'Your CustomerID has been changed';
                }

            } else {
                $response['msg'] = 'Mikrotik ID not found.';
            }
        } else {
            $response['status'] = true;
            $response['msg'] = 'Mikrotike configuration not set.';
        }

        return $response;
    }

    public static function changePassword( $params = array() ) {
        $response = ['status' => false, 'msg' => ''];
        if( self::mikrotik_enabled() ) {
            self::connect();
            if (self::$connected == false) {
                $response['msg'] = 'Could not connect to router';
                return $response;
            }
            if ($params['password'] != '') {
                $customer = new Request('/ppp/secret/set');
                $customer->setArgument('.id', $params['.id']);
                $customer->setArgument('password', $params['password']);
                if (self::$client->sendSync($customer)->getType() === Response::TYPE_FINAL) {
                    $response['status'] = true;
                } else {
                    $response['msg'] = 'Sorry! cannot change password';
                }
            } else {
                $response['msg'] = 'Your password is empty';
            }
        } else {
            $response['status'] = true;
            $response['msg'] = 'Mikrotike configuration not set.';
        }
        return $response;
    }

    /**
     * Change Profile means Change the Packege
     **/
    public static function changeProfile( $params = array() ) {
        $response = ['status' => false, 'msg' => ''];
        if( self::mikrotik_enabled() ) {
            self::connect();
            if (self::$connected == false) {
                $response['msg'] = 'Could not connect to router';
                return $response;
            }
            if ($params['profile'] != '') {
                $customer = new Request('/ppp/secret/set');
                $customer->setArgument('.id', $params['id']);
                $customer->setArgument('profile', $params['profile']);
                if (self::$client->sendSync($customer)->getType() === Response::TYPE_FINAL) {
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
            $response['msg'] = 'Mikrotike configuration not set.';
        }

        return $response;
    }

    private static function mikrotik_enabled()
    {
        if ( getOption('mikrotik_access') ) {
            if( !empty( self::$host ) && !empty( self::$user ) && self::$password && self::$port ) {
                return true;
            }
        }
        return false;
    }

    private function _exist( $params ) {
        self::connect();
        if( self::$connected == false ) {
            $this->customer_exist = false;
        }
        if( $params['name'] ) {
            $customer = new Request('/ppp/secret/getall');
            $customer->setArgument('.proplist', '.id,name,profile,service');
            $customer->setQuery(Query::where('name', $params['name']));
            $id = self::$client->sendSync($customer)->getProperty('.id');
            if( !empty($id) && is_array($id) ) {
                $this->customer_exist = true;
            }
        }
    }

    public static function reboot()
    {
        self::connect();

        if( self::$connected ) {
            $request = new Request(
                '/system scheduler add name=REBOOT interval=2s
                on-event="/system scheduler remove REBOOT;/system reboot"'
            );
            self::$client->sendSync($request);
        }
    }

    public function __destruct()
    {
        // unset(self::$client);
        self::$client = null;
    }
}
