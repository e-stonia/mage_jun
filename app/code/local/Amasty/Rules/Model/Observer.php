<?php
/**
 * @copyright   Copyright (c) 2009-11 Amasty
 */
class Amasty_Rules_Model_Observer
{
    /**
     * Each element contains
     *  'items_left'   unprocessed items count
     *  'items'        array of matchig items with their discounts
     *
     * @var array
     */
    private $rules = array();
    
    const TYPE_CHEAPEST  = 'the_cheapest';
    const TYPE_EXPENCIVE = 'the_most_expencive';
    const TYPE_FIXED     = 'to_fixed';
    const TYPE_EACH_N    = 'each_n';
    
    
    /**
     * Process sales rule form creation
     * @param   Varien_Event_Observer $observer
     */
    public function handleFormCreation($observer)
    {
        $actionsSelect = $observer->getForm()->getElement('simple_action');
        
        $values = $actionsSelect->getValues();
        
        $values[] = array(
            'value' => self::TYPE_CHEAPEST, 
            'label' => Mage::helper('amrules')->__('The Cheapest')
        );
        $values[] = array(
            'value' => self::TYPE_EXPENCIVE, 
            'label' => Mage::helper('amrules')->__('The Most Expensive')
        );
        $values[] = array(
            'value' => self::TYPE_FIXED, 
            'label' => Mage::helper('amrules')->__('Fixed Item Price')
        );
        $values[] = array(
            'value' => self::TYPE_EACH_N, 
            'label' => Mage::helper('amrules')->__('Each N-th Free')
        );
        $actionsSelect->setValues($values);
        
        return $this;
    }
    /**
     * Process quote item validation and discount calculation
     * @param   Varien_Event_Observer $observer
     */
    public function handleValidation($observer) 
    {
        try{
            $rule = $observer->getEvent()->getRule();
            $observer->getEvent()->getItem()->save();
            $itemId = $observer->getEvent()->getItem()->getId();
            if (!in_array($rule->getSimpleAction(), array(self::TYPE_CHEAPEST, self::TYPE_EXPENCIVE, self::TYPE_EACH_N))) {
                return $this;
            }
            // init total discount info for the rule first time
            if (!isset($this->rules[$rule->getId()])) {
                $this->rules[$rule->getId()] = $this->_initRule($rule);
            }  
            
            $r = $this->rules[$rule->getId()];
            if (empty($r[$itemId])){
                return $this;
            }
            $result = $observer->getEvent()->getResult();
            $result->setDiscountAmount($r[$itemId]['discount']);
            $result->setBaseDiscountAmount($r[$itemId]['base_discount']);
        } catch (Exception $e){
            if (isset($_GET['debug'])) {
                print_r($e->getMessage());
                exit;
            }
        }
        
        return $this;
    }

 
    private function _initRule($rule) 
    {
        $r = array();        
        
        $prices = $this->_getSortedCartPices($rule);
        if (!$prices){
            return $r;
        }
        
        $qty = $this->_getQty($rule, count($prices));
        if ($qty < 1){
            return $r;
        }
        
        if ($rule->getSimpleAction() == self::TYPE_CHEAPEST){
            $prices = array_slice($prices, 0, $qty);
        } 
        elseif ($rule->getSimpleAction() == self::TYPE_EXPENCIVE){
            $prices = array_slice($prices, -$qty, $qty);
        } 
        elseif ($rule->getSimpleAction() == self::TYPE_EACH_N) {
            $step = (int)$rule->getDiscountStep();
            if ($step < 2)
                return;
                
            $prices = array_reverse($prices); // now it is from  big to small (80, 50, 50, 30 ...)
        }
        
        $percentage  = (int) $rule->getDiscountAmount();
        if (!$percentage){
            $percentage  = 100;
        }
        $percentage = ($percentage / 100.0);
        
        $lastId     = -1;
        foreach ($prices as $i => $price){
            
            // skip items beside each, say 3-d, depends on the $step
            if ($rule->getSimpleAction() == self::TYPE_EACH_N && (($i+1) % $step))
                continue;
            
            $discount     = $price['price'] * $percentage;
            $baseDiscount = $price['base_price'] * $percentage;
            
            if ($price['id'] != $lastId){
                $lastId = intVal($price['id']);
                
                $r[$lastId] = array();
                $r[$lastId]['discount']      = $discount;
                $r[$lastId]['base_discount'] = $baseDiscount;
            }else{
                $r[$lastId]['discount']      += $discount;
                $r[$lastId]['base_discount'] += $baseDiscount;
            }
        }
        
        return $r;
    }
    
    /**
     * Determines qty of the discounted items
     *
     * @param Mage_Sales_Model_Rule $rule
     * @return int qty
     */
    private function _getQty($rule, $cartQty)
    {
        /**
         * We use "Discount Qty Step (Buy X)" field to add more than 1 free item,
         * and we use "Maximum Qty Discount is Applied to" to limit count of free items
         *
         */
        $discountStep   = (int) $rule->getDiscountStep();
        $maxDiscountQty = (int) $rule->getDiscountQty();

        // if discountStep is not specified - max free items qty equals to 1
        // if discountStep is specified, but discountQty is not - add as many free items as possible (no more than cart items qty)
        if (!$discountStep) {
            $discountQty = 1;
        } 
        else {
            if (!$maxDiscountQty) {
                $maxDiscountQty = $cartQty;
            }

            $discountQty = floor($cartQty / $discountStep);
            
            if ($discountQty > $maxDiscountQty) {
                $discountQty = $maxDiscountQty;
            }
        }
        
        return $discountQty;        
    }
    
    /**
     * Creates an array of the all prices in the cart
     *
     * @return array
     */
    private function _getSortedCartPices($rule)
    {
        $cart = Mage::getSingleton('checkout/cart');
        
        $prices = array();
        foreach ($cart->getItems() as $item) {
            
            // calculate for valid items only
            if (!$rule->getActions()->validate($item)) {
                continue;
            }
            
            // for bundle items - do not process child products
            if ($item->getParentItemId()) {
                continue;
            }
            
            $price     = $this->_getItemPrice($item);
            $basePrice = $this->_getItemBasePrice($item);
            for ($i=0; $i < $item->getTotalQty(); ++$i){
                $prices[] = array(
                    'price'       => $price, // don't call the function in a long cycle
                    'base_price'  => $basePrice,
                    'id'          => $item->getId(),
                 );
            }
        } // foreach
        
        usort($prices, array($this, 'comparePrices'));   
        
        return $prices;     
    }
    
    /**
     * Return item price in the store base currency
     *
     * @param Mage_Sales_Model_Quote_Item_Abstract $item
     * @return float
     */
    private function _getItemBasePrice($item)
    {
        $price = $item->getDiscountCalculationPrice();
        return ($price !== null) ? $item->getBaseDiscountCalculationPrice() : $item->getBaseCalculationPrice();
    }
    
    /**
     * Return item price in currently active for quote currency
     *
     * @param Mage_Sales_Model_Quote_Item_Abstract $item
     * @return float
     */
    private function _getItemPrice($item)
    {
        $price = $item->getDiscountCalculationPrice();
        return ($price !== null) ? $price : $item->getCalculationPrice();
    }
    
    public static function comparePrices($a, $b)
    {
        $res = ($a['price'] < $b['price']) ? -1 : 1; 
        if ($a['price'] == $b['price']) {
            $res = 0;
        }
        
        return $res;       
    }
    
}