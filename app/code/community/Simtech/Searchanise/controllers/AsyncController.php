<?php
/***************************************************************************
*                                                                          *
*   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
*                                                                          *
****************************************************************************
* PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
* "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
****************************************************************************/
class Simtech_Searchanise_AsyncController extends Mage_Core_Controller_Front_Action
{
    protected $_notUseHttpRequestText = null;
    
    public function getNotUseHttpRequestText()
    {
        if (is_null($this->_notUseHttpRequestText)) {
            $this->_notUseHttpRequestText = $this->getRequest()->getParam(Simtech_Searchanise_Helper_ApiSe::NOT_USE_HTTP_REQUEST);
        }
        
        return $this->_notUseHttpRequestText;
    }
    
    public function checkNotUseHttpRequest()
    {
        return ($this->getNotUseHttpRequestText() == Simtech_Searchanise_Helper_ApiSe::NOT_USE_HTTP_REQUEST_KEY) ? true : false;
    }

    /**
     * Dispatch event before action
     *
     * @return void
    */
    public function preDispatch()
    {
        $this->setFlag('', self::FLAG_NO_START_SESSION, 1);
        $this->setFlag('', self::FLAG_NO_CHECK_INSTALLATION, 1);
        $this->setFlag('', self::FLAG_NO_COOKIES_REDIRECT, 0);
        $this->setFlag('', self::FLAG_NO_PRE_DISPATCH, 1);
        $this->setFlag('', self::FLAG_NO_POST_DISPATCH, 1);

        parent::preDispatch();
    }

    /*
     * async
    */
    public function indexAction()
    {
        if (Mage::helper('searchanise/ApiSe')->getStatusModule() == 'Y') {
            Mage::app('admin')->setUseSessionInUrl(false);
            Mage::app('customer')->setUseSessionInUrl(false);

            if (Mage::helper('searchanise/ApiSe')->checkStartAsync()) {
                $check = $this->checkNotUseHttpRequest();
                // code if need not use httprequest
                // not need in current version
                // $check = true;

                if ($check) {
                    @ignore_user_abort(true);
                    @set_time_limit(0);
                    
                    $result = Mage::helper('searchanise/ApiSe')->async();
                    
                    die($result);
                    
                } else {
                    @ignore_user_abort(false);
                    @set_time_limit(Mage::helper('searchanise/ApiSe')->getAjaxAsyncTimeout());
                    $asyncUrl = Mage::helper('searchanise/ApiSe')->getAsyncUrl(false, 0, false);

                    Mage::helper('searchanise/ApiSe')->httpRequest(
                        Zend_Http_Client::GET,
                        $asyncUrl,
                        array(
                            Simtech_Searchanise_Helper_ApiSe::NOT_USE_HTTP_REQUEST => Simtech_Searchanise_Helper_ApiSe::NOT_USE_HTTP_REQUEST_KEY,
                        ),
                        array(),
                        array(),
                        Mage::helper('searchanise/ApiSe')->getAjaxAsyncTimeout(),
                        2
                    );
                }
            }
        }
        
        return $this;
    }
}