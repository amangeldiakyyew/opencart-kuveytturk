<?php
ini_set('display_errors', false);
ini_set('display_startup_errors', false);

class ControllerExtensionPaymentKuveytTurk extends Controller
{

    public $mode = 'live';
    public $lang = 'TR';
    public $secureType = '3DModel';
    public $orderId;
    public $amount;
    public $instalment = 0;
    public $successUrl;
    public $failureUrl;
    public $successMessage = 'Ödemeniz Alındı.';

    protected $order = false;

    protected $currencyNumber = '0949';
    protected $card;

    protected $merchantId;
    protected $customerId;
    protected $apiUsername;
    protected $apiPassword;

    protected $currencies = [
        'TRY' => '0949'
    ];

    private $gateWays = [
        'test' => [
            '3dModel' => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate',
            '3dModelProvision' => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate',
        ],
        'live' => [
            '3dModel' => 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate',
            '3dModelProvision' => 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelProvisionGate',
        ],
    ];


    public function index()
    {
        $this->load->library('SameSiteCookieSetter');
        $path = ini_get('session.cookie_path');
        $domain = $this->request->server['HTTP_HOST'];
        foreach ($_COOKIE as $k => $v) {
            SameSiteCookieSetter::setcookie($k, $v, array('secure' => true, 'samesite' => 'None', 'path' => $path, 'domain' => $domain));
        }

        if (!isset($this->session->data['order_id'])) {
            return false;
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $data = [
            'order' => $order_info
        ];

        return $this->load->view('extension/payment/kuveytturk', $data);

    }

    public function pay()
    {

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return 'Nope';
        }

        $this->load->model('checkout/order');
        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if ($order_info) {
            $Name = $_POST["CardHolderName"];
            $CardNumber = $_POST["CardNumber"];
            $CardExpireDateMonth = $_POST["CardExpireDateMonth"];
            $CardExpireDateYear = $_POST["CardExpireDateYear"];
            $CardCVV2 = $_POST["CardCVV2"];

            if (strlen($CardExpireDateMonth) < 2) {
                $CardExpireDateMonth = "0" . $CardExpireDateMonth;
            } elseif (strlen($CardExpireDateMonth) > 2) {
                $CardExpireDateMonth = substr($CardExpireDateMonth, -2);
            }
            if (strlen($CardExpireDateYear) > 2) {
                $CardExpireDateYear = substr($CardExpireDateYear, -2);
            }

            $this->init();
            $this->setOrder($order_info);
            $this->setOrderId($order_id);
            $this->setAmount($order_info['total']);
            $this->setCard($Name, $CardNumber, $CardExpireDateYear, $CardExpireDateMonth, $CardCVV2);
            echo $this->threeDPay();
            exit();
        }

        return 'İşlem Hatalı.';

    }

