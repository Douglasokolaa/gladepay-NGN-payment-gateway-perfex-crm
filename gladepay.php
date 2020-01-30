<?php
/**
 * Ensures that the module init file can't be accessed directly, only within the application.
 */
defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: gladepay 
Description: gladepay module for more info click on Author Name
Author: Boxvibe Technologies
Author URI: https://www.boxvibe.com
Version: 1.0.0
Requires at least: 2.3.*
*/

/**
 * Module URL
 * e.q. https://crm-installation.com/module_name/
 * @param  string $module  module system name
 * @param  string $segment additional string to append to the URL
 * @return string
 */
register_payment_gateway('gladepay_gateway', 'gladepay');


