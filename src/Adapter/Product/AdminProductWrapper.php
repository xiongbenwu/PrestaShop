<?php
/**
 * 2007-2016 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
namespace PrestaShop\PrestaShop\Adapter\Product;

/**
 * Admin controller wrapper for new Architecture, about Product admin controller.
 */
class AdminProductWrapper
{
    private $errors = array();
    private $translator;
    private $legacyContext;

    /**
     * Constructor : Inject Symfony\Component\Translation Translator
     *
     * @param object $translator
     */
    public function __construct($translator, $legacyContext)
    {
        $this->translator = $translator;
        $this->legacyContext = $legacyContext->getContext();
    }

    /**
     * getInstance
     * Get the legacy AdminProductsControllerCore instance
     *
     * @return \AdminProductsControllerCore instance
     */
    public function getInstance()
    {
        return new \AdminProductsControllerCore();
    }

    /**
     * processProductAttribute
     * Update a combination
     *
     * @param object $product
     * @param array $combinationValues the posted values
     *
     * @return \AdminProductsControllerCore instance
     */
    public function processProductAttribute($product, $combinationValues)
    {
        $id_product_attribute = (int)$combinationValues['id_product_attribute'];
        $images = array();

        if (!\CombinationCore::isFeatureActive() || $id_product_attribute == 0) {
            return;
        }

        if (!isset($combinationValues['attribute_wholesale_price'])) {
            $combinationValues['attribute_wholesale_price'] = 0;
        }
        if (!isset($combinationValues['attribute_price_impact'])) {
            $combinationValues['attribute_price_impact'] = 0;
        }
        if (!isset($combinationValues['attribute_weight_impact'])) {
            $combinationValues['attribute_weight_impact'] = 0;
        }
        if (!isset($combinationValues['attribute_ecotax'])) {
            $combinationValues['attribute_ecotax'] = 0;
        }
        if ((isset($combinationValues['attribute_default']) && $combinationValues['attribute_default'] == 1)) {
            $product->deleteDefaultAttributes();
        }
        if (!empty($combinationValues['id_image_attr'])) {
            $images = $combinationValues['id_image_attr'];
        }

        $product->updateAttribute(
            $id_product_attribute,
            $combinationValues['attribute_wholesale_price'],
            $combinationValues['attribute_price'] * $combinationValues['attribute_price_impact'],
            $combinationValues['attribute_weight'] * $combinationValues['attribute_weight_impact'],
            $combinationValues['attribute_unity'] * $combinationValues['attribute_unit_impact'],
            $combinationValues['attribute_ecotax'],
            $images,
            $combinationValues['attribute_reference'],
            $combinationValues['attribute_ean13'],
            (isset($combinationValues['attribute_default']) && $combinationValues['attribute_default'] == 1),
            isset($combinationValues['attribute_location']) ? $combinationValues['attribute_location'] : null,
            $combinationValues['attribute_upc'],
            $combinationValues['attribute_minimal_quantity'],
            $combinationValues['available_date_attribute'],
            false,
            array(),
            $combinationValues['attribute_isbn']
        );

        \StockAvailableCore::setProductDependsOnStock((int)$product->id, $product->depends_on_stock, null, $id_product_attribute);
        \StockAvailableCore::setProductOutOfStock((int)$product->id, $product->out_of_stock, null, $id_product_attribute);

        $product->checkDefaultAttributes();

        if ((isset($combinationValues['attribute_default']) && $combinationValues['attribute_default'] == 1)) {
            \ProductCore::updateDefaultAttribute((int)$product->id);
            if (isset($id_product_attribute)) {
                $product->cache_default_attribute = (int)$id_product_attribute;
            }

            if ($available_date = $combinationValues['available_date_attribute']) {
                $product->setAvailableDate($available_date);
            } else {
                $product->setAvailableDate();
            }
        }

        if(isset($combinationValues['attribute_quantity'])){
            $this->processQuantityUpdate($product, $combinationValues['attribute_quantity'], $id_product_attribute);
        }

    }

