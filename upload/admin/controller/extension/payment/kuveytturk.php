<?php

class ControllerExtensionPaymentKuveytTurk extends Controller {
    private $error = array();

    public function index()
    {
        $this->load->model('setting/setting');
        $this->load->language('extension/payment/kuveytturk');

        $this->document->setTitle($this->language->get('heading_title'));


        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_kuveytturk', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $data['heading_title'] = $this->language->get('heading_title');

        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['text_installment'] = $this->language->get('text_installment');
        $data['entry_description'] = $this->language->get('entry_description');
        $data['entry_merchant'] = $this->language->get('entry_merchant');
        $data['entry_backref'] = $this->language->get('entry_backref');
        $data['entry_currency'] = $this->language->get('entry_currency');
        $data['entry_language'] = $this->language->get('entry_language');
        $data['entry_total'] = $this->language->get('entry_total');
        $data['entry_order_status'] = $this->language->get('entry_order_status');
        $data['entry_order_payment_complete_status'] = $this->language->get('entry_order_payment_complete_status');
        $data['entry_order_payment_reversed_status'] = $this->language->get('entry_order_payment_reversed_status');
        $data['entry_order_payment_refund_status'] = $this->language->get('entry_order_payment_refund_status');
        $data['entry_unconfirmed_order_status'] = $this->language->get('entry_unconfirmed_order_status');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_sort_order'] = $this->language->get('entry_sort_order');
        $data['help_instalment'] = $this->language->get('help_instalment');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/kuveytturk', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/payment/kuveytturk', 'user_token=' . $this->session->data['user_token'], true);

        $data['fetch'] = $this->url->link('extension/payment/kuveytturk/fetch', 'user_token=' . $this->session->data['user_token'] . '#reportstab', true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        if (isset($this->request->post['payment_kuveytturk_mode'])) {
            $data['payment_kuveytturk_mode'] = $this->request->post['payment_kuveytturk_mode'];
        } else {
            $data['payment_kuveytturk_mode'] = $this->config->get('payment_kuveytturk_mode');
        }

        if (isset($this->request->post['payment_kuveytturk_display_name'])) {
            $data['payment_kuveytturk_display_name'] = $this->request->post['payment_kuveytturk_display_name'];
        } else {
            $data['payment_kuveytturk_display_name'] = $this->config->get('payment_kuveytturk_display_name');
        }

        if (isset($this->request->post['payment_kuveytturk_merchant_id'])) {
            $data['payment_kuveytturk_merchant_id'] = $this->request->post['payment_kuveytturk_merchant_id'];
        } else {
            $data['payment_kuveytturk_merchant_id'] = $this->config->get('payment_kuveytturk_merchant_id');
        }

        if (isset($this->request->post['payment_kuveytturk_customer_id'])) {
            $data['payment_kuveytturk_customer_id'] = $this->request->post['payment_kuveytturk_customer_id'];
        } else {
            $data['payment_kuveytturk_customer_id'] = $this->config->get('payment_kuveytturk_customer_id');
        }

        if (isset($this->request->post['payment_kuveytturk_api_username'])) {
            $data['payment_kuveytturk_api_username'] = $this->request->post['payment_kuveytturk_api_username'];
        } else {
            $data['payment_kuveytturk_api_username'] = $this->config->get('payment_kuveytturk_api_username');
        }

        if (isset($this->request->post['payment_kuveytturk_api_password'])) {
            $data['payment_kuveytturk_api_password'] = $this->request->post['payment_kuveytturk_api_password'];
        } else {
            $data['payment_kuveytturk_api_password'] = $this->config->get('payment_kuveytturk_api_password');
        }

        if (isset($this->request->post['payment_kuveytturk_order_payment_complete_status_id'])) {
            $data['payment_kuveytturk_order_payment_complete_status_id'] = $this->request->post['payment_kuveytturk_order_payment_complete_status_id'];
        } else {
            $data['payment_kuveytturk_order_payment_complete_status_id'] = $this->config->get('payment_kuveytturk_order_payment_complete_status_id');
        }


        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();


        if (isset($this->request->post['payment_kuveytturk_status'])) {
            $data['payment_kuveytturk_status'] = $this->request->post['payment_kuveytturk_status'];
        } else {
            $data['payment_kuveytturk_status'] = $this->config->get('payment_kuveytturk_status');
        }

        if (isset($this->request->post['payment_kuveytturk_sort_order'])) {
            $data['payment_kuveytturk_sort_order'] = $this->request->post['payment_kuveytturk_sort_order'];
        } else {
            $data['payment_kuveytturk_sort_order'] = $this->config->get('payment_kuveytturk_sort_order');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/kuveytturk', $data));


    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/kuveytturk')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }

    public function install()
    {

    }
}

?>
