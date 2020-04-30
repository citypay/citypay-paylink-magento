<!--![image](https://citypay.com/static/img/logo-x500.png)-->
<img src="https://citypay.com/static/img/logo-x500.png" height="75"/>
     
# Magento 2 Payment Module

CityPay Paylink Magento 2 Payment Module is a secure payment method in your Magento2 webshop. Integrating CityPay Paylink with Magento 2 is fast and easy.

To make payments in your Magento 2 webshop, download the CityPay Paylink Magento 2 module here and you will be able to offer a vast variety of most 
frequently used national and international online payment methods and solutions for worldwide internet commerce.

## Installing CityPay Paylink Payment Module

1. Change to directory where magento is installed (i.e cd /var/www/html/)
1. Run `composer require citypay/magento-paylink`
1. Ensure that the plugin is enabled (i.e using Magento CLI -> magento module:enable CityPay_Paylink)

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

## Customer Experience

### Checkout
- Customer will be presented with the option to pay via CityPay Hosted Payment Form
![checkout](https://user-images.githubusercontent.com/28923983/71171419-aa1fd980-2255-11ea-8818-dfe2a4b9f303.png)

___
<img width="908" alt="place_order" src="https://user-images.githubusercontent.com/28923983/71171423-aab87000-2255-11ea-995a-0e5dff49a19b.png">

___
### Paylink Payment Form

- Customer will be presented with the CityPay Paylink Form
<img width="550" alt="paylink_form" src="https://user-images.githubusercontent.com/28923983/71171422-aab87000-2255-11ea-9871-666fd7b131f3.png">

___

### Post Processing
- Customer will be redirected back to Magento Store once they hit the *Return to Store* button
<img width="618" alt="transaction_aproved" src="https://user-images.githubusercontent.com/28923983/71171427-ab510680-2255-11ea-84cb-42580326f569.png">

___
<img width="1679" alt="success_page" src="https://user-images.githubusercontent.com/28923983/71171426-aab87000-2255-11ea-94b9-89f1d6a11eb9.png">

___
- Customers order summary will mention order status as *Processing*
![customer_orders](https://user-images.githubusercontent.com/28923983/71171421-aab87000-2255-11ea-8a40-051f97758ad0.png)

___
- Order details page will display CityPay Payment as the Payment Method
![customer_order_details](https://user-images.githubusercontent.com/28923983/71171420-aa1fd980-2255-11ea-95df-15bc35d9cc35.png)

___

## Admin Experience

### Orders 

- Order will be set to Processing on the Merchant Backend Console and Merchant 
will have the ability to view that the payment was made by the CityPay Payment Module.
![admin_orders](https://user-images.githubusercontent.com/28923983/71171418-aa1fd980-2255-11ea-9406-048600dd39ff.png)

___

![admin_order_info](https://user-images.githubusercontent.com/28923983/71171416-aa1fd980-2255-11ea-83ec-4f2e64d8eb05.png)