    /**
     * Update a quantity for a product or a combination.
     *
     * Does not work in Advanced stock management.
     *
     * @param \ProductCore $product
     * @param integer $quantity
     * @param integer $forAttributeId
     */
    public function processQuantityUpdate(\ProductCore $product, $quantity, $forAttributeId = 0)
    {
        // Hook triggered by legacy code below: actionUpdateQuantity('id_product', 'id_product_attribute', 'quantity')
        \StockAvailableCore::setQuantity((int)$product->id, $forAttributeId, $quantity);
        \HookCore::exec('actionProductUpdate', array('id_product' => (int)$product->id, 'product' => $product));
    }

    /**
     * Update the out of stock strategy
     *
     * @param \ProductCore $product
     * @param integer $out_of_stock
     */
    public function processProductOutOfStock(\ProductCore $product, $out_of_stock)
    {
        \StockAvailableCore::setProductOutOfStock((int)$product->id, (int)$out_of_stock);
    }

    /**
     * Set if a product depends on stock (ASM). For a product or a combination.
     *
     * Does work only in Advanced stock management.
     *
     * @param \ProductCore $product
     * @param boolean $dependsOnStock
     * @param integer $forAttributeId
     */
    public function processDependsOnStock(\ProductCore $product, $dependsOnStock, $forAttributeId = 0)
    {
        \StockAvailableCore::setProductDependsOnStock((int)$product->id, $dependsOnStock, null, $forAttributeId);
    }

    /**
     * processProductSpecificPrice
     * Add/Update specific price
     *
     * @param int $id_product
     * @param array $specificPriceValues the posted values
     *
     * @return \AdminProductsControllerCore instance
     */
    public function processProductSpecificPrice($id_product, $specificPriceValues)
    {
        $id_product_attribute = $specificPriceValues['sp_id_product_attribute'];
        $id_shop = $specificPriceValues['sp_id_shop'] ? $specificPriceValues['sp_id_shop'] : 0;
        $id_currency = $specificPriceValues['sp_id_currency'] ? $specificPriceValues['sp_id_currency'] : 0;
        $id_country = $specificPriceValues['sp_id_country'] ? $specificPriceValues['sp_id_country'] : 0;
        $id_group = $specificPriceValues['sp_id_group'] ? $specificPriceValues['sp_id_group'] : 0;
        $id_customer = !empty($specificPriceValues['sp_id_customer']['data']) ? $specificPriceValues['sp_id_customer']['data'][0] : 0;
        $price = isset($specificPriceValues['leave_bprice']) ? '-1' : $specificPriceValues['sp_price'];
        $from_quantity = $specificPriceValues['sp_from_quantity'];
        $reduction = (float)$specificPriceValues['sp_reduction'];
        $reduction_tax = $specificPriceValues['sp_reduction_tax'];
        $reduction_type = !$reduction ? 'amount' : $specificPriceValues['sp_reduction_type'];
        $reduction_type = $reduction_type == '-' ? 'amount' : $reduction_type;
        $from = $specificPriceValues['sp_from'];
        if (!$from) {
            $from = '0000-00-00 00:00:00';
        }
        $to = $specificPriceValues['sp_to'];
        if (!$to) {
            $to = '0000-00-00 00:00:00';
        }

        if (($price == '-1') && ((float)$reduction == '0')) {
            $this->errors[] = $this->translator->trans('No reduction value has been submitted', array(), 'Admin.Catalog.Notification');
        } elseif ($to != '0000-00-00 00:00:00' && strtotime($to) < strtotime($from)) {
            $this->errors[] = $this->translator->trans('Invalid date range', array(), 'Admin.Catalog.Notification');
        } elseif ($reduction_type == 'percentage' && ((float)$reduction <= 0 || (float)$reduction > 100)) {
            $this->errors[] = $this->translator->trans('Submitted reduction value (0-100) is out-of-range', array(), 'Admin.Catalog.Notification');
        } elseif ($this->validateSpecificPrice($id_product, $id_shop, $id_currency, $id_country, $id_group, $id_customer, $price, $from_quantity, $reduction, $reduction_type, $from, $to, $id_product_attribute)) {
            $specificPrice = new \SpecificPriceCore();
            $specificPrice->id_product = (int)$id_product;
            $specificPrice->id_product_attribute = (int)$id_product_attribute;
            $specificPrice->id_shop = (int)$id_shop;
            $specificPrice->id_currency = (int)($id_currency);
            $specificPrice->id_country = (int)($id_country);
            $specificPrice->id_group = (int)($id_group);
            $specificPrice->id_customer = (int)$id_customer;
            $specificPrice->price = (float)($price);
            $specificPrice->from_quantity = (int)($from_quantity);
            $specificPrice->reduction = (float)($reduction_type == 'percentage' ? $reduction / 100 : $reduction);
            $specificPrice->reduction_tax = $reduction_tax;
            $specificPrice->reduction_type = $reduction_type;
            $specificPrice->from = $from;
            $specificPrice->to = $to;

            if (!$specificPrice->add()) {
                $this->errors[] = $this->translator->trans('An error occurred while updating the specific price.', array(), 'Admin.Catalog.Notification');
            }
        }

        return $this->errors;
    }

