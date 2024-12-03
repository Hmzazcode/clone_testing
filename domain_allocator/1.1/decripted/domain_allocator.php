<?php

use WHMCS\Database\Capsule;

if ( ! defined( "WHMCS" ) ) {
    die( "This file cannot be accessed directly" );
}

function domain_allocator_config(){
    $titles = Capsule::table('tbladmins')->pluck('username');

    $adminUsersOptions = [];
    foreach ($titles as $title) {
        $adminUsersOptions[$title] = $title;
    }

    return [
        'name'        => 'Domain Allocator',
        'description' => 'Domain Allocator module enables clients to seamlessly add and assign domains to their existing hosting accounts directly from the WHMCS client area',
        'author'=>'<a href="https://whmpress.com/modules">WHMPress</a>',
        'language'    => 'English',
        'version'     => '1.0',
        'fields' => [
            "Enable Module Logs" => array (
                "FriendlyName" => "Enable Module Logs",
                "Type" => "yesno",
                "Size" => "25",
                "Description" => "Check Box",
            ),
            "Admin User" => array (
                "FriendlyName" => "Admin User",
                "Type" => "dropdown",
                "Options" => implode(',', array_keys($adminUsersOptions)),
                "Description" => "Sample Dropdown",
                "Default" => "3",
            ),

            'License Key' => [
                'FriendlyName' => 'License Key',
                'Type' => 'text',
                'Size' => '25',
                'Default' => 'Enter Your License Key',
            ],
        ],


    ];
}
function domain_allocator_activate(){
    try {
        return [
            'status'      => 'success',
            'description' => 'Module successfully',
        ];
    } catch ( \Exception $e ) {
        return [
            'status'      => "error",
            'description' => 'Unable to create this module: ' . $e->getMessage(),
        ];
    }
}

function domain_allocator_deactivate(){
    try {
        Capsule::schema()->dropIfExists('mod_price_updater');
        return [
            'status'      => 'success',
            'description' => 'Successfully deactivated module',
        ];
    } catch ( \Exception $e ) {
        return [
            "status"      => "error",
            "description" => "Unable to deactivated module: {$e->getMessage()}",
        ];
    }
}

function domain_allocator_output ($vars){}