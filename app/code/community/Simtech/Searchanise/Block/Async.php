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
class Simtech_Searchanise_Block_Async extends Mage_Core_Block_Text
{
    protected function _toHtml()
    {
        $html = '';

        if (Mage::helper('searchanise/ApiSe')->checkStatusModule()) {
            if (Mage::app()->getStore()->isAdmin()) {
                if (Mage::helper('searchanise/ApiSe')->checkAutoInstall()) {
                    $textNotification = Mage::helper('searchanise')->__('Searchanise was successfully installed. Catalog indexation in process. <a href="%s">Searchanise Admin Panel</a>.', Mage::helper('searchanise/ApiSe')->getModuleUrl());

                    Mage::helper('searchanise/ApiSe')->setNotification('N', Mage::helper('searchanise')->__('Notice'), $textNotification);

                    $autoInstallInBackground = false;
                    // not used because notification will not show
                    if ($autoInstallInBackground) {
                        $signupUrl = Mage::helper("adminhtml")->getUrl(Mage::helper('searchanise/ApiSe')->getConnectLink());
                        $html .= "\n<object data=\"$signupUrl\" width=\"0\" height=\"0\"></object>\n";
                    } else {
                        if (Mage::helper('searchanise/ApiSe')->signup(null, false) == true) {
                            Mage::helper('searchanise/ApiSe')->queueImport(null, false);
                        }
                    }
                } else {
                    Mage::helper('searchanise/ApiSe')->showNotificationAsyncCompleted();
                }
            }

            if (Mage::helper('searchanise/ApiSe')->checkAjaxAsync()) {
                $asyncUrl = Mage::helper('searchanise/ApiSe')->getAsyncUrl(false);

                $html .= "\n<object data=\"$asyncUrl\" width=\"0\" height=\"0\"></object>\n";

                // code for ajax async
                // not need in current version
                // $html .= 
                // "<script type=\"text/javascript\">
                // //<![CDATA[
                //     new Ajax.Request('$asyncUrl', {method: 'get', asynchronous: true});
                // //]]>
                // </script>";
            }
        }

        return $html;
    }
}