    /**
     * Validate a specific price
     */
    private function validateSpecificPrice($id_product, $id_shop, $id_currency, $id_country, $id_group, $id_customer, $price, $from_quantity, $reduction, $reduction_type, $from, $to, $id_combination = 0)
    {
        if (!\Validate::isUnsignedId($id_shop) || !\ValidateCore::isUnsignedId($id_currency) || !\ValidateCore::isUnsignedId($id_country) || !\ValidateCore::isUnsignedId($id_group) || !\ValidateCore::isUnsignedId($id_customer)) {
            $this->errors[] = 'Wrong IDs';
        } elseif ((!isset($price) && !isset($reduction)) || (isset($price) && !\ValidateCore::isNegativePrice($price)) || (isset($reduction) && !\ValidateCore::isPrice($reduction))) {
            $this->errors[] = 'Invalid price/discount amount';
        } elseif (!\ValidateCore::isUnsignedInt($from_quantity)) {
            $this->errors[] = 'Invalid quantity';
        } elseif ($reduction && !\ValidateCore::isReductionType($reduction_type)) {
            $this->errors[] = 'Please select a discount type (amount or percentage).';
        } elseif ($from && $to && (!\ValidateCore::isDateFormat($from) || !\ValidateCore::isDateFormat($to))) {
            $this->errors[] = 'The from/to date is invalid.';
        } elseif (\SpecificPriceCore::exists((int)$id_product, $id_combination, $id_shop, $id_group, $id_country, $id_currency, $id_customer, $from_quantity, $from, $to, false)) {
            $this->errors[] = 'A specific price already exists for these parameters.';
        } else {
            return true;
        }
        return false;
    }

