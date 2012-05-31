<?php

/**
 * NOTICE OF LICENSE
 *
 * The MIT License
 *
 * Copyright (c) 2009 S. Landsbek (slandsbek@gmail.com)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package    SLandsbek_SimpleOrderExport
 * @copyright  Copyright (c) 2009 S. Landsbek (slandsbek@gmail.com)
 * @license    http://opensource.org/licenses/mit-license.php  The MIT License
 */

/**
 * Exports orders to csv file. If an order contains multiple ordered items, each item gets
 * added on a separate row.
 */
class SLandsbek_SimpleOrderExport_Model_Export_Csv extends SLandsbek_SimpleOrderExport_Model_Export_Abstract {
    const ENCLOSURE = '"';
    const DELIMITER = ',';

    /**
     * Concrete implementation of abstract method to export given orders to csv file in var/export.
     *
     * @param $orders List of orders of type Mage_Sales_Model_Order or order ids to export.
     * @return String The name of the written csv file in var/export
     */
    function writeLine($fp, $data) {
        $str = implode(',', $data) . "\n";

        return fputs($fp, $str);
    }

    public function exportOrderDetailsById($order) {
        $order = Mage::getModel('sales/order')->load($order);
        $filenames = array();
        $counts = array();
        $orderItems = $order->getItemsCollection();
        $itemInc = 0;

        foreach ($orderItems as $item) {
            $sku = $item->getData("sku");
            $type = substr($sku, strlen($sku) - 2);
            if (array_key_exists($type, $filenames))
                $fp = $filenames[$type]["file"];
            else {
                $fileName = $order->getRealOrderId() . "_" . $type . '.csv';
                $fp = fopen(Mage::getBaseDir('export') . "/orders/" . $fileName, 'w');
                $filenames[$type] = array("file" => $fp, "count" => 0, "filename" => $fileName);
                $this->addOrderInfo($order, $fp, $type);
            }
            $this->addToFile($item, $fp);
            $filenames[$type]["count"] = $filenames[$type]['count'] + 1;
        }

        $conn_id = ftp_connect("92.63.140.173");
        // login with username and password
        $login_result = ftp_login($conn_id, "client", "psAAdd391");
        ftp_chdir($conn_id, "httpdocs/jungent/export/");

        foreach ($filenames as $file) {
            $record = array(
                '"FTR"',
                $file["count"]
            );
            $this->writeLine($file["file"], $record);
            fclose($file["file"]);

            // upload a file
            if (ftp_put($conn_id, $fileName, Mage::getBaseDir('export') . "/orders/" . $file["filename"], FTP_ASCII)) {
                //echo "successfully uploaded $file\n";
            } else {
                //echo "There was a problem while uploading $file\n";
            }
        }

        ftp_close($conn_id);
        return $fileName;
    }

    public function exportItemsById($order) {
        $fileName = 'items.csv';
        $dirname = 10000000 + $order;
        @mkdir(Mage::getBaseDir('export') . "/orders/{$dirname}/");
        chmod(Mage::getBaseDir('export') . "/orders/{$dirname}/", 777);
        $fp = fopen(Mage::getBaseDir('export') . "/orders/{$dirname}/" . $fileName, 'w');
        $this->writeHeadRowForItems($fp);
        $order = Mage::getModel('sales/order')->load($order);

        $this->writeOrderItems($order, $fp);
        fclose($fp);
        return $fileName;
    }

    public function exportOrders($orders) {
        $fileName = 'order_export_' . date("Ymd_His") . '.csv';
        $fp = fopen(Mage::getBaseDir('export') . '/' . $fileName, 'w');

        $this->writeHeadRow($fp);
        foreach ($orders as $order) {
            $order = Mage::getModel('sales/order')->load($order);
            $this->writeOrder($order, $fp);
        }

        fclose($fp);

        return $fileName;
    }

    /**
     * Writes the head row with the column names in the csv file.
     * 
     * @param $fp The file handle of the csv file
     */
    protected function writeHeadRow($fp) {

        //fputcsv($fp, $this->getHeadRowValues(), self::DELIMITER, self::ENCLOSURE);
    }

    protected function writeHeadRowForItems($fp) {
        fputcsv($fp, $this->getHeadRowValuesForItems(), self::DELIMITER, self::ENCLOSURE);
    }

