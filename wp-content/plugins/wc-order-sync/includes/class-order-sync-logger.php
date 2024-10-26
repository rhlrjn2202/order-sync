<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Order_Sync_Logger {
    public static function log_error($message, $data = array()) {
        $log_entry = date('[Y-m-d H:i:s] ') . $message;
        if (!empty($data)) {
            $log_entry .= ' Data: ' . json_encode($data);
        }
        error_log($log_entry);
    }

    public static function log_success($message, $data = array()) {
        $log_entry = date('[Y-m-d H:i:s] ') . $message;
        if (!empty($data)) {
            $log_entry .= ' Data: ' . json_encode($data);
        }
        error_log($log_entry);
    }
}