    /**
     * Get specific prices list for a product
     *
     * @param object $product
     * @param object $defaultCurrency
     * @param array $shops Available shops
     * @param array $currencies Available currencies
     * @param array $countries Available countries
     * @param array $groups Available users groups
     *
     * @return array
     */
    public function getSpecificPricesList($product, $defaultCurrency, $shops, $currencies, $countries, $groups)
    {
        $content = array();
        $specific_prices = \SpecificPriceCore::getByProductId((int)$product->id);

        $tmp = array();
        foreach ($shops as $shop) {
            $tmp[$shop['id_shop']] = $shop;
        }
        $shops = $tmp;
        $tmp = array();
        foreach ($currencies as $currency) {
            $tmp[$currency['id_currency']] = $currency;
        }
        $currencies = $tmp;

        $tmp = array();
        foreach ($countries as $country) {
            $tmp[$country['id_country']] = $country;
        }
        $countries = $tmp;

        $tmp = array();
        foreach ($groups as $group) {
            $tmp[$group['id_group']] = $group;
        }
        $groups = $tmp;

        if (is_array($specific_prices) && count($specific_prices)) {
            foreach ($specific_prices as $specific_price) {
                $id_currency = $specific_price['id_currency'] ? $specific_price['id_currency'] : $defaultCurrency->id;
                if (!isset($currencies[$id_currency])) {
                    continue;
                }

                $current_specific_currency = $currencies[$id_currency];
                if ($specific_price['reduction_type'] == 'percentage') {
                    $impact = '- ' . ($specific_price['reduction'] * 100) . ' %';
                } elseif ($specific_price['reduction'] > 0) {
                    $impact = '- ' . \ToolsCore::displayPrice(\Tools::ps_round($specific_price['reduction'], 2), $current_specific_currency) . ' ';
                    if ($specific_price['reduction_tax']) {
                        $impact .= '(' . $this->translator->trans('Tax incl.', array(), 'Admin.Global') . ')';
                    } else {
                        $impact .= '(' . $this->translator->trans('Tax excl.', array(), 'Admin.Global') . ')';
                    }
                } else {
                    $impact = '--';
                }

                if ($specific_price['from'] == '0000-00-00 00:00:00' && $specific_price['to'] == '0000-00-00 00:00:00') {
                    $period = $this->translator->trans('Unlimited', array(), 'Admin.Global');
                } else {
                    $period = $this->translator->trans('From', array(), 'Admin.Global') . ' ' . ($specific_price['from'] != '0000-00-00 00:00:00' ? $specific_price['from'] : '0000-00-00 00:00:00') . '<br />' . $this->translator->trans('to', array(), 'Admin.Global') . ' ' . ($specific_price['to'] != '0000-00-00 00:00:00' ? $specific_price['to'] : '0000-00-00 00:00:00');
                }
                if ($specific_price['id_product_attribute']) {
                    $combination = new \CombinationCore((int)$specific_price['id_product_attribute']);
                    $attributes = $combination->getAttributesName(1);
                    $attributes_name = '';
                    foreach ($attributes as $attribute) {
                        $attributes_name .= $attribute['name'] . ' - ';
                    }
                    $attributes_name = rtrim($attributes_name, ' - ');
                } else {
                    $attributes_name = $this->translator->trans('All combinations', array(), 'Admin.Catalog.Feature');
                }

                $rule = new \SpecificPriceRuleCore((int)$specific_price['id_specific_price_rule']);
                $rule_name = ($rule->id ? $rule->name : '--');

                if ($specific_price['id_customer']) {
                    $customer = new \CustomerCore((int)$specific_price['id_customer']);
                    if (\ValidateCore::isLoadedObject($customer)) {
                        $customer_full_name = $customer->firstname . ' ' . $customer->lastname;
                    }
                    unset($customer);
                }

                if (!$specific_price['id_shop'] || in_array($specific_price['id_shop'], \ShopCore::getContextListShopID())) {
                    $can_delete_specific_prices = true;
                    if (\ShopCore::isFeatureActive()) {
                        $can_delete_specific_prices = (count($this->legacyContext->employee->getAssociatedShops()) > 1 && !$specific_price['id_shop']) || $specific_price['id_shop'];
                    }

                    $price = \ToolsCore::ps_round($specific_price['price'], 2);
                    $fixed_price = ($price == \ToolsCore::ps_round($product->price, 2) || $specific_price['price'] == -1) ? '--' : \ToolsCore::displayPrice($price, $current_specific_currency);

                    $content[] = [
                        'id_specific_price' => $specific_price['id_specific_price'],
                        'id_product' => $product->id,
                        'rule_name' => $rule_name,
                        'attributes_name' => $attributes_name,
                        'shop' => ($specific_price['id_shop'] ? $shops[$specific_price['id_shop']]['name'] : $this->translator->trans('All shops', array(), 'Admin.Global')),
                        'currency' => ($specific_price['id_currency'] ? $currencies[$specific_price['id_currency']]['name'] : $this->translator->trans('All currencies', array(), 'Admin.Global')),
                        'country' => ($specific_price['id_country'] ? $countries[$specific_price['id_country']]['name'] : $this->translator->trans('All countries', array(), 'Admin.Global')),
                        'group' => ($specific_price['id_group'] ? $groups[$specific_price['id_group']]['name'] : $this->translator->trans('All groups', array(), 'Admin.Global')),
                        'customer' => (isset($customer_full_name) ? $customer_full_name : $this->translator->trans('All customers', array(), 'Admin.Global')),
                        'fixed_price' => $fixed_price,
                        'impact' => $impact,
                        'period' => $period,
                        'from_quantity' => $specific_price['from_quantity'],
                        'can_delete' => (!$rule->id && $can_delete_specific_prices) ? true : false
                    ];

                    unset($customer_full_name);
                }
            }
        }

        return $content;
    }

