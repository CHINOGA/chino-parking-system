<?php
require_once __DIR__ . '/../park/booking-app/includes/OAuth.php';

// Define OAuthSignatureMethod_HMAC_SHA1 class if it doesn't exist
if (!class_exists('OAuthSignatureMethod_HMAC_SHA1')) {
    class OAuthSignatureMethod_HMAC_SHA1 {
        public function build_signature($request, $consumer, $token) {
            $base_string = $request->get_signature_base_string();
            $request->base_string = $base_string;
            $key_parts = array(
                $consumer->secret,
                ($token) ? $token->secret : ""
            );
            $key_parts = array_map('rawurlencode', $key_parts);
            $key = implode('&', $key_parts);
            return base64_encode(hash_hmac('sha1', $base_string, $key, true));
        }
    }
}

class PesapalIntegration {
    private $consumer_key;
    private $consumer_secret;
    private $callback_url;
    private $post_url = 'https://www.pesapal.com/API/PostPesapalDirectOrderV4';

    public function __construct($consumer_key, $consumer_secret, $callback_url) {
        $this->consumer_key = $consumer_key;
        $this->consumer_secret = $consumer_secret;
        $this->callback_url = $callback_url;
    }

    /**
     * Generate the Pesapal payment iframe URL
     * @param array $orderData Associative array with keys:
     *  - amount (float)
     *  - description (string)
     *  - type (string, e.g. 'MERCHANT')
     *  - reference (string, unique transaction reference)
     *  - first_name (string)
     *  - last_name (string)
     *  - email (string)
     *  - phone_number (string)
     *  - currency (string, e.g. 'TZS')
     * @return string Signed iframe URL
     */
    public function generateIframeUrl(array $orderData) {
        // The OAuth.php from the library does not define a signature method class,
        // so we define a compatible one here.
        $signature_method = new OAuthSignatureMethod_HMAC_SHA1();

        $post_xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
        <PesapalDirectOrderInfo xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" 
            Amount=\"{$orderData['amount']}\" 
            Description=\"{$orderData['description']}\" 
            Type=\"{$orderData['type']}\" 
            Reference=\"{$orderData['reference']}\" 
            FirstName=\"{$orderData['first_name']}\" 
            LastName=\"{$orderData['last_name']}\" 
            Email=\"{$orderData['email']}\" 
            PhoneNumber=\"{$orderData['phone_number']}\" 
            Currency=\"{$orderData['currency']}\" 
            xmlns=\"http://www.pesapal.com\" />";

        $consumer = new OAuthConsumer($this->consumer_key, $this->consumer_secret);

        $params = array();
        $params['oauth_callback'] = $this->callback_url;
        $params['pesapal_request_data'] = $post_xml;

        $iframe_src = OAuthRequest::from_consumer_and_token($consumer, null, "GET", $this->post_url, $params);
        
        // The sign_request method in the simplified OAuth.php library expects
        // the signature method to be passed in.
        $iframe_src->sign_request($signature_method, $consumer, null);

        return $iframe_src->__toString();
    }
}
?>
