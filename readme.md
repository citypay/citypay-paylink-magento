<!--![image](https://www.citypay.com/wp-content/uploads/2022/08/Citypaylogo-x500.png)-->
<img src="https://www.citypay.com/wp-content/uploads/2022/08/Citypaylogo-x500.png" height="75"/>
     
# Magento 2 Payment Module

CityPay Paylink Magento 2 Payment Module is a secure payment method in your Magento2 webshop. Integrating CityPay Paylink with Magento 2 is fast and easy.

To make payments in your Magento 2 webshop, download the CityPay Paylink Magento 2 module here, and you will be able to offer a vast variety of most 
frequently used national and international online payment methods and solutions for worldwide internet commerce.

## Installing CityPay Paylink Payment Module

1. Change to directory where magento is installed (i.e. cd /var/www/html/)
2. Run `composer require citypay/magento-paylink`
3. Ensure that the plugin is enabled (i.e. using Magento CLI -> magento module:enable CityPay_Paylink)

## User Documentation

### Configuring the payment module
To configure the module, log in to your administrator backend.

1. Go to **Stores** -> **Configuration** -> **Sales** -> **Payment methods** and find and click on **CityPay Paylink Gateway** Settings under **OTHER PAYMENT METHODS**

![plugin_config](https://user-images.githubusercontent.com/28923983/71171425-aab87000-2255-11ea-9d85-d8550f4841c3.png)


### Merchant required settings
To be able to use the CityPay Paylink Plugin the merchant must configure their following **Required** fields:
 - **Licence Key** (CityPay Client LK)
 - **Merchant Id** (CityPay Merchant Id)
 - **Order Confirmation Email Address** (email to which the merchant receipt email go to)
 - **Postback Host** (Internet accessible Base URL for the magento store so that postback can be handled and order status can be updated)
    *if using localhost in development we suggest using a product like ngrok to create a tunnel so that our postbacks 
    are remotely accessible through the internet.
 - **Processing Mode** (Select TEST to process in test mode or LIVE to perform Live Transactions)

### Enabling logging
The interaction between Magento and CityPay Paylink hosted payment form service may be monitored by enabling the Debug option appearing on the module settings form.

Log payment events appearing in the resultant log file will help to trace any difficulties you may experience accepting payments using the CityPay Paylink service.

The log file can be found at `{root}/var/log/debug.log` where root is normally `/var/www/html/magento`.

## Customer Experience

### Checkout
- Customer will be presented with the option to pay via CityPay Hosted Payment Form

![checkout](https://user-images.githubusercontent.com/86474060/236212991-a2b591e5-54f4-49a9-9034-43ecf3263de8.png)

___
### Paylink Payment Form

- Customer will be presented with the CityPay Paylink Form

![paylink_form](https://user-images.githubusercontent.com/86474060/236213523-e4ae5ac1-778f-4f64-a3b8-64d333dbc7ba.png)

___

### Post Processing
- Customer will be redirected back to Magento Store once they hit the *Return to Store* button

![payment_result](https://user-images.githubusercontent.com/86474060/236214060-c0bfee76-0106-4fea-9700-11ff784397b2.png)
___

![store](https://user-images.githubusercontent.com/86474060/236214778-dc3a47d8-7189-4ee7-bc97-62fcc48f9eb8.png)

___

- Customers order summary will mention order status as *Processing*

![customer_orders](https://user-images.githubusercontent.com/86474060/236218888-2af26320-366a-462c-a222-8db1e0b0f093.png)

___

- Order details page will display CityPay Payment as the Payment Method

![customer_order_details](https://user-images.githubusercontent.com/86474060/236219021-5e119273-243a-4eb6-bf19-f31450400206.png)

___

## Admin Experience

### Orders 

- Order will be set to Processing on the Merchant Backend Console and Merchant 
will have the ability to view that the payment was made by the CityPay Payment Module.

![admin_orders](https://user-images.githubusercontent.com/86474060/236219156-2c94a576-9b8e-4bdb-9720-c8af0cf2f3ca.png)

___

![admin_order_info](https://user-images.githubusercontent.com/86474060/236220049-3d1fe0c7-87a3-4dba-93ae-8c63c82702d6.png)