    /**
     * Delete a specific price
     *
     * @param int $id_specific_price
     *
     * @return array error & status
     */
    public function deleteSpecificPrice($id_specific_price)
    {
        if (!$id_specific_price || !\ValidateCore::isUnsignedId($id_specific_price)) {
            $error = $this->translator->trans('The specific price ID is invalid.', array(), 'Admin.Catalog.Notification');
        } else {
            $specificPrice = new \SpecificPriceCore((int)$id_specific_price);
            if (!$specificPrice->delete()) {
                $error = $this->translator->trans('An error occurred while attempting to delete the specific price.', array(), 'Admin.Catalog.Notification');
            }
        }

        if (isset($error)) {
            return array(
                'status' => 'error',
                'message'=> $error
            );
        }

        return array(
            'status' => 'ok',
            'message'=> $this->translator->trans('Successful deletion', array(), 'Admin.Notifications.Success'),
        );
    }

    /**
     * Get price priority
     *
     * @param null|int $idProduct
     *
     * @return array
     */
    public function getPricePriority($idProduct = null)
    {
        if (!$idProduct) {
            return [
                0 => "id_shop",
                1 => "id_currency",
                2 => "id_country",
                3 => "id_group"
            ];
        }

        $specific_price_priorities = \SpecificPriceCore::getPriority((int)$idProduct);

        // Not use id_customer
        if ($specific_price_priorities[0] == 'id_customer') {
            unset($specific_price_priorities[0]);
        }

        return array_values($specific_price_priorities);
    }

    /**
     * Process customization collection
     *
     * @param object $product
     * @param array $data
     *
     * @return bool
     */
    public function processProductCustomization($product, $data)
    {
        $customization_ids = array();
        if ($data) {
            foreach ($data as $customization) {
                $customization_ids[] = (int)$customization['id_customization_field'];
            }
        }

        //remove customization field langs
        foreach ($product->getCustomizationFieldIds() as $customizationFiled) {
            \Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'customization_field_lang WHERE `id_customization_field` = '.(int)$customizationFiled['id_customization_field']);
        }

        //remove unused customization for the product
        \Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'customization_field WHERE 
            `id_product` = '.(int)$product->id.' AND `id_customization_field` NOT IN ('.implode(",", $customization_ids).')');

        //create new customizations
        $countFieldText = 0;
        $countFieldFile = 0;
        $productCustomizableValue = 0;
        $hasRequiredField = false;
        $shopList = \ShopCore::getContextListShopID();

