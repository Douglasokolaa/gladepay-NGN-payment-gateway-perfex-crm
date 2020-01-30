<?php

defined('BASEPATH') or exit('No direct script access allowed');

class gladepay_gateway extends App_gateway
{   

    protected $sandbox_url = 'http://demo.api.gladepay.com/checkout.js';

    protected $production_url = 'https://api.gladepay.com/checkout.js';      

    public function __construct()
    {
    $this->ci = &get_instance();
      /**
         * Call App_gateway __construct function
         */
        //parent::__construct();
        /**
         * REQUIRED
         * Gateway unique id
         * The ID must be alpha/alphanumeric
         */
        $this->setId('gladepay');

        /**
         * REQUIRED
         * Gateway name
         */
        $this->setName('Gladepay');

        /**
         * Add gateway settings
        */
        $this->setSettings(
        [
            [
                'name'      => 'gladepay_merchant_id',
                'encrypted' => true,
                'label'     => 'Merchant ID',
                ],
            [
                'name'      => 'gladepay_merchant_key',
                'encrypted' => true,
                'label'     => 'Merchant Key',
                ], 
                [
                'name'      => 'gladepay_merchant_id_test',
                'label'     => 'Merchant id Test',
                'default_value'    => 'GP0000001',
                ],
                [
                'name'      => 'gladepay_merchant_key_test',
                'label'     => 'Merchant Key Test',
                'default_value'    => '123456789',
              ],
            [
                'name'             => 'currencies',
                'label'            => 'settings_paymentmethod_currencies',
                'default_value'    => 'NGN,USD',
                ],
            [
                'name'          => 'description_dashboard',
                'label'         => 'settings_paymentmethod_description',
                'type'          => 'textarea',
                'default_value' => 'Payment for Invoice {invoice_number}',
            ],
            [
                'name'          => 'test_mode_enabled',
                'type'          => 'yes_no',
                'default_value' => 1,
                'label'         => 'settings_paymentmethod_testing_mode',
                ],
            ]
        );

        /*
        * notice
        */

    //  hooks()->add_action('before_render_payment_gateway_settings', 'gladepay_notice');

    }

    /**
     * REQUIRED FUNCTIONS
     * @param  array $data
     * @return mixed
     */
    public function process_payment($data)
    { 
         $this->ci->session->set_userdata(['gladepay_total' => number_format($data['amount'], 2, '.', '')]);
        redirect(site_url('gladepay/make_payment?invoiceid=' . $data['invoiceid'] . '&hash=' . $data['invoice']->hash));
    }
    /**
     * gets ateway url FUNCTIONS
     * @return url
     */
    public function get_action_url()
    {
        return $this->getSetting('test_mode_enabled') == '1' ? $this->sandbox_url : $this->production_url;
    }

    public function merchant_id()
    {
        return $this->getSetting('test_mode_enabled') == '1' ? $this->getSetting('gladepay_merchant_id_test') : $this->decryptSetting('gladepay_merchant_id');
    }

    public function merchant_key()
    {
        return $this->getSetting('test_mode_enabled') == '1' ? $this->getSetting('gladepay_merchant_key_test') : $this->decryptSetting('gladepay_merchant_key');
    }
    /**
     * gentransaction referrence FUNCTION
     * @param  array $data
     * @return ref
     */
    public function gen_transaction_id($data)
    {
       $tx_ref = format_invoice_number($data['invoice']->id).'-'.time();
       $tx_ref = str_replace('/', '',$tx_ref);
       return $tx_ref;
     }
    /**
     * Dashboard Notice FUNCTION
     * @param  array $data
     * @return html
     */
    function gladepay_notice($gateway)
    {
        if ($gateway['id'] == 'gladepay') {
            echo '<p class="text-warning">' . _l('GladePay Notice') . '</p>';
            echo '<p class="alert alert-warning bold">Create Your Gladepay Account  <a href="https://dashboard.gladepay.com/register"> HERE </a>. MAKE SURE YOU USE <B> 8OYPSE4POUMEW9P</B> AS REFERRAL </p>';
        }
    }

}
