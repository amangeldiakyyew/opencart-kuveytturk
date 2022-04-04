<?php

class ModelExtensionPaymentKuveytTurk extends Model
{
    public function getMethod($address, $total)
    {
        $method_data = array();
        $method_data = array(
            'code' => 'kuveytturk',
            'title' => $this->config->get('payment_kuveytturk_display_name'),
            'terms' => '',
            'sort_order' => $this->config->get('payment_kuveytturk_sort_order')
        );
        return $method_data;
    }
}
