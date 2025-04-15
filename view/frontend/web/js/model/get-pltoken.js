/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/**
 * @api
 */
define(
    [
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Customer/js/customer-data'
    ],
    function (storage, errorProcessor, fullScreenLoader, customerData) {
        'use strict';

        return function (serviceUrl, payload, messageContainer) {
            fullScreenLoader.startLoader();

            var request = storage.post(
                serviceUrl,
                JSON.stringify(payload)
            );

            // Use the appropriate method based on what's supported
            if (typeof request.done === 'function') {
                request
                    .done(function (response) {
                        handleResponse(response);
                    })
                    .fail(function (response) {
                        errorProcessor.process(response, messageContainer);
                    })
                    .always(function () {
                        fullScreenLoader.stopLoader();
                    });
            } else if (typeof request.success === 'function') {
                request
                    .success(function (response) {
                        handleResponse(response);
                    })
                    .fail(function (response) {
                        errorProcessor.process(response, messageContainer);
                    })
                    .always(function () {
                        fullScreenLoader.stopLoader();
                    });
            }

            function handleResponse(response) {
                let jResponse = JSON.parse(response);

                if (jResponse.result === 1 && jResponse.url) {
                    window.location.replace(jResponse.url);
                }
            }

            return request;
        };
    }
);
