<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Dewatasoft_ChangeAttributeSet_Adminhtml
 * @copyright   Copyright (c) 2013 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 *  Dewatasoft Catalog product controller
 *
 * @category   Mage
 * @package    Dewatasoft_ChangeAttributeSet_Adminhtml
 * @author     Dewatasoft Team <komang@dewatasoft.com>
 */
class Dewatasoft_ChangeAttributeSet_Adminhtml_Catalog_ProductController extends Mage_Adminhtml_Controller_Action
{

	public function changeattributesetAction()
	{
		$productIds = $this->getRequest()->getParam('product');
		$storeId = (int)$this->getRequest()->getParam('store', 0);
		if (!is_array($productIds)) {
			$this->_getSession()->addError($this->__('Please select product(s)'));
		}
		else {
			try {
				foreach ($productIds as $productId) {
					$product = Mage::getSingleton('catalog/product')
						->unsetData()
						->setStoreId($storeId)
						->load($productId)
						->setAttributeSetId($this->getRequest()->getParam('attribute_set'))
						->setIsMassupdate(true)
						->save();
				}
				Mage::dispatchEvent('catalog_product_massupdate_after', array('products'=>$productIds));
				$this->_getSession()->addSuccess(
					$this->__('Total of %d record(s) were successfully updated', count($productIds))
				);
			}
			catch (Exception $e) {
				$this->_getSession()->addException($e, $e->getMessage());
			}
		}
		$this->_redirect('adminhtml/catalog_product/index/', array());
	}	
}
