<?php 

namespace ContaoIsotopePaytweakBundle\Paytweak;

/**
 * This class overrides the default Paytweak Wrapper
 * It allows changing the API URL depending on the environment
 */
class WrapperOverride extends Wrapper
{
    protected $api_dev = 'https://api.paytweak.dev/v1/';
    protected $api_prod = 'https://api.paytweak.com/v1/';

	public function __construct($key_pub = '', $key_priv = '', $mode = 'DEV')
    {
    	parent::__construct($key_pub, $key_priv);
        $this->api = 'DEV' === $mode ? $this->api_dev : $this->api_prod;
    }
}