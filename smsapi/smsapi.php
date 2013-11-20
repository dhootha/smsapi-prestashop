<?php

/*
* The MIT License (MIT)
* 
* Copyright (c) 2013 Iztok Svetik
* 
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
* 
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
* 
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*
* -----------------------------------------------------------------------------
* @author   Iztok Svetik
* @website  http://www.isd.si
* @github   https://github.com/iztoksvetik
*/

if (!defined('_PS_VERSION_'))
  exit;
include_once(dirname(__FILE__) . '/CSVRow.php');

class SmsApi extends Module
{
  public function __construct()
  {
    $this->name = 'smsapi';
    $this->tab = 'administration';
    $this->version = '1.0';
    $this->author = 'Iztok Svetik - isd.si';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.5.9'); 
 
    parent::__construct();
 
    $this->displayName = $this->l('SmsAPI customer export');
    $this->description = $this->l('Export CSV file of customers mobile numbers.');
 
    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
 
  }
 
  public function install()
  {
    if (Shop::isFeatureActive())
      Shop::setContext(Shop::CONTEXT_ALL);
   
    return parent::install();

  }

  public function uninstall()
  {
    return parent::uninstall();
  }

  public function getContent()
  {
    if (Tools::isSubmit('submitsmsapi')) { 
      if (Tools::getValue('all') == 1) {
        $all = true;
      }
      else {
        $all = false;
      }

      if (Tools::getValue('group') == 1) {
        $group = true;
        $data = $this->exportCSV($group, $all, Tools::getValue('buyers'), Tools::getValue('nonbuyers'));
      }
      else {
        $group = false;
        $data = $this->exportCSV($group, $all);
      }
      
      session_start();
      $_SESSION['sa_data'] = $data;
      header('Location: ../modules/smsapi/export.php');
    }

    return $this->displayForm();
  }

  public function displayForm()
  {
    // Get default Language
    $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
     
    // Init Fields form array
    $fields_form[0]['form'] = array(
        'legend' => array(
            'title' => $this->l('Order products by sales'),
        ),
        'input' => array(
            array(
                'type' => 'radio',
                'label' => $this->l('Group users'),
                'name' => 'group',
                'desc' => $this->l('Set user group according to if they have bought any products in store or not'),
                'is_bool' => true,
                'class' => 't',
                'values' => array(
                  array(
                    'id' => 'active_on',
                    'value' => 1,
                    'label' => $this->l('Enabled')
                  ),
                  array(
                    'id' => 'active_off',
                    'value' => 0,
                    'label' => $this->l('Disabled')
                  )
                )
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Buyers group name'),
                'name' => 'buyers',
                'desc' => $this->l('Name the group of those who bought products if you wish to group customers'),
                'size' => 30
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Non buyers group name'),
                'name' => 'nonbuyers',
                'desc' => $this->l('Name the group of those who have registered but havent bought any products, if you wish to group customers'),
                'size' => 30
            ),
            array(
                'type' => 'radio',
                'label' => $this->l('All numbers'),
                'name' => 'all',
                'desc' => $this->l('Export multiple numbers if user has more then one'),
                'is_bool' => true,
                'class' => 't',
                'values' => array(
                  array(
                    'id' => 'active_on',
                    'value' => 1,
                    'label' => $this->l('Enabled')
                  ),
                  array(
                    'id' => 'active_off',
                    'value' => 0,
                    'label' => $this->l('Disabled')
                  )
                )
            )
        ),
        'submit' => array(
            'title' => $this->l('Export CSV'),
            'class' => 'button'
        )
    );
     
    $helper = new HelperForm();
     
    // Module, t    oken and currentIndex
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
     
    // Language
    $helper->default_form_language = $default_lang;
    $helper->allow_employee_form_lang = $default_lang;
     
    // Title and toolbar
    $helper->title = $this->displayName;
    $helper->show_toolbar = true;        // false -> remove toolbar
    $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
    $helper->submit_action = 'submit'.$this->name;
    $helper->toolbar_btn = array(
        'save' => array(
            'desc' => $this->l('Export CSV'),
            'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
            '&token='.$helper->token,
        ),
        'back' => array(
            'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->l('Back to list')
        )
    );

    $helper->fields_value = array(
      'group' => true,
      'all' => true,
      'buyers' => '',
      'nonbuyers' => ''
      );
     
     
    return $helper->generateForm($fields_form);
  }
  private function exportCSV($group_buyers, $all_customers_numbers, $buyers = '', $nonbuyers = '')
  {
    $data = array();
    $customers = Customer::getCustomers();

    foreach ($customers as $c) {
      $customer = new Customer($c['id_customer']);
      $addreses = $customer->getAddresses(2);
      $numbers = array();
      foreach ($addreses as $address) {
        if ($address['id_country'] == 193) {
          if (!empty($address['phone_mobile'])) {
            $numbers[] = $address['phone_mobile'];
          }
          if (!empty($address['phone'])) {
            $numbers[] = $address['phone'];
          }
        }
      }
      $numbers_validated = array();
      foreach ($numbers as $number) {
        // Replace all the possible bad input, first remove all but digits, then replace possible country code with 0
        $number = preg_replace('/[^0-9]/', '', $number);
        $number = preg_replace('/^386/', '0', $number);
        $number = preg_replace('/^00386/', '0', $number);
        
        // All Slovene phone numbers are exactly 9 digits long, if thats not the case its not a valid number
        if (strlen($number) === 9) {
          // First 3 digits of Slovene mobile numbers 
          // 030, 031, 040, 041, 051, 064, 068, 070, 071
          if (preg_match('/^0(30|31|40|41|51|64|68|70|71)/', $number)) {
            // Make sure one number is not in listed more then once
            if (! in_array($number, $numbers_validated)) {
              $numbers_validated[] = $number;
            }
          }
        }
      }
      // Has customer bought any products in the shop
      $products = $customer->getBoughtProducts();
      $group = '';
      // Has user requested a CSV with grouped buyers
      if ($group_buyers) {
        if (empty($products)) {
          $group = $nonbuyers;
        }
        else {
          $group = $buyers;
        }
      }

      if (!empty($numbers_validated)) {
        // Has user requested all the customer numbers or just one
        if ($all_customers_numbers) {
          foreach ($numbers_validated as $number_valid) {
            $row = new CSVRow();
            $row->number = $number_valid;
            $row->firstname = $customer->firstname;
            $row->lastname = $customer->lastname;
            $row->group = $group;
            $data[] = $row;
          }
        }
        else {
          $row = new CSVRow();
          $row->number = $numbers_validated[0];
          $row->firstname = $customer->firstname;
          $row->lastname = $customer->lastname;
          $row->group = $group;
          $data[] = $row;
        }
      }
      
    }

    return $data;
  }
}
