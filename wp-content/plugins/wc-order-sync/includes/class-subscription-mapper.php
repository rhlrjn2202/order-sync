<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Order_Sync_Subscription_Mapper {
    private static $subscription_map = array(
        '222' => array(
            'duration' => 1,
            'price' => 222,
            'membership_level' => 1 // Replace with your actual membership level ID
        ),
        '555' => array(
            'duration' => 3,
            'price' => 555,
            'membership_level' => 2 // Replace with your actual membership level ID
        ),
        '1111' => array(
            'duration' => 6,
            'price' => 1111,
            'membership_level' => 3 // Replace with your actual membership level ID
        ),
        '2000' => array(
            'duration' => 12,
            'price' => 2000,
            'membership_level' => 4 // Replace with your actual membership level ID
        )
    );

    public static function get_subscription_details($price) {
        $price_key = strval($price);
        return isset(self::$subscription_map[$price_key]) ? self::$subscription_map[$price_key] : null;
    }

    public static function validate_subscription_price($price) {
        $price_key = strval($price);
        return isset(self::$subscription_map[$price_key]);
    }

    public static function get_membership_level_id($price) {
        $details = self::get_subscription_details($price);
        return $details ? $details['membership_level'] : null;
    }
}