    /**
     * Writes the row(s) for the given order in the csv file.
     * A row is added to the csv file for each ordered item. 
     * 
     * @param Mage_Sales_Model_Order $order The order to write csv of
     * @param $fp The file handle of the csv file
     */
    public function writeOrderItems($order, $fp) {
        $orderItems = $order->getItemsCollection();
        foreach ($orderItems as $item) {
            $item = $item->toArray();

            $array = array(
                $item['product_id'],
                $item['name'],
                $item['sku'],
                $item['qty_ordered'],
                $item['price'],
                $item['row_total']
            );
            //d($array);
            ///fputcsv($fp,$array,self::DELIMITER,self::ENCLOSURE);
        }
    }

    protected function addToFile($item, $fp) {
        $item = $item->toArray();
        $product = Mage::getModel('catalog/product')->load($item['product_id']);

        //d($item);

        $discount = $product->getPrice() - $product->getFinalPrice();
        $percentage = number_format(100 - (($product->getFinalPrice() / $product->getPrice()) * 100), 2);

        $discountAmmount = $item['discount_amount'];
        $totalAmmount = $item['row_total'];
        $discountPercent = ($discountAmmount * 100) / $totalAmmount;
        $tax = $product->getData('tax_value');

        $price = round($product->getPrice() - ($product->getPrice() * $discountPercent) / 100 , 2);
        $totalAmmount = round($product->getPrice() * intval($item['qty_ordered']) , 2);
        $taxPrice = $tax * (($totalAmmount - $discountAmmount) / 100);

        $without_Tax = $product->getPrice() - (($product->getPrice() / 100) * $product->getData('tax_value'));
        $without_Tax = number_format($without_Tax, 2);

        $ean = "PUUDUB";


        if ($product->getEan())
            $ean = $product->getEan();
            $data = array(
            '"LIN"',
            '"' . $product->getSku() . '"',
            '"' . $product->getName() . '"',
            '"' . $product->getData('unit_id') . '"',
            intval($item['qty_ordered']),
            number_format($tax, 2),
            round($price - $discount , 2),
            '"' . $ean . '"',
            round((($totalAmmount - $discountAmmount) + $taxPrice) , 2),
            '""',
            $percentage,
        );
//        var_dump($discountPercent);exit;
        $i++;
        $this->writeLine($fp, $data);

//        $data = array(
//            '"Discount Percentage"',
//            '"' . $discountPercent . '"'
//        );
//        $i++;
//        $this->writeLine($fp, $data);
    }

    protected function addOrderInfo($order, $fp, $type = "99") {
        $customerAddressId = Mage::getSingleton('customer/session')->getCustomer()->getDefaultBilling();
        if ($customerAddressId) {
            $address = Mage::getModel('customer/address')->load($customerAddressId);
        }
        $addressArray = $address->toArray();
//        $addressString = $addressArray['company'] . " " . $addressArray['firstname'] . " " .
//                $addressArray['lastname'] . " " . 
//                $addressArray['street'] . " " . $addressArray['city'] . " " . 
//                $addressArray['postcode'] . " " . $countryName . " " . $addressArray['telephone'];
        $addressString = $addressArray['street'] . " " . $addressArray['city'] ;
//        var_dump($addressString);echo "<br />";
//        var_dump($addressArray);echo "<br />";
//        var_dump($address->format("text"));exit;
        $record = array(
            '"pakkumine"',
            '12018324',
            '"' . $type . '"',
            date("Y", $order->getCreatedAtDate()->getTimestamp()),
            '"' . $order->getRealOrderId() . '"',
            '""',
            '"' . Mage::getSingleton('customer/session')->getCustomer()->getData('taxvat') . '"',
            '""',
            date("Ymd", $order->getCreatedAtDate()->getTimestamp()),
            date("Ymd", $order->getCreatedAtDate()->getTimestamp() + 86400),
            '""',
//            '"' . $address->format("text") . '"',
            '"' . $addressString . '"',
            '"' . Mage::getSingleton('customer/session')->getCustomer()->getEmail() . '"',
            '""',
            '""',
            '""',
            '""',
            '""',
            '"' . Mage::getSingleton('customer/session')->getCustomer()->getData('suffix') . ' +  ' . Mage::getSingleton('core/session')->getCustomerComment() . '"',
            '"' . date("Ymdhis", $order->getCreatedAtDate()->getTimestamp()) . '"'
        );

        $this->writeLine($fp, $record);
    }

