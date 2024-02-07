<?php


#
#   PAYTWEAK API wrapper PHP
#   (c)Copyright 2014-2022 PAYTWEAK
#

#
#   Pour toute question relative à l'usage de nos api's, vous pouvez écrire à
#   support@paytweak.com
#

namespace ContaoIsotopePaytweakBundle\Paytweak;

class Wrapper
{

    protected $key_public = null;
    protected $key_private = null;
    protected $message = null;
    protected $work_token = null;
    protected $api = null;


  /* ----- CONSTRUCTION ----- */

    public function __construct($key_pub = '', $key_priv = '')
    {
        $this->key_public = $key_pub;
        $this->key_private = $key_priv;
        $this->api = "https://api.paytweak.dev/v1/";
        $this->message = array();
    }


    /* ----- CONNEXION A L API ----- */

    public function api_connect()
    {
        $this->message = array();
        $ch            = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->api."hello");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Paytweak-API-KEY: ".$this->key_public));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);

            $result        = curl_exec($ch);

        //  Récupération du Security-Token renvoyé par l'API

        if (isset(json_decode($result, true)['Paytweak-Security-Token'])) {
            $token         = json_decode($result, true)['Paytweak-Security-Token'];
        } else {
            $this->add_message('code', json_decode($result, true)['code']);
            $this->add_message('message', json_decode($result, true)['message']);
            curl_close($ch);
            return;
        }

        //  Génération du token de vérification à l'aide de la clé secrete

        $r_token       = base64_encode(trim($token).$this->key_private);

        //  Verify

        curl_setopt($ch, CURLOPT_URL, $this->api."verify");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Paytweak-USER-TOKEN: $r_token"));

            $result        = curl_exec($ch);

        //  Récupération du Work-Token qui permet d'effectuer les requetes

        if (isset(json_decode($result, true)['Paytweak-Work-Token'])) {
            $this->work_token    = json_decode($result, true)['Paytweak-Work-Token'];
        } else {
            $this->add_message('code', json_decode($result, true)['code']);
            $this->add_message('message', json_decode($result, true)['message']);
            curl_close($ch);
            return;
        }

        $this->add_message('code', 'OK');
        $this->add_message('message', 'CONNECTION DONE : connexion API done');
        curl_close($ch);
    }


    /* ----- DECONNEXION API ----- */

    public function api_disconnect()
    {
            $ch            = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->api."quit");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Paytweak-Token: ".$this->work_token));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_COOKIESESSION, true);
            curl_exec($ch);
            curl_close($ch);

            $this->message = array();
            $this->add_message('code', 'WRAPPER_API_DECONNECTION');
            $this->add_message('message', 'deconnexion API done');
    }



    /* ----- API GET METHOD ------ */

    public function api_get_method($ref, $args)
    {
        $url = $this->api.$ref.'?';
        $url_args='';

        foreach ($args as $key => $val) {
            $url_args .=$key.'='.$val.'&';
        }
        $url .= $url_args;
        $ch            = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Paytweak-Token: ".$this->work_token));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

            $result = curl_exec($ch);

        $this->message = array();
        $this->add_response($result);
        curl_close($ch);
    }


    /* ----- API POSTMETHOD ------ */

    public function api_post_method($ref, $args)
    {
        $url = $this->api.$ref;

        $ch            = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Paytweak-Token: ".$this->work_token));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

        $query      = http_build_query($args);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);

            $result = curl_exec($ch);

        $this->message = array();
        $this->add_response($result);
        curl_close($ch);
    }


    /* ----- API CUSTOM METHOD ------ */

    public function api_custom_method($ref, $type)
    {
        $url = $this->api.$ref;

        $ch            = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Paytweak-Token: ".$this->work_token));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);

            $result = curl_exec($ch);

        $this->message = array();
        $this->add_response($result);
        // $this->add_message('url ',$ref);
        curl_close($ch);
    }


    /* ----- API PUT METHOD ------ */

    public function api_put_method($ref, $id)
    {
        $this->api_custom_method($ref.'/'.$id, 'PUT');
    }


    /* ----- API DELETE METHOD ------ */

    public function api_delete_method($ref, $id)
    {
        $this->api_custom_method($ref.'/'.$id, 'DELETE');
    }


    /* ----- API PATCH METHOD ------ */

    public function api_patch_method($ref, $id, $args)
    {
        $url = $ref.'/'.$id.'?';
        $url_args='';
        foreach ($args as $key => $val) {
            if ($key == 'message' || $key == 'html' || $key == 'name' || $key == 'subject' || $key == 'description' || $key == 'short_description') {
                $val_encode=urlencode($val);
            } else {
                $val_encode=$val;
            }
            $url_args .=$key.'='.$val_encode.'&';
        }
        $url .= $url_args;
        $this->api_custom_method($url, 'PATCH');
    }


    /* -----  KEYS ------ */

    public function get_key_public()
    {
        $this->message = array();
        $this->add_message('key_public', $this->key_public);
        $this->show_message();
    }
    public function get_key_private()
    {
        $this->message = array();
        $this->add_message('key_private', $this->key_private);
        $this->show_message();
    }



    /* ----- RESPONSES & MESSAGING ------ */

    private function add_response($resp)
    {
        $this->message = json_decode($resp);
    }

    private function add_message($arg1, $arg2)
    {
        $this->message[$arg1] = $arg2;
    }

    public function show_message()
    {
        print_r(json_encode($this->message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT, 4092));
    }

    public function get_message()
    {
        return json_encode($this->message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT, 4092);
    }
}
