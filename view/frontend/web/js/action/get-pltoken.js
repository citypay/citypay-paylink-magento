/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * @api
 */
define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Customer/js/model/customer',
    'CityPay_Paylink/js/model/get-pltoken'
], function (quote, urlBuilder, customer, getplTokenService) {
    'use strict';

    return function (paymentData, messageContainer) {
        var serviceUrl, payload;

        payload = {
            paymentMethod: paymentData
        };

        // MODIFIED URLs relative to place-order.js
        if (customer.isLoggedIn()) {
            serviceUrl = urlBuilder.createUrl('/paylink/pltoken-information', {});
        } else {
            serviceUrl = urlBuilder.createUrl('/guest-carts/pltoken-information', {});
            payload.email = quote.guestEmail;
        }
        //alert ('call '+ serviceUrl);
        return getplTokenService(serviceUrl, payload, messageContainer);
    };
});
