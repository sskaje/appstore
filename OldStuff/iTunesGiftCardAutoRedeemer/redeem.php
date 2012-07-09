<?php 
/** 
 * iTunes AutoRedeemer 
 * 
 * @author sskaje sskaje@gmail.com 
 */ 

if (isset($argv[1]) && is_valid_redeem($argv[1])) { 
    # USAGE 1: php auto_redeemer.php XXXXXXXXXXXX 
    $redeem_codes = array($argv[1]); 
} else if (isset($argv[1]) && is_file($argv[1])) { 
    # USAGE 2: php auto_redeemer.php 1.txt 
    $redeem_codes = array_map('trim', file($argv[1])); 
} else if (is_file('itunes.log')) { 
    # USAGE 3: php auto_redeemer.php 
    $redeem_codes = array_map('trim', file('itunes.log')); 
} else { 
    exit; 
} 

# 是否只是测试卡的有效性 
$testonly = false; 
# apple id 
$apple_id = 'xxx@sina.cn'; 
# 密码 
$password = 'xxx'; 
# 每个apple id会分配到不同的pxx-buy.itunes.apple.com，但是实测开 CURLOPT_FOLLOWLOCATION 就可以无视了，会有个307的头 
$domain_suffix = '33'; 
# GUID，全16进制 
$guid = 'xxxxxxxx.AF883918.00000000.F93819A9.CC0183D3.33019387.512309CA'; 

$login_url = "https://p{$domain_suffix}-buy.itunes.apple.com/WebObjects/MZFinance.woa/wa/authenticate"; 
$login_referer = 'http://itunes.apple.com/WebObjects/MZStore.woa/wa/viewGrouping?id=25204&mt=8&s=143441&pillIdentifier=iphone'; 
$login_post = 'matchineName=LOCALHOST&why=signin&attempt=1&createSession=true&guid='.urlencode($guid).'&appleId='.urlencode($apple_id).'&password='.urlencode($password); 

$ch = curl_init(); 
init_curl(); 
login(); 

foreach ($redeem_codes as $code) { 
    redeem($code, $testonly); 
} 

function init_curl() 
{ 
    global $ch; 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 

    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
    curl_setopt($ch, CURLOPT_USERAGENT, "iTunes/10.6 (Windows; Microsoft Windows 7 x64 Ultimate Edition Service Pack 1 (Build 7601)) AppleWebKit/534.54.16"); 
    curl_setopt($ch, CURLOPT_COOKIEFILE, '_cookie.txt'); 
    curl_setopt($ch, CURLOPT_COOKIEJAR, '_cookie.txt'); 

    curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
    curl_setopt($ch, CURLOPT_VERBOSE, 0); 
    curl_setopt($ch, CURLOPT_HEADER, 0); 
} 
function login() 
{ 
    global $ch, $login_url, $login_post, $domain_suffix; 
    curl_setopt($ch, CURLOPT_URL, $login_url); 
    curl_setopt($ch, CURLOPT_POST, 1); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, $login_post); 

    $ret = curl_exec($ch); 

    $m = array(); 
    if (!preg_match('#<key>passwordToken</key><string>([a-zA-Z0-9\+\=\/]+)</string>#', $ret, $m)) { 
        die('bad pwd token'); 
    } 
    $password_token = $m[1]; 

    $m = array(); 
    if (!preg_match('#<key>dsid</key><integer>([0-9]+)</integer>#', $ret, $m)) { 
        die('bad dsid'); 
    } 
    $dsid = $m[1]; 
    # 一个md5的id，没注意来源，header上有，但是实测无用 
    $cuid = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'; 

    $headers = array( 
        'X-Token: ' . $password_token, 
        #    'X-Apple-Tz: 28800', 
        #    'X-Apple-Cuid: ' . $cuid, 
        'X-Dsid: ' . $dsid, 
        #    'X-Apple-Store-Front: 143441-1,12', 
        #    "Origin: https://p{$domain_suffix}-buy.itunes.apple.com", 
    ); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
} 

function redeem($redeem_code, $test_only=false)  
{ 
    $redeem_code = strtoupper($redeem_code); 
    echo "Code: {$redeem_code} ... "; 

    global $ch, $domain_suffix; 
    curl_setopt($ch, CURLOPT_HTTPGET, 1); 
    $page_url = "https://p{$domain_suffix}-buy.itunes.apple.com/WebObjects/MZFinance.woa/wa/redeemLandingPage"; 
    curl_setopt($ch, CURLOPT_URL, $page_url); 
    $ret = curl_exec($ch); 

    $m = array(); 
    if (!preg_match('#<form name=".+" method="post" action="(/WebObjects/MZFinance.woa/wo/[0-9\.]+)">#', $ret, $m)) { 
        die('bad form in redeem landing page'); 
    } 
    $form_url = "https://p{$domain_suffix}-buy.itunes.apple.com{$m[1]}"; 

    if (!preg_match('#<input class="submit" id="redeemButton" type="submit" value="Redeem" name="([0-9\.]+)" />#', $ret, $m)) { 
        die('bad redeem form input name'); 
    } 
    $form_name = $m[1]; 

    $post_fields = "code={$redeem_code}&{$form_name}=Redeem"; 
    curl_setopt($ch, CURLOPT_URL, $form_url); 
    curl_setopt($ch, CURLOPT_POST, 1); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields); 
    #curl_setopt($ch, CURLOPT_REFERER, "https://p{$domain_suffix}-buy.itunes.apple.com/WebObjects/MZFinance.woa/wa/redeemLandingPage"); 

    $ret = curl_exec($ch); 
    if (false !== (strpos($ret, 'already been redeemed'))) { 
        echo "Already redeemed\n"; 
        return false; 
    } else if (false !== (strpos($ret, 'The code you entered is not recognized as a valid code'))) { 
        echo "Invalid code\n"; 
        return false; 
    } else { 
        echo "OK!\n"; 
        file_put_contents('code.txt', $redeem_code . "\n", FILE_APPEND); 
            file_put_contents('redeemed.txt', $ret); 
        if (!$test_only) { 
            $m = array(); 
            preg_match('#<key>url</key><string>(https://buy.itunes.apple.com/WebObjects/MZFinance.woa/wa/com.apple.jingle.app.finance.DirectAction/redeemGiftCertificate.+)</string>#', $ret, $m); 
            $url = htmlspecialchars_decode($m[1]); 

            curl_setopt($ch, CURLOPT_URL, $url); 
            curl_setopt($ch, CURLOPT_HTTPGET, 1); 
            $ret = curl_exec($ch); 
            file_put_contents('redeem_output.txt', $ret); 

            if (strpos($ret, 'Your Apple ID has been credited with $') !== false) { 
                $m = array(); 
                preg_match('#Your Apple ID has been credited with \$([0-9\.]+).#', $ret, $m); 
                $redeemed = $m[1]; 
                preg_match('#Your balance is \$([0-9\.,]+).#', $ret, $m); 
                $balance = $m[1]; 
                echo "Redeemed: \${$redeemed}. Balance: \${$balance}\n"; 
            } 
        } 
        return true; 
    } 
} 

function is_valid_redeem($redeem_code)  
{ 
    return preg_match('#^X[A-Z]{15}$#', $redeem_code); 
} 

