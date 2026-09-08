<?php
require_once 'init.php';
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;

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

    try {
        $client = Mikrotik::getClient($router->ip_address, $router->username, $router->password);

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
// Single user live traffic (100% FIXED USING DIRECT MIKROTIK INTERFACE PROPERTY)
if(isset($_GET['_route']) && $_GET['_route']=='plugin/get_user_traffic'){
    $router_id = $_GET['router_id'] ?? 0;
    $username  = $_GET['username'] ?? '';
    $service   = $_GET['service_type'] ?? 'PPPoE';
    
    if(!$router_id || !$username) exit(json_encode(['tx'=>0,'rx'=>0,'tx_rate'=>0,'rx_rate'=>0,'tx_human'=>'0 B/s','rx_human'=>'0 B/s']));

    $router = ORM::for_table('tbl_routers')->find_one($router_id);
    if(!$router) exit(json_encode(['tx'=>0,'rx'=>0,'tx_rate'=>0,'rx_rate'=>0,'tx_human'=>'0 B/s','rx_human'=>'0 B/s']));

    $traffic = ['tx'=>0,'rx'=>0,'tx_rate'=>0,'rx_rate'=>0,'tx_human'=>'0 B/s','rx_human'=>'0 B/s'];

    try {
        $client = Mikrotik::getClient($router->ip_address,$router->username,$router->password);

        if($service=='PPPoE'){
            // ১. একটিভ পিপিইওই লিস্ট থেকে ওই ইউজারের আসল ইন্টারফেসের নাম খুঁজে বের করা
            $pppPrint = $client->sendSync(new Request('/ppp/active/print'));
            $actualIfaceName = '';
            
            foreach($pppPrint as $ppp){
                if($ppp->getType()!==Response::TYPE_DATA) continue;
                if(strtolower(trim($ppp->getProperty('name'))) == strtolower(trim($username))){
                    // মিক্রোটিক অনেক সময় 'interface' প্রোপার্টিতে আসল ইন্টারফেসের ডাইনামিক নাম দেয়
                    $actualIfaceName = $ppp->getProperty('interface');
                    break;
                }
            }

            // ২. যদি একটিভ প্রিন্ট থেকে ইন্টারফেসের নাম না পাওয়া যায়, তবে ব্যাকআপ হিসেবে স্ট্যান্ডার্ড ফরমেট ট্রাই করবে
            $ifaceStats = $client->sendSync((new Request('/interface/print'))->setArgument('stats',true));
            $ifaceData = [];
            
            foreach($ifaceStats as $iface){
                if($iface->getType()!==Response::TYPE_DATA) continue;
                $name = $iface->getProperty('name');
                if(!$name) continue;
                
                // সব নাম লোয়ারকেস ও ক্লিন করে ম্যাপ করা হচ্ছে
                $cleanName = strtolower(trim($name, "<> "));
                $ifaceData[$cleanName] = [
                    'tx_total' => (int)($iface->getProperty('tx-byte')??0),
                    'rx_total' => (int)($iface->getProperty('rx-byte')??0),
                    'tx_rate'  => (int)($iface->getProperty('tx-rate')??0),
                    'rx_rate'  => (int)($iface->getProperty('rx-rate')??0),
                ];
            }

            // ম্যাচিং প্রায়োরিটি লজিক
            $matchedData = null;
            if(!empty($actualIfaceName)){
                $cleanActualName = strtolower(trim($actualIfaceName, "<> "));
                if(isset($ifaceData[$cleanActualName])){
                    $matchedData = $ifaceData[$cleanActualName];
                }
            }
            
            // ব্যাকআপ ম্যাচিং (যদি ডাইনামিক ইন্টারফেসের নাম না মিলে)
            if(!$matchedData){
                $backupName = "pppoe-" . strtolower(trim($username));
                if(isset($ifaceData[$backupName])){
                    $matchedData = $ifaceData[$backupName];
                }
            }

            if($matchedData){
                // মিক্রোটিক ইন্টারফেস পার্সপেক্টিভ ফিক্স (TX = Router Out/Download, RX = Router In/Upload)
                $traffic['tx'] = $matchedData['tx_total'];
                $traffic['rx'] = $matchedData['rx_total'];
                $tx_rate = $matchedData['tx_rate'];
                $rx_rate = $matchedData['rx_rate'];

                // সেশন ভিত্তিক সেফটি নেট (যদি মিক্রোটিক রেট ০ পাঠায়)
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
                if(strtolower(trim($hs->getProperty('user'))) == strtolower(trim($username))){
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
// Format bytes
function formatBytes($bytes,$precision=2){
    $units = ['B','KB','MB','GB','TB'];
    $bytes = max($bytes,0);
    $pow = floor(($bytes ? log($bytes)/log(1024) : 0));
    $pow = min($pow,count($units)-1);
    $bytes /= pow(1024,$pow);
    return round($bytes,$precision).' '.$units[$pow];
}

// --------------------------
// Format speed human readable
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
        $ui->assign('_title','PPPoE Online Users');
        $ui->assign('_system_menu','pppoe_online');
        $admin = Admin::_info(); $ui->assign('_admin',$admin);

        $routers = ORM::for_table('tbl_routers')->where('enabled',1)->find_many();
        $users = [];

        foreach($routers as $router){
            $sock=@fsockopen($router['ip_address'],8728,$errno,$errstr,1);
            if(!$sock) continue;
            fclose($sock);

            try {
                $client = Mikrotik::getClient($router['ip_address'],$router['username'],$router['password']);
                $pppResponse = $client->sendSync(new Request('/ppp/active/print'));
                $ifaceResponse = $client->sendSync((new Request('/interface/print'))->setArgument('stats',true));
                
                $active_usernames = [];
                foreach($pppResponse as $ppp){
                    if($ppp->getType()===Response::TYPE_DATA){
                        $uname = $ppp->getProperty('name');
                        if($uname) {
                            $active_usernames[] = trim($uname);
                        }
                    }
                }

                $customer_map = [];
                if(!empty($active_usernames)){
                    $customers = ORM::for_table('tbl_customers')
                        ->where_in('username', $active_usernames)
                        ->find_many();
                    
                    foreach($customers as $cust){
                        $customer_map[strtolower(trim($cust->username))] = $cust;
                    }
                }

                $ifaceData = [];
                foreach($ifaceResponse as $iface){
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
                
                foreach($pppResponse as $ppp){
                    if($ppp->getType()!==Response::TYPE_DATA) continue;
                    
                    $username = $ppp->getProperty('name');
                    $username_lc = strtolower(trim($username));
                    $actualIface = $ppp->getProperty('interface');

                    $customer_info = $customer_map[$username_lc] ?? null;
                    
                    if ($admin['user_type'] != 'SuperAdmin') {
                        if (!$customer_info || $customer_info->created_by != $admin['id']) {
                            continue; 
                        }
                    }

                    if($customer_info){
                        $fullname = (!empty($customer_info->fullname)) ? $customer_info->fullname : $customer_info->username;
                        $address = (!empty($customer_info->address)) ? $customer_info->address : '-';
                    } else {
                        $fullname = 'Not in Database';
                        $address = '-';
                    }

                    // ইন্টারফেস ম্যাচিং
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
            echo json_encode($users, JSON_PRETTY_PRINT);
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
        $ui->assign('_title','Hotspot Online Users');
        $ui->assign('_system_menu','hotspot_online');
        $admin = Admin::_info(); $ui->assign('_admin',$admin);

        $routers = ORM::for_table('tbl_routers')->where('enabled',1)->find_many();
        $users = [];
        if(session_status()!==PHP_SESSION_ACTIVE) session_start();

        foreach($routers as $router){
            $sock=@fsockopen($router['ip_address'],8728,$errno,$errstr,1);
            if(!$sock) continue;
            fclose($sock);

            try{
                $client = Mikrotik::getClient($router['ip_address'],$router['username'],$router['password']);
                $hsList = $client->sendSync(new Request('/ip/hotspot/active/print'));
                
                $active_usernames = [];
                foreach($hsList as $hs){
                    if($hs->getType()===Response::TYPE_DATA){
                        $uname = $hs->getProperty('user');
                        if($uname) {
                            $active_usernames[] = trim($uname);
                        }
                    }
                }

                $customer_map = [];
                if(!empty($active_usernames)){
                    $customers = ORM::for_table('tbl_customers')
                        ->where_in('username', $active_usernames)
                        ->find_many();
                    
                    foreach($customers as $cust){
                        $customer_map[strtolower(trim($cust->username))] = $cust;
                    }
                }

                foreach($hsList as $hs){
                    if($hs->getType()!==Response::TYPE_DATA) continue;

                    $username = $hs->getProperty('user') ?? '-';
                    $username_lc = strtolower(trim($username));
                    
                    $customer_info = $customer_map[$username_lc] ?? null;
                    
                    if ($admin['user_type'] != 'SuperAdmin') {
                        if (!$customer_info || $customer_info->created_by != $admin['id']) {
                            continue;
                        }
                    }

                    $bytes_in = (int)($hs->getProperty('bytes-in') ?? 0);
                    $bytes_out = (int)($hs->getProperty('bytes-out') ?? 0);
                    $uptime = $hs->getProperty('uptime') ?? '-';
                    $id = $hs->getProperty('.id');
                    
                    $fullname = ($customer_info) ? $customer_info->fullname : 'Unknown';
                    $address = ($customer_info) ? $customer_info->address : '-';

                    $key = 'hotspot_traffic_'.$router['id'].'_'.$username_lc;

                    $tx_rate = $rx_rate = 0;
                    $currentTime = microtime(true);

                    if(isset($_SESSION[$key])){
                        $prev = $_SESSION[$key];
                        $timeDiff = max(0.5, $currentTime - $prev['time']); 
                        $tx_rate = max(0, $bytes_out - $prev['tx']) / $timeDiff;
                        $rx_rate = max(0, $bytes_in - $prev['rx']) / $timeDiff;
                    }

                    $_SESSION[$key] = ['tx'=>$bytes_out,'rx'=>$bytes_in,'time'=>$currentTime];

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
            echo json_encode($users, JSON_PRETTY_PRINT);
            exit;
        }

        $ui->assign('online_users',$users);
        $ui->display('hotspot_online.tpl');
    }
}