        if ($data) {
            foreach ($data as $customization) {
                if ($customization['require']) {
                    $hasRequiredField = true;
                }

                //create label
                if (isset($customization['id_customization_field'])) {
                    $id_customization_field = (int)$customization['id_customization_field'];
                    \Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'customization_field`
					SET `required` = ' . ($customization['require'] ? 1 : 0) . ', `type` = ' . (int)$customization['type'] . '
					WHERE `id_customization_field` = '.$id_customization_field);
                } else {
                		\Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'customization_field` (`id_product`, `type`, `required`)
                    	VALUES ('.(int)$product->id.', '.(int)$customization['type'].', '.($customization['require'] ? 1 : 0).')');
               		$id_customization_field = (int)\Db::getInstance()->Insert_ID();
                }

                // Create multilingual label name
                $langValues = '';
                foreach (\LanguageCore::getLanguages() as $language) {
                    $name = $customization['label'][$language['id_lang']];
                    foreach ($shopList as $id_shop) {
                        $langValues .= '('.(int)$id_customization_field.', '.(int)$language['id_lang'].', '.$id_shop .',\''.$name.'\'), ';
                    }
                }
                \Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'customization_field_lang` (`id_customization_field`, `id_lang`, `id_shop`, `name`) VALUES '.rtrim($langValues, ', '));

                if ($customization['type'] == 0) {
                    $countFieldFile++;
                } else {
                    $countFieldText++;
                }
            }

