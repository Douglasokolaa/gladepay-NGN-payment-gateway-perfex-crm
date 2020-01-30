<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Gladepay extends App_Controller
{
    public function make_payment()
    
    {
        check_invoice_restrictions($this->input->get('invoiceid'), $this->input->get('hash'));

        $this->load->model('invoices_model');
        $invoice = $this->invoices_model->get($this->input->get('invoiceid'));

        load_client_language($invoice->clientid);

        $data['invoice'] = $invoice;
        $data['total']  = $this->session->userdata('gladepay_total');
        $data['key']     = $this->gladepay_gateway->merchant_id();

        $this->load->model('clients_model');
        $contacts = $this->clients_model->get_contacts($data['invoice']->clientid);
        if (count($contacts) == 1) {
            $contact    = $contacts[0];
            $firstname = $contact['firstname'] ;
            $data['firstname']   =  $contact['firstname'];
            $lastname = $contact['lastname'];
            $data['lastname']    =  $contact['lastname'];

            if ($contact['email']) {
                $email = $contact['email'];
                $data['email']       = $email;
            }
            if ($contact['phonenumber']) {
                $phonenumber = $contact['phonenumber'];
                $data['phonenumber'] = $phonenumber;
            }
        }

        $data['txnid']      = $this->gladepay_gateway->gen_transaction_id($data);
        $data['action_url'] = $this->uri->uri_string() . '?invoiceid=' . $invoice->id . '&hash=' . $invoice->hash;

        if (is_client_logged_in()) {
            $contact = $this->clients_model->get_contact(get_contact_user_id());
        } else {
            if (total_rows(db_prefix().'contacts', ['userid' => $invoice->clientid]) == 1) {
                $contact = $this->clients_model->get_contact(get_primary_contact_user_id($invoice->clientid));
            }
        }

        if (isset($contact) && $contact) {
            $data['firstname']   = $contact->firstname;
            $data['lastname']    = $contact->lastname;
            $data['email']       = $contact->email;
            $data['phonenumber'] = $contact->phonenumber;
        }

    echo $this->get_html($data);
    }

    public function get_html($data)
    {
       ob_start(); ?>
       <?php echo payment_gateway_head(_l('payment_for_invoice') . ' ' . format_invoice_number($data['invoice']->id)); ?>
         <body class="gateway-gladepay  ">
           <div class="container">
              <div class="col-md-8 col-md-offset-2 mtop30">
                 <div class="mbot30 text-center">
                    <?php echo payment_gateway_logo(); ?>
                 </div>
                 <div class="row">
                    <div class="panel_s">
                       <div class="panel-body">
                          <h3 class="no-margin">
                             <b><?php echo _l('payment_for_invoice'); ?> </b>
                             <a href="<?php echo site_url('invoice/' . $data['invoice']->id . '/' . $data['invoice']->hash); ?>">
                             <b><?php echo format_invoice_number($data['invoice']->id); ?></b>
                             </a>
                          </h3>
                          <h4><?php echo _l('payment_total', app_format_money($data['total'], $data['invoice']->currency_name)); ?></h4>
                          <hr />
                          <input type="submit" class="btn btn-info" value="<?php echo _l('submit_payment'); ?>" onclick = "makepayment()" />

                       </div>
                    </div>
                 </div>
              </div>
           </div>
           <?php echo payment_gateway_scripts(); ?>
          <script type="text/javascript" src="<?php echo $this->gladepay_gateway->get_action_url(); ?>"></script>
           <script>
            function makepayment() {
              initPayment({
                  MID: "<?php echo $data['key']; ?>",
                  customer_txnref: "<?php echo  $data['txnid']; ?>" ,
                  email:"<?php echo $data['email'] ; ?>",
                  firstname:"<?php echo $data['firstname'] ; ?>",
                  lastname:"<?php echo $data['lastname']  ; ?>",
                  title: "<?php echo str_replace('{invoice_number}', format_invoice_number($data['invoice']->id), $this->gladepay_gateway->getSetting('description_dashboard')); ?>",
                  description: "<?php echo str_replace('{invoice_number}', format_invoice_number($data['invoice']->id), $this->gladepay_gateway->getSetting('description_dashboard')); ?>",
                  amount: <?php echo $data['total'] ?>,
                  country: "NG",
                  currency: "<?php echo $data['invoice']->currency_name; ?>",
                  onclose: function() {
                      location.replace("<?php echo site_url('/gladepay/failure?invoiceid=' . $data['invoice']->id . '&hash=' . $data['invoice']->hash); ?>")
                  },
                  callback: function(response) {
                      var doug = response.txnRef;
                    jim = "<?php echo site_url('/gladepay/success?invoiceid=' . $data['invoice']->id . '&hash=' . $data['invoice']->hash. '&txnref='); ?>"+doug;
                    location.replace(jim)                  } 
                });}
           </script>
           <?php echo payment_gateway_footer(); ?>
        <?php
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }

    public function success()
    {
        $invoiceid = $this->input->get('invoiceid');
        $hash      = $this->input->get('hash');
        $txnref    = $this->input->get('txnref');

        check_invoice_restrictions($invoiceid, $hash);

        $post_fields["action"] = "verify";
        $post_fields["txnRef"] = $txnref;
        $post_head['key'] = $this->gladepay_gateway->merchant_key();
        $post_head['mid'] = $this->gladepay_gateway->merchant_id();

        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => "https://demo.api.gladepay.com/payment",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_POSTFIELDS => json_encode($post_fields),
        CURLOPT_HTTPHEADER => json_encode($post_head),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
        log_activity("cURL Error #:" . $err);
        } else {
        echo $response;
       $response = json_decode($response,true);
        }       
        
        if ($err) {
            set_alert('warning', _l('invalid_transaction'.$err->message));
        } else {
            if ($response['status'] == 200) {
              if ($response['txnStatus'] == 'successful') {
                $success = $this->gladepay_gateway->addPayment(
                [
                  'amount'        => $response['chargedAmount'],
                  'invoiceid'     => $invoiceid,
                  'transactionid' => $response['customer_txnref'],
                  'paymentmethod' => $response['payment_method'],
                  ]
                );
                if ($success) {
                    set_alert('success', _l('online_payment_recorded_success'));
                } else {
                    set_alert('danger', _l('online_payment_recorded_success_fail_database'));
                }
            }else {
              set_alert('danger', 'Your transaction ' .$response['message'] );
            }

          } else {
                if ($this->Gladepay_gateway->getSetting('test_mode_enabled') == '1') {
                    log_activity('Gladepay Transaction Not With Status Success: ' . var_export($_POST, true));
                }
                set_alert('warning', 'Thank You. Your transaction status is ' . $response['txnStatus']);
            }
        }
        $this->session->unset_userdata('gladepay_total');
      redirect(site_url('invoice/' . $invoiceid . '/' . $hash));
    }

    public function failure()
    {
      $invoiceid = $this->input->get('invoiceid');
      $hash      = $this->input->get('hash');

      set_alert('warning', _l('invalid_transaction'));

      $this->session->unset_userdata('gladepay_total');

      redirect(site_url('invoice/' . $invoiceid . '/' . $hash));
    }
}