    protected function writeOrder($order, $fp) {
        $common = $this->getCommonOrderValues($order);
        $orderItems = $order->getItemsCollection();
        $itemInc = 0;

        $customerAddressId = Mage::getSingleton('customer/session')->getCustomer()->getDefaultBilling();
        if ($customerAddressId) {
            $address = Mage::getModel('customer/address')->load($customerAddressId);
        }

//d(get_class_methods($address));

        $addressArray = $address->toArray();
//        $addressString = $addressArray['company'] . " " . $addressArray['firstname'] . " " .
//                $addressArray['lastname'] . " " . 
//                $addressArray['street'] . " " . $addressArray['city'] . " " . 
//                $addressArray['postcode'] . " " . $countryName . " " . $addressArray['telephone'];
        $addressString = $addressArray['street'] . " " . $addressArray['city'] ;
        $record = array(
            '"pakkumine"',
            '12018324',
            '"99"',
            date("Y", $order->getCreatedAtDate()->getTimestamp()),
            '"' . $order->getRealOrderId() . '"',
            '""',
            '"' . Mage::getSingleton('customer/session')->getCustomer()->getData('taxvat') . '"',
            '""',
            date("Ymd", $order->getCreatedAtDate()->getTimestamp()),
            date("Ymd", $order->getCreatedAtDate()->getTimestamp() + 86400),
            '""',
            '"' . $addressString . '"',
            '"' . Mage::getSingleton('customer/session')->getCustomer()->getEmail() . '"',
            '""',
            '""',
            '""',
            '""',
            '""',
            '"' . Mage::getSingleton('customer/session')->getCustomer()->getData('suffix') . ' +  ' . Mage::getSingleton('core/session')->getCustomerComment() . '"',
            '"' . date("Ymdhis", $order->getCreatedAtDate()->getTimestamp()) . '"'
        );

        $this->writeLine($fp, $record);
        $orderItems = $order->getItemsCollection();



        $i = 0;
        foreach ($orderItems as $item) {
            $item = $item->toArray();
            $product = Mage::getModel('catalog/product')->load($item['product_id']);



            $discount = $product->getPrice() - $product->getFinalPrice();
            $percentage = number_format(100 - (($product->getFinalPrice() / $product->getPrice()) * 100), 2);

            $without_Tax = $product->getPrice() - (($product->getPrice() / 100) * $product->getData('tax_value'));
            $without_Tax = number_format($without_Tax, 2);

            $ean = "PUUDUB";
            if ($product->getEan())
                $ean = $product->getEan();
            $data = array(
                '"LIN"',
                '"' . $product->getSku() . '"',
                '"' . $product->getName() . '"',
                '"' . $product->getData('unit_id') . '"',
                intval($item['qty_ordered']),
                number_format($product->getData('tax_value'), 2),
                $product->getPrice() - $discount,
                '"' . $ean . '"',
                ($product->getPrice() - $discount) * intval($item['qty_ordered']),
                '""',
                $percentage,
            );
            $i++;
            $this->writeLine($fp, $data);
        }

        $record = array(
            '"FTR"',
            count($orderItems)
        );
        $this->writeLine($fp, $record);
        fclose($fp);







        // foreach ($orderItems as $item)
//        {
//            if (!$item->isDummy()) {
//                $record = array_merge($common, $this->getOrderItemValues($item, $order, ++$itemInc));
//                fputcsv($fp, $record, self::DELIMITER, self::ENCLOSURE);
//            }
//        }
    }

    /**
     * Returns the head column names.
     * 
     * @return Array The array containing all column names
     */
    protected function getHeadRowValues() {

        return array(
            'Order Number',
            'Order Date',
            'Order Status',
            'Order Purchased From',
            'Order Payment Method',
            'Order Shipping Method',
            'Order Subtotal',
            'Order Tax',
            'Order Shipping',
            'Order Discount',
            'Order Grand Total',
            'Order Paid',
            'Order Refunded',
            'Order Due',
            'Total Qty Items Ordered',
            'Customer Name',
            'Customer Email',
            'Shipping Name',
            'Shipping Company',
            'Shipping Street',
            'Shipping Zip',
            'Shipping City',
            'Shipping State',
            'Shipping State Name',
            'Shipping Country',
            'Shipping Country Name',
            'Shipping Phone Number',
            'Billing Name',
            'Billing Company',
            'Billing Street',
            'Billing Zip',
            'Billing City',
            'Billing State',
            'Billing State Name',
            'Billing Country',
            'Billing Country Name',
            'Billing Phone Number',
            'Order Item Increment',
            'Item Name',
            'Item Status',
            'Item SKU',
            'Item Options',
            'Item Original Price',
            'Item Price',
            'Item Qty Ordered',
            'Item Qty Invoiced',
            'Item Qty Shipped',
            'Item Qty Canceled',
            'Item Qty Refunded',
            'Item Tax',
            'Item Discount',
            'Item Total'
        );
    }

