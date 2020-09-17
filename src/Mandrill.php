<?php

namespace Pendolf\Mandrill;

use Pendolf\Mandrill\Exceptions\Error;
use Pendolf\Mandrill\Exceptions\HttpError;
use Pendolf\Mandrill\Exceptions\Invalid_CustomDNS;
use Pendolf\Mandrill\Exceptions\Invalid_CustomDNSPending;
use Pendolf\Mandrill\Exceptions\Invalid_DeleteDefaultPool;
use Pendolf\Mandrill\Exceptions\Invalid_DeleteNonEmptyPool;
use Pendolf\Mandrill\Exceptions\Invalid_EmptyDefaultPool;
use Pendolf\Mandrill\Exceptions\Invalid_Template;
use Pendolf\Mandrill\Exceptions\InvalidKey;
use Pendolf\Mandrill\Exceptions\Invalid_Reject;
use Pendolf\Mandrill\Exceptions\Invalid_Tag_Name;
use Pendolf\Mandrill\Exceptions\IP_ProvisionLimit;
use Pendolf\Mandrill\Exceptions\Metadata_FieldLimit;
use Pendolf\Mandrill\Exceptions\NoSendingHistory;
use Pendolf\Mandrill\Exceptions\PaymentRequired;
use Pendolf\Mandrill\Exceptions\PoorReputation;
use Pendolf\Mandrill\Exceptions\ServiceUnavailable;
use Pendolf\Mandrill\Exceptions\Unknown_Export;
use Pendolf\Mandrill\Exceptions\Unknown_InboundDomain;
use Pendolf\Mandrill\Exceptions\Unknown_InboundRoute;
use Pendolf\Mandrill\Exceptions\Unknown_IP;
use Pendolf\Mandrill\Exceptions\Unknown_Message;
use Pendolf\Mandrill\Exceptions\Unknown_MetadataField;
use Pendolf\Mandrill\Exceptions\Unknown_Pool;
use Pendolf\Mandrill\Exceptions\Unknown_Sender;
use Pendolf\Mandrill\Exceptions\Unknown_Subaccount;
use Pendolf\Mandrill\Exceptions\Unknown_Template;
use Pendolf\Mandrill\Exceptions\Unknown_TrackingDomain;
use Pendolf\Mandrill\Exceptions\Unknown_Url;
use Pendolf\Mandrill\Exceptions\Unknown_Webhook;
use Pendolf\Mandrill\Exceptions\ValidationError;

class Mandrill {
    
    public $apikey;
    public $ch;
    public $root = 'https://mandrillapp.com/api/1.0';
    public $debug = false;

    public static $error_map = array(
        "ValidationError" => ValidationError::class,
        "Invalid_Key" => InvalidKey::class,
        "PaymentRequired" => PaymentRequired::class,
        "Unknown_Subaccount" => Unknown_Subaccount::class,
        "Unknown_Template" => Unknown_Template::class,
        "ServiceUnavailable" => ServiceUnavailable::class,
        "Unknown_Message" => Unknown_Message::class,
        "Invalid_Tag_Name" => Invalid_Tag_Name::class,
        "Invalid_Reject" => Invalid_Reject::class,
        "Unknown_Sender" => Unknown_Sender::class,
        "Unknown_Url" => Unknown_Url::class,
        "Unknown_TrackingDomain" => Unknown_TrackingDomain::class,
        "Invalid_Template" => Invalid_Template::class,
        "Unknown_Webhook" => Unknown_Webhook::class,
        "Unknown_InboundDomain" => Unknown_InboundDomain::class,
        "Unknown_InboundRoute" => Unknown_InboundRoute::class,
        "Unknown_Export" => Unknown_Export::class,
        "IP_ProvisionLimit" => IP_ProvisionLimit::class,
        "Unknown_Pool" => Unknown_Pool::class,
        "NoSendingHistory" => NoSendingHistory::class,
        "PoorReputation" => PoorReputation::class,
        "Unknown_IP" => Unknown_IP::class,
        "Invalid_EmptyDefaultPool" => Invalid_EmptyDefaultPool::class,
        "Invalid_DeleteDefaultPool" => Invalid_DeleteDefaultPool::class,
        "Invalid_DeleteNonEmptyPool" => Invalid_DeleteNonEmptyPool::class,
        "Invalid_CustomDNS" => Invalid_CustomDNS::class,
        "Invalid_CustomDNSPending" => Invalid_CustomDNSPending::class,
        "Metadata_FieldLimit" => Metadata_FieldLimit::class,
        "Unknown_MetadataField" => Unknown_MetadataField::class,
    );

    public function __construct($apikey=null) {
        if(!$apikey) $apikey = getenv('MANDRILL_APIKEY');
        if(!$apikey) $apikey = $this->readConfigs();
        if(!$apikey) throw new Error('You must provide a Mandrill API key');
        $this->apikey = $apikey;

        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mandrill-PHP/1.0.55');
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_HEADER, false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 600);

        $this->root = rtrim($this->root, '/') . '/';

        $this->templates = new Templates($this);
        $this->exports = new Exports($this);
        $this->users = new Users($this);
        $this->rejects = new Rejects($this);
        $this->inbound = new Inbound($this);
        $this->tags = new Tags($this);
        $this->messages = new Messages($this);
        $this->whitelists = new Whitelists($this);
        $this->ips = new Ips($this);
        $this->internal = new Internal($this);
        $this->subaccounts = new SubAccounts($this);
        $this->urls = new Urls($this);
        $this->webhooks = new Webhooks($this);
        $this->senders = new Senders($this);
        $this->metadata = new Metadata($this);
    }

    public function __destruct() {
        curl_close($this->ch);
    }

    public function call($url, $params) {
        $params['key'] = $this->apikey;
        $params = json_encode($params);
        $ch = $this->ch;

        curl_setopt($ch, CURLOPT_URL, $this->root . $url . '.json');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_VERBOSE, $this->debug);

        $start = microtime(true);
        $this->log('Call to ' . $this->root . $url . '.json: ' . $params);
        if($this->debug) {
            $curl_buffer = fopen('php://memory', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $curl_buffer);
        }

        $response_body = curl_exec($ch);
        $info = curl_getinfo($ch);
        $time = microtime(true) - $start;
        if($this->debug) {
            rewind($curl_buffer);
            $this->log(stream_get_contents($curl_buffer));
            fclose($curl_buffer);
        }
        $this->log('Completed in ' . number_format($time * 1000, 2) . 'ms');
        $this->log('Got response: ' . $response_body);

        if(curl_error($ch)) {
            throw new HttpError("API call to $url failed: " . curl_error($ch));
        }
        $result = json_decode($response_body, true);
        if($result === null) throw new Error('We were unable to decode the JSON response from the Mandrill API: ' . $response_body);
        
        if(floor($info['http_code'] / 100) >= 4) {
            throw $this->castError($result);
        }

        return $result;
    }

    public function readConfigs() {
        $paths = array('~/.mandrill.key', '/etc/mandrill.key');
        foreach($paths as $path) {
            if(file_exists($path)) {
                $apikey = trim(file_get_contents($path));
                if($apikey) return $apikey;
            }
        }
        return false;
    }

    public function castError($result) {
        if($result['status'] !== 'error' || !$result['name']) throw new Error('We received an unexpected error: ' . json_encode($result));

        $class = (isset(self::$error_map[$result['name']])) ? self::$error_map[$result['name']] : Error::class;
        return new $class($result['message'], $result['code']);
    }

    public function log($msg) {
        if($this->debug) error_log($msg);
    }
}


