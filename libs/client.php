<?php
namespace WaSender;

class WaSenderClient {
    private $api_url;
    private $api_key;
    private $phone_number;

    public function __construct($api_url, $api_key, $phone_number) {
        $this->api_url = $api_url;
        $this->api_key = $api_key;
        $this->phone_number = $phone_number;
    }

    private function remoteCall($path, $data, $method = 'POST') {
        $url = $this->api_url . $path;
        $params = array(
            'api_key' => $this->api_key,
            'sender' => $this->phone_number,
            'number' => $data['number'],
            'message' => $data['message']
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($params)))
            );
        }
        

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    public function sendTextMessage($number, $message) {
        $data = array(
            'number' => $number,
            'message' => $message
        );

        return $this->remoteCall('/send-message', $data);
    }

    public function sendMediaMessage($number, $message, $media_url, $caption = null, $ppt = null, $type = 'image') {
        if (!$this->allowed_media_type($type)) {
            throw new Exception('Invalid media type');
        }

        $data = array(
            'number' => $number,
            'message' => $message,
            'image_url' => $image_url,
            'type' => $type
        );

        if ($caption) {
            $data['caption'] = $caption;
        }

        if ($ppt) {
            $data['ppt'] = $ppt;
        }

        return $this->remoteCall('/send-media', $data);
    }

    public function sendPollMessage($number, $name, $options, $countable = true) {
        $data = array(
            'number' => $number,
            'name' => $name,
            'options' => $options,
            'countable' => $countable ? '1' : '0'
        );

        return $this->remoteCall('/send-poll', $data);
    }

    public function sendButton($number, $message, $buttons, $footer = null, $url = null) {
        $data = array(
            'number' => $number,
            'message' => $message,
            'button' => $buttons
        );

        if ($footer) {
            $data['footer'] = $footer;
        }

        if ($url) {
            $data['url'] = $url;
        }

        return $this->remoteCall('/send-button', $data);
    }

    public function sendTemplateButton($number, $message, $template, $url = null, $footer = null) {
        $data = array(
            'number' => $number,
            'message' => $message,
            'template' => $template
        );

        if ($footer) {
            $data['footer'] = $footer;
        }

        if ($url) {
            $data['url'] = $url;
        }

        return $this->remoteCall('/send-template', $data);
    }

    public function generateQrCode($sender_number) {
        $data = array(
            'device' => $sender_number
        );

        return $this->remoteCall('/generate-qr', $data);
    }

    private function allowed_media_type($type) {
        $allowed_types = array('image', 'video', 'audio', 'pdf', 'xls', 'xlsx', 'doc', 'docx', 'zip');
        return in_array($type, $allowed_types);
    }
}