    protected function getHeadRowValuesForItems() {
        return array(
            'Product Id',
            'Product Name',
            'SKU',
            'Ordered Count',
            'Item Price',
            'Item Total',
        );
    }

    /**
     * Returns the values which are identical for each row of the given order. These are
     * all the values which are not item specific: order data, shipping address, billing
     * address and order totals.
     * 
     * @param Mage_Sales_Model_Order $order The order to get values from
     * @return Array The array containing the non item specific values
     */
    protected function getCommonOrderValues($order) {
        $shippingAddress = !$order->getIsVirtual() ? $order->getShippingAddress() : null;
        $billingAddress = $order->getBillingAddress();

        return array(
            $order->getRealOrderId(),
            Mage::helper('core')->formatDate($order->getCreatedAt(), 'medium', true),
            $order->getStatus(),
            $this->getStoreName($order),
            $this->getPaymentMethod($order),
            $this->getShippingMethod($order),
            $this->formatPrice($order->getData('subtotal'), $order),
            $this->formatPrice($order->getData('tax_amount'), $order),
            $this->formatPrice($order->getData('shipping_amount'), $order),
            $this->formatPrice($order->getData('discount_amount'), $order),
            $this->formatPrice($order->getData('grand_total'), $order),
            $this->formatPrice($order->getData('total_paid'), $order),
            $this->formatPrice($order->getData('total_refunded'), $order),
            $this->formatPrice($order->getData('total_due'), $order),
            $this->getTotalQtyItemsOrdered($order),
            $order->getCustomerName(),
            $order->getCustomerEmail(),
            $shippingAddress ? $shippingAddress->getName() : '',
            $shippingAddress ? '' : $shippingAddress->getData("company"),
            $shippingAddress ? $shippingAddress->getData("street") : '',
            $shippingAddress ? $shippingAddress->getData("postcode") : '',
            $shippingAddress ? $shippingAddress->getData("city") : '',
            $shippingAddress ? $shippingAddress->getRegionCode() : '',
            $shippingAddress ? $shippingAddress->getRegion() : '',
            $shippingAddress ? $shippingAddress->getCountry() : '',
            $shippingAddress ? $shippingAddress->getCountryModel()->getName() : '',
            $shippingAddress ? $shippingAddress->getData("telephone") : '',
            $billingAddress->getName(),
            $billingAddress->getData("company"),
            $billingAddress->getData("street"),
            $billingAddress->getData("postcode"),
            $billingAddress->getData("city"),
            $billingAddress->getRegionCode(),
            $billingAddress->getRegion(),
            $billingAddress->getCountry(),
            $billingAddress->getCountryModel()->getName(),
            $billingAddress->getData("telephone")
        );
    }

    /**
     * Returns the item specific values.
     * 
     * @param Mage_Sales_Model_Order_Item $item The item to get values from
     * @param Mage_Sales_Model_Order $order The order the item belongs to
     * @return Array The array containing the item specific values
     */
    protected function getOrderItemValues($item, $order, $itemInc=1) {
        return array(
            $itemInc,
            $item->getName(),
            $item->getStatus(),
            $this->getItemSku($item),
            $this->getItemOptions($item),
            $this->formatPrice($item->getOriginalPrice(), $order),
            $this->formatPrice($item->getData('price'), $order),
            (int) $item->getQtyOrdered(),
            (int) $item->getQtyInvoiced(),
            (int) $item->getQtyShipped(),
            (int) $item->getQtyCanceled(),
            (int) $item->getQtyRefunded(),
            $this->formatPrice($item->getTaxAmount(), $order),
            $this->formatPrice($item->getDiscountAmount(), $order),
            $this->formatPrice($this->getItemTotal($item), $order)
        );
    }

}

?>