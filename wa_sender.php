<?php

use Blesta\Core\Util\Input\Fields\InputFields;
use WaSender\WaSenderClient;

require_once dirname(__FILE__) . DS . 'libs' . DS . 'client.php';

/**
 * WA Sender Messenger
 *
 * @package blesta
 * @subpackage blesta.components.messengers.wasender
 * @copyright Copyright (c) 2023, PT Pedjoeang Digital Networks.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.pedjoeangdigital.net/ Pedjoeang Digital Networks
 */
class WaSender extends Messenger
{
    /**
     * Initializes the messenger.
     */
    public function __construct()
    {
        // Load configuration required by this messenger
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load the helpers required by this messenger
        Loader::loadHelpers($this, ['Html']);

        // Load the language required by this messenger
        Language::loadLang('wa_sender', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Returns all fields used when setting up a messenger, including any
     * javascript to execute when the page is rendered with these fields.
     *
     * @param array $vars An array of post data submitted to the manage messenger page
     * @return InputFields An InputFields object, containing the fields to render
     *  as well as any additional HTML markup to include
     */
    public function getConfigurationFields(&$vars = [])
    {
        $fields = new InputFields();

        // Phone Number
        $phoneNumber = $fields->label(Language::_('WaSender.configuration_fields.phone_number', true), 'wasender_phone_number');
        $phoneNumber->attach(
            $fields->fieldText('phone_number', (isset($vars['phone_number']) ? $vars['phone_number'] : null), ['id' => 'wasender_phone_number'])
        );
        $phoneNumber->attach(
            $fields->tooltip(Language::_('WaSender.configuration_fields.phone_number_help', true))
        );
        $fields->setField($phoneNumber);

        // API Key
        $apiKey = $fields->label(Language::_('WaSender.configuration_fields.api_key', true), 'wasender_api_key');
        $apiKey->attach(
            $fields->fieldText('api_key', (isset($vars['api_key']) ? $vars['api_key'] : null), ['id' => 'wasender_api_key'])
        );
        $apiKey->attach(
            $fields->tooltip(Language::_('WaSender.configuration_fields.api_key_help', true))
        );
        $fields->setField($apiKey);

        // API Url
        $apiUrl = $fields->label(Language::_('WaSender.configuration_fields.api_url', true), 'wasender_api_url');
        $apiUrl->attach(
            $fields->fieldText('api_url', (isset($vars['api_url']) ? $vars['api_url'] : null), ['id' => 'wasender_api_url'])
        );
        $apiUrl->attach(
            $fields->tooltip(Language::_('WaSender.configuration_fields.api_url_help', true))
        );
        $fields->setField($apiUrl);

        return $fields;
    }

    /**
     * Updates the meta data for this messenger
     *
     * @param array $vars An array of messenger info to add
     * @return array A numerically indexed array of meta fields containing:
     *
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function setMeta(array $vars)
    {
        $meta_fields = ['phone_number', 'api_key', 'api_url'];
        $encrypted_fields = ['api_key'];

        $meta = [];
        foreach ($vars as $key => $value) {
            if (in_array($key, $meta_fields)) {
                $meta[] = [
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
                ];
            }
        }

        return $meta;
    }

    /**
     * Send a message.
     *
     * @param mixed $to_user_id The user ID this message is to
     * @param string $content The content of the message to send
     * @param string $type The type of the message to send (optional)
     */
    public function send($to_user_id, $content, $type = null)
    {
        // Initialize the API
        $meta = $this->getMessengerMeta();

        if (!($api = $this->getApi())) {
            return null;
        }

        Loader::loadModels($this, ['Staff', 'Clients', 'Contacts']);

        // Fetch user information
        $is_client = true;
        if (($user = $this->Staff->getByUserId($to_user_id))) {
            $is_client = false;
        } else {
            $user = $this->Clients->getByUserId($to_user_id);

            $phone_numbers = $this->Contacts->getNumbers($user->contact_id);
            if (is_array($phone_numbers) && !empty($phone_numbers)) {
                $user->phone_number = reset($phone_numbers);
            }
        }

        // Send message
        $error = null;
        $success = false;
        $recipient = $is_client
                ? (isset($user->phone_number->number) ? $user->phone_number->number : null)
                : (isset($user->number_mobile) ? $user->number_mobile : null);
        
        if ($type == 'sms') {
            // SMS allows up to 918 characters, by concatenating 6 messages of 153 characters each
            if (strlen($content) > 918) {
                $content = substr($content, 0, 918);
            }

            $params = [
                'from' => $meta->phone_number,
                'body' => $content,
                'to' => $recipient
            ];
            $this->log($to_user_id, json_encode($params, JSON_PRETTY_PRINT), 'input', true);
            
            if (empty($recipient)) {
                $response = [
                    'status' => false,
                    'message' => 'Recipient phone number is empty'
                ];
                $this->log($to_user_id, json_encode($response, JSON_PRETTY_PRINT), 'output', false);
                return null;
            } 
            $recipient = str_replace('+', '', $recipient);
            // Send SMS
            try {
                $response = $api->sendTextMessage(
                    $recipient,
                    $content
                );

                $success = $response->status == true;

                $this->log($to_user_id, json_encode($response, JSON_PRETTY_PRINT), 'output', $success);
            } catch (\Exception $e) {
                $error = $e->getMessage();
                $success = false;

                $this->log($to_user_id, json_encode($error, JSON_PRETTY_PRINT), 'output', $success);
            }
        } else {
            $params = [
                'from' => $meta->phone_number,
                'body' => $content
            ];
            $this->log($to_user_id, json_encode($params, JSON_PRETTY_PRINT), 'input', true);

            // Send Whatsapp
            try {
                $response = $api->sendTextMessage(
                    $recipient,
                    $content
                );

                $success = $response->status == true;
                $this->log($to_user_id, json_encode($response, JSON_PRETTY_PRINT), 'output', $success);
            } catch (\Exception $e) {
                $error = $e->getMessage();
                $success = false;

                $this->log($to_user_id, json_encode($error, JSON_PRETTY_PRINT), 'output', $success);
            }
        }
    }

    /**
     * Gets an instance of the WaSender API.
     *
     * @return WaSender\WaSenderClient An instance of the WaSender API Wrapper or null if the API can't be initialized
     */
    private function getApi()
    {
        $meta = $this->getMessengerMeta();

        try {
            return new WaSenderClient($meta->api_url, $meta->api_key, $meta->phone_number);
        } catch (\Exception $e) {
            $this->setMessage('error', $e->getMessage());

            return null;
        }
    }
}