    public function callback()
    {
        $AuthenticationResponse = $_POST["AuthenticationResponse"];
        $RequestContent = urldecode($AuthenticationResponse);

        $xxml = simplexml_load_string($RequestContent) or false;
        if (!$xxml) {
            $this->session->data['error'] = 'Bankaya Bağlanılamadı.';
            return $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }

        if ($xxml->ResponseCode != '00') {
            //hata
            $this->session->data['error'] = (string)$xxml->ResponseMessage;
            return $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
        $data = [
            'ResponseCode' => $xxml->ResponseCode,
            'ResponseMessage' => $xxml->ResponseMessage,
            'MerchantOrderId' => $xxml->VPosMessage->MerchantOrderId,
            'OrderId' => $xxml->VPosMessage->OrderId,
            'ProvisionNumber' => $xxml->VPosMessage->ProvisionNumber,
            'RRN' => $xxml->VPosMessage->RRN,
            'Stan' => $xxml->VPosMessage->Stan,
            'MD' => $xxml->MD,
            'Amount' => $xxml->VPosMessage->Amount,
            'HashData' => $xxml->VPosMessage->HashData,
        ];

        $this->init();
        return $this->threeDConfirm($data);
    }

    /****************************************************/

    public function init()
    {
        $merchantId = trim(html_entity_decode($this->config->get('payment_kuveytturk_merchant_id')));
        $customerId = trim(html_entity_decode($this->config->get('payment_kuveytturk_customer_id')));
        $apiUsername = trim(html_entity_decode($this->config->get('payment_kuveytturk_api_username')));
        $apiPassword = trim(html_entity_decode($this->config->get('payment_kuveytturk_api_password')));
        $this->merchantId = $merchantId;
        $this->customerId = $customerId;
        $this->apiUsername = $apiUsername;
        $this->apiPassword = $apiPassword;
        $this->successUrl = HTTPS_SERVER . 'index.php?route=extension/payment/kuveytturk/callback/?status=success';
        $this->failureUrl = HTTPS_SERVER . 'index.php?route=extension/payment/kuveytturk/callback/?status=failure';
        $this->setMode($this->config->get('payment_kuveytturk_mode'));
    }

    public function threeDPay()
    {
        if (@$this->formatNumber(@$this->order['total']) != $this->amount) {
            die('Hatalı Tutar');
        }

        $HashedPassword = base64_encode(sha1($this->apiPassword, "ISO-8859-9")); //md5($Password);
        $unHashed = $this->merchantId . $this->orderId . $this->amount . $this->successUrl . $this->failureUrl . $this->apiUsername . $HashedPassword;
        $HashData = base64_encode(sha1($unHashed, "ISO-8859-9"));
        $cardType = substr($this->card['number'], 1) == "4" ? "Visa" : "MasterCard";

        $xml = '<KuveytTurkVPosMessage xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
            . '<APIVersion>1.0.0</APIVersion>'
            . '<OkUrl>' . $this->successUrl . '</OkUrl>'
            . '<FailUrl>' . $this->failureUrl . '</FailUrl>'
            . '<HashData>' . $HashData . '</HashData>'
            . '<MerchantId>' . $this->merchantId . '</MerchantId>'
            . '<CustomerId>' . $this->customerId . '</CustomerId>'
            . '<UserName>' . $this->apiUsername . '</UserName>'
            . '<CardNumber>' . $this->card['number'] . '</CardNumber>'
            . '<CardExpireDateYear>' . $this->card['year'] . '</CardExpireDateYear>'
            . '<CardExpireDateMonth>' . $this->card['month'] . '</CardExpireDateMonth>'
            . '<CardCVV2>' . $this->card['cv2'] . '</CardCVV2>'
            . '<CardHolderName>' . $this->card['owner'] . '</CardHolderName>'
            . '<CardType>' . $cardType . '</CardType>'
            . '<BatchID>0</BatchID>'
            . '<TransactionType>Sale</TransactionType>'
            . '<InstallmentCount>0</InstallmentCount>'
            . '<Amount>' . $this->amount . '</Amount>'
            . '<DisplayAmount>' . $this->amount . '</DisplayAmount>'
            . '<CurrencyCode>' . $this->currencyNumber . '</CurrencyCode>'
            . '<MerchantOrderId>' . $this->orderId . '</MerchantOrderId>'
            . '<OrderId>' . $this->orderId . '</OrderId>'
            . '<TransactionSecurity>3</TransactionSecurity>'
            . '</KuveytTurkVPosMessage>';

        $ch = curl_init();
        //curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_MAX_TLSv1_2);
        //curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1 | CURL_SSLVERSION_MAX_TLSv1_1 | CURL_SSLVERSION_MAX_TLSv1_2);
        curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/xml', 'Content-length: ' . strlen($xml)));
        curl_setopt($ch, CURLOPT_HEADER, false); //Serverdan gelen Header bilgilerini önemseme.
        curl_setopt($ch, CURLOPT_URL, $this->gateWays[$this->mode]['3dModel']); //Baglanacagi URL
        curl_setopt($ch, CURLOPT_POST, true); //POST Metodu kullanarak verileri gönder
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //Transfer sonuçlarini al.

        $data = curl_exec($ch);
        curl_close($ch);

        if (curl_errno($ch)) { // CURL HATASI
            return 'Hata';
        } else {
            return $data;
        }
    }

    public function threeDConfirm($data)
    {
        $HashedPassword = base64_encode(sha1($this->apiPassword, "ISO-8859-9")); //md5($Password);
        $HashData = base64_encode(sha1($this->merchantId . $data['MerchantOrderId'] . $data['Amount'] . $this->apiUsername . $HashedPassword, "ISO-8859-9"));

        $xml = '<KuveytTurkVPosMessage xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                <APIVersion>1.0.0</APIVersion>
                <HashData>' . $HashData . '</HashData>
                <MerchantId>' . $this->merchantId . '</MerchantId>
                <CustomerId>' . $this->customerId . '</CustomerId>
                <UserName>' . $this->apiUsername . '</UserName>
                <TransactionType>Sale</TransactionType>
                <InstallmentCount>0</InstallmentCount>
                <CurrencyCode>0949</CurrencyCode>
                <Amount>' . $data['Amount'] . '</Amount>
                <MerchantOrderId>' . $data['MerchantOrderId'] . '</MerchantOrderId>
                <TransactionSecurity>3</TransactionSecurity>
                <KuveytTurkVPosAdditionalData>
                <AdditionalData>
                    <Key>MD</Key>
                    <Data>' . $data['MD'] . '</Data>
                </AdditionalData>
            </KuveytTurkVPosAdditionalData>
            </KuveytTurkVPosMessage>';

        $ch = curl_init();
        //curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_MAX_TLSv1_2);
        //curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1 | CURL_SSLVERSION_MAX_TLSv1_1 | CURL_SSLVERSION_MAX_TLSv1_2);
        curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/xml', 'Content-length: ' . strlen($xml)));
        curl_setopt($ch, CURLOPT_POST, true); //POST Metodu kullanarak verileri gönder
        curl_setopt($ch, CURLOPT_HEADER, false); //Serverdan gelen Header bilgilerini önemseme.
        curl_setopt($ch, CURLOPT_URL, $this->gateWays[$this->mode]['3dModelProvision']); //Baglanacagi URL
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //Transfer sonuçlarini al.

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $this->session->data['error'] = 'Banka ile iletişim sırasında bir sorun meydana geldi.';
            return $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
        curl_close($ch);
        $rxml = simplexml_load_string($response);
        if (!$rxml) {
            $this->session->data['error'] = 'Banka ile iletişim sırasında bir sorun meydana geldi.';
            return $this->response->redirect($this->url->link('checkout/checkout', '', true));
        } else {
            if ($rxml->ResponseCode == '00') {
                $this->load->model('checkout/order');
                $order_info = $this->model_checkout_order->getOrder($rxml->VPosMessage->MerchantOrderId);
                if ($order_info) {
                    $this->session->data['success'] = $this->successMessage;
                    $comment = 'Kuveyt Turk Aracılığı ile Vpos Ödemesi Alındı';
                    $this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('payment_kuveytturk_order_payment_complete_status_id'), $comment);
                    return $this->response->redirect($this->url->link('checkout/success'));
                } else {
                    $this->session->data['error'] = 'Sipariş Bulunamadı.';
                    return $this->response->redirect($this->url->link('checkout/checkout', '', true));
                }
            } else {
                $this->session->data['error'] = (string)$rxml->ResponseMessage;
                return $this->response->redirect($this->url->link('checkout/checkout', '', true));
            }
        }
    }

    public function setMode($mode)
    {
        if ($mode == 'test') {
            $this->mode = 'test';
        } else {
            $this->mode = 'live';
        }
    }

    public function checkHash()
    {
        $hashString = $this->merchantId . $this->customerId . $_POST['OrderId'] . $_POST['AuthCode'] . $_POST['ProcReturnCode'] . $_POST['3DStatus'] . $_POST['ResponseRnd'] . $this->username;
        $hash = base64_encode(pack('H*', sha1($hashString)));
        return $hash == $_POST['ResponseHash'];
    }

    public function setOrder($order = false)
    {
        $this->order = $order;
    }

    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
    }

    public function setAmount($amount)
    {
        $price = number_format($amount, 2, "", "");
        $this->amount = intval($price);
    }

    public function formatNumber($amount)
    {
        return number_format($amount, 2, "", "");
    }

    public function setCard($owner, $number, $year, $month, $cv2)
    {
        return $this->card = [
            'owner' => $owner,
            'number' => $number,
            'year' => $year,
            'month' => $month,
            'cv2' => $cv2,
        ];
    }

}