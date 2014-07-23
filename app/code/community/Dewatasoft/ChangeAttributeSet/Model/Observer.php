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
 *  Dewatasoft ChangeAttributeSet Observer Model
 *
 * @category   Mage
 * @package    Dewatasoft_ChangeAttributeSet_Model_Observer
 * @author     Dewatasoft Team <komang@dewatasoft.com>
 */
 
class  Dewatasoft_ChangeAttributeSet_Model_Observer
{
	
	/**
	 * add Massaction Option to Product grid
	 * 
	 * @param $observer Varien_Event
	 */
	public function addMassactionToProductGrid($observer)
	{
		$block = $observer->getBlock();
		
		if($block instanceof Mage_Adminhtml_Block_Catalog_Product_Grid){
			
			$sets = Mage::getResourceModel('eav/entity_attribute_set_collection')
				->setEntityTypeFilter(Mage::getModel('catalog/product')->getResource()->getTypeId())
				->load()
				->toOptionHash();
		
			$block->getMassactionBlock()->addItem('flagbit_changeattributeset', array(
				'label'=> Mage::helper('catalog')->__('Change attribute set'),
				'url'  => $block->getUrl('*/*/changeattributeset', array('_current'=>true)),
				'additional' => array(
					'visibility' => array(
						'name' => 'attribute_set',
						'type' => 'select',
						'class' => 'required-entry',
						'label' => Mage::helper('catalog')->__('Attribute Set'),
						'values' => $sets
					)
				)
			)); 			
		}
	}
	
}
