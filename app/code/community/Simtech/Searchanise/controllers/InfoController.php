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

class Simtech_Searchanise_InfoController extends Mage_Core_Controller_Front_Action
{
    const OUTPUT             = 'visual';
    const PARENT_PRIVATE_KEY = 'parent_private_key';

    public function indexAction()
    {
        $visual           = $this->getRequest()->getParam(self::OUTPUT);
        $parentPrivateKey = $this->getRequest()->getParam(self::PARENT_PRIVATE_KEY);

        if ((empty($parentPrivateKey)) || 
            (Mage::helper('searchanise/ApiSe')->getParentPrivateKey() !== $parentPrivateKey)) {
            $_options = Mage::helper('searchanise/ApiSe')->getAddonOptions();
            $options = array('status' => $_options['addon_status']);
            foreach ($_options as $k => $v) {
                if (strpos($k, 'api_key') !== false) {
                    $options[$k] = $v;
                }
            }
            
            if ($visual) {
                Mage::helper('searchanise/ApiSe')->printR($options);
            } else {
                echo Mage::helper('core')->jsonEncode($options);
            }
        } else {
            $options = Mage::helper('searchanise/ApiSe')->getAddonOptions();

            if ($visual) {
                Mage::helper('searchanise/ApiSe')->printR($options);
            } else {
                echo Mage::helper('core')->jsonEncode($options);
            }
        }

        die();
    }
}