            $productCustomizableValue = $hasRequiredField ? 2 : 1;
        }

        //update product count fields labels
        \Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'product` SET `customizable` = '.$productCustomizableValue.', `uploadable_files` = '.(int)$countFieldFile.', `text_fields` = '.(int)$countFieldText.' WHERE `id_product` = '.(int)$product->id);

        //update product_shop count fields labels
        \ObjectModelCore::updateMultishopTable('product', array(
            'customizable' => $productCustomizableValue,
            'uploadable_files' => (int)$countFieldFile,
            'text_fields' => (int)$countFieldText,
        ), 'a.id_product = '.(int)$product->id);

        \ConfigurationCore::updateGlobalValue('PS_CUSTOMIZATION_FEATURE_ACTIVE', '1');
    }

    /**
     * Update product download
     *
     * @param object $product
     * @param array $data
     *
     * @return bool
     */
    public function updateDownloadProduct($product, $data)
    {
        $id_product_download = \ProductDownloadCore::getIdFromIdProduct((int)$product->id, false);
        $download = new \ProductDownloadCore($id_product_download ? $id_product_download : null);

        if ((int)$data['is_virtual_file'] == 1) {
            $fileName = null;
            $file = $data['file'];

            if (!empty($file)) {
                $fileName = \ProductDownloadCore::getNewFilename();
                $file->move(_PS_DOWNLOAD_DIR_, $fileName);
            }

            $product->setDefaultAttribute(0);//reset cache_default_attribute

            $download->id_product = (int)$product->id;
            $download->display_filename = $data['name'];
            $download->filename = $fileName ? $fileName : $download->filename;
            $download->date_add = date('Y-m-d H:i:s');
            $download->date_expiration = $data['expiration_date'] ? $data['expiration_date'].' 23:59:59' : '';
            $download->nb_days_accessible = (int)$data['nb_days'];
            $download->nb_downloadable = (int)$data['nb_downloadable'];
            $download->active = 1;
            $download->is_shareable = 0;

            if (!$id_product_download) {
                $download->save();
            } else {
                $download->update();
            }
        } else {
            if (!empty($id_product_download)) {
                $download->date_expiration = date('Y-m-d H:i:s', time() - 1);
                $download->active = 0;
                $download->update();
            }
        }

        return $download;
    }

    /**
     * Delete file from a virtual product
     *
     * @param object $product
     */
    public function processDeleteVirtualProductFile($product)
    {
        $id_product_download = \ProductDownloadCore::getIdFromIdProduct((int)$product->id, false);
        $download = new \ProductDownloadCore($id_product_download ? $id_product_download : null);

        if ($download && !empty($download->filename)) {
            unlink(_PS_DOWNLOAD_DIR_.$download->filename);
            \Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'product_download` SET filename = "" WHERE `id_product_download` = '.(int)$download->id);
        }
    }

    /**
     * Delete a virtual product
     *
     * @param object $product
     */
    public function processDeleteVirtualProduct($product)
    {
        $id_product_download = \ProductDownloadCore::getIdFromIdProduct((int)$product->id, false);
        $download = new \ProductDownloadCore($id_product_download ? $id_product_download : null);

        if ($download) {
            $download->delete(true);
        }
    }

    /**
     * Add attachement file
     *
     * @param object $product
     * @param array $data
     * @param array $locales
     *
     * @return object|null Attachement
     */
    public function processAddAttachment($product, $data, $locales)
    {
        $attachment = null;
        $file = $data['file'];
        if (!empty($file)) {
            $fileName = sha1(microtime());
            $attachment = new \AttachmentCore();

            foreach ($locales as $locale) {
                $attachment->name[(int)$locale['id_lang']] = $data['name'];
                $attachment->description[(int)$locale['id_lang']] = $data['description'];
            }

            $attachment->file = $fileName;
            $attachment->mime = $file->getMimeType();
            $attachment->file_name = $file->getClientOriginalName();

            $file->move(_PS_DOWNLOAD_DIR_, $fileName);

            if ($attachment->add()) {
                $attachment->attachProduct($product->id);
            }
        }

        return $attachment;
    }

    /**
     * Process product attachments
     *
     * @param object $product
     * @param array $data
     */
    public function processAttachments($product, $data)
    {
        \AttachmentCore::attachToProduct($product->id, $data);
    }

    /**
     * Update images positions
     *
     * @param array $data Indexed array with id product/position
     */
    public function ajaxProcessUpdateImagePosition($data)
    {
        foreach ($data as $id => $position) {
            $img = new \ImageCore((int)$id);
            $img->position = (int)$position;
            $img->update();
        }
    }

    /**
     * Update image legend and cover
     *
     * @param int $idImage
     * @param array $data
     *
     * @return object image
     */
    public function ajaxProcessUpdateImage($idImage, $data)
    {
        $img = new \ImageCore((int)$idImage);
        if ($data['cover']) {
            \ImageCore::deleteCover((int)$img->id_product);
            $img->cover = 1;
        }
        $img->legend = $data['legend'];
        $img->update();

        return $img;
    }

    /**
     * Generate preview URL
     *
     * @param object $product
     * @param bool $preview
     *
     * @return string preview url
     */
    public function getPreviewUrl($product, $preview=true)
    {
        $context = \ContextCore::getContext();
        $id_lang = \ConfigurationCore::get('PS_LANG_DEFAULT', null, null, $context->shop->id);

        if (!\ShopUrlCore::getMainShopDomain()) {
            return false;
        }

        $is_rewrite_active = (bool)\ConfigurationCore::get('PS_REWRITING_SETTINGS');
        $preview_url = $context->link->getProductLink(
            $product,
            $product->link_rewrite[$context->language->id],
            \CategoryCore::getLinkRewrite($product->id_category_default, $context->language->id),
            null,
            $id_lang,
            (int)$context->shop->id,
            0,
            $is_rewrite_active
        );

        if (!$product->active && $preview) {
            $preview_url = $this->getPreviewUrlDeactivate($preview_url);
        }

        return $preview_url;
    }

    /**
     * Generate preview URL deactivate
     *
     * @param string $preview_url
     *
     * @return string preview url deactivate
     */
    public function getPreviewUrlDeactivate($preview_url)
    {
        $context = \ContextCore::getContext();
        $token = \ToolsCore::getAdminTokenLite('AdminProducts');

        $admin_dir = dirname($_SERVER['PHP_SELF']);
        $admin_dir = substr($admin_dir, strrpos($admin_dir, '/') + 1);
        $preview_url_deactivate = $preview_url . ((strpos($preview_url, '?') === false) ? '?' : '&') . 'adtoken=' . $token . '&ad=' . $admin_dir . '&id_employee=' . (int)$context->employee->id;

        return $preview_url_deactivate;
    }

    /**
     * Generate preview URL
     *
     * @param integer $productId
     *
     * @return string preview url
     */
    public function getPreviewUrlFromId($productId)
    {
        $product = new \ProductCore($productId, false);
        return $this->getPreviewUrl($product);
    }
}
