<?php
require_once 'init.php';
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;

// --------------------------
// 0. Helper Function: Fast Port Check
// --------------------------
if (!function_exists('isRouterReachable')) {
    function isRouterReachable($ip, $port = 8728, $timeout = 1) {
        $connection = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        return false;
    }
}

// --------------------------
// Register PPPoE & Hotspot menu
register_menu("PPPoE Online", true, "pppoe_online_ui", 'AFTER_PLANS', 'ion ion-network');
register_menu("Hotspot Online", true, "hotspot_online_ui", 'AFTER_PLANS', 'ion ion-android-wifi');

// Disconnect user route (AJAX)
if(isset($_GET['_route']) && $_GET['_route']=='plugin/disconnect_user'){
    $router_id = $_GET['router_id'] ?? 0;
    $username  = $_GET['user_id'] ?? '';
    $service   = $_GET['service_type'] ?? '';

    if(!$router_id || !$username) 
        exit(json_encode(['status'=>false,'msg'=>'Invalid parameters']));

    $router = ORM::for_table('tbl_routers')->find_one($router_id);
    if(!$router) 
        exit(json_encode(['status'=>false,'msg'=>'Router not found']));

    $api_port = !empty($router->api_port) ? $router->api_port : 8728;
    if(!isRouterReachable($router->ip_address, $api_port, 1)){
        exit(json_encode(['status'=>false,'msg'=>'Router is offline or unreachable']));
    }

    try {
        ini_set('default_socket_timeout', 2);
        $client = Mikrotik::getClient($router->ip_address, $router->username, $router->password, 2);

        if($service == 'PPPoE'){
            $pppList = $client->sendSync(new Request('/ppp/active/print'));
            $id = null;
            foreach($pppList as $ppp){
                if($ppp->getType() !== Response::TYPE_DATA) continue;
                if($ppp->getProperty('name') == $username){
                    $id = $ppp->getProperty('.id');
                    break;
                }
            }
            if(!$id) exit(json_encode(['status'=>false,'msg'=>'PPPoE user not active']));
            $client->sendSync((new Request('/ppp/active/remove'))->setArgument('.id', $id));

        } else {
            $hsList = $client->sendSync(new Request('/ip/hotspot/active/print'));
            $id = null;
            foreach($hsList as $hs){
                if($hs->getType() !== Response::TYPE_DATA) continue;
                if($hs->getProperty('user') == $username){
                    $id = $hs->getProperty('.id');
                    break;
                }
            }
            if(!$id) exit(json_encode(['status'=>false,'msg'=>'Hotspot user not active']));
            $client->sendSync((new Request('/ip/hotspot/active/remove'))->setArgument('.id', $id));
        }

        echo json_encode(['status'=>true,'msg'=>'User disconnected']);
    } catch(Exception $e){
        echo json_encode(['status'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// --------------------------
// Single user live traffic AJAX
if(isset($_GET['_route']) && $_GET['_route']=='plugin/get_user_traffic'){
    $router_id = $_GET['router_id'] ?? 0;
    $username  = $_GET['username'] ?? '';
    $service   = $_GET['service_type'] ?? 'PPPoE';
    
    if(!$router_id || !$username) exit(json_encode(['tx'=>0,'rx'=>0,'tx_rate'=>0,'rx_rate'=>0,'tx_human'=>'0 B/s','rx_human'=>'0 B/s']));

    $router = ORM::for_table('tbl_routers')->find_one($router_id);
    if(!$router) exit(json_encode(['tx'=>0,'rx'=>0,'tx_rate'=>0,'rx_rate'=>0,'tx_human'=>'0 B/s','rx_human'=>'0 B/s']));

    $traffic = ['tx'=>0,'rx'=>0,'tx_rate'=>0,'rx_rate'=>0,'tx_human'=>'0 B/s','rx_human'=>'0 B/s'];

    $api_port = !empty($router->api_port) ? $router->api_port : 8728;
    if(!isRouterReachable($router->ip_address, $api_port, 1)){
        header('Content-Type: application/json');
        echo json_encode($traffic);
        exit;
    }

    try {
        ini_set('default_socket_timeout', 2);
        $client = Mikrotik::getClient($router->ip_address, $router->username, $router->password, 2);

        if($service=='PPPoE'){
            $pppPrint = $client->sendSync(new Request('/ppp/active/print'));
            $actualIfaceName = '';
            
            foreach($pppPrint as $ppp){
                if($ppp->getType()!==Response::TYPE_DATA) continue;
                if(strcasecmp(trim($ppp->getProperty('name')), trim($username)) === 0){
                    $actualIfaceName = $ppp->getProperty('interface');
                    break;
                }
            }

            $ifaceStats = $client->sendSync((new Request('/interface/print'))->setArgument('stats',true));
            $ifaceData = [];
            
            foreach($ifaceStats as $iface){
                if($iface->getType()!==Response::TYPE_DATA) continue;
                $name = $iface->getProperty('name');
                if(!$name) continue;
                
                $cleanName = strtolower(trim($name, "<> "));
                $ifaceData[$cleanName] = [
                    'tx_total' => (int)($iface->getProperty('tx-byte')??0),
                    'rx_total' => (int)($iface->getProperty('rx-byte')??0),
                    'tx_rate'  => (int)($iface->getProperty('tx-rate')??0),
                    'rx_rate'  => (int)($iface->getProperty('rx-rate')??0),
                ];
            }

            $matchedData = null;
            if(!empty($actualIfaceName)){
                $cleanActualName = strtolower(trim($actualIfaceName, "<> "));
                $matchedData = $ifaceData[$cleanActualName] ?? null;
            }
            
            if(!$matchedData){
                $backupName = "pppoe-" . strtolower(trim($username));
                $matchedData = $ifaceData[$backupName] ?? null;
            }

            if($matchedData){
                $traffic['tx'] = $matchedData['tx_total'];
                $traffic['rx'] = $matchedData['rx_total'];
                $tx_rate = $matchedData['tx_rate'];
                $rx_rate = $matchedData['rx_rate'];

                if($tx_rate == 0 && $rx_rate == 0){
                    if(session_status()!==PHP_SESSION_ACTIVE) session_start();
                    $key = 'pppoe_rate_'.$router_id.'_'.strtolower(trim($username));
                    $currentTime = microtime(true);

                    if(isset($_SESSION[$key])){
                        $prev = $_SESSION[$key];
                        $timeDiff = max(0.5, $currentTime - $prev['time']);
                        $tx_rate = max(0, $traffic['tx'] - $prev['tx']) / $timeDiff;
                        $rx_rate = max(0, $traffic['rx'] - $prev['rx']) / $timeDiff;
                    }
                    $_SESSION[$key] = ['tx' => $traffic['tx'], 'rx' => $traffic['rx'], 'time' => $currentTime];
                }

                $traffic['tx_rate'] = $tx_rate; 
                $traffic['rx_rate'] = $rx_rate; 
                $traffic['tx_human'] = formatSpeed($tx_rate);
                $traffic['rx_human'] = formatSpeed($rx_rate);
            }
        } else { 
            $hsList = $client->sendSync(new Request('/ip/hotspot/active/print'));
            foreach($hsList as $hs){
                if($hs->getType()!==Response::TYPE_DATA) continue;
                if(strcasecmp(trim($hs->getProperty('user')), trim($username)) === 0){
                    $traffic['tx'] = (int)($hs->getProperty('bytes-out') ?? 0);
                    $traffic['rx'] = (int)($hs->getProperty('bytes-in') ?? 0);
                    
                    $tx_rate = 0; $rx_rate = 0;
                    if(session_status()!==PHP_SESSION_ACTIVE) session_start();
                    $key = 'hotspot_rate_'.$router_id.'_'.strtolower(trim($username));
                    $currentTime = microtime(true);

                    if(isset($_SESSION[$key])){
                        $prev = $_SESSION[$key];
                        $timeDiff = max(0.5, $currentTime - $prev['time']); 
                        $tx_rate = max(0, $traffic['tx'] - $prev['tx']) / $timeDiff;
                        $rx_rate = max(0, $traffic['rx'] - $prev['rx']) / $timeDiff;
                    }
                    $_SESSION[$key] = ['tx'=>$traffic['tx'],'rx'=>$traffic['rx'],'time'=>$currentTime];

                    $traffic['tx_rate'] = $tx_rate;
                    $traffic['rx_rate'] = $rx_rate;
                    $traffic['tx_human'] = formatSpeed($tx_rate);
                    $traffic['rx_human'] = formatSpeed($rx_rate);
                    break;
                }
            }
        }
    } catch(Exception $e){
        // Fail-silent
    }

    header('Content-Type: application/json');
    echo json_encode($traffic);
    exit;
}

// --------------------------
// Helper Functions
function formatBytes($bytes,$precision=2){
    $units = ['B','KB','MB','GB','TB'];
    $bytes = max($bytes,0);
    $pow = floor(($bytes ? log($bytes)/log(1024) : 0));
    $pow = min($pow,count($units)-1);
    $bytes /= pow(1024,$pow);
    return round($bytes,$precision).' '.$units[$pow];
}

function formatSpeed($bytes){
    $units = ['B/s', 'KB/s', 'MB/s', 'GB/s', 'TB/s'];
    if($bytes<=0) return '0 B/s';
    $i=floor(log($bytes,1024));
    $i=min($i,count($units)-1);
    return round($bytes/pow(1024,$i),2).' '.$units[$i];
}

// --------------------------
// PPPoE Online UI
if(!function_exists('pppoe_online_ui')){
    function pppoe_online_ui(){
        global $ui; _admin();
        ini_set('default_socket_timeout', 2);

        $ui->assign('_title','PPPoE Online Users');
        $ui->assign('_system_menu','pppoe_online');
        $admin = Admin::_info(); $ui->assign('_admin',$admin);

        // ১. লগইন করা ইউজারের তথ্য বের করা (router কলামের বদলে routers ব্যবহার করা হয়েছে)
        $logged_user = ORM::for_table('tbl_users')->select_many('routers', 'user_type')->where('id', $admin['id'])->find_one();
        $user_router = $logged_user ? trim($logged_user->routers) : '';
        $user_type   = $logged_user ? strtolower(trim($logged_user->user_type)) : '';

        // ২. রাউটার ফিল্টারিং
        $routerQuery = ORM::for_table('tbl_routers')->where('enabled', 1);

        if (in_array($user_type, ['superadmin', 'admin']) && ($user_router == 'all' || $user_router == '0' || empty($user_router))) {
            // SuperAdmin / Main Admin
        } else if (!empty($user_router) && $user_router != '0' && $user_router != 'all') {
            $routerQuery->where_raw("(id = ? OR name = ?)", [$user_router, $user_router]);
        } else {
            $routerQuery->where('id', 0);
        }

        $routers = $routerQuery->find_many();
        $users = [];

        foreach($routers as $router){
            $api_port = !empty($router['api_port']) ? $router['api_port'] : 8728;

            // রাউটার অফলাইন থাকলে দ্রুত স্কিপ করা হবে
            if (!isRouterReachable($router['ip_address'], $api_port, 1)) {
                continue;
            }

            try {
                $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password'], 2);
                $pppResponse = $client->sendSync(new Request('/ppp/active/print'));
                $ifaceResponse = $client->sendSync((new Request('/interface/print'))->setArgument('stats', true));
                
                $active_usernames = [];
                $ppp_list = [];

                foreach($pppResponse as $ppp){
                    if($ppp->getType() === Response::TYPE_DATA){
                        $uname = $ppp->getProperty('name');
                        if($uname) {
                            $uname_trim = trim($uname);
                            $active_usernames[] = $uname_trim;
                            $ppp_list[] = $ppp;
                        }
                    }
                }

                if(empty($active_usernames)) continue;

                // কাস্টমার টেবিল অপটিমাইজড ফেচ
                $customer_map = [];
                $custQuery = ORM::for_table('tbl_customers')
                    ->select_many('username', 'fullname', 'address')
                    ->where_in('username', $active_usernames);
                
                if (!in_array($user_type, ['superadmin'])) {
                    $custQuery->where_raw("(admin_id = ? OR created_by = ?)", [$admin['id'], $admin['id']]);
                }

                $customers = $custQuery->find_many();
                foreach($customers as $cust){
                    $customer_map[strtolower(trim($cust->username))] = $cust;
                }

                // ইন্টারফেস ডাটা হ্যাশ ম্যাপ
                $ifaceData = [];
                foreach($ifaceResponse as $iface){
                    if($iface->getType() !== Response::TYPE_DATA) continue;
                    $name = $iface->getProperty('name');
                    if(!$name) continue;
                    
                    $cleanName = strtolower(trim($name, "<> "));
                    $ifaceData[$cleanName] = [
                        'tx_total' => (int)($iface->getProperty('tx-byte') ?? 0),
                        'rx_total' => (int)($iface->getProperty('rx-byte') ?? 0),
                        'tx_rate'  => (int)($iface->getProperty('tx-rate') ?? 0),
                        'rx_rate'  => (int)($iface->getProperty('rx-rate') ?? 0),
                    ];
                }
                
                foreach($ppp_list as $ppp){
                    $username = $ppp->getProperty('name');
                    $username_lc = strtolower(trim($username));
                    $customer_info = $customer_map[$username_lc] ?? null;

                    if (!in_array($user_type, ['superadmin']) && !$customer_info) {
                        continue;
                    }

                    $actualIface = $ppp->getProperty('interface');
                    $fullname = ($customer_info && !empty($customer_info->fullname)) ? $customer_info->fullname : $username;
                    $address = ($customer_info && !empty($customer_info->address)) ? $customer_info->address : '-';

                    $matched = null;
                    if(!empty($actualIface)){
                        $matched = $ifaceData[strtolower(trim($actualIface, "<> "))] ?? null;
                    }
                    if(!$matched){
                        $matched = $ifaceData["pppoe-".$username_lc] ?? null;
                    }

                    $upload = $matched['tx_total'] ?? 0;
                    $download = $matched['rx_total'] ?? 0;
                    $tx_rate = $matched['tx_rate'] ?? 0;
                    $rx_rate = $matched['rx_rate'] ?? 0;

                    $users[] = [
                        'router_id'=>$router['id']??0,
                        'router_name'=>$router['name']??'Router',
                        'id'=>$ppp->getProperty('.id'),
                        'name'=>$username,
                        'fullname'=>$fullname, 
                        'address'=>$address,   
                        'ip'=>$ppp->getProperty('address')??'-',
                        'mac'=>$ppp->getProperty('caller-id')??'-',
                        'service_type'=>'PPPoE',
                        'status'=>'on',
                        'upload'=>$upload,
                        'download'=>$download,
                        'total'=>$upload+$download,
                        'tx_rate'=>$tx_rate,
                        'rx_rate'=>$rx_rate,
                        'tx_human'=>formatSpeed($tx_rate),
                        'rx_human'=>formatSpeed($rx_rate),
                        'uptime'=>$ppp->getProperty('uptime')??'-'
                    ];
                }
            } catch(Exception $e){ continue; }
        }

        if(isset($_GET['ajax']) && $_GET['ajax']==1){
            header('Content-Type: application/json');
            echo json_encode($users);
            exit;
        }

        $ui->assign('online_users',$users);
        $ui->display('pppoe_online.tpl');
    }
}

// --------------------------
// Hotspot Online UI
if(!function_exists('hotspot_online_ui')){
    function hotspot_online_ui(){
        global $ui; _admin();
        ini_set('default_socket_timeout', 2);

        $ui->assign('_title','Hotspot Online Users');
        $ui->assign('_system_menu','hotspot_online');
        $admin = Admin::_info(); $ui->assign('_admin',$admin);

        // ১. লগইন করা ইউজারের তথ্য বের করা (router কলামের বদলে routers ব্যবহার করা হয়েছে)
        $logged_user = ORM::for_table('tbl_users')->select_many('routers', 'user_type')->where('id', $admin['id'])->find_one();
        $user_router = $logged_user ? trim($logged_user->routers) : '';
        $user_type   = $logged_user ? strtolower(trim($logged_user->user_type)) : '';

        // ২. রাউটার ফিল্টারিং
        $routerQuery = ORM::for_table('tbl_routers')->where('enabled', 1);

        if (in_array($user_type, ['superadmin', 'admin']) && ($user_router == 'all' || $user_router == '0' || empty($user_router))) {
            // SuperAdmin / Main Admin
        } else if (!empty($user_router) && $user_router != '0' && $user_router != 'all') {
            $routerQuery->where_raw("(id = ? OR name = ?)", [$user_router, $user_router]);
        } else {
            $routerQuery->where('id', 0);
        }

        $routers = $routerQuery->find_many();
        $users = [];
        if(session_status() !== PHP_SESSION_ACTIVE) session_start();

        foreach($routers as $router){
            $api_port = !empty($router['api_port']) ? $router['api_port'] : 8728;

            // রাউটার অফলাইন থাকলে দ্রুত স্কিপ করা হবে
            if (!isRouterReachable($router['ip_address'], $api_port, 1)) {
                continue;
            }

            try {
                $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password'], 2);
                $hsList = $client->sendSync(new Request('/ip/hotspot/active/print'));
                
                $active_usernames = [];
                $hs_active_items = [];

                foreach($hsList as $hs){
                    if($hs->getType() === Response::TYPE_DATA){
                        $uname = $hs->getProperty('user');
                        if($uname) {
                            $uname_trim = trim($uname);
                            $active_usernames[] = $uname_trim;
                            $hs_active_items[] = $hs;
                        }
                    }
                }

                if(empty($active_usernames)) continue;

                $customer_map = [];
                $custQuery = ORM::for_table('tbl_customers')
                    ->select_many('username', 'fullname', 'address')
                    ->where_in('username', $active_usernames);
                
                if (!in_array($user_type, ['superadmin'])) {
                    $custQuery->where_raw("(admin_id = ? OR created_by = ?)", [$admin['id'], $admin['id']]);
                }

                $customers = $custQuery->find_many();
                foreach($customers as $cust){
                    $customer_map[strtolower(trim($cust->username))] = $cust;
                }

                $currentTime = microtime(true);

                foreach($hs_active_items as $hs){
                    $username = $hs->getProperty('user') ?? '-';
                    $username_lc = strtolower(trim($username));
                    
                    $customer_info = $customer_map[$username_lc] ?? null;

                    if (!in_array($user_type, ['superadmin']) && !$customer_info) {
                        continue;
                    }

                    $bytes_in = (int)($hs->getProperty('bytes-in') ?? 0);
                    $bytes_out = (int)($hs->getProperty('bytes-out') ?? 0);
                    $uptime = $hs->getProperty('uptime') ?? '-';
                    $id = $hs->getProperty('.id');
                    
                    $fullname = ($customer_info && !empty($customer_info->fullname)) ? $customer_info->fullname : $username;
                    $address = ($customer_info && !empty($customer_info->address)) ? $customer_info->address : '-';

                    $key = 'hotspot_traffic_'.$router['id'].'_'.$username_lc;
                    $tx_rate = $rx_rate = 0;

                    if(isset($_SESSION[$key])){
                        $prev = $_SESSION[$key];
                        $timeDiff = max(0.5, $currentTime - $prev['time']); 
                        $tx_rate = max(0, $bytes_out - $prev['tx']) / $timeDiff;
                        $rx_rate = max(0, $bytes_in - $prev['rx']) / $timeDiff;
                    }

                    $_SESSION[$key] = ['tx'=>$bytes_out, 'rx'=>$bytes_in, 'time'=>$currentTime];

                    $users[] = [
                        'router_id'=>$router['id'],
                        'router_name'=>$router['name'] ?? 'Router',
                        'id'=>$id,
                        'name'=>$username,
                        'fullname'=>$fullname,
                        'address'=>$address,
                        'ip'=>$hs->getProperty('address') ?? '-',
                        'mac'=>$hs->getProperty('mac-address') ?? '-',
                        'service_type'=>'Hotspot',
                        'status'=>'on',
                        'upload'=>$bytes_out,
                        'download'=>$bytes_in,
                        'total'=>$bytes_in+$bytes_out,
                        'tx_rate'=>$tx_rate,
                        'rx_rate'=>$rx_rate,
                        'tx_human'=>formatSpeed($tx_rate),
                        'rx_human'=>formatSpeed($rx_rate),
                        'uptime'=>$uptime
                    ];
                }

            } catch(Exception $e){ continue; }
        }

        if(isset($_GET['ajax']) && $_GET['ajax']==1){
            header('Content-Type: application/json');
            echo json_encode($users);
            exit;
        }

        $ui->assign('online_users',$users);
        $ui->display('hotspot_online.tpl');
